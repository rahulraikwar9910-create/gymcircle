import pytest
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from datetime import date
import uuid

from app.models.auth import User


@pytest.mark.anyio
async def test_super_admin_dashboard_and_reports(client: AsyncClient, db: AsyncSession):
    # ==================================================
    # 1. SETUP GYM A & MAKE A PAYMENT
    # ==================================================
    reg_a = {
        "email": "owner_a@gymcircle.com",
        "password": "password123",
        "first_name": "Owner",
        "last_name": "A",
        "gym_name": "Gym Alpha",
        "gym_slug": "gym-alpha"
    }
    res_a = await client.post("/api/v1/auth/register-owner", json=reg_a)
    user_a = res_a.json()
    gym_a_id = user_a["gym_roles"][0]["gym_id"]
    
    login_a_res = await client.post("/api/v1/auth/login", json={"email": "owner_a@gymcircle.com", "password": "password123"})
    token_a = login_a_res.json()["access_token"]
    headers_a = {"Authorization": f"Bearer {token_a}", "X-Gym-ID": gym_a_id}

    # Register client
    client_payload = {
        "email": "client_alpha@example.com",
        "phone": "9998887776",
        "first_name": "John",
        "last_name": "Client"
    }
    create_client_res = await client.post("/api/v1/clients", json=client_payload, headers=headers_a)
    client_id = create_client_res.json()["id"]

    # Create plan and subscribe
    plan_payload = {
        "name": "Standard Monthly",
        "price": 1500.00,
        "duration_days": 30
    }
    create_plan_res = await client.post("/api/v1/memberships/plans", json=plan_payload, headers=headers_a)
    plan_id = create_plan_res.json()["id"]

    sub_payload = {
        "client_id": client_id,
        "plan_id": plan_id,
        "start_date": str(date.today())
    }
    await client.post("/api/v1/memberships/subscribe", json=sub_payload, headers=headers_a)

    # Fetch pending invoice
    ledger_res = await client.get("/api/v1/payments/ledger", headers=headers_a)
    invoice = ledger_res.json()[0]
    invoice_id = invoice["id"]

    # Create payment order
    order_payload = {
        "entity_type": "MEMBERSHIP",
        "entity_id": invoice_id
    }
    order_res = await client.post("/api/v1/payments-integration/create-order", json=order_payload, headers=headers_a)
    order_id = order_res.json()["order_id"]

    # Verify payment (makes status SUCCESS and logs splits)
    verify_payload = {
        "razorpay_order_id": order_id,
        "razorpay_payment_id": "pay_abc123",
        "razorpay_signature": "sig_xyz"
    }
    await client.post("/api/v1/payments-integration/verify-payment", json=verify_payload, headers=headers_a)

    # ==================================================
    # 2. GYM OWNER A TRYING TO READ ADMIN DASHBOARD (403 CHECK)
    # ==================================================
    metric_a_res = await client.get("/api/v1/admin/dashboard/metrics", headers=headers_a)
    assert metric_a_res.status_code == 403

    # ==================================================
    # 3. SETUP PLATFORM SUPER ADMIN
    # ==================================================
    reg_super = {
        "email": "superadmin@gymcircle.com",
        "password": "password123",
        "first_name": "Super",
        "last_name": "Admin",
        "gym_name": "Platform",
        "gym_slug": "platform"
    }
    await client.post("/api/v1/auth/register-owner", json=reg_super)
    
    login_sa = await client.post("/api/v1/auth/login", json={"email": "superadmin@gymcircle.com", "password": "password123"})
    token_sa = login_sa.json()["access_token"]
    headers_sa = {"Authorization": f"Bearer {token_sa}"}

    # Grant Super Admin role in DB
    sa_user_q = select(User).where(User.email == "superadmin@gymcircle.com")
    sa_res = await db.execute(sa_user_q)
    sa_user = sa_res.scalar_one()
    from app.models.auth import UserGymRole
    role_sa = UserGymRole(user_id=sa_user.id, gym_id=None, role="SUPER_ADMIN", is_active=True)
    db.add(role_sa)
    await db.commit()

    # ==================================================
    # 4. SUPER ADMIN ACCESS AUDITS (METRICS & GYMS)
    # ==================================================
    metric_sa_res = await client.get("/api/v1/admin/dashboard/metrics", headers=headers_sa)
    assert metric_sa_res.status_code == 200
    metrics = metric_sa_res.json()
    assert float(metrics["total_platform_revenue"]) == 1500.0
    assert float(metrics["total_platform_fees_collected"]) == 10.0
    assert metrics["total_gyms_count"] == 2  # Gym A and Platform Gym

    # Gyms contributions breakdown list
    gyms_report_res = await client.get("/api/v1/admin/dashboard/gyms", headers=headers_sa)
    assert gyms_report_res.status_code == 200
    gyms_list = gyms_report_res.json()
    assert len(gyms_list) == 2
    
    # Gym A should have 1 active member and 1500.00 revenue contribution
    gym_a_report = [g for g in gyms_list if g["gym_id"] == gym_a_id][0]
    assert gym_a_report["active_members_count"] == 1
    assert float(gym_a_report["total_revenue_generated"]) == 1500.00

    # ==================================================
    # 5. SETTLEMENTS & RELEASE STATUS UPDATES
    # ==================================================
    settlements_res = await client.get("/api/v1/admin/dashboard/settlements", headers=headers_sa)
    assert settlements_res.status_code == 200
    tx_list = settlements_res.json()
    assert len(tx_list) == 1
    tx_id = tx_list[0]["id"]
    assert tx_list[0]["settlement_status"] == "UNSETTLED"

    # Mark transaction as SETTLED
    settle_payload = {"settlement_status": "SETTLED"}
    settle_res = await client.post(f"/api/v1/admin/dashboard/settlements/{tx_id}/settle", json=settle_payload, headers=headers_sa)
    assert settle_res.status_code == 200
    assert settle_res.json()["settlement_status"] == "SETTLED"
