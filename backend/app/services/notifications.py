import logging
from abc import ABC, abstractmethod
from datetime import datetime, date, timedelta, timezone
from typing import Optional, List, Dict, Any
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy.orm import selectinload
import uuid

from app.models.auth import User, Gym
from app.models.gym import FeeLedger, ClientGymMembership
from app.models.notifications import NotificationTemplate, NotificationLog

logger = logging.getLogger("gymcircle.notifications")


class BaseNotificationProvider(ABC):
    @abstractmethod
    async def send(self, recipient: str, subject: Optional[str], body: str) -> bool:
        pass


class MockEmailProvider(BaseNotificationProvider):
    async def send(self, recipient: str, subject: Optional[str], body: str) -> bool:
        logger.info(f"[MOCK EMAIL] To: {recipient} | Subject: {subject} | Body: {body}")
        return True


class MockSMSProvider(BaseNotificationProvider):
    async def send(self, recipient: str, subject: Optional[str], body: str) -> bool:
        logger.info(f"[MOCK SMS] To: {recipient} | Body: {body}")
        return True


class MockWhatsAppProvider(BaseNotificationProvider):
    async def send(self, recipient: str, subject: Optional[str], body: str) -> bool:
        logger.info(f"[MOCK WHATSAPP] To: {recipient} | Body: {body}")
        return True


# Dispatcher Manager
class NotificationManager:
    def __init__(self):
        self.providers: Dict[str, BaseNotificationProvider] = {
            "EMAIL": MockEmailProvider(),
            "SMS": MockSMSProvider(),
            "WHATSAPP": MockWhatsAppProvider()
        }

    async def send(
        self,
        db: AsyncSession,
        gym_id: uuid.UUID,
        client: User,
        channel: str,
        subject: Optional[str],
        body_template: str,
        variables: Dict[str, Any]
    ) -> NotificationLog:
        # Replaces placeholders like {client_name} with actual values
        body = body_template
        for key, val in variables.items():
            placeholder = f"{{{key}}}"
            body = body.replace(placeholder, str(val))

        # Select recipient based on channel
        recipient = client.email if channel == "EMAIL" else client.phone
        if not recipient:
            log = NotificationLog(
                gym_id=gym_id,
                client_id=client.id,
                channel=channel,
                recipient="UNKNOWN",
                status="FAILED",
                error_message=f"Missing contact info for client on channel {channel}"
            )
            db.add(log)
            await db.flush()
            return log

        provider = self.providers.get(channel.upper())
        if not provider:
            log = NotificationLog(
                gym_id=gym_id,
                client_id=client.id,
                channel=channel,
                recipient=recipient,
                status="FAILED",
                error_message=f"Unsupported notification channel: {channel}"
            )
            db.add(log)
            await db.flush()
            return log

        try:
            success = await provider.send(recipient, subject, body)
            status_val = "SENT" if success else "FAILED"
            error_msg = None if success else "Provider failed to send"
        except Exception as e:
            success = False
            status_val = "FAILED"
            error_msg = str(e)

        log = NotificationLog(
            gym_id=gym_id,
            client_id=client.id,
            channel=channel,
            recipient=recipient,
            status=status_val,
            error_message=error_msg
        )
        db.add(log)
        await db.flush()
        return log


notification_manager = NotificationManager()


async def scan_and_notify_dues(db: AsyncSession, gym_id: uuid.UUID) -> Dict[str, int]:
    today = date.today()
    counts = {
        "due_today": 0,
        "due_3_days": 0,
        "due_7_days": 0,
        "overdue": 0,
        "expiring_soon": 0,
        "sent": 0
    }

    # 1. Fetch gym custom templates, or use default template definitions
    templates_q = select(NotificationTemplate).where(NotificationTemplate.gym_id == gym_id)
    templates_res = await db.execute(templates_q)
    templates = {t.name: t for t in templates_res.scalars().all()}

    def get_template(name: str, default_subject: str, default_body: str) -> tuple[Optional[str], str]:
        # Helper to fallback to standard defaults if no custom template is saved
        t = templates.get(name)
        if t:
            return t.subject, t.body
        return default_subject, default_body

    # ==========================================
    # SCAN FEE DUEDATES
    # ==========================================
    # Fetch all PENDING invoices for the gym, eager loading client details
    invoices_q = (
        select(FeeLedger)
        .where(FeeLedger.gym_id == gym_id, FeeLedger.status == "PENDING")
        .options(selectinload(FeeLedger.client))
    )
    invoices_res = await db.execute(invoices_q)
    invoices = invoices_res.scalars().all()

    for invoice in invoices:
        if not invoice.due_date:
            continue
            
        due_diff = (invoice.due_date - today).days
        category = None
        
        if due_diff == 0:
            category = "due_today"
            tpl_name = "fee_due_today"
            subject, body = get_template(
                tpl_name,
                "Fees Due Today",
                "Hi {client_name}, your membership fee of Rs. {amount} is due today."
            )
        elif due_diff == 3:
            category = "due_3_days"
            tpl_name = "fee_due_3_days"
            subject, body = get_template(
                tpl_name,
                "Upcoming Fee Reminder",
                "Hi {client_name}, your membership fee of Rs. {amount} is due in 3 days."
            )
        elif due_diff == 7:
            category = "due_7_days"
            tpl_name = "fee_due_7_days"
            subject, body = get_template(
                tpl_name,
                "Upcoming Fee Reminder",
                "Hi {client_name}, your membership fee of Rs. {amount} is due in 7 days."
            )
        elif due_diff < 0:
            category = "overdue"
            tpl_name = "fee_overdue"
            subject, body = get_template(
                tpl_name,
                "ALERT: Overdue Fees Notice",
                "Hi {client_name}, your membership fee of Rs. {amount} is overdue by {days_late} days. Please pay immediately."
            )

        if category:
            counts[category] += 1
            variables = {
                "client_name": f"{invoice.client.first_name} {invoice.client.last_name}",
                "amount": invoice.amount,
                "due_date": str(invoice.due_date),
                "days_late": abs(due_diff)
            }
            # Dispatch default notification via SMS
            log = await notification_manager.send(
                db=db,
                gym_id=gym_id,
                client=invoice.client,
                channel="SMS",
                subject=subject,
                body_template=body,
                variables=variables
            )
            if log.status == "SENT":
                counts["sent"] += 1

    # ==========================================
    # SCAN MEMBERSHIP EXPIRATIONS (ACTIVE ending in exactly 7 days)
    # ==========================================
    memberships_q = (
        select(ClientGymMembership)
        .where(
            ClientGymMembership.gym_id == gym_id,
            ClientGymMembership.status == "ACTIVE",
            ClientGymMembership.end_date == today + timedelta(days=7)
        )
        .options(selectinload(ClientGymMembership.client), selectinload(ClientGymMembership.plan))
    )
    memberships_res = await db.execute(memberships_q)
    memberships = memberships_res.scalars().all()

    for membership in memberships:
        counts["expiring_soon"] += 1
        subject, body = get_template(
            "membership_expiring_soon",
            "Membership Expiring Soon",
            "Hi {client_name}, your {plan_name} membership is expiring in 7 days on {end_date}. Renew today!"
        )
        variables = {
            "client_name": f"{membership.client.first_name} {membership.client.last_name}",
            "plan_name": membership.plan.name,
            "end_date": str(membership.end_date)
        }
        log = await notification_manager.send(
            db=db,
            gym_id=gym_id,
            client=membership.client,
            channel="SMS",
            subject=subject,
            body_template=body,
            variables=variables
        )
        if log.status == "SENT":
            counts["sent"] += 1

    return counts
