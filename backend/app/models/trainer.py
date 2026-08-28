from typing import Optional, List
from datetime import date, time, datetime
from decimal import Decimal
from sqlalchemy import String, Boolean, ForeignKey, Numeric, Date, Time, DateTime, Table, Column
from sqlalchemy.orm import Mapped, mapped_column, relationship
from app.models.base import BaseModel

# Association Table for Many-to-Many Trainer <-> Specialization
trainer_specialization_association = Table(
    "trainer_specialization_association",
    BaseModel.metadata,
    Column("trainer_id", ForeignKey("trainer_profiles.id", ondelete="CASCADE"), primary_key=True),
    Column("specialization_id", ForeignKey("specializations.id", ondelete="CASCADE"), primary_key=True)
)


class Specialization(BaseModel):
    __tablename__ = "specializations"

    name: Mapped[str] = mapped_column(String(255), unique=True, index=True, nullable=False)
    category: Mapped[str] = mapped_column(String(100), default="CORE_FITNESS")  # CORE_FITNESS, ADDITIONAL_GOALS


class TrainerProfile(BaseModel):
    __tablename__ = "trainer_profiles"

    user_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), unique=True, nullable=False
    )
    gym_id: Mapped[Optional[ForeignKey]] = mapped_column(
        ForeignKey("gyms.id", ondelete="SET NULL"), nullable=True, index=True
    )
    bio: Mapped[Optional[str]] = mapped_column(String(2000), nullable=True)
    experience_years: Mapped[int] = mapped_column(default=0)
    certifications: Mapped[Optional[str]] = mapped_column(String(1000), nullable=True)
    languages: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    city: Mapped[str] = mapped_column(String(100), nullable=False)
    area: Mapped[str] = mapped_column(String(100), nullable=False)
    is_online: Mapped[bool] = mapped_column(Boolean, default=True)
    is_offline: Mapped[bool] = mapped_column(Boolean, default=True)
    latitude: Mapped[Optional[Decimal]] = mapped_column(Numeric(9, 6), nullable=True)
    longitude: Mapped[Optional[Decimal]] = mapped_column(Numeric(9, 6), nullable=True)
    rating: Mapped[Decimal] = mapped_column(Numeric(3, 2), default=0.0)
    reviews_count: Mapped[int] = mapped_column(default=0)

    # Relationships
    user: Mapped["User"] = relationship("User", foreign_keys=[user_id])
    gym: Mapped[Optional["Gym"]] = relationship("Gym", foreign_keys=[gym_id])
    specializations: Mapped[List["Specialization"]] = relationship(
        "Specialization", secondary=trainer_specialization_association
    )
    services: Mapped[List["TrainerService"]] = relationship(
        "TrainerService", back_populates="trainer", cascade="all, delete-orphan"
    )


class TrainerService(BaseModel):
    __tablename__ = "trainer_services"

    trainer_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("trainer_profiles.id", ondelete="CASCADE"), nullable=False, index=True
    )
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    description: Mapped[Optional[str]] = mapped_column(String(1000), nullable=True)
    price: Mapped[Decimal] = mapped_column(Numeric(10, 2), nullable=False)

    # Relationships
    trainer: Mapped["TrainerProfile"] = relationship("TrainerProfile", back_populates="services")
    packages: Mapped[List["TrainerServicePackage"]] = relationship(
        "TrainerServicePackage", back_populates="service", cascade="all, delete-orphan"
    )


class TrainerServicePackage(BaseModel):
    __tablename__ = "trainer_service_packages"

    service_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("trainer_services.id", ondelete="CASCADE"), nullable=False, index=True
    )
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    price: Mapped[Decimal] = mapped_column(Numeric(10, 2), nullable=False)
    sessions_count: Mapped[int] = mapped_column(nullable=False)
    duration_days: Mapped[int] = mapped_column(nullable=False)

    # Relationships
    service: Mapped["TrainerService"] = relationship("TrainerService", back_populates="packages")


class TrainerBooking(BaseModel):
    __tablename__ = "trainer_bookings"

    client_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    trainer_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("trainer_profiles.id", ondelete="CASCADE"), nullable=False, index=True
    )
    package_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("trainer_service_packages.id", ondelete="CASCADE"), nullable=False, index=True
    )
    booking_date: Mapped[date] = mapped_column(Date, nullable=False)
    status: Mapped[str] = mapped_column(String(50), default="PENDING")  # PENDING, CONFIRMED, CANCELLED, COMPLETED
    price_paid: Mapped[Decimal] = mapped_column(Numeric(10, 2), nullable=False)

    # Relationships
    client: Mapped["User"] = relationship("User", foreign_keys=[client_id])
    trainer: Mapped["TrainerProfile"] = relationship("TrainerProfile", foreign_keys=[trainer_id])
    package: Mapped["TrainerServicePackage"] = relationship("TrainerServicePackage")
