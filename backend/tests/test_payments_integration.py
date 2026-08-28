import pytest
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession
from datetime import date
from decimal import Decimal


@pytest.mark.anyio
async def test_payments_integration_and_platform_fees(client: AsyncClient, db: AsyncSession):
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
    sub_res = await client.post("/api/v1/memberships/subscribe", json=sub_payload, headers=headers_a)
    assert sub_res.status_code == 201

    # Fetch pending ledger invoice
    ledger_res = await client.get("/api/v1/payments/ledger", headers=headers_a)
    invoice = ledger_res.json()[0]
    invoice_id = invoice["id"]
    assert invoice["status"] == "PENDING"

    # ==================================================
    # 2. CREATE PAYMENT ORDER
    # ==================================================
    order_payload = {
        "entity_type": "MEMBERSHIP",
        "entity_id": invoice_id
    }
    order_res = await client.post("/api/v1/payments-integration/create-order", json=order_payload, headers=headers_a)
    assert order_res.status_code == 201
    order_data = order_res.json()
    order_id = order_data["order_id"]
    assert order_id.startswith("order_")
    assert float(order_data["gross_amount"]) == 1500.0
    assert float(order_data["platform_fee"]) == 10.0
    assert float(order_data["gateway_fee"]) == 30.0  # 2% of 1500
    assert float(order_data["net_amount"]) == 1460.0  # 1500 - 10 - 30

    # ==================================================
    # 3. VERIFY SIGNATURE (SUCCESS)
    # ==================================================
    verify_payload = {
        "razorpay_order_id": order_id,
        "razorpay_payment_id": "pay_abc123",
        "razorpay_signature": "sig_xyz"
    }
    verify_res = await client.post("/api/v1/payments-integration/verify-payment", json=verify_payload, headers=headers_a)
    assert verify_res.status_code == 200
    tx_data = verify_res.json()
    assert tx_data["status"] == "SUCCESS"
    assert tx_data["payment_id"] == "pay_abc123"

    # Verify the invoice is now marked PAID
    ledger_res_2 = await client.get("/api/v1/payments/ledger", headers=headers_a)
    invoice_updated = ledger_res_2.json()[0]
    assert invoice_updated["status"] == "PAID"
    assert invoice_updated["payment_mode"] == "RAZORPAY"
    assert invoice_updated["transaction_id"] == "pay_abc123"

    # ==================================================
    # 4. MULTI-TENANT ISOLATION
    # ==================================================
    # Register Owner B
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

    # Owner B lists transactions (should see 0, isolation check)
    tx_b_res = await client.get("/api/v1/payments-integration/transactions", headers=headers_b)
    assert tx_b_res.status_code == 200
    assert len(tx_b_res.json()) == 0

    # Owner A lists transactions (should see 1, containing the successful payment)
    tx_a_res = await client.get("/api/v1/payments-integration/transactions", headers=headers_a)
    assert len(tx_a_res.json()) == 1
    assert tx_a_res.json()[0]["order_id"] == order_id
    assert float(tx_a_res.json()[0]["net_amount"]) == 1460.0
