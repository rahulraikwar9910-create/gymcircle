from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy.orm import selectinload
from sqlalchemy import func
from datetime import datetime, date, timezone, timedelta
from decimal import Decimal
import uuid
from typing import List

from app.core import deps
from app.models.auth import User
from app.models.gym import FeeLedger, ClientGymMembership
from app.schemas.gym import FeeLedgerResponse, PaymentRecordRequest, DashboardMetricsResponse

router = APIRouter()


@router.post("/record/{invoice_id}", response_model=FeeLedgerResponse)
async def record_payment(
    invoice_id: uuid.UUID,
    pay_in: PaymentRecordRequest,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER", "GYM_MANAGER", "RECEPTIONIST"])),
    gym_id: str = Depends(deps.get_gym_id_header)
):
    if not gym_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Missing gym context header X-Gym-ID"
        )
    gym_uuid = uuid.UUID(gym_id)

    # Fetch invoice
    query = select(FeeLedger).where(
        FeeLedger.id == invoice_id,
        FeeLedger.gym_id == gym_uuid
    )
    result = await db.execute(query)
    invoice = result.scalar_one_or_none()
    if not invoice:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Invoice not found for this gym"
        )

    if invoice.status == "PAID":
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Invoice is already paid"
        )

    # Update invoice fields
    invoice.status = "PAID"
    invoice.payment_mode = pay_in.payment_mode
    invoice.transaction_id = pay_in.transaction_id
    invoice.notes = pay_in.notes
    invoice.payment_date = datetime.now(timezone.utc)
    await db.flush()

    # Load client relationship for response
    query_updated = (
        select(FeeLedger)
        .where(FeeLedger.id == invoice.id)
        .options(selectinload(FeeLedger.client).selectinload(User.gym_roles))
    )
    res = await db.execute(query_updated)
    return res.scalar_one()


@router.get("/ledger", response_model=List[FeeLedgerResponse])
async def get_ledger(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user),
    gym_id: str = Depends(deps.get_gym_id_header)
):
    if not gym_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Missing gym context header X-Gym-ID"
        )
    gym_uuid = uuid.UUID(gym_id)

    # Multi-tenancy check
    is_admin_or_staff = current_user.is_super_admin or any(
        r.role in ["GYM_OWNER", "GYM_MANAGER", "RECEPTIONIST"] and str(r.gym_id) == gym_id
        for r in current_user.gym_roles
    )

    query = select(FeeLedger).where(FeeLedger.gym_id == gym_uuid).options(selectinload(FeeLedger.client).selectinload(User.gym_roles))

    # If they are a CLIENT, filter to only return their own invoices
    if not is_admin_or_staff:
        query = query.where(FeeLedger.client_id == current_user.id)

    result = await db.execute(query)
    return result.scalars().all()


@router.get("/dashboard-metrics", response_model=DashboardMetricsResponse)
async def get_dashboard_metrics(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER", "GYM_MANAGER"])),
    gym_id: str = Depends(deps.get_gym_id_header)
):
    if not gym_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Missing gym context header X-Gym-ID"
        )
    gym_uuid = uuid.UUID(gym_id)

    # 1. Today's collections
    today_start = datetime.combine(date.today(), datetime.min.time(), tzinfo=timezone.utc)
    today_end = datetime.combine(date.today(), datetime.max.time(), tzinfo=timezone.utc)
    
    today_q = select(func.sum(FeeLedger.amount)).where(
        FeeLedger.gym_id == gym_uuid,
        FeeLedger.status == "PAID",
        FeeLedger.payment_date >= today_start,
        FeeLedger.payment_date <= today_end
    )
    today_res = await db.execute(today_q)
    today_sum = today_res.scalar() or Decimal("0.00")

    # 2. Monthly collections
    month_start = datetime.combine(date.today().replace(day=1), datetime.min.time(), tzinfo=timezone.utc)
    month_q = select(func.sum(FeeLedger.amount)).where(
        FeeLedger.gym_id == gym_uuid,
        FeeLedger.status == "PAID",
        FeeLedger.payment_date >= month_start
    )
    month_res = await db.execute(month_q)
    month_sum = month_res.scalar() or Decimal("0.00")

    # 3. Pending fees total
    pending_q = select(func.sum(FeeLedger.amount)).where(
        FeeLedger.gym_id == gym_uuid,
        FeeLedger.status == "PENDING"
    )
    pending_res = await db.execute(pending_q)
    pending_sum = pending_res.scalar() or Decimal("0.00")

    # 4. Overdue count (PENDING and past due date)
    overdue_q = select(func.count(FeeLedger.id)).where(
        FeeLedger.gym_id == gym_uuid,
        FeeLedger.status == "PENDING",
        FeeLedger.due_date < date.today()
    )
    overdue_res = await db.execute(overdue_q)
    overdue_count = overdue_res.scalar() or 0

    # 5. Expiring count (memberships expiring in next 7 days)
    exp_q = select(func.count(ClientGymMembership.id)).where(
        ClientGymMembership.gym_id == gym_uuid,
        ClientGymMembership.status == "ACTIVE",
        ClientGymMembership.end_date >= date.today(),
        ClientGymMembership.end_date <= date.today() + timedelta(days=7)
    )
    exp_res = await db.execute(exp_q)
    expiring_count = exp_res.scalar() or 0

    return DashboardMetricsResponse(
        today_collection=today_sum,
        monthly_collection=month_sum,
        pending_fees_total=pending_sum,
        overdue_count=overdue_count,
        expiring_count=expiring_count
    )
