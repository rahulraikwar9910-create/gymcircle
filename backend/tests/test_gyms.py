import pytest
import uuid
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from app.models.auth import Gym, User, UserGymRole
from app.core import deps
from app.core.security import create_access_token


@pytest.mark.anyio
async def test_create_gym_super_admin(client: AsyncClient, db: AsyncSession):
    # Setup Super Admin user
    super_admin = User(
        email="super@gymcircle.com",
        hashed_password="hashed_password",
        first_name="Super",
        last_name="Admin"
    )
    db.add(super_admin)
    await db.flush()
    
    role = UserGymRole(
        user_id=super_admin.id,
        gym_id=None,
        role="SUPER_ADMIN",
        is_active=True
    )
    db.add(role)
    await db.flush()
    
    token = create_access_token(subject=super_admin.id)
    headers = {"Authorization": f"Bearer {token}"}
    
    payload = {
        "name": "Super Gym",
        "slug": "super-gym",
        "email": "supergym@example.com"
    }
    
    response = await client.post("/api/v1/gyms", json=payload, headers=headers)
    assert response.status_code == 201
    
    data = response.json()
    assert data["name"] == "Super Gym"
    assert data["slug"] == "super-gym"
    
    # Verify in DB
    query = select(Gym).where(Gym.slug == "super-gym")
    res = await db.execute(query)
    gym = res.scalar_one_or_none()
    assert gym is not None


@pytest.mark.anyio
async def test_create_gym_forbidden_for_owner(client: AsyncClient, db: AsyncSession):
    # Setup standard owner registration
    payload = {
        "email": "gymowner@example.com",
        "password": "password123",
        "first_name": "Gym",
        "last_name": "Owner",
        "gym_name": "My Gym",
        "gym_slug": "my-gym"
    }
    reg_response = await client.post("/api/v1/auth/register-owner", json=payload)
    owner_id = reg_response.json()["id"]
    
    # Login
    login_payload = {"email": "gymowner@example.com", "password": "password123"}
    login_res = await client.post("/api/v1/auth/login", json=login_payload)
    token = login_res.json()["access_token"]
    
    headers = {"Authorization": f"Bearer {token}"}
    
    # Owner trying to create a new Gym through the admin API
    new_gym_payload = {
        "name": "Gold Gym",
        "slug": "gold-gym"
    }
    response = await client.post("/api/v1/gyms", json=new_gym_payload, headers=headers)
    assert response.status_code == 403


@pytest.mark.anyio
async def test_list_and_get_gym_isolation(client: AsyncClient, db: AsyncSession):
    # Register Owner A
    reg_a = {
        "email": "owner_a@example.com",
        "password": "password123",
        "first_name": "Owner",
        "last_name": "A",
        "gym_name": "Gym A",
        "gym_slug": "gym-a"
    }
    res_a = await client.post("/api/v1/auth/register-owner", json=reg_a)
    user_a = res_a.json()
    gym_a_id = user_a["gym_roles"][0]["gym_id"]
    
    login_a_res = await client.post("/api/v1/auth/login", json={"email": "owner_a@example.com", "password": "password123"})
    token_a = login_a_res.json()["access_token"]

    # Register Owner B
    reg_b = {
        "email": "owner_b@example.com",
        "password": "password123",
        "first_name": "Owner",
        "last_name": "B",
        "gym_name": "Gym B",
        "gym_slug": "gym-b"
    }
    res_b = await client.post("/api/v1/auth/register-owner", json=reg_b)
    user_b = res_b.json()
    gym_b_id = user_b["gym_roles"][0]["gym_id"]
    
    # Case 1: Owner A lists their gyms (should only see Gym A)
    headers_a = {"Authorization": f"Bearer {token_a}"}
    response = await client.get("/api/v1/gyms", headers=headers_a)
    assert response.status_code == 200
    gyms = response.json()
    assert len(gyms) == 1
    assert gyms[0]["id"] == gym_a_id

    # Case 2: Owner A gets details of Gym A (authorized)
    response = await client.get(f"/api/v1/gyms/{gym_a_id}", headers=headers_a)
    assert response.status_code == 200
    assert response.json()["name"] == "Gym A"

    # Case 3: Owner A gets details of Gym B (forbidden)
    response = await client.get(f"/api/v1/gyms/{gym_b_id}", headers=headers_a)
    assert response.status_code == 403
