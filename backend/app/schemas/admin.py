from datetime import datetime
from typing import Dict, Optional, List
from decimal import Decimal
from pydantic import BaseModel, Field, UUID4, ConfigDict


class SuperAdminMetricsResponse(BaseModel):
    total_platform_revenue: Decimal
    total_platform_fees_collected: Decimal
    total_gyms_count: int
    total_users_count: int
    active_memberships_count: int
    active_trainers_count: int
    users_by_role: Dict[str, int]


class GymReportResponse(BaseModel):
    model_config = ConfigDict(from_attributes=True)

    gym_id: UUID4
    name: str
    slug: str
    active_members_count: int
    total_revenue_generated: Decimal
    created_at: datetime


class SettleTransactionRequest(BaseModel):
    settlement_status: str = Field(..., description="SETTLED, UNSETTLED")
