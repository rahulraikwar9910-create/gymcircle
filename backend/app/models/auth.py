from typing import Optional, List
from datetime import datetime
from sqlalchemy import String, Boolean, ForeignKey, UniqueConstraint, DateTime
from sqlalchemy.orm import Mapped, mapped_column, relationship
from app.models.base import BaseModel


class User(BaseModel):
    __tablename__ = "users"

    email: Mapped[Optional[str]] = mapped_column(
        String(255), unique=True, index=True, nullable=True
    )
    phone: Mapped[Optional[str]] = mapped_column(
        String(20), unique=True, index=True, nullable=True
    )
    hashed_password: Mapped[str] = mapped_column(String(255))
    first_name: Mapped[str] = mapped_column(String(100))
    last_name: Mapped[str] = mapped_column(String(100))
    is_active: Mapped[bool] = mapped_column(Boolean, default=True)

    # Relationship to user gym roles
    gym_roles: Mapped[List["UserGymRole"]] = relationship(
        "UserGymRole", back_populates="user", cascade="all, delete-orphan"
    )

    @property
    def is_super_admin(self) -> bool:
        return any(role.role == "SUPER_ADMIN" for role in self.gym_roles)


class Gym(BaseModel):
    __tablename__ = "gyms"

    name: Mapped[str] = mapped_column(String(255))
    slug: Mapped[str] = mapped_column(String(255), unique=True, index=True)
    email: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    phone: Mapped[Optional[str]] = mapped_column(String(20), nullable=True)
    address: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    city: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    state: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    pincode: Mapped[Optional[str]] = mapped_column(String(10), nullable=True)
    status: Mapped[str] = mapped_column(String(50), default="ACTIVE")  # ACTIVE, INACTIVE, SUSPENDED

    # Relationship to user gym roles
    user_roles: Mapped[List["UserGymRole"]] = relationship(
        "UserGymRole", back_populates="gym", cascade="all, delete-orphan"
    )


class UserGymRole(BaseModel):
    __tablename__ = "user_gym_roles"

    user_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    gym_id: Mapped[Optional[ForeignKey]] = mapped_column(
        ForeignKey("gyms.id", ondelete="CASCADE"), nullable=True, index=True
    )
    role: Mapped[str] = mapped_column(String(50))  # SUPER_ADMIN, GYM_OWNER, GYM_MANAGER, RECEPTIONIST, TRAINER, CLIENT
    is_active: Mapped[bool] = mapped_column(Boolean, default=True)

    # Relationships
    user: Mapped["User"] = relationship("User", back_populates="gym_roles")
    gym: Mapped[Optional["Gym"]] = relationship("Gym", back_populates="user_roles")

    # Composite Unique constraint to prevent duplicate role assignments
    __table_args__ = (
        UniqueConstraint("user_id", "gym_id", "role", name="uq_user_gym_role"),
    )


class UserRefreshToken(BaseModel):
    __tablename__ = "user_refresh_tokens"

    user_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False
    )
    token: Mapped[str] = mapped_column(String(255), unique=True, index=True, nullable=False)
    expires_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), nullable=False)
    is_revoked: Mapped[bool] = mapped_column(Boolean, default=False)

    user: Mapped["User"] = relationship("User")


class UserInvitation(BaseModel):
    __tablename__ = "user_invitations"

    gym_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("gyms.id", ondelete="CASCADE"), nullable=False
    )
    email: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    phone: Mapped[Optional[str]] = mapped_column(String(20), nullable=True)
    role: Mapped[str] = mapped_column(String(50), nullable=False)
    token: Mapped[str] = mapped_column(String(255), unique=True, index=True, nullable=False)
    expires_at: Mapped[datetime] = mapped_column(DateTime(timezone=True), nullable=False)
    is_accepted: Mapped[bool] = mapped_column(Boolean, default=False)
    created_by: Mapped[Optional[ForeignKey]] = mapped_column(
        ForeignKey("users.id", ondelete="SET NULL"), nullable=True
    )

    gym: Mapped["Gym"] = relationship("Gym")
    creator: Mapped[Optional["User"]] = relationship("User")
