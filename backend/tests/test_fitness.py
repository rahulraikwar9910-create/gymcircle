import pytest
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from datetime import date
import uuid

from app.models.auth import User, UserGymRole
from app.models.trainer import TrainerProfile


@pytest.mark.anyio
async def test_fitness_planners_and_progress_logs(client: AsyncClient, db: AsyncSession):
    # ==================================================
    # 1. SETUP GYM A & ROLES
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
    gym_a_uuid = uuid.UUID(gym_a_id)
    
    login_a_res = await client.post("/api/v1/auth/login", json={"email": "owner_a@gymcircle.com", "password": "password123"})
    token_a = login_a_res.json()["access_token"]
    headers_a = {"Authorization": f"Bearer {token_a}", "X-Gym-ID": gym_a_id}

    # Register client A
    client_payload = {
        "email": "client_alpha@example.com",
        "phone": "9998887776",
        "first_name": "John",
        "last_name": "Client"
    }
    create_client_res = await client.post("/api/v1/clients", json=client_payload, headers=headers_a)
    client_id = create_client_res.json()["id"]

    # Update client A's password in DB so we can log in
    from app.core.security import get_password_hash
    c_user_q = select(User).where(User.id == uuid.UUID(client_id))
    c_res = await db.execute(c_user_q)
    c_user = c_res.scalar_one()
    c_user.hashed_password = get_password_hash("password123")
    await db.commit()

    # Login Client A
    login_c_res = await client.post("/api/v1/auth/login", json={"email": "client_alpha@example.com", "password": "password123"})
    token_c = login_c_res.json()["access_token"]
    headers_c = {"Authorization": f"Bearer {token_c}", "X-Gym-ID": gym_a_id}

    # Register trainer A
    reg_ta = {
        "email": "trainer_a@gymcircle.com",
        "password": "password123",
        "first_name": "Trainer",
        "last_name": "Alpha",
        "gym_name": "Gym Alpha Test",
        "gym_slug": "gym-alpha-test"
    }
    await client.post("/api/v1/auth/register-owner", json=reg_ta)
    
    login_ta = await client.post("/api/v1/auth/login", json={"email": "trainer_a@gymcircle.com", "password": "password123"})
    token_ta = login_ta.json()["access_token"]
    headers_ta = {"Authorization": f"Bearer {token_ta}", "X-Gym-ID": gym_a_id}
    
    ta_user_q = select(User).where(User.email == "trainer_a@gymcircle.com")
    ta_res = await db.execute(ta_user_q)
    ta_user = ta_res.scalar_one()
    
    role_ta = UserGymRole(user_id=ta_user.id, gym_id=gym_a_uuid, role="TRAINER", is_active=True)
    db.add(role_ta)
    
    # Setup trainer profile
    profile_ta = TrainerProfile(
        user_id=ta_user.id,
        gym_id=gym_a_uuid,
        bio="Trainer bio",
        experience_years=5,
        city="Bangalore",
        area="Koramangala"
    )
    db.add(profile_ta)
    await db.commit()

    # ==================================================
    # 2. EXERCISE SEEDING
    # ==================================================
    ex_payload_1 = {
        "name": "Barbell Bench Press",
        "muscle_group": "Chest",
        "description": "Chest press on flat bench"
    }
    ex_res_1 = await client.post("/api/v1/fitness/exercises", json=ex_payload_1, headers=headers_ta)
    assert ex_res_1.status_code == 201
    bench_id = ex_res_1.json()["id"]

    ex_payload_2 = {
        "name": "Barbell Back Squat",
        "muscle_group": "Legs",
        "description": "Back squats for quad strength"
    }
    ex_res_2 = await client.post("/api/v1/fitness/exercises", json=ex_payload_2, headers=headers_ta)
    assert ex_res_2.status_code == 201
    squat_id = ex_res_2.json()["id"]

    # ==================================================
    # 3. WORKOUT PLAN BUILDER
    # ==================================================
    workout_payload = {
        "client_id": client_id,
        "name": "Hypertrophy Push-Legs Split",
        "description": "Custom workout plan for John",
        "days": [
            {
                "day_name": "Day 1 - Push",
                "order": 0,
                "exercises": [
                    {
                        "exercise_id": bench_id,
                        "sets": 4,
                        "reps": "8-12",
                        "rest_seconds": 90,
                        "notes": "Go heavy, target hypertrophy",
                        "order": 0
                    }
                ]
            },
            {
                "day_name": "Day 2 - Legs",
                "order": 1,
                "exercises": [
                    {
                        "exercise_id": squat_id,
                        "sets": 4,
                        "reps": "10",
                        "rest_seconds": 120,
                        "notes": "Keep depth parallel",
                        "order": 0
                    }
                ]
            }
        ]
    }
    work_res = await client.post("/api/v1/fitness/workouts", json=workout_payload, headers=headers_ta)
    assert work_res.status_code == 201
    work_data = work_res.json()
    assert len(work_data["days"]) == 2
    assert work_data["days"][0]["day_name"] == "Day 1 - Push"
    assert len(work_data["days"][0]["exercises"]) == 1
    assert work_data["days"][0]["exercises"][0]["exercise"]["name"] == "Barbell Bench Press"

    # Client lists their workout plans
    work_list_res = await client.get("/api/v1/fitness/workouts", headers=headers_c)
    assert work_list_res.status_code == 200
    assert len(work_list_res.json()) == 1
    assert work_list_res.json()[0]["name"] == "Hypertrophy Push-Legs Split"

    # ==================================================
    # 4. DIET PLAN BUILDER
    # ==================================================
    diet_payload = {
        "client_id": client_id,
        "name": "High Protein Bulk Plan",
        "description": "Caloric surplus for muscle gains",
        "meals": [
            {
                "name": "Breakfast",
                "calories": 750,
                "protein_g": 35,
                "carbs_g": 90,
                "fat_g": 18,
                "items": "100g Oats, 4 Egg Whites, 1 scoop Whey",
                "order": 0
            },
            {
                "name": "Post-Workout Shake",
                "calories": 300,
                "protein_g": 30,
                "carbs_g": 40,
                "fat_g": 2,
                "items": "1 scoop Whey, 1 Banana",
                "order": 1
            }
        ]
    }
    diet_res = await client.post("/api/v1/fitness/diets", json=diet_payload, headers=headers_ta)
    assert diet_res.status_code == 201
    diet_data = diet_res.json()
    assert diet_data["total_calories"] == 1050  # 750 + 300
    assert diet_data["total_protein_g"] == 65  # 35 + 30
    assert len(diet_data["meals"]) == 2

    # ==================================================
    # 5. FITNESS PROGRESS LOGS & BMI CALCULATIONS
    # ==================================================
    progress_payload = {
        "weight_kg": 70.0,
        "height_cm": 175.0,
        "body_fat_percentage": 14.5,
        "notes": "Starting progress measurement"
    }
    prog_res = await client.post("/api/v1/fitness/progress", json=progress_payload, headers=headers_c)
    assert prog_res.status_code == 201
    prog_data = prog_res.json()
    # BMI = 70 / (1.75 * 1.75) = 22.857... -> rounded to 22.86
    assert float(prog_data["bmi"]) == 22.86

    # ==================================================
    # 6. MUTLI-TENANT/SCOPED PRIVACY BOUNDARIES
    # ==================================================
    # Register Client B
    client_b_payload = {
        "email": "client_beta@gymcircle.com",
        "phone": "9998887775",
        "first_name": "Beta",
        "last_name": "Client"
    }
    create_client_b_res = await client.post("/api/v1/clients", json=client_b_payload, headers=headers_a)
    client_b_id = create_client_b_res.json()["id"]

    # Update client B's password in DB so we can log in
    cb_user_q = select(User).where(User.id == uuid.UUID(client_b_id))
    cb_res = await db.execute(cb_user_q)
    cb_user = cb_res.scalar_one()
    cb_user.hashed_password = get_password_hash("password123")
    await db.commit()

    login_b = await client.post("/api/v1/auth/login", json={"email": "client_beta@gymcircle.com", "password": "password123"})
    token_b = login_b.json()["access_token"]
    headers_b = {"Authorization": f"Bearer {token_b}", "X-Gym-ID": gym_a_id}

    # Client B lists progress logs (should see 0, privacy check)
    prog_b_res = await client.get("/api/v1/fitness/progress", headers=headers_b)
    assert prog_b_res.status_code == 200
    assert len(prog_b_res.json()) == 0

    # Client B tries to request Client A's logs directly (should return 403 Forbidden since they are not staff)
    prog_b_direct_res = await client.get(f"/api/v1/fitness/progress?client_id={client_id}", headers=headers_b)
    assert prog_b_direct_res.status_code == 403
