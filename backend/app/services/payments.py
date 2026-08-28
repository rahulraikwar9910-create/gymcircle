import logging
import uuid
import secrets
from datetime import datetime, timezone
from decimal import Decimal
from typing import Dict, Any, Optional
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select

from app.models.payments import PaymentTransaction
from app.models.gym import FeeLedger
from app.models.trainer import TrainerBooking

logger = logging.getLogger("gymcircle.payments")


# Mock Razorpay Class structures
class MockRazorpayOrder:
    def create(self, data: Dict[str, Any]) -> Dict[str, Any]:
        order_id = f"order_{secrets.token_hex(8)}"
        return {
            "id": order_id,
            "amount": data["amount"],
            "currency": data["currency"],
            "receipt": data.get("receipt"),
            "status": "created"
        }


class MockRazorpayUtility:
    def verify_payment_signature(self, params: Dict[str, str]) -> bool:
        # Simplistic mockup: verifies signature structure
        order_id = params.get("razorpay_order_id")
        payment_id = params.get("razorpay_payment_id")
        signature = params.get("razorpay_signature")
        if not order_id or not payment_id or not signature:
            raise ValueError("Invalid signature parameters")
        return True


class MockRazorpayClient:
    def __init__(self):
        self.order = MockRazorpayOrder()
        self.utility = MockRazorpayUtility()


# Payment Management Service
class PaymentService:
    def __init__(self):
        self.client = MockRazorpayClient()

    def calculate_splits(self, gross_amount: Decimal) -> Dict[str, Decimal]:
        platform_fee = Decimal("10.00")
        # 2% gateway fee simulation
        gateway_fee = (gross_amount * Decimal("0.02")).quantize(Decimal("0.01"))
        net_amount = gross_amount - platform_fee - gateway_fee
        return {
            "gross_amount": gross_amount,
            "platform_fee": platform_fee,
            "gateway_fee": gateway_fee,
            "net_amount": net_amount
        }

    async def create_gateway_order(
        self,
        db: AsyncSession,
        user_id: uuid.UUID,
        gym_id: Optional[uuid.UUID],
        entity_type: str,
        entity_id: uuid.UUID,
        gross_amount: Decimal
    ) -> PaymentTransaction:
        # 1. Math calculations
        splits = self.calculate_splits(gross_amount)

        # 2. Call Razorpay
        amount_paise = int(gross_amount * 100)
        rz_order = self.client.order.create({
            "amount": amount_paise,
            "currency": "INR",
            "receipt": str(entity_id)
        })

        order_id = rz_order["id"]

        # 3. Save pending transaction
        transaction = PaymentTransaction(
            gym_id=gym_id,
            user_id=user_id,
            entity_type=entity_type,
            entity_id=str(entity_id),
            order_id=order_id,
            gross_amount=splits["gross_amount"],
            platform_fee=splits["platform_fee"],
            gateway_fee=splits["gateway_fee"],
            net_amount=splits["net_amount"],
            status="PENDING",
            settlement_status="UNSETTLED"
        )
        db.add(transaction)
        await db.flush()
        return transaction

    async def verify_gateway_payment(
        self,
        db: AsyncSession,
        order_id: str,
        payment_id: str,
        signature: str
    ) -> PaymentTransaction:
        # 1. Fetch matching transaction
        tx_query = select(PaymentTransaction).where(PaymentTransaction.order_id == order_id)
        tx_res = await db.execute(tx_query)
        transaction = tx_res.scalar_one_or_none()
        if not transaction:
            raise ValueError("Transaction order ID not found")

        if transaction.status == "SUCCESS":
            return transaction

        # 2. Verify signature
        self.client.utility.verify_payment_signature({
            "razorpay_order_id": order_id,
            "razorpay_payment_id": payment_id,
            "razorpay_signature": signature
        })

        # 3. Update payment transaction
        transaction.status = "SUCCESS"
        transaction.payment_id = payment_id
        await db.flush()

        # 4. Resolve and update target entity status
        target_uuid = uuid.UUID(transaction.entity_id)
        if transaction.entity_type == "MEMBERSHIP":
            invoice_q = select(FeeLedger).where(FeeLedger.id == target_uuid)
            invoice_res = await db.execute(invoice_q)
            invoice = invoice_res.scalar_one_or_none()
            if invoice:
                invoice.status = "PAID"
                invoice.payment_mode = "RAZORPAY"
                invoice.transaction_id = payment_id
                invoice.payment_date = datetime.now(timezone.utc)
                await db.flush()

        elif transaction.entity_type == "TRAINER_BOOKING":
            booking_q = select(TrainerBooking).where(TrainerBooking.id == target_uuid)
            booking_res = await db.execute(booking_q)
            booking = booking_res.scalar_one_or_none()
            if booking:
                booking.status = "CONFIRMED"
                await db.flush()

        return transaction


payment_service = PaymentService()
