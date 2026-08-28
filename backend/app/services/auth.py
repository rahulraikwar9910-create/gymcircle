from typing import Optional
import uuid
from datetime import datetime, timedelta, timezone
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy.orm import selectinload

from app.core.config import settings
from app.core.security import get_password_hash, verify_password, create_access_token, create_refresh_token, decode_token
from app.models.auth import User, Gym, UserGymRole, UserRefreshToken, UserInvitation
from app.schemas.auth import OwnerRegisterRequest, LoginRequest


async def authenticate_user(
    db: AsyncSession,
    login_data: LoginRequest
) -> Optional[User]:
    # Query user by email
    query = (
        select(User)
        .where(User.email == login_data.email)
        .options(selectinload(User.gym_roles))
    )
    result = await db.execute(query)
    user = result.scalar_one_or_none()
    
    if not user:
        return None
    
    if not verify_password(login_data.password, user.hashed_password):
        return None
        
    return user


async def register_owner_and_gym(
    db: AsyncSession,
    reg_data: OwnerRegisterRequest
) -> tuple[User, Gym]:
    # Check if user already exists
    user_query = select(User).where(User.email == reg_data.email)
    user_result = await db.execute(user_query)
    if user_result.scalar_one_or_none():
        raise ValueError("User with this email already exists")

    # Check if gym slug already exists
    gym_query = select(Gym).where(Gym.slug == reg_data.gym_slug)
    gym_result = await db.execute(gym_query)
    if gym_result.scalar_one_or_none():
        raise ValueError("Gym with this slug already exists")

    # Create Gym
    gym = Gym(
        name=reg_data.gym_name,
        slug=reg_data.gym_slug,
        email=reg_data.email,
        phone=reg_data.phone
    )
    db.add(gym)
    await db.flush()  # Flush to get the gym.id

    # Create User
    hashed_password = get_password_hash(reg_data.password)
    user = User(
        email=reg_data.email,
        phone=reg_data.phone,
        hashed_password=hashed_password,
        first_name=reg_data.first_name,
        last_name=reg_data.last_name
    )
    db.add(user)
    await db.flush()  # Flush to get user.id

    # Create UserGymRole association as GYM_OWNER
    gym_role = UserGymRole(
        user_id=user.id,
        gym_id=gym.id,
        role="GYM_OWNER",
        is_active=True
    )
    db.add(gym_role)
    await db.flush()

    # Load gym roles relationship before returning
    query = (
        select(User)
        .where(User.id == user.id)
        .options(selectinload(User.gym_roles))
    )
    result = await db.execute(query)
    user = result.scalar_one()

    return user, gym


async def create_and_save_refresh_token(
    db: AsyncSession,
    user_id: uuid.UUID
) -> str:
    expires_delta = timedelta(days=settings.REFRESH_TOKEN_EXPIRE_DAYS)
    expires_at = datetime.now(timezone.utc) + expires_delta
    
    token = create_refresh_token(subject=user_id, expires_delta=expires_delta)
    
    db_token = UserRefreshToken(
        user_id=user_id,
        token=token,
        expires_at=expires_at,
        is_revoked=False
    )
    db.add(db_token)
    await db.flush()
    return token


