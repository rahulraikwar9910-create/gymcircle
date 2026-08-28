from typing import Optional, List
from datetime import date, time, datetime
from decimal import Decimal
from sqlalchemy import String, ForeignKey, Numeric, Date, Time, Integer
from sqlalchemy.orm import Mapped, mapped_column, relationship
from app.models.base import BaseModel


class Exercise(BaseModel):
    __tablename__ = "exercises"

    name: Mapped[str] = mapped_column(String(255), unique=True, index=True, nullable=False)
    muscle_group: Mapped[str] = mapped_column(String(100), nullable=False)
    description: Mapped[Optional[str]] = mapped_column(String(1000), nullable=True)
    video_url: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)


class WorkoutPlan(BaseModel):
    __tablename__ = "workout_plans"

    trainer_id: Mapped[Optional[ForeignKey]] = mapped_column(
        ForeignKey("trainer_profiles.id", ondelete="SET NULL"), nullable=True, index=True
    )
    client_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    description: Mapped[Optional[str]] = mapped_column(String(1000), nullable=True)

    # Relationships
    trainer: Mapped[Optional["TrainerProfile"]] = relationship("TrainerProfile")
    client: Mapped["User"] = relationship("User")
    days: Mapped[List["WorkoutDay"]] = relationship(
        "WorkoutDay", back_populates="plan", cascade="all, delete-orphan", order_by="WorkoutDay.order"
    )


class WorkoutDay(BaseModel):
    __tablename__ = "workout_days"

    plan_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("workout_plans.id", ondelete="CASCADE"), nullable=False, index=True
    )
    day_name: Mapped[str] = mapped_column(String(100), nullable=False)  # e.g., Day 1 - Push
    order: Mapped[int] = mapped_column(Integer, default=0)

    # Relationships
    plan: Mapped["WorkoutPlan"] = relationship("WorkoutPlan", back_populates="days")
    exercises: Mapped[List["WorkoutDayExercise"]] = relationship(
        "WorkoutDayExercise", back_populates="day", cascade="all, delete-orphan", order_by="WorkoutDayExercise.order"
    )


class WorkoutDayExercise(BaseModel):
    __tablename__ = "workout_day_exercises"

    day_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("workout_days.id", ondelete="CASCADE"), nullable=False, index=True
    )
    exercise_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("exercises.id", ondelete="RESTRICT"), nullable=False, index=True
    )
    sets: Mapped[int] = mapped_column(Integer, default=3)
    reps: Mapped[str] = mapped_column(String(50), default="10")  # e.g. "8-12"
    rest_seconds: Mapped[int] = mapped_column(Integer, default=60)
    notes: Mapped[Optional[str]] = mapped_column(String(1000), nullable=True)
    order: Mapped[int] = mapped_column(Integer, default=0)

    # Relationships
    day: Mapped["WorkoutDay"] = relationship("WorkoutDay", back_populates="exercises")
    exercise: Mapped["Exercise"] = relationship("Exercise")


class DietPlan(BaseModel):
    __tablename__ = "diet_plans"

    trainer_id: Mapped[Optional[ForeignKey]] = mapped_column(
        ForeignKey("trainer_profiles.id", ondelete="SET NULL"), nullable=True, index=True
    )
    client_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    description: Mapped[Optional[str]] = mapped_column(String(1000), nullable=True)
    total_calories: Mapped[int] = mapped_column(Integer, default=0)
    total_protein_g: Mapped[int] = mapped_column(Integer, default=0)
    total_carbs_g: Mapped[int] = mapped_column(Integer, default=0)
    total_fat_g: Mapped[int] = mapped_column(Integer, default=0)

    # Relationships
    trainer: Mapped[Optional["TrainerProfile"]] = relationship("TrainerProfile")
    client: Mapped["User"] = relationship("User")
    meals: Mapped[List["DietMeal"]] = relationship(
        "DietMeal", back_populates="plan", cascade="all, delete-orphan", order_by="DietMeal.order"
    )


class DietMeal(BaseModel):
    __tablename__ = "diet_meals"

    plan_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("diet_plans.id", ondelete="CASCADE"), nullable=False, index=True
    )
    name: Mapped[str] = mapped_column(String(100), nullable=False)  # e.g., Breakfast
    time: Mapped[Optional[time]] = mapped_column(Time, nullable=True)
    calories: Mapped[int] = mapped_column(Integer, default=0)
    protein_g: Mapped[int] = mapped_column(Integer, default=0)
    carbs_g: Mapped[int] = mapped_column(Integer, default=0)
    fat_g: Mapped[int] = mapped_column(Integer, default=0)
    items: Mapped[str] = mapped_column(String(2000), nullable=False)  # Food description
    order: Mapped[int] = mapped_column(Integer, default=0)

    # Relationships
    plan: Mapped["DietPlan"] = relationship("DietPlan", back_populates="meals")


class ProgressLog(BaseModel):
    __tablename__ = "progress_logs"

    client_id: Mapped[ForeignKey] = mapped_column(
        ForeignKey("users.id", ondelete="CASCADE"), nullable=False, index=True
    )
    date: Mapped[date] = mapped_column(Date, default=date.today)
    weight_kg: Mapped[Decimal] = mapped_column(Numeric(5, 2), nullable=False)
    height_cm: Mapped[Decimal] = mapped_column(Numeric(5, 2), nullable=False)
    bmi: Mapped[Decimal] = mapped_column(Numeric(4, 2), nullable=False)
    body_fat_percentage: Mapped[Optional[Decimal]] = mapped_column(Numeric(4, 2), nullable=True)
    notes: Mapped[Optional[str]] = mapped_column(String(1000), nullable=True)

    # Relationships
    client: Mapped["User"] = relationship("User")
