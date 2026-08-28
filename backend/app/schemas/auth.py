from datetime import datetime
from typing import Optional, List
from pydantic import BaseModel, EmailStr, Field, UUID4, ConfigDict


class UserBase(BaseModel):
    email: Optional[EmailStr] = None
    phone: Optional[str] = Field(default=None, max_length=20)
    first_name: str = Field(..., max_length=100)
    last_name: str = Field(..., max_length=100)


class UserCreate(UserBase):
    password: str = Field(..., min_length=6)


class UserGymRoleResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    gym_id: Optional[UUID4] = None
    role: str
    is_active: bool


class UserResponse(UserBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    is_active: bool
    created_at: datetime
    updated_at: datetime
    gym_roles: List[UserGymRoleResponse] = []


class GymBase(BaseModel):
    name: str = Field(..., max_length=255)
    slug: str = Field(..., max_length=255)
    email: Optional[EmailStr] = None
    phone: Optional[str] = Field(default=None, max_length=20)
    address: Optional[str] = Field(default=None, max_length=255)
    city: Optional[str] = Field(default=None, max_length=100)
    state: Optional[str] = Field(default=None, max_length=100)
    pincode: Optional[str] = Field(default=None, max_length=10)


class GymCreate(GymBase):
    pass


class GymResponse(GymBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    status: str
    created_at: datetime
    updated_at: datetime


class OwnerRegisterRequest(BaseModel):
    email: EmailStr
    phone: Optional[str] = Field(default=None, max_length=20)
    password: str = Field(..., min_length=6)
    first_name: str = Field(..., max_length=100)
    last_name: str = Field(..., max_length=100)
    gym_name: str = Field(..., max_length=255)
    gym_slug: str = Field(..., max_length=255)


class LoginRequest(BaseModel):
    email: EmailStr
    password: str


class Token(BaseModel):
    access_token: str
    refresh_token: str
    token_type: str = "bearer"


class TokenPayload(BaseModel):
    sub: Optional[str] = None
    type: Optional[str] = None


class TokenRefreshRequest(BaseModel):
    refresh_token: str


class UserInvitationCreate(BaseModel):
    email: Optional[EmailStr] = None
    phone: Optional[str] = Field(default=None, max_length=20)
    role: str = Field(..., description="SUPER_ADMIN, GYM_OWNER, GYM_MANAGER, RECEPTIONIST, TRAINER, CLIENT")


class UserInvitationResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    gym_id: UUID4
    email: Optional[EmailStr] = None
    phone: Optional[str] = None
    role: str
    token: str
    expires_at: datetime
    is_accepted: bool
    created_by: Optional[UUID4] = None


class AcceptInviteRequest(BaseModel):
    token: str
    password: str = Field(..., min_length=6)
    first_name: str = Field(..., max_length=100)
    last_name: str = Field(..., max_length=100)