async def rotate_refresh_token(
    db: AsyncSession,
    refresh_token_str: str
) -> tuple[str, str]:
    payload = decode_token(refresh_token_str, settings.JWT_REFRESH_SECRET)
    if not payload or payload.get("type") != "refresh":
        raise ValueError("Invalid refresh token")
        
    user_id_str = payload.get("sub")
    if not user_id_str:
        raise ValueError("Invalid token subject")
        
    user_uuid = uuid.UUID(user_id_str)
    
    # Query database for the refresh token
    query = select(UserRefreshToken).where(UserRefreshToken.token == refresh_token_str)
    result = await db.execute(query)
    db_token = result.scalar_one_or_none()
    
    if not db_token:
        raise ValueError("Refresh token not found")
        
    # Check if expired or revoked
    now_utc = datetime.now(timezone.utc)
    db_expires = db_token.expires_at.replace(tzinfo=timezone.utc) if db_token.expires_at.tzinfo is None else db_token.expires_at
    if db_token.is_revoked or db_expires < now_utc:
        if db_token.is_revoked:
            # Security Incident: Replay attack detected. Revoke all active refresh tokens for the user
            revoke_query = (
                select(UserRefreshToken)
                .where(UserRefreshToken.user_id == user_uuid, UserRefreshToken.is_revoked == False)
            )
            res = await db.execute(revoke_query)
            active_tokens = res.scalars().all()
            for t in active_tokens:
                t.is_revoked = True
            await db.flush()
            raise ValueError("Refresh token has already been used. Security incident triggered: all sessions revoked.")
        else:
            raise ValueError("Refresh token expired")

    # Mark old token as revoked
    db_token.is_revoked = True
    await db.flush()
    
    # Generate new pair
    new_access_token = create_access_token(subject=user_uuid)
    new_refresh_token = await create_and_save_refresh_token(db, user_uuid)
    
    return new_access_token, new_refresh_token


async def create_user_invitation(
    db: AsyncSession,
    gym_id: uuid.UUID,
    creator_id: uuid.UUID,
    email: Optional[str],
    phone: Optional[str],
    role: str
) -> UserInvitation:
    # Validate role is acceptable
    allowed_roles = ["SUPER_ADMIN", "GYM_OWNER", "GYM_MANAGER", "RECEPTIONIST", "TRAINER", "CLIENT"]
    if role not in allowed_roles:
        raise ValueError(f"Invalid invitation role: {role}")
        
    token = str(uuid.uuid4())
    expires_at = datetime.now(timezone.utc) + timedelta(hours=48)
    
    invitation = UserInvitation(
        gym_id=gym_id,
        email=email,
        phone=phone,
        role=role,
        token=token,
        expires_at=expires_at,
        is_accepted=False,
        created_by=creator_id
    )
    db.add(invitation)
    await db.flush()
    return invitation


async def accept_user_invitation(
    db: AsyncSession,
    token: str,
    password: str,
    first_name: str,
    last_name: str
) -> User:
    # Query invitation
    query = (
        select(UserInvitation)
        .where(UserInvitation.token == token)
    )
    result = await db.execute(query)
    invitation = result.scalar_one_or_none()
    
    if not invitation:
        raise ValueError("Invitation link not found")
        
    if invitation.is_accepted:
        raise ValueError("Invitation has already been accepted")
        
    inv_expires = invitation.expires_at.replace(tzinfo=timezone.utc) if invitation.expires_at.tzinfo is None else invitation.expires_at
    if inv_expires < datetime.now(timezone.utc):
        raise ValueError("Invitation link has expired")
        
    # Create user
    # Check if email/phone already taken
    if invitation.email:
        email_query = select(User).where(User.email == invitation.email)
        res = await db.execute(email_query)
        if res.scalar_one_or_none():
            raise ValueError("A user with this email address already exists")
            
    hashed_password = get_password_hash(password)
    user = User(
        email=invitation.email,
        phone=invitation.phone,
        hashed_password=hashed_password,
        first_name=first_name,
        last_name=last_name
    )
    db.add(user)
    await db.flush()
    
    # Assign Gym Role
    role_link = UserGymRole(
        user_id=user.id,
        gym_id=invitation.gym_id,
        role=invitation.role,
        is_active=True
    )
    db.add(role_link)
    await db.flush()
    
    # Mark invitation as accepted
    invitation.is_accepted = True
    await db.flush()
    
    # Load user roles relationship before returning
    query = (
        select(User)
        .where(User.id == user.id)
        .options(selectinload(User.gym_roles))
    )
    result = await db.execute(query)
    user = result.scalar_one()
    
    return user
