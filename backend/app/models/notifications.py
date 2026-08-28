from typing import Optional
from datetime import datetime, timezone
from sqlalchemy import String, ForeignKey, DateTime
from sqlalchemy.orm import Mapped, mapped_column, relationship
from app.models.base import BaseModel


class NotificationTemplate(BaseModel):
    __tablename__ = "notification_templates"

    gym_id: Mapped[Optional[ForeignKey]] = mapped_column(
        ForeignKey("gyms.id", ondelete="CASCADE"), nullable=True
    )
    name: Mapped[str] = mapped_column(String(255), nullable=False)  # e.g., 'due_reminder_3_days'
    channel: Mapped[str] = mapped_column(String(50), nullable=False)  # EMAIL, SMS, WHATSAPP
    subject: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    body: Mapped[str] = mapped_column(String(2000), nullable=False)

    # Relationships
    gym: Mapped[Optional["Gym"]] = relationship("Gym")


class NotificationLog(BaseModel):
    __tablename__ = "notification_logs"

    gym_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("gyms.id", ondelete="CASCADE"), nullable=False
    )
    client_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False
    )
    channel: Mapped[str] = mapped_column(String(50), nullable=False)  # EMAIL, SMS, WHATSAPP
    recipient: Mapped[str] = mapped_column(String(255), nullable=False)
    status: Mapped[str] = mapped_column(String(50), default="PENDING")  # SENT, FAILED, PENDING
    error_message: Mapped[Optional[str]] = mapped_column(String(1000), nullable=True)
    sent_at: Mapped[datetime] = mapped_column(
        DateTime(timezone=True), default=lambda: datetime.now(timezone.utc)
    )

    # Relationships
    gym: Mapped["Gym"] = relationship("Gym")
    client: Mapped["User"] = relationship("User")
