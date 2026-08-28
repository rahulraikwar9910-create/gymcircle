import pytest
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy import select
from datetime import date
from decimal import Decimal

from app.models.auth import User, UserGymRole
from app.models.trainer import Specialization, TrainerProfile, TrainerBooking


@pytest.mark.anyio
async def test_trainer_management_and_marketplace(client: AsyncClient, db: AsyncSession):
    # ==================================================
    # 0. SEED SPECIALIZATIONS IN DATABASE
    # ==================================================
    spec_cardio = Specialization(name="Aerobic/Cardiovascular Endurance", category="CORE_FITNESS")
    spec_weight = Specialization(name="Weight Loss", category="ADDITIONAL_GOALS")
    db.add(spec_cardio)
    db.add(spec_weight)
    await db.flush()
    cardio_id = spec_cardio.id
    weight_id = spec_weight.id

    # ==================================================
    # 1. SETUP TRAINER A & TRAINER B
    # ==================================================
    # Trainer A
    import uuid
    reg_ta = {
        "email": "trainer_a@gymcircle.com",
        "password": "password123",
        "first_name": "Trainer",
        "last_name": "Alpha",
        "gym_name": "Gym Alpha Test",
        "gym_slug": "gym-alpha-test"
    }
    res_ta = await client.post("/api/v1/auth/register-owner", json=reg_ta) # Using register endpoint for simplicity, then force-mapping role
    user_ta = res_ta.json()
    gym_a_id = user_ta["gym_roles"][0]["gym_id"]
    gym_a_uuid = uuid.UUID(gym_a_id)

    # Login & Force Role to TRAINER
    login_ta = await client.post("/api/v1/auth/login", json={"email": "trainer_a@gymcircle.com", "password": "password123"})
    token_ta = login_ta.json()["access_token"]
    
    # We need to grant TRAINER role to Trainer A
    ta_user_q = select(User).where(User.email == "trainer_a@gymcircle.com")
    ta_res = await db.execute(ta_user_q)
    ta_user = ta_res.scalar_one()
    
    role_ta = UserGymRole(user_id=ta_user.id, gym_id=gym_a_uuid, role="TRAINER", is_active=True)
    db.add(role_ta)
    await db.commit()

    headers_ta = {"Authorization": f"Bearer {token_ta}", "X-Gym-ID": gym_a_id}

    # Trainer B
    reg_tb = {
        "email": "trainer_b@gymcircle.com",
        "password": "password123",
        "first_name": "Trainer",
        "last_name": "Beta",
        "gym_name": "Gym Beta Test",
        "gym_slug": "gym-beta-test"
    }
    res_tb = await client.post("/api/v1/auth/register-owner", json=reg_tb)
    user_tb = res_tb.json()
    gym_b_id = user_tb["gym_roles"][0]["gym_id"]
    gym_b_uuid = uuid.UUID(gym_b_id)
    
    login_tb = await client.post("/api/v1/auth/login", json={"email": "trainer_b@gymcircle.com", "password": "password123"})
    token_tb = login_tb.json()["access_token"]
    
    tb_user_q = select(User).where(User.email == "trainer_b@gymcircle.com")
    tb_res = await db.execute(tb_user_q)
    tb_user = tb_res.scalar_one()
    
    role_tb = UserGymRole(user_id=tb_user.id, gym_id=gym_b_uuid, role="TRAINER", is_active=True)
    db.add(role_tb)
    await db.commit()
    
    headers_tb = {"Authorization": f"Bearer {token_tb}", "X-Gym-ID": gym_b_id}

    # ==================================================
    # 2. CREATE TRAINER PROFILES
    # ==================================================
    # Profile A: Koramangala, Bangalore (12.9716, 77.5946)
    profile_a_payload = {
        "bio": "Certified strength trainer",
        "experience_years": 5,
        "certifications": "ACE Certified",
        "languages": "English, Hindi",
        "city": "Bangalore",
        "area": "Koramangala",
        "is_online": True,
        "is_offline": True,
        "latitude": 12.9716,
        "longitude": 77.5946,
        "specializations": [str(cardio_id)]
    }
    prof_a_res = await client.post("/api/v1/trainers/profile", json=profile_a_payload, headers=headers_ta)
    assert prof_a_res.status_code == 200
    prof_a_data = prof_a_res.json()
    assert prof_a_data["area"] == "Koramangala"
    assert len(prof_a_data["specializations"]) == 1
    trainer_a_profile_id = prof_a_data["id"]

    # Profile B: HSR Layout, Bangalore (12.9300, 77.6200)
    profile_b_payload = {
        "bio": "Nutrition specialist",
        "experience_years": 8,
        "certifications": "NASM Nutrition",
        "languages": "English",
        "city": "Bangalore",
        "area": "HSR Layout",
        "is_online": True,
        "is_offline": False,
        "latitude": 12.9300,
        "longitude": 77.6200,
        "specializations": [str(weight_id)]
    }
    prof_b_res = await client.post("/api/v1/trainers/profile", json=profile_b_payload, headers=headers_tb)
    assert prof_b_res.status_code == 200

    # ==================================================
    # 3. SERVICES & PACKAGES
    # ==================================================
    # Trainer A creates a Personal Training Service
    service_payload = {
        "name": "One-on-One Strength Coaching",
        "description": "Customized personal training sessions",
        "price": 2000.00
    }
    service_res = await client.post("/api/v1/trainers/services", json=service_payload, headers=headers_ta)
    assert service_res.status_code == 201
    service_data = service_res.json()
    service_id = service_data["id"]

    # Define package: 12 sessions for 18,000.00
    package_payload = {
        "name": "12 Sessions Strength Pack",
        "price": 18000.00,
        "sessions_count": 12,
        "duration_days": 45
    }
    package_res = await client.post(f"/api/v1/trainers/services/{service_id}/packages", json=package_payload, headers=headers_ta)
    assert package_res.status_code == 201
    package_data = package_res.json()
    package_id = package_data["id"]

    # ==================================================
    # 4. MARKETPLACE DISCOVERY ENGINE
    # ==================================================
    # Setup Gym Owner / Admin login context to search (or search as independent user)
    reg_client = {
        "email": "client_discover@gymcircle.com",
        "password": "password123",
        "first_name": "John",
        "last_name": "Discover",
        "gym_name": "Gym Client Test",
        "gym_slug": "gym-client-test"
    }
    res_client = await client.post("/api/v1/auth/register-owner", json=reg_client)
    user_client = res_client.json()
    gym_c_id = user_client["gym_roles"][0]["gym_id"]
    gym_c_uuid = uuid.UUID(gym_c_id)
    
    login_client = await client.post("/api/v1/auth/login", json={"email": "client_discover@gymcircle.com", "password": "password123"})
    token_client = login_client.json()["access_token"]
    headers_client = {"Authorization": f"Bearer {token_client}", "X-Gym-ID": gym_c_id}
    
    # Give role CLIENT to discover user
    dc_user_q = select(User).where(User.email == "client_discover@gymcircle.com")
    dc_res = await db.execute(dc_user_q)
    dc_user = dc_res.scalar_one()
    role_dc = UserGymRole(user_id=dc_user.id, gym_id=gym_c_uuid, role="CLIENT", is_active=True)
    db.add(role_dc)
    await db.commit()

    # Query by specialization (Cardio)
    mkt_spec_res = await client.get(f"/api/v1/trainers/marketplace?specialization_id={cardio_id}", headers=headers_client)
    assert mkt_spec_res.status_code == 200
    mkt_spec_data = mkt_spec_res.json()
    assert len(mkt_spec_data) == 1
    assert mkt_spec_data[0]["profile"]["area"] == "Koramangala"

    # Proximity Search (lat=12.97, lon=77.6): Max Distance 3km (should find Trainer A only)
    mkt_dist_3_res = await client.get("/api/v1/trainers/marketplace?latitude=12.97&longitude=77.6&max_distance=3.0", headers=headers_client)
    assert mkt_dist_3_res.status_code == 200
    mkt_dist_3_data = mkt_dist_3_res.json()
    assert len(mkt_dist_3_data) == 1
    assert mkt_dist_3_data[0]["profile"]["area"] == "Koramangala"

    # Proximity Search: Max Distance 10km (should find both, Trainer A closest)
    mkt_dist_10_res = await client.get("/api/v1/trainers/marketplace?latitude=12.97&longitude=77.6&max_distance=10.0", headers=headers_client)
    assert len(mkt_dist_10_res.json()) == 2
    assert mkt_dist_10_res.json()[0]["profile"]["area"] == "Koramangala"

    # ==================================================
    # 5. TRAINER BOOKING FLOW
    # ==================================================
    booking_payload = {
        "trainer_id": trainer_a_profile_id,
        "package_id": package_id,
        "booking_date": str(date.today())
    }
    booking_res = await client.post("/api/v1/trainers/bookings", json=booking_payload, headers=headers_client)
    assert booking_res.status_code == 201
    booking_data = booking_res.json()
    assert booking_data["status"] == "PENDING"
    assert float(booking_data["price_paid"]) == 18000.0
    booking_id = booking_data["id"]

    # ==================================================
    # 6. SECURITY & STATUS UPDATES BOUNDARIES
    # ==================================================
    # Trainer B tries to confirm Trainer A's booking (should fail with 403)
    confirm_tb_res = await client.patch(f"/api/v1/trainers/bookings/{booking_id}/status?status=CONFIRMED", headers=headers_tb)
    assert confirm_tb_res.status_code == 403

    # Trainer A confirms booking (should succeed)
    confirm_ta_res = await client.patch(f"/api/v1/trainers/bookings/{booking_id}/status?status=CONFIRMED", headers=headers_ta)
    assert confirm_ta_res.status_code == 200
    assert confirm_ta_res.json()["status"] == "CONFIRMED"
