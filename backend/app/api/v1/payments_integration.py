from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy.orm import selectinload
import uuid
from typing import List

from app.core import deps
from app.models.auth import User
from app.models.gym import FeeLedger
from app.models.trainer import TrainerBooking, TrainerProfile
from app.models.payments import PaymentTransaction
from app.schemas.payments import (
    OrderCreateRequest,
    OrderResponse,
    PaymentVerificationRequest,
    PaymentTransactionResponse
)
from app.services.payments import payment_service

router = APIRouter()


@router.post("/create-order", response_model=OrderResponse, status_code=status.HTTP_201_CREATED)
async def create_payment_order(
    req: OrderCreateRequest,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user)
):
    gym_id = None
    gross_amount = None

    if req.entity_type == "MEMBERSHIP":
        # Fetch invoice details
        inv_query = select(FeeLedger).where(FeeLedger.id == req.entity_id)
        inv_res = await db.execute(inv_query)
        invoice = inv_res.scalar_one_or_none()
        if not invoice:
            raise HTTPException(status_code=404, detail="Fee invoice not found")
        if invoice.status == "PAID":
            raise HTTPException(status_code=400, detail="Invoice is already paid")
        
        gross_amount = invoice.amount
        gym_id = invoice.gym_id

    elif req.entity_type == "TRAINER_BOOKING":
        # Fetch booking details
        book_query = select(TrainerBooking).where(TrainerBooking.id == req.entity_id)
        book_res = await db.execute(book_query)
        booking = book_res.scalar_one_or_none()
        if not booking:
            raise HTTPException(status_code=404, detail="Trainer booking not found")
        if booking.status != "PENDING":
            raise HTTPException(status_code=400, detail="Booking is not in PENDING status")
            
        gross_amount = booking.price_paid
        
        # Resolve trainer profile gym context
        profile_query = select(TrainerProfile).where(TrainerProfile.id == booking.trainer_id)
        prof_res = await db.execute(profile_query)
        profile = prof_res.scalar_one_or_none()
        if profile:
            gym_id = profile.gym_id

    else:
        raise HTTPException(status_code=400, detail="Unsupported entity type for payment")

    # Generate gateway order
    tx = await payment_service.create_gateway_order(
        db=db,
        user_id=current_user.id,
        gym_id=gym_id,
        entity_type=req.entity_type,
        entity_id=req.entity_id,
        gross_amount=gross_amount
    )

    return OrderResponse(
        order_id=tx.order_id,
        gross_amount=tx.gross_amount,
        platform_fee=tx.platform_fee,
        gateway_fee=tx.gateway_fee,
        net_amount=tx.net_amount
    )


@router.post("/verify-payment", response_model=PaymentTransactionResponse)
async def verify_payment(
    req: PaymentVerificationRequest,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user)
):
    try:
        tx = await payment_service.verify_gateway_payment(
            db=db,
            order_id=req.razorpay_order_id,
            payment_id=req.razorpay_payment_id,
            signature=req.razorpay_signature
        )
    except ValueError as e:
        raise HTTPException(status_code=400, detail=str(e))

    # Reload relationships for response
    query_reload = (
        select(PaymentTransaction)
        .where(PaymentTransaction.id == tx.id)
        .options(selectinload(PaymentTransaction.user).selectinload(User.gym_roles))
    )
    res = await db.execute(query_reload)
    return res.scalar_one()


@router.get("/transactions", response_model=List[PaymentTransactionResponse])
async def get_payment_transactions(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user),
    gym_id: str = Depends(deps.get_gym_id_header)
):
    base_query = (
        select(PaymentTransaction)
        .options(selectinload(PaymentTransaction.user).selectinload(User.gym_roles))
    )

    # Scoping logic
    # 1. Super Admin: full access
    if current_user.is_super_admin:
        result = await db.execute(base_query)
        return result.scalars().all()

    # 2. Gym Owner/Manager: access restricted to their gym context header
    is_owner_or_staff = any(
        r.role in ["GYM_OWNER", "GYM_MANAGER"] and str(r.gym_id) == gym_id
        for r in current_user.gym_roles
    )

    if is_owner_or_staff and gym_id:
        gym_uuid = uuid.UUID(gym_id)
        query = base_query.where(PaymentTransaction.gym_id == gym_uuid)
        result = await db.execute(query)
        return result.scalars().all()

    # 3. Trainer context: show transactions related to their bookings
    trainer_query = select(TrainerProfile).where(TrainerProfile.user_id == current_user.id)
    tp_res = await db.execute(trainer_query)
    trainer_profile = tp_res.scalar_one_or_none()

    if trainer_profile:
        # Fetch all bookings for this trainer
        bookings_query = select(TrainerBooking.id).where(TrainerBooking.trainer_id == trainer_profile.id)
        bookings_res = await db.execute(bookings_query)
        booking_ids = [str(bid) for bid in bookings_res.scalars().all()]
        
        query = base_query.where(
            PaymentTransaction.entity_type == "TRAINER_BOOKING",
            PaymentTransaction.entity_id.in_(booking_ids)
        )
        result = await db.execute(query)
        return result.scalars().all()

    # 4. Fallback Client context: show only their own payments
    query = base_query.where(PaymentTransaction.user_id == current_user.id)
    result = await db.execute(query)
    return result.scalars().all()
