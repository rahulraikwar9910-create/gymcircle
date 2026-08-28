from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
import uuid
from typing import List

from app.core import deps
from app.models.auth import User, UserGymRole
from app.models.gym import ClientProfile
from app.schemas.gym import ClientCreateRequest, ClientDetailResponse
from app.core.security import get_password_hash

router = APIRouter()


@router.post("", response_model=ClientDetailResponse, status_code=status.HTTP_201_CREATED)
async def create_client(
    client_in: ClientCreateRequest,
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

    # Email and Phone unique checks
    if client_in.email:
        email_query = select(User).where(User.email == client_in.email)
        result = await db.execute(email_query)
        if result.scalar_one_or_none():
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="User with this email already exists"
            )

    if client_in.phone:
        phone_query = select(User).where(User.phone == client_in.phone)
        result = await db.execute(phone_query)
        if result.scalar_one_or_none():
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="User with this phone number already exists"
            )

    dummy_password = get_password_hash(str(uuid.uuid4()))

    # Create User record
    user = User(
        email=client_in.email,
        phone=client_in.phone,
        hashed_password=dummy_password,
        first_name=client_in.first_name,
        last_name=client_in.last_name,
        is_active=True
    )
    db.add(user)
    await db.flush()

    # Create Role Assignment
    gym_role = UserGymRole(
        user_id=user.id,
        gym_id=gym_uuid,
        role="CLIENT",
        is_active=True
    )
    db.add(gym_role)
    await db.flush()

    # Create Client Profile
    profile = ClientProfile(
        user_id=user.id,
        gym_id=gym_uuid,
        date_of_birth=client_in.date_of_birth,
        gender=client_in.gender,
        emergency_contact_name=client_in.emergency_contact_name,
        emergency_contact_phone=client_in.emergency_contact_phone,
        joining_date=client_in.joining_date,
        notes=client_in.notes
    )
    db.add(profile)
    await db.flush()

    return ClientDetailResponse(
        id=user.id,
        email=user.email,
        phone=user.phone,
        first_name=user.first_name,
        last_name=user.last_name,
        is_active=user.is_active,
        profile=profile
    )


@router.get("", response_model=List[ClientDetailResponse])
async def list_clients(
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
        select(User)
        .join(UserGymRole, User.id == UserGymRole.user_id)
        .where(UserGymRole.gym_id == gym_uuid, UserGymRole.role == "CLIENT")
    )
    result = await db.execute(query)
    users = result.scalars().all()

    client_responses = []
    for user in users:
        profile_query = select(ClientProfile).where(
            ClientProfile.user_id == user.id,
            ClientProfile.gym_id == gym_uuid
        )
        profile_res = await db.execute(profile_query)
        profile = profile_res.scalar_one_or_none()
        
        client_responses.append(
            ClientDetailResponse(
                id=user.id,
                email=user.email,
                phone=user.phone,
                first_name=user.first_name,
                last_name=user.last_name,
                is_active=user.is_active,
                profile=profile
            )
        )
    return client_responses


@router.get("/{client_id}", response_model=ClientDetailResponse)
async def get_client_detail(
    client_id: uuid.UUID,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER", "GYM_MANAGER", "RECEPTIONIST", "CLIENT", "TRAINER"])),
    gym_id: str = Depends(deps.get_gym_id_header)
):
    if not gym_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Missing gym context header X-Gym-ID"
        )
    gym_uuid = uuid.UUID(gym_id)

    # Restrict clients to only their own profiles, and restrict staff to clients of their own gym
    if not current_user.is_super_admin:
        is_owner_or_staff = any(
            r.role in ["GYM_OWNER", "GYM_MANAGER", "RECEPTIONIST"] and str(r.gym_id) == gym_id
            for r in current_user.gym_roles
        )
        if not is_owner_or_staff and current_user.id != client_id:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Not authorized to access this client detail"
            )
        
        # Verify the target client is associated with this gym context
        role_query = select(UserGymRole).where(
            UserGymRole.user_id == client_id,
            UserGymRole.gym_id == gym_uuid
        )
        role_res = await db.execute(role_query)
        if not role_res.scalar_one_or_none():
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="Not authorized to access this client detail"
            )

    user_query = select(User).where(User.id == client_id)
    res = await db.execute(user_query)
    user = res.scalar_one_or_none()
    if not user:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Client not found"
        )

    profile_query = select(ClientProfile).where(
        ClientProfile.user_id == client_id,
        ClientProfile.gym_id == gym_uuid
    )
    profile_res = await db.execute(profile_query)
    profile = profile_res.scalar_one_or_none()

    return ClientDetailResponse(
        id=user.id,
        email=user.email,
        phone=user.phone,
        first_name=user.first_name,
        last_name=user.last_name,
        is_active=user.is_active,
        profile=profile
    )
