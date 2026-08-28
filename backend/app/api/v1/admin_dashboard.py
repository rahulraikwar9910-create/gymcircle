from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy.orm import selectinload
from sqlalchemy import func
from decimal import Decimal
import uuid
from typing import List

from app.core import deps
from app.models.auth import User, Gym, UserGymRole
from app.models.gym import ClientGymMembership
from app.models.trainer import TrainerProfile
from app.models.payments import PaymentTransaction
from app.schemas.admin import SuperAdminMetricsResponse, GymReportResponse, SettleTransactionRequest
from app.schemas.payments import PaymentTransactionResponse

router = APIRouter()


@router.get("/metrics", response_model=SuperAdminMetricsResponse)
async def get_platform_metrics(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["SUPER_ADMIN"]))
):
    # 1. Total Platform Revenue (gross)
    rev_q = select(func.sum(PaymentTransaction.gross_amount)).where(PaymentTransaction.status == "SUCCESS")
    rev_res = await db.execute(rev_q)
    total_rev = rev_res.scalar() or Decimal("0.00")

    # 2. Total Platform Fees Collected (sum of flat ₹10 splits)
    fee_q = select(func.sum(PaymentTransaction.platform_fee)).where(PaymentTransaction.status == "SUCCESS")
    fee_res = await db.execute(fee_q)
    total_fees = fee_res.scalar() or Decimal("0.00")

    # 3. Total Gyms registered
    gyms_q = select(func.count(Gym.id))
    gyms_res = await db.execute(gyms_q)
    total_gyms = gyms_res.scalar() or 0

    # 4. Total Users registered
    users_q = select(func.count(User.id))
    users_res = await db.execute(users_q)
    total_users = users_res.scalar() or 0

    # 5. Active Memberships count
    mem_q = select(func.count(ClientGymMembership.id)).where(ClientGymMembership.status == "ACTIVE")
    mem_res = await db.execute(mem_q)
    total_mems = mem_res.scalar() or 0

    # 6. Active Trainers count
    trainer_q = select(func.count(TrainerProfile.id))
    trainer_res = await db.execute(trainer_q)
    total_trainers = trainer_res.scalar() or 0

    # 7. Users grouped by Role
    role_q = select(UserGymRole.role, func.count(UserGymRole.id)).group_by(UserGymRole.role)
    role_res = await db.execute(role_q)
    role_counts = {role: count for role, count in role_res.all()}

    return SuperAdminMetricsResponse(
        total_platform_revenue=total_rev,
        total_platform_fees_collected=total_fees,
        total_gyms_count=total_gyms,
        total_users_count=total_users,
        active_memberships_count=total_mems,
        active_trainers_count=total_trainers,
        users_by_role=role_counts
    )


@router.get("/gyms", response_model=List[GymReportResponse])
async def get_gyms_revenue_report(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["SUPER_ADMIN"]))
):
    gyms_q = select(Gym)
    gyms_res = await db.execute(gyms_q)
    gyms = gyms_res.scalars().all()

    gym_reports = []

    for gym in gyms:
        # Count active members
        members_q = select(func.count(UserGymRole.id)).where(
            UserGymRole.gym_id == gym.id,
            UserGymRole.role == "CLIENT",
            UserGymRole.is_active == True
        )
        members_res = await db.execute(members_q)
        active_members = members_res.scalar() or 0

        # Calculate revenue generated
        rev_q = select(func.sum(PaymentTransaction.gross_amount)).where(
            PaymentTransaction.gym_id == gym.id,
            PaymentTransaction.status == "SUCCESS"
        )
        rev_res = await db.execute(rev_q)
        gym_rev = rev_res.scalar() or Decimal("0.00")

        gym_reports.append(
            GymReportResponse(
                gym_id=gym.id,
                name=gym.name,
                slug=gym.slug,
                active_members_count=active_members,
                total_revenue_generated=gym_rev,
                created_at=gym.created_at
            )
        )

    return gym_reports


@router.get("/settlements", response_model=List[PaymentTransactionResponse])
async def get_settlements_list(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["SUPER_ADMIN"]))
):
    # Retrieve all successful transactions for audit
    query = (
        select(PaymentTransaction)
        .where(PaymentTransaction.status == "SUCCESS")
        .options(selectinload(PaymentTransaction.user).selectinload(User.gym_roles))
    )
    result = await db.execute(query)
    return result.scalars().all()


@router.post("/settlements/{transaction_id}/settle", response_model=PaymentTransactionResponse)
async def settle_transaction(
    transaction_id: uuid.UUID,
    req: SettleTransactionRequest,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["SUPER_ADMIN"]))
):
    query = select(PaymentTransaction).where(PaymentTransaction.id == transaction_id)
    result = await db.execute(query)
    tx = result.scalar_one_or_none()
    if not tx:
        raise HTTPException(status_code=404, detail="Payment transaction not found")

    tx.settlement_status = req.settlement_status
    await db.flush()

    # Reload relations
    query_reload = (
        select(PaymentTransaction)
        .where(PaymentTransaction.id == tx.id)
        .options(selectinload(PaymentTransaction.user).selectinload(User.gym_roles))
    )
    res = await db.execute(query_reload)
    return res.scalar_one()
