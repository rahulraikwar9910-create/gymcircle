from datetime import date, time, datetime
from typing import Optional, List
from decimal import Decimal
from pydantic import BaseModel, Field, UUID4, ConfigDict


# ==========================================
# EXERCISE SCHEMAS
# ==========================================
class ExerciseBase(BaseModel):
    name: str = Field(..., max_length=255)
    muscle_group: str = Field(..., max_length=100)
    description: Optional[str] = Field(default=None, max_length=1000)
    video_url: Optional[str] = Field(default=None, max_length=255)


class ExerciseCreate(ExerciseBase):
    pass


class ExerciseResponse(ExerciseBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4


# ==========================================
# WORKOUT SCHEMAS
# ==========================================
class WorkoutDayExerciseBase(BaseModel):
    exercise_id: UUID4
    sets: int = Field(default=3, gt=0)
    reps: str = Field(default="10", max_length=50)
    rest_seconds: int = Field(default=60, ge=0)
    notes: Optional[str] = Field(default=None, max_length=1000)
    order: int = Field(default=0)


class WorkoutDayExerciseCreate(WorkoutDayExerciseBase):
    pass


class WorkoutDayExerciseResponse(WorkoutDayExerciseBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    day_id: UUID4
    exercise: Optional[ExerciseResponse] = None


class WorkoutDayBase(BaseModel):
    day_name: str = Field(..., max_length=100)
    order: int = Field(default=0)


class WorkoutDayCreate(WorkoutDayBase):
    exercises: List[WorkoutDayExerciseCreate] = []


class WorkoutDayResponse(WorkoutDayBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    plan_id: UUID4
    exercises: List[WorkoutDayExerciseResponse] = []


class WorkoutPlanBase(BaseModel):
    client_id: UUID4
    name: str = Field(..., max_length=255)
    description: Optional[str] = Field(default=None, max_length=1000)


class WorkoutPlanCreate(WorkoutPlanBase):
    days: List[WorkoutDayCreate] = []


class WorkoutPlanResponse(WorkoutPlanBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    trainer_id: Optional[UUID4] = None
    days: List[WorkoutDayResponse] = []


# ==========================================
# DIET SCHEMAS
# ==========================================
class DietMealBase(BaseModel):
    name: str = Field(..., max_length=100)
    time: Optional[time] = None
    calories: int = Field(default=0, ge=0)
    protein_g: int = Field(default=0, ge=0)
    carbs_g: int = Field(default=0, ge=0)
    fat_g: int = Field(default=0, ge=0)
    items: str = Field(..., max_length=2000)
    order: int = Field(default=0)


class DietMealCreate(DietMealBase):
    pass


class DietMealResponse(DietMealBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    plan_id: UUID4


class DietPlanBase(BaseModel):
    client_id: UUID4
    name: str = Field(..., max_length=255)
    description: Optional[str] = Field(default=None, max_length=1000)


class DietPlanCreate(DietPlanBase):
    meals: List[DietMealCreate] = []


class DietPlanResponse(DietPlanBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    trainer_id: Optional[UUID4] = None
    total_calories: int
    total_protein_g: int
    total_carbs_g: int
    total_fat_g: int
    meals: List[DietMealResponse] = []


# ==========================================
# PROGRESS LOG SCHEMAS
# ==========================================
class ProgressLogBase(BaseModel):
    weight_kg: Decimal = Field(..., gt=0)
    height_cm: Decimal = Field(..., gt=0)
    body_fat_percentage: Optional[Decimal] = Field(default=None, ge=0)
    notes: Optional[str] = Field(default=None, max_length=1000)


class ProgressLogCreate(ProgressLogBase):
    pass


class ProgressLogResponse(ProgressLogBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    client_id: UUID4
    date: date
    bmi: Decimal
