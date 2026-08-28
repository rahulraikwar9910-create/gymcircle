from typing import Optional
from datetime import date, time, datetime
from decimal import Decimal
from sqlalchemy import String, Boolean, ForeignKey, Numeric, Date, Time, DateTime
from sqlalchemy.orm import Mapped, mapped_column, relationship
from app.models.base import BaseModel


class ClientProfile(BaseModel):
    __tablename__ = "client_profiles"

    user_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), unique=True, nullable=False
    )
    gym_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("gyms.id", ondelete="CASCADE"), nullable=False, index=True
    )
    date_of_birth: Mapped[Optional[date]] = mapped_column(Date, nullable=True)
    gender: Mapped[Optional[str]] = mapped_column(String(50), nullable=True)
    emergency_contact_name: Mapped[Optional[str]] = mapped_column(String(100), nullable=True)
    emergency_contact_phone: Mapped[Optional[str]] = mapped_column(String(20), nullable=True)
    joining_date: Mapped[date] = mapped_column(Date, default=date.today)
    notes: Mapped[Optional[str]] = mapped_column(String(1000), nullable=True)

    # Relationships
    user: Mapped["User"] = relationship("User", foreign_keys=[user_id])
    gym: Mapped["Gym"] = relationship("Gym", foreign_keys=[gym_id])


class MembershipPlan(BaseModel):
    __tablename__ = "membership_plans"

    gym_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("gyms.id", ondelete="CASCADE"), nullable=False, index=True
    )
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    description: Mapped[Optional[str]] = mapped_column(String(1000), nullable=True)
    price: Mapped[Decimal] = mapped_column(Numeric(10, 2), nullable=False)
    duration_days: Mapped[int] = mapped_column(nullable=False)
    is_active: Mapped[bool] = mapped_column(Boolean, default=True)

    # Relationships
    gym: Mapped["Gym"] = relationship("Gym", foreign_keys=[gym_id])


class ClientGymMembership(BaseModel):
    __tablename__ = "client_gym_memberships"

    gym_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("gyms.id", ondelete="CASCADE"), nullable=False, index=True
    )
    client_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    plan_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("membership_plans.id", ondelete="CASCADE"), nullable=False, index=True
    )
    start_date: Mapped[date] = mapped_column(Date, nullable=False)
    end_date: Mapped[date] = mapped_column(Date, nullable=False)
    status: Mapped[str] = mapped_column(String(50), default="ACTIVE")  # ACTIVE, EXPIRED, CANCELLED

    # Relationships
    gym: Mapped["Gym"] = relationship("Gym", foreign_keys=[gym_id])
    client: Mapped["User"] = relationship("User", foreign_keys=[client_id])
    plan: Mapped["MembershipPlan"] = relationship("MembershipPlan", foreign_keys=[plan_id])


class FeeLedger(BaseModel):
    __tablename__ = "fee_ledger"

    gym_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("gyms.id", ondelete="CASCADE"), nullable=False, index=True
    )
    client_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    membership_id: Mapped[Optional[ForeignKey]] = mapped_column(
        ForeignKey("client_gym_memberships.id", ondelete="SET NULL"), nullable=True, index=True
    )
    amount: Mapped[Decimal] = mapped_column(Numeric(10, 2), nullable=False)
    payment_mode: Mapped[str] = mapped_column(String(50))  # CASH, UPI, RAZORPAY, OTHER
    status: Mapped[str] = mapped_column(String(50), default="PENDING")  # PAID, PENDING, FAILED
    transaction_id: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    payment_date: Mapped[Optional[datetime]] = mapped_column(DateTime(timezone=True), nullable=True)
    due_date: Mapped[Optional[date]] = mapped_column(Date, nullable=True)
    notes: Mapped[Optional[str]] = mapped_column(String(1000), nullable=True)

    # Relationships
    gym: Mapped["Gym"] = relationship("Gym", foreign_keys=[gym_id])
    client: Mapped["User"] = relationship("User", foreign_keys=[client_id])
    membership: Mapped[Optional["ClientGymMembership"]] = relationship("ClientGymMembership", foreign_keys=[membership_id])


class Attendance(BaseModel):
    __tablename__ = "attendance"

    gym_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("gyms.id", ondelete="CASCADE"), nullable=False, index=True
    )
    client_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    date: Mapped[date] = mapped_column(Date, default=date.today)
    check_in: Mapped[time] = mapped_column(Time, nullable=False)
    check_out: Mapped[Optional[time]] = mapped_column(Time, nullable=True)

    # Relationships
    gym: Mapped["Gym"] = relationship("Gym", foreign_keys=[gym_id])
    client: Mapped["User"] = relationship("User", foreign_keys=[client_id])
