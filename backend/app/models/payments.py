from typing import Optional
from datetime import datetime, timezone
from decimal import Decimal
from sqlalchemy import String, ForeignKey, Numeric, DateTime
from sqlalchemy.orm import Mapped, mapped_column, relationship
from app.models.base import BaseModel


class PaymentTransaction(BaseModel):
    __tablename__ = "payment_transactions"

    gym_id: Mapped[Optional[ForeignKey]] = mapped_column(
        ForeignKey("gyms.id", ondelete="SET NULL"), nullable=True, index=True
    )
    user_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    entity_type: Mapped[str] = mapped_column(String(50), nullable=False)  # MEMBERSHIP, TRAINER_BOOKING
    entity_id: Mapped[ForeignKey] = mapped_column(String(255), nullable=False)  # ID of FeeLedger or TrainerBooking
    order_id: Mapped[str] = mapped_column(String(255), unique=True, index=True, nullable=False)  # order_xyz
    payment_id: Mapped[Optional[str]] = mapped_column(String(255), unique=True, nullable=True)  # pay_xyz
    gross_amount: Mapped[Decimal] = mapped_column(Numeric(10, 2), nullable=False)
    platform_fee: Mapped[Decimal] = mapped_column(Numeric(10, 2), default=Decimal("10.00"))
    gateway_fee: Mapped[Decimal] = mapped_column(Numeric(10, 2), default=Decimal("0.00"))
    net_amount: Mapped[Decimal] = mapped_column(Numeric(10, 2), nullable=False)
    status: Mapped[str] = mapped_column(String(50), default="PENDING")  # PENDING, SUCCESS, FAILED
    settlement_status: Mapped[str] = mapped_column(String(50), default="UNSETTLED")  # UNSETTLED, SETTLED

    # Relationships
    gym: Mapped[Optional["Gym"]] = relationship("Gym")
    user: Mapped["User"] = relationship("User")
