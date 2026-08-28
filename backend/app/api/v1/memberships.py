from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy.orm import selectinload
from datetime import timedelta, date
import uuid
from typing import List

from app.core import deps
from app.models.auth import User
from app.models.gym import MembershipPlan, ClientGymMembership, FeeLedger
from app.schemas.gym import (
    MembershipPlanCreate,
    MembershipPlanResponse,
    ClientGymMembershipCreate,
    ClientGymMembershipResponse
)

router = APIRouter()


@router.post("/plans", response_model=MembershipPlanResponse, status_code=status.HTTP_201_CREATED)
async def create_membership_plan(
    plan_in: MembershipPlanCreate,
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

    plan = MembershipPlan(
        gym_id=gym_uuid,
        name=plan_in.name,
        description=plan_in.description,
        price=plan_in.price,
        duration_days=plan_in.duration_days,
        is_active=True
    )
    db.add(plan)
    await db.flush()
    return plan


@router.get("/plans", response_model=List[MembershipPlanResponse])
async def list_membership_plans(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER", "GYM_MANAGER", "RECEPTIONIST", "CLIENT"])),
    gym_id: str = Depends(deps.get_gym_id_header)
):
    if not gym_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Missing gym context header X-Gym-ID"
        )
    gym_uuid = uuid.UUID(gym_id)

    query = select(MembershipPlan).where(
        MembershipPlan.gym_id == gym_uuid,
        MembershipPlan.is_active == True
    )
    result = await db.execute(query)
    return result.scalars().all()


@router.post("/subscribe", response_model=ClientGymMembershipResponse, status_code=status.HTTP_201_CREATED)
async def subscribe_client(
    sub_in: ClientGymMembershipCreate,
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

    # 1. Fetch and validate plan
    plan_query = select(MembershipPlan).where(
        MembershipPlan.id == sub_in.plan_id,
        MembershipPlan.gym_id == gym_uuid
    )
    plan_res = await db.execute(plan_query)
    plan = plan_res.scalar_one_or_none()
    if not plan:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Membership plan not found for this gym"
        )

    # 2. Check if client exists
    client_query = select(User).where(User.id == sub_in.client_id)
    client_res = await db.execute(client_query)
    client = client_res.scalar_one_or_none()
    if not client:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Client not found"
        )

    # 3. Calculate end_date
    end_date = sub_in.start_date + timedelta(days=plan.duration_days)

    # 4. Create membership
    membership = ClientGymMembership(
        gym_id=gym_uuid,
        client_id=sub_in.client_id,
        plan_id=sub_in.plan_id,
        start_date=sub_in.start_date,
        end_date=end_date,
        status="ACTIVE"
    )
    db.add(membership)
    await db.flush()

    # 5. Generate ledger invoice as PENDING
    invoice = FeeLedger(
        gym_id=gym_uuid,
        client_id=sub_in.client_id,
        membership_id=membership.id,
        amount=plan.price,
        payment_mode="CASH",  # Default placeholder
        status="PENDING",
        due_date=sub_in.start_date,
        notes=f"Invoice for plan: {plan.name}"
    )
    db.add(invoice)
    await db.flush()

    # Load relationship for response
    query = (
        select(ClientGymMembership)
        .where(ClientGymMembership.id == membership.id)
        .options(selectinload(ClientGymMembership.plan))
    )
    result = await db.execute(query)
    membership = result.scalar_one()

    return membership


@router.get("/active", response_model=List[ClientGymMembershipResponse])
async def list_active_memberships(
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

    query = (
        select(ClientGymMembership)
        .where(
            ClientGymMembership.gym_id == gym_uuid,
            ClientGymMembership.status == "ACTIVE"
        )
        .options(selectinload(ClientGymMembership.plan))
    )
    result = await db.execute(query)
    return result.scalars().all()
