from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy.orm import selectinload
from datetime import datetime, date, time
import uuid
from typing import List

from app.core import deps
from app.models.auth import User
from app.models.gym import Attendance, FeeLedger, ClientGymMembership
from app.schemas.gym import AttendanceCheckInRequest, CheckInResultResponse, AttendanceResponse

router = APIRouter()


@router.post("/check-in", response_model=CheckInResultResponse, status_code=status.HTTP_201_CREATED)
async def check_in(
    check_in_in: AttendanceCheckInRequest,
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

    # Validate client exists
    client_q = select(User).where(User.id == check_in_in.client_id)
    client_res = await db.execute(client_q)
    client = client_res.scalar_one_or_none()
    if not client:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Client not found"
        )

    # Check if already checked in today (prevent duplicate active check-ins)
    today = date.today()
    dup_q = select(Attendance).where(
        Attendance.gym_id == gym_uuid,
        Attendance.client_id == check_in_in.client_id,
        Attendance.date == today,
        Attendance.check_out == None
    )
    dup_res = await db.execute(dup_q)
    if dup_res.scalar_one_or_none():
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Client is already checked in with an active session"
        )

    # Record check-in
    now = datetime.now()
    attendance = Attendance(
        gym_id=gym_uuid,
        client_id=check_in_in.client_id,
        date=today,
        check_in=now.time()
    )
    db.add(attendance)
    await db.flush()

    # Calculate alert flags
    # 1. Check pending fees
    fees_q = select(FeeLedger).where(
        FeeLedger.gym_id == gym_uuid,
        FeeLedger.client_id == check_in_in.client_id,
        FeeLedger.status == "PENDING"
    )
    fees_res = await db.execute(fees_q)
    has_pending = len(fees_res.scalars().all()) > 0

    # 2. Check active membership status
    mem_q = select(ClientGymMembership).where(
        ClientGymMembership.gym_id == gym_uuid,
        ClientGymMembership.client_id == check_in_in.client_id,
        ClientGymMembership.status == "ACTIVE",
        ClientGymMembership.end_date >= today
    )
    mem_res = await db.execute(mem_q)
    has_active_membership = mem_res.scalar_one_or_none() is not None
    expired = not has_active_membership

    # Construct status message
    if expired and has_pending:
        msg = "ALERT: Membership expired AND pending fees outstanding!"
    elif expired:
        msg = "ALERT: Membership expired!"
    elif has_pending:
        msg = "ALERT: Pending fees outstanding!"
    else:
        msg = "Access granted"

    # Eager load client before returning
    query = (
        select(Attendance)
        .where(Attendance.id == attendance.id)
        .options(selectinload(Attendance.client).selectinload(User.gym_roles))
    )
    result = await db.execute(query)
    attendance = result.scalar_one()

    return CheckInResultResponse(
        attendance=attendance,
        has_pending_fees=has_pending,
        membership_expired=expired,
        message=msg
    )


@router.post("/check-out", response_model=AttendanceResponse)
async def check_out(
    check_out_in: AttendanceCheckInRequest,  # Reuse model containing client_id
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

    # Find the active check-in session for today
    query = select(Attendance).where(
        Attendance.gym_id == gym_uuid,
        Attendance.client_id == check_out_in.client_id,
        Attendance.date == date.today(),
        Attendance.check_out == None
    )
    result = await db.execute(query)
    attendance = result.scalar_one_or_none()
    if not attendance:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Active check-in session not found for today"
        )

    # Set check-out time
    attendance.check_out = datetime.now().time()
    await db.flush()

    # Load client relationship
    query_updated = (
        select(Attendance)
        .where(Attendance.id == attendance.id)
        .options(selectinload(Attendance.client).selectinload(User.gym_roles))
    )
    res = await db.execute(query_updated)
    return res.scalar_one()


@router.get("", response_model=List[AttendanceResponse])
async def list_attendance(
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

    is_admin_or_staff = current_user.is_super_admin or any(
        r.role in ["GYM_OWNER", "GYM_MANAGER", "RECEPTIONIST"] and str(r.gym_id) == gym_id
        for r in current_user.gym_roles
    )

    query = select(Attendance).where(Attendance.gym_id == gym_uuid).options(selectinload(Attendance.client).selectinload(User.gym_roles))

    # If CLIENT, filter to only their own attendance
    if not is_admin_or_staff:
        query = query.where(Attendance.client_id == current_user.id)

    result = await db.execute(query)
    return result.scalars().all()
