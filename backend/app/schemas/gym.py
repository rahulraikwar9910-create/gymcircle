from datetime import date, time, datetime
from typing import Optional, List
from decimal import Decimal
from pydantic import BaseModel, EmailStr, Field, UUID4, ConfigDict
from app.schemas.auth import UserResponse


class ClientProfileBase(BaseModel):
    date_of_birth: Optional[date] = None
    gender: Optional[str] = Field(default=None, max_length=50)
    emergency_contact_name: Optional[str] = Field(default=None, max_length=100)
    emergency_contact_phone: Optional[str] = Field(default=None, max_length=20)
    joining_date: date = Field(default_factory=date.today)
    notes: Optional[str] = Field(default=None, max_length=1000)


class ClientProfileCreate(ClientProfileBase):
    user_id: UUID4
    gym_id: UUID4


class ClientProfileResponse(ClientProfileBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    user_id: UUID4
    gym_id: UUID4


class ClientCreateRequest(ClientProfileBase):
    email: Optional[EmailStr] = None
    phone: Optional[str] = Field(default=None, max_length=20)
    first_name: str = Field(..., max_length=100)
    last_name: str = Field(..., max_length=100)


class ClientDetailResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4  # user_id
    email: Optional[str] = None
    phone: Optional[str] = None
    first_name: str
    last_name: str
    is_active: bool
    profile: Optional[ClientProfileResponse] = None


class MembershipPlanBase(BaseModel):
    name: str = Field(..., max_length=255)
    description: Optional[str] = Field(default=None, max_length=1000)
    price: Decimal = Field(..., gt=0)
    duration_days: int = Field(..., gt=0)


class MembershipPlanCreate(MembershipPlanBase):
    pass


class MembershipPlanResponse(MembershipPlanBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    gym_id: UUID4
    is_active: bool


class ClientGymMembershipBase(BaseModel):
    client_id: UUID4
    plan_id: UUID4
    start_date: date = Field(default_factory=date.today)


class ClientGymMembershipCreate(ClientGymMembershipBase):
    pass


class ClientGymMembershipResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    gym_id: UUID4
    client_id: UUID4
    plan_id: UUID4
    start_date: date
    end_date: date
    status: str
    plan: Optional[MembershipPlanResponse] = None


class FeeLedgerResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    gym_id: UUID4
    client_id: UUID4
    membership_id: Optional[UUID4] = None
    amount: Decimal
    payment_mode: str
    status: str
    transaction_id: Optional[str] = None
    payment_date: Optional[datetime] = None
    due_date: Optional[date] = None
    notes: Optional[str] = None
    client: Optional[UserResponse] = None


class PaymentRecordRequest(BaseModel):
    payment_mode: str = Field(..., description="CASH, UPI, RAZORPAY, OTHER")
    transaction_id: Optional[str] = Field(default=None, max_length=255)
    notes: Optional[str] = Field(default=None, max_length=1000)


class DashboardMetricsResponse(BaseModel):
    today_collection: Decimal
    monthly_collection: Decimal
    pending_fees_total: Decimal
    overdue_count: int
    expiring_count: int


class AttendanceCheckInRequest(BaseModel):
    client_id: UUID4


class AttendanceResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    gym_id: UUID4
    client_id: UUID4
    date: date
    check_in: time
    check_out: Optional[time] = None
    client: Optional[UserResponse] = None


class CheckInResultResponse(BaseModel):
    attendance: AttendanceResponse
    has_pending_fees: bool
    membership_expired: bool
    message: str
