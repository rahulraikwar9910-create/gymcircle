from datetime import date, time, datetime
from typing import Optional, List
from decimal import Decimal
from pydantic import BaseModel, Field, UUID4, ConfigDict
from app.schemas.auth import UserResponse, GymResponse


class SpecializationResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    name: str
    category: str


class TrainerProfileBase(BaseModel):
    bio: Optional[str] = Field(default=None, max_length=2000)
    experience_years: int = Field(default=0, ge=0)
    certifications: Optional[str] = Field(default=None, max_length=1000)
    languages: Optional[str] = Field(default=None, max_length=255)
    city: str = Field(..., max_length=100)
    area: str = Field(..., max_length=100)
    is_online: bool = True
    is_offline: bool = True
    latitude: Optional[Decimal] = Field(default=None)
    longitude: Optional[Decimal] = Field(default=None)


class TrainerProfileCreate(TrainerProfileBase):
    specializations: List[UUID4] = []
    gym_id: Optional[UUID4] = None


class TrainerProfileResponse(TrainerProfileBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    user_id: UUID4
    gym_id: Optional[UUID4] = None
    rating: Decimal
    reviews_count: int
    specializations: List[SpecializationResponse] = []


class TrainerServiceBase(BaseModel):
    name: str = Field(..., max_length=255)
    description: Optional[str] = Field(default=None, max_length=1000)
    price: Decimal = Field(..., gt=0)


class TrainerServiceCreate(TrainerServiceBase):
    pass


class TrainerServicePackageBase(BaseModel):
    name: str = Field(..., max_length=255)
    price: Decimal = Field(..., gt=0)
    sessions_count: int = Field(..., gt=0)
    duration_days: int = Field(..., gt=0)


class TrainerServicePackageCreate(TrainerServicePackageBase):
    pass


class TrainerServicePackageResponse(TrainerServicePackageBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    service_id: UUID4


class TrainerServiceResponse(TrainerServiceBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    trainer_id: UUID4
    packages: List[TrainerServicePackageResponse] = []


class TrainerMarketplaceResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4  # user_id
    first_name: str
    last_name: str
    email: Optional[str] = None
    phone: Optional[str] = None
    profile: Optional[TrainerProfileResponse] = None
    distance_km: Optional[float] = None


class TrainerBookingCreate(BaseModel):
    trainer_id: UUID4
    package_id: UUID4
    booking_date: date = Field(default_factory=date.today)


class TrainerBookingResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    client_id: UUID4
    trainer_id: UUID4
    package_id: UUID4
    booking_date: date
    status: str
    price_paid: Decimal
    client: Optional[UserResponse] = None
    trainer: Optional[TrainerProfileResponse] = None
    package: Optional[TrainerServicePackageResponse] = None
