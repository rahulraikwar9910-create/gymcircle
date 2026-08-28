import pytest
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from datetime import date, timedelta
from decimal import Decimal
import uuid

from app.models.auth import User, Gym
from app.models.gym import ClientProfile, MembershipPlan, ClientGymMembership, FeeLedger, Attendance


@pytest.mark.anyio
async def test_full_gym_features_and_tenant_isolation(client: AsyncClient, db: AsyncSession):
    # ==================================================
    # 1. SETUP GYM A & OWNER A
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
    
    # Login Owner A
    login_a_res = await client.post("/api/v1/auth/login", json={"email": "owner_a@gymcircle.com", "password": "password123"})
    token_a = login_a_res.json()["access_token"]
    headers_a = {"Authorization": f"Bearer {token_a}", "X-Gym-ID": gym_a_id}

    # ==================================================
    # 2. CLIENT MANAGEMENT
    # ==================================================
    client_payload = {
        "email": "client_alpha@example.com",
        "phone": "9988776655",
        "first_name": "John",
        "last_name": "Client",
        "date_of_birth": "1995-05-15",
        "gender": "MALE",
        "emergency_contact_name": "Emergency Parent",
        "emergency_contact_phone": "9998887776",
        "notes": "Prefers morning workouts"
    }
    
    create_client_res = await client.post("/api/v1/clients", json=client_payload, headers=headers_a)
    assert create_client_res.status_code == 201
    client_data = create_client_res.json()
    client_id = client_data["id"]
    assert client_data["profile"]["notes"] == "Prefers morning workouts"
    assert client_data["profile"]["gym_id"] == gym_a_id

    # List Clients
    list_clients_res = await client.get("/api/v1/clients", headers=headers_a)
    assert list_clients_res.status_code == 200
    clients_list = list_clients_res.json()
    assert len(clients_list) == 1
    assert clients_list[0]["id"] == client_id

    # ==================================================
    # 3. MEMBERSHIPS & PLANS
    # ==================================================
    plan_payload = {
        "name": "Standard Monthly",
        "description": "Access to cardio and strength sections",
        "price": 1500.00,
        "duration_days": 30
    }
    create_plan_res = await client.post("/api/v1/memberships/plans", json=plan_payload, headers=headers_a)
    assert create_plan_res.status_code == 201
    plan_data = create_plan_res.json()
    plan_id = plan_data["id"]
    assert float(plan_data["price"]) == 1500.00

    # Subscribe client
    sub_payload = {
        "client_id": client_id,
        "plan_id": plan_id,
        "start_date": str(date.today())
    }
    sub_res = await client.post("/api/v1/memberships/subscribe", json=sub_payload, headers=headers_a)
    assert sub_res.status_code == 201
    membership_data = sub_res.json()
    membership_id = membership_data["id"]
    assert membership_data["status"] == "ACTIVE"
    # End date should be today + 30 days
    assert membership_data["end_date"] == str(date.today() + timedelta(days=30))

    # Verify pending invoice in Fee Ledger
    ledger_res = await client.get("/api/v1/payments/ledger", headers=headers_a)
    assert ledger_res.status_code == 200
    ledger_data = ledger_res.json()
    assert len(ledger_data) == 1
    invoice = ledger_data[0]
    assert invoice["status"] == "PENDING"
    assert float(invoice["amount"]) == 1500.00
    invoice_id = invoice["id"]

    # ==================================================
    # 4. DASHBOARD METRICS (BEFORE PAYMENT)
    # ==================================================
    metrics_res = await client.get("/api/v1/payments/dashboard-metrics", headers=headers_a)
    assert metrics_res.status_code == 200
    metrics_data = metrics_res.json()
    assert float(metrics_data["today_collection"]) == 0.0
    assert float(metrics_data["pending_fees_total"]) == 1500.0
    assert metrics_data["expiring_count"] == 0  # Expiring soon is within 7 days, 30 days > 7

    # ==================================================
    # 5. RECORD PAYMENT
    # ==================================================
    pay_payload = {
        "payment_mode": "UPI",
        "transaction_id": "TXN998877",
        "notes": "Paid via Google Pay"
    }
    pay_res = await client.post(f"/api/v1/payments/record/{invoice_id}", json=pay_payload, headers=headers_a)
    assert pay_res.status_code == 200
    updated_invoice = pay_res.json()
    assert updated_invoice["status"] == "PAID"
    assert updated_invoice["payment_mode"] == "UPI"
    assert updated_invoice["transaction_id"] == "TXN998877"

    # Verify Dashboard Metrics (AFTER PAYMENT)
    metrics_res = await client.get("/api/v1/payments/dashboard-metrics", headers=headers_a)
    metrics_data = metrics_res.json()
    assert float(metrics_data["today_collection"]) == 1500.0
    assert float(metrics_data["monthly_collection"]) == 1500.0
    assert float(metrics_data["pending_fees_total"]) == 0.0

    # ==================================================
    # 6. ATTENDANCE & CHECK-IN ALERTS
    # ==================================================
    check_in_res = await client.post("/api/v1/attendance/check-in", json={"client_id": client_id}, headers=headers_a)
    assert check_in_res.status_code == 201
    check_in_data = check_in_res.json()
    assert check_in_data["has_pending_fees"] is False
    assert check_in_data["membership_expired"] is False
    assert check_in_data["message"] == "Access granted"

    # Check-out
    check_out_res = await client.post("/api/v1/attendance/check-out", json={"client_id": client_id}, headers=headers_a)
    assert check_out_res.status_code == 200
    check_out_data = check_out_res.json()
    assert check_out_data["check_out"] is not None

    # ==================================================
    # 7. MULTI-TENANT ISOLATION TESTS
    # ==================================================
    # Setup Gym B & Owner B
    reg_b = {
        "email": "owner_b@gymcircle.com",
        "password": "password123",
        "first_name": "Owner",
        "last_name": "B",
        "gym_name": "Gym Beta",
        "gym_slug": "gym-beta"
    }
    res_b = await client.post("/api/v1/auth/register-owner", json=reg_b)
    user_b = res_b.json()
    gym_b_id = user_b["gym_roles"][0]["gym_id"]
    
    login_b_res = await client.post("/api/v1/auth/login", json={"email": "owner_b@gymcircle.com", "password": "password123"})
    token_b = login_b_res.json()["access_token"]
    headers_b = {"Authorization": f"Bearer {token_b}", "X-Gym-ID": gym_b_id}

    # Case 1: Owner B lists clients (should return empty list, isolation check)
    list_b_res = await client.get("/api/v1/clients", headers=headers_b)
    assert list_b_res.status_code == 200
    assert len(list_b_res.json()) == 0

    # Case 2: Owner B requests detail of Client A (should return 403, scope block)
    detail_b_res = await client.get(f"/api/v1/clients/{client_id}", headers=headers_b)
    assert detail_b_res.status_code == 403

    # Case 3: Owner B requests dashboard metrics (should return 0.00)
    metrics_b_res = await client.get("/api/v1/payments/dashboard-metrics", headers=headers_b)
    assert float(metrics_b_res.json()["today_collection"]) == 0.0

    # Case 4: Owner B tries to record payment on Owner A's invoice (should return 404, not found in their gym scope)
    pay_b_res = await client.post(f"/api/v1/payments/record/{invoice_id}", json=pay_payload, headers=headers_b)
    assert pay_b_res.status_code == 404
