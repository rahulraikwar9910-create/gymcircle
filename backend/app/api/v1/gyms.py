from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
import uuid
from typing import List

from app.core import deps
from app.models.auth import Gym, User
from app.schemas.auth import GymCreate, GymResponse

router = APIRouter()


@router.post("", response_model=GymResponse, status_code=status.HTTP_201_CREATED)
async def create_gym(
    gym_in: GymCreate,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["SUPER_ADMIN"]))
):
    # Verify slug uniqueness
    query = select(Gym).where(Gym.slug == gym_in.slug)
    result = await db.execute(query)
    if result.scalar_one_or_none():
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Gym with this slug already exists"
        )
        
    gym = Gym(
        name=gym_in.name,
        slug=gym_in.slug,
        email=gym_in.email,
        phone=gym_in.phone,
        address=gym_in.address,
        city=gym_in.city,
        state=gym_in.state,
        pincode=gym_in.pincode
    )
    db.add(gym)
    await db.flush()
    return gym


@router.get("", response_model=List[GymResponse])
async def list_gyms(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user)
):
    # SUPER_ADMIN sees all gyms
    if current_user.is_super_admin:
        query = select(Gym)
        result = await db.execute(query)
        return result.scalars().all()
        
    # Normal users see only their linked gyms
    gym_ids = [role.gym_id for role in current_user.gym_roles if role.gym_id is not None]
    if not gym_ids:
        return []
        
    query = select(Gym).where(Gym.id.in_(gym_ids))
    result = await db.execute(query)
    return result.scalars().all()


@router.get("/{gym_id}", response_model=GymResponse)
async def get_gym(
    gym_id: uuid.UUID,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user)
):
    # Ensure they have access (or is SUPER_ADMIN)
    has_access = current_user.is_super_admin or any(
        role.gym_id == gym_id for role in current_user.gym_roles if role.gym_id is not None
    )
    if not has_access:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="You do not have permission to access this gym's details"
        )
        
    query = select(Gym).where(Gym.id == gym_id)
    result = await db.execute(query)
    gym = result.scalar_one_or_none()
    if not gym:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="Gym not found"
        )
    return gym
