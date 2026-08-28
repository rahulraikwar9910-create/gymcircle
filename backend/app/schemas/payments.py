from datetime import datetime
from typing import Optional
from decimal import Decimal
from pydantic import BaseModel, Field, UUID4, ConfigDict
from app.schemas.auth import UserResponse


class OrderCreateRequest(BaseModel):
    entity_type: str = Field(..., description="MEMBERSHIP, TRAINER_BOOKING")
    entity_id: UUID4 = Field(...)


class OrderResponse(BaseModel):
    order_id: str
    gross_amount: Decimal
    platform_fee: Decimal
    gateway_fee: Decimal
    net_amount: Decimal


class PaymentVerificationRequest(BaseModel):
    razorpay_order_id: str
    razorpay_payment_id: str
    razorpay_signature: str


class PaymentTransactionResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    id: UUID4
    gym_id: Optional[UUID4] = None
    user_id: UUID4
    entity_type: str
    entity_id: str
    order_id: str
    payment_id: Optional[str] = None
    gross_amount: Decimal
    platform_fee: Decimal
    gateway_fee: Decimal
    net_amount: Decimal
    status: str
    settlement_status: str
    created_at: datetime
    user: Optional[UserResponse] = None
