from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.core import deps
from app.core.security import create_access_token
from app.schemas.auth import (
    OwnerRegisterRequest,
    UserResponse,
    LoginRequest,
    Token,
    TokenRefreshRequest,
    UserInvitationCreate,
    UserInvitationResponse,
    AcceptInviteRequest
)
from app.services import auth as auth_service
from app.models.auth import User

router = APIRouter()


@router.post("/register-owner", response_model=UserResponse, status_code=status.HTTP_201_CREATED)
async def register_owner(
    reg_data: OwnerRegisterRequest,
    db: AsyncSession = Depends(deps.get_db)
):
    try:
        user, _ = await auth_service.register_owner_and_gym(db, reg_data)
        return user
    except ValueError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e)
        )


@router.post("/login", response_model=Token)
async def login(
    login_data: LoginRequest,
    db: AsyncSession = Depends(deps.get_db)
):
    user = await auth_service.authenticate_user(db, login_data)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Incorrect email or password",
        )
    
    access_token = create_access_token(subject=user.id)
    refresh_token = await auth_service.create_and_save_refresh_token(db, user.id)
    
    return Token(
        access_token=access_token,
        refresh_token=refresh_token
    )


@router.post("/refresh", response_model=Token)
async def refresh(
    refresh_data: TokenRefreshRequest,
    db: AsyncSession = Depends(deps.get_db)
):
    try:
        access_token, refresh_token = await auth_service.rotate_refresh_token(
            db, refresh_data.refresh_token
        )
        return Token(
            access_token=access_token,
            refresh_token=refresh_token
        )
    except ValueError as e:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail=str(e)
        )


@router.post("/invite", response_model=UserInvitationResponse, status_code=status.HTTP_201_CREATED)
async def invite(
    inv_data: UserInvitationCreate,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER", "GYM_MANAGER", "RECEPTIONIST"])),
    gym_id: str = Depends(deps.get_gym_id_header)
):
    import uuid
    if not gym_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Missing gym context header X-Gym-ID"
        )
    try:
        gym_uuid = uuid.UUID(gym_id)
    except ValueError:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Invalid gym ID format"
        )

    try:
        invitation = await auth_service.create_user_invitation(
            db=db,
            gym_id=gym_uuid,
            creator_id=current_user.id,
            email=inv_data.email,
            phone=inv_data.phone,
            role=inv_data.role
        )
        return invitation
    except ValueError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e)
        )


@router.post("/accept-invite", response_model=UserResponse)
async def accept_invite(
    accept_data: AcceptInviteRequest,
    db: AsyncSession = Depends(deps.get_db)
):
    try:
        user = await auth_service.accept_user_invitation(
            db=db,
            token=accept_data.token,
            password=accept_data.password,
            first_name=accept_data.first_name,
            last_name=accept_data.last_name
        )
        return user
    except ValueError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e)
        )


@router.get("/me", response_model=UserResponse)
async def read_users_me(
    current_user: User = Depends(deps.get_current_active_user)
):
    return current_user
