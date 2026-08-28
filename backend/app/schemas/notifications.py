from datetime import datetime
from typing import Optional, List
from pydantic import BaseModel, Field, UUID4, ConfigDict
from app.schemas.auth import UserResponse


class NotificationTemplateBase(BaseModel):
    name: str = Field(..., max_length=255)
    channel: str = Field(..., description="EMAIL, SMS, WHATSAPP")
    subject: Optional[str] = Field(default=None, max_length=255)
    body: str = Field(..., max_length=2000)


class NotificationTemplateCreate(NotificationTemplateBase):
    pass


class NotificationTemplateResponse(NotificationTemplateBase):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    gym_id: Optional[UUID4] = None


class NotificationLogResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    gym_id: UUID4
    client_id: UUID4
    channel: str
    recipient: str
    status: str
    error_message: Optional[str] = None
    sent_at: datetime
    client: Optional[UserResponse] = None


class ManualNotificationRequest(BaseModel):
    client_id: UUID4
    channel: str = Field(..., description="EMAIL, SMS, WHATSAPP")
    subject: Optional[str] = Field(default=None, max_length=255)
    body: str = Field(..., max_length=2000)


class ScanDuesResponse(BaseModel):
    due_today_count: int
    due_3_days_count: int
    due_7_days_count: int
    overdue_count: int
    expiring_soon_count: int
    notifications_sent_count: int
