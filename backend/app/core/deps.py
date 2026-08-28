from typing import AsyncGenerator, List, Optional
from fastapi import Depends, HTTPException, Header, status
from fastapi.security import OAuth2PasswordBearer
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy.orm import selectinload
import jwt

from app.core.config import settings
from app.core.database import AsyncSessionLocal
from app.core.security import decode_token
from app.models.auth import User, UserGymRole
from app.schemas.auth import TokenPayload

oauth2_scheme = OAuth2PasswordBearer(tokenUrl="/api/v1/auth/login")


async def get_db() -> AsyncGenerator[AsyncSession, None]:
    async with AsyncSessionLocal() as session:
        try:
            yield session
            await session.commit()
        except Exception:
            await session.rollback()
            raise


async def get_current_user(
    db: AsyncSession = Depends(get_db),
    token: str = Depends(oauth2_scheme)
) -> User:
    credentials_exception = HTTPException(
        status_code=status.HTTP_401_UNAUTHORIZED,
        detail="Could not validate credentials",
        headers={"WWW-Authenticate": "Bearer"},
    )
    payload_data = decode_token(token, settings.JWT_SECRET)
    if not payload_data:
        raise credentials_exception
    
    try:
        token_data = TokenPayload(**payload_data)
    except Exception:
        raise credentials_exception

    if token_data.type != "access" or not token_data.sub:
        raise credentials_exception

    import uuid
    try:
        user_uuid = uuid.UUID(token_data.sub)
    except ValueError:
        raise credentials_exception

    # Query user and eager load their gym roles
    query = (
        select(User)
        .where(User.id == user_uuid)
        .options(selectinload(User.gym_roles))
    )
    result = await db.execute(query)
    user = result.scalar_one_or_none()

    if user is None:
        raise credentials_exception
    
    return user


async def get_current_active_user(
    current_user: User = Depends(get_current_user)
) -> User:
    if not current_user.is_active:
        raise HTTPException(status_code=400, detail="Inactive user")
    return current_user


def get_gym_id_header(x_gym_id: Optional[str] = Header(None, alias="X-Gym-ID")) -> Optional[str]:
    return x_gym_id


class RoleChecker:
    def __init__(self, allowed_roles: List[str]):
        self.allowed_roles = allowed_roles

    async def __call__(
        self,
        current_user: User = Depends(get_current_active_user),
        gym_id: Optional[str] = Depends(get_gym_id_header)
    ) -> User:
        # Super admin has unrestricted bypass
        if current_user.is_super_admin:
            return current_user

        # User is active and has roles
        for user_gym_role in current_user.gym_roles:
            if not user_gym_role.is_active:
                continue

            # Role match check
            if user_gym_role.role in self.allowed_roles:
                # If SUPER_ADMIN allowed but they aren't one, check gym-level roles
                if user_gym_role.role != "SUPER_ADMIN":
                    # If role is gym-specific, gym_id must match context header
                    if gym_id and str(user_gym_role.gym_id) == gym_id:
                        return current_user
                    # If no gym_id header is passed, but the role exists, they might be authorized (e.g. general trainer/client lookup),
                    # but for tenant isolation we require a matching gym context.
                    elif not gym_id:
                        # Fallback: if the user only belongs to one gym, treat that as the implicit tenant context
                        # or raise 400 Bad Request requiring tenant context header.
                        non_super_roles = [r for r in current_user.gym_roles if r.role != "SUPER_ADMIN"]
                        if len(non_super_roles) == 1 and str(non_super_roles[0].gym_id) == str(user_gym_role.gym_id):
                            return current_user

        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="The user does not have enough privileges or is accessing an incorrect gym context"
        )
