from fastapi import APIRouter, Depends, HTTPException, status, BackgroundTasks
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy.orm import selectinload
import uuid
from typing import List

from app.core import deps
from app.models.auth import User, UserGymRole
from app.models.notifications import NotificationTemplate, NotificationLog
from app.schemas.notifications import (
    NotificationTemplateCreate,
    NotificationTemplateResponse,
    NotificationLogResponse,
    ManualNotificationRequest,
    ScanDuesResponse
)
from app.services import notifications as notify_service

router = APIRouter()


@router.post("/scan-dues", response_model=ScanDuesResponse)
async def trigger_dues_scan(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER", "GYM_MANAGER"])),
    gym_id: str = Depends(deps.get_gym_id_header)
):
    if not gym_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Missing gym context header X-Gym-ID"
        )
    gym_uuid = uuid.UUID(gym_id)

    res = await notify_service.scan_and_notify_dues(db, gym_uuid)
    
    return ScanDuesResponse(
        due_today_count=res["due_today"],
        due_3_days_count=res["due_3_days"],
        due_7_days_count=res["due_7_days"],
        overdue_count=res["overdue"],
        expiring_soon_count=res["expiring_soon"],
        notifications_sent_count=res["sent"]
    )


@router.post("/send-manual", response_model=NotificationLogResponse)
async def send_manual_notification(
    req: ManualNotificationRequest,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER", "GYM_MANAGER", "RECEPTIONIST"])),
    gym_id: str = Depends(deps.get_gym_id_header)
):
    if not gym_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Missing gym context header X-Gym-ID"
        )
    gym_uuid = uuid.UUID(gym_id)

    # Verify target client is associated with this gym context
    role_query = select(UserGymRole).where(
        UserGymRole.user_id == req.client_id,
        UserGymRole.gym_id == gym_uuid,
        UserGymRole.role == "CLIENT"
    )
    role_res = await db.execute(role_query)
    if not role_res.scalar_one_or_none():
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Client is not associated with this gym context"
        )

    # Fetch client details
    client_q = select(User).where(User.id == req.client_id)
    client_res = await db.execute(client_q)
    client = client_res.scalar_one()

    log = await notify_service.notification_manager.send(
        db=db,
        gym_id=gym_uuid,
        client=client,
        channel=req.channel,
        subject=req.subject,
        body_template=req.body,
        variables={}
    )
    
    # Load client relationship
    query_updated = (
        select(NotificationLog)
        .where(NotificationLog.id == log.id)
        .options(selectinload(NotificationLog.client).selectinload(User.gym_roles))
    )
    res = await db.execute(query_updated)
    return res.scalar_one()


@router.get("/logs", response_model=List[NotificationLogResponse])
async def get_notification_logs(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER", "GYM_MANAGER"])),
    gym_id: str = Depends(deps.get_gym_id_header)
):
    if not gym_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Missing gym context header X-Gym-ID"
        )
    gym_uuid = uuid.UUID(gym_id)

    query = (
        select(NotificationLog)
        .where(NotificationLog.gym_id == gym_uuid)
        .options(selectinload(NotificationLog.client).selectinload(User.gym_roles))
    )
    result = await db.execute(query)
    return result.scalars().all()


@router.post("/templates", response_model=NotificationTemplateResponse)
async def create_or_update_template(
    template_in: NotificationTemplateCreate,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER", "GYM_MANAGER"])),
    gym_id: str = Depends(deps.get_gym_id_header)
):
    if not gym_id:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Missing gym context header X-Gym-ID"
        )
    gym_uuid = uuid.UUID(gym_id)

    # Check if template already exists (upsert logic)
    query = select(NotificationTemplate).where(
        NotificationTemplate.gym_id == gym_uuid,
        NotificationTemplate.name == template_in.name,
        NotificationTemplate.channel == template_in.channel
    )
    result = await db.execute(query)
    template = result.scalar_one_or_none()

    if template:
        template.subject = template_in.subject
        template.body = template_in.body
    else:
        template = NotificationTemplate(
            gym_id=gym_uuid,
            name=template_in.name,
            channel=template_in.channel,
            subject=template_in.subject,
            body=template_in.body
        )
        db.add(template)
        
    await db.flush()
    return template
