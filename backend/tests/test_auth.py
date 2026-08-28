import pytest
from fastapi import Depends
from httpx import AsyncClient
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy.orm import selectinload

from app.models.auth import User, Gym, UserGymRole
from app.core import deps
from app.main import app

# Add a dummy protected route for testing tenant access control
@app.get("/api/v1/test-tenant")
async def check_tenant_route(
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER"]))
):
    return {"status": "authorized", "user_id": str(current_user.id)}


@pytest.mark.anyio
async def test_owner_registration(client: AsyncClient, db: AsyncSession):
    payload = {
        "email": "owner@example.com",
        "phone": "9876543210",
        "password": "securepassword123",
        "first_name": "John",
        "last_name": "Doe",
        "gym_name": "Iron Paradise",
        "gym_slug": "iron-paradise"
    }
    
    response = await client.post("/api/v1/auth/register-owner", json=payload)
    assert response.status_code == 201
    
    data = response.json()
    assert data["email"] == "owner@example.com"
    assert data["first_name"] == "John"
    assert data["last_name"] == "Doe"
    assert len(data["gym_roles"]) == 1
    assert data["gym_roles"][0]["role"] == "GYM_OWNER"
    
    # Verify DB state
    import uuid
    user_uuid = uuid.UUID(data["id"])
    query = select(User).where(User.id == user_uuid).options(selectinload(User.gym_roles))
    res = await db.execute(query)
    user = res.scalar_one_or_none()
    assert user is not None
    assert len(user.gym_roles) == 1
    assert user.gym_roles[0].role == "GYM_OWNER"
    
    gym_id = user.gym_roles[0].gym_id
    gym_query = select(Gym).where(Gym.id == gym_id)
    gym_res = await db.execute(gym_query)
    gym = gym_res.scalar_one_or_none()
    assert gym is not None
    assert gym.name == "Iron Paradise"
    assert gym.slug == "iron-paradise"


@pytest.mark.anyio
async def test_login_success(client: AsyncClient, db: AsyncSession):
    # Setup user
    register_payload = {
        "email": "login_test@example.com",
        "password": "password123",
        "first_name": "Login",
        "last_name": "Test",
        "gym_name": "Login Gym",
        "gym_slug": "login-gym"
    }
    await client.post("/api/v1/auth/register-owner", json=register_payload)
    
    # Login
    login_payload = {
        "email": "login_test@example.com",
        "password": "password123"
    }
    response = await client.post("/api/v1/auth/login", json=login_payload)
    assert response.status_code == 200
    token_data = response.json()
    assert "access_token" in token_data
    assert "refresh_token" in token_data
    assert token_data["token_type"] == "bearer"


@pytest.mark.anyio
async def test_login_invalid_password(client: AsyncClient):
    login_payload = {
        "email": "nonexistent@example.com",
        "password": "wrongpassword"
    }
    response = await client.post("/api/v1/auth/login", json=login_payload)
    assert response.status_code == 401


@pytest.mark.anyio
async def test_read_users_me(client: AsyncClient):
    # Register & Login to get token
    register_payload = {
        "email": "me_test@example.com",
        "password": "password123",
        "first_name": "Me",
        "last_name": "Test",
        "gym_name": "Me Gym",
        "gym_slug": "me-gym"
    }
    await client.post("/api/v1/auth/register-owner", json=register_payload)
    
    login_payload = {"email": "me_test@example.com", "password": "password123"}
    login_res = await client.post("/api/v1/auth/login", json=login_payload)
    token = login_res.json()["access_token"]
    
    # Call me endpoint
    headers = {"Authorization": f"Bearer {token}"}
    response = await client.get("/api/v1/auth/me", headers=headers)
    assert response.status_code == 200
    data = response.json()
    assert data["email"] == "me_test@example.com"


@pytest.mark.anyio
async def test_tenant_isolation(client: AsyncClient, db: AsyncSession):
    # Register Gym A and Owner A
    reg_a = {
        "email": "owner_a@example.com",
        "password": "password123",
        "first_name": "Owner",
        "last_name": "A",
        "gym_name": "Gym A",
        "gym_slug": "gym-a"
    }
    res_a = await client.post("/api/v1/auth/register-owner", json=reg_a)
    user_a_data = res_a.json()
    gym_a_id = user_a_data["gym_roles"][0]["gym_id"]
    
    login_a_res = await client.post("/api/v1/auth/login", json={"email": "owner_a@example.com", "password": "password123"})
    token_a = login_a_res.json()["access_token"]

    # Register Gym B and Owner B
    reg_b = {
        "email": "owner_b@example.com",
        "password": "password123",
        "first_name": "Owner",
        "last_name": "B",
        "gym_name": "Gym B",
        "gym_slug": "gym-b"
    }
    res_b = await client.post("/api/v1/auth/register-owner", json=reg_b)
    user_b_data = res_b.json()
    gym_b_id = user_b_data["gym_roles"][0]["gym_id"]

    # Case 1: Owner A accesses Gym A (authorized)
    headers_a_gym_a = {
        "Authorization": f"Bearer {token_a}",
        "X-Gym-ID": gym_a_id
    }
    res = await client.get("/api/v1/test-tenant", headers=headers_a_gym_a)
    assert res.status_code == 200
    
    # Case 2: Owner A accesses Gym B (unauthorized)
    headers_a_gym_b = {
        "Authorization": f"Bearer {token_a}",
        "X-Gym-ID": gym_b_id
    }
    res = await client.get("/api/v1/test-tenant", headers=headers_a_gym_b)
    assert res.status_code == 403


@pytest.mark.anyio
async def test_refresh_token_rotation_and_replay_protection(client: AsyncClient, db: AsyncSession):
    # Register & Login
    reg_payload = {
        "email": "ref_test@example.com",
        "password": "password123",
        "first_name": "Ref",
        "last_name": "Test",
        "gym_name": "Ref Gym",
        "gym_slug": "ref-gym"
    }
    await client.post("/api/v1/auth/register-owner", json=reg_payload)
    
    login_res = await client.post("/api/v1/auth/login", json={"email": "ref_test@example.com", "password": "password123"})
    assert login_res.status_code == 200
    tokens = login_res.json()
    r_token_1 = tokens["refresh_token"]

    # Refresh 1: Should succeed and return access_token_2 and refresh_token_2
    refresh_res_1 = await client.post("/api/v1/auth/refresh", json={"refresh_token": r_token_1})
    assert refresh_res_1.status_code == 200
    tokens_2 = refresh_res_1.json()
    r_token_2 = tokens_2["refresh_token"]
    assert r_token_1 != r_token_2

    # Replay attack: Reuse r_token_1 again. Should fail.
    replay_res = await client.post("/api/v1/auth/refresh", json={"refresh_token": r_token_1})
    assert replay_res.status_code == 401

    # Verify that the valid r_token_2 has also been revoked as a security measure!
    refresh_res_2 = await client.post("/api/v1/auth/refresh", json={"refresh_token": r_token_2})
    assert refresh_res_2.status_code == 401


@pytest.mark.anyio
async def test_user_invitation_and_acceptance(client: AsyncClient, db: AsyncSession):
    # Register Owner
    reg_payload = {
        "email": "owner_invite@example.com",
        "password": "password123",
        "first_name": "Owner",
        "last_name": "Invite",
        "gym_name": "Invite Gym",
        "gym_slug": "invite-gym"
    }
    reg_res = await client.post("/api/v1/auth/register-owner", json=reg_payload)
    gym_id = reg_res.json()["gym_roles"][0]["gym_id"]
    
    # Login Owner
    login_res = await client.post("/api/v1/auth/login", json={"email": "owner_invite@example.com", "password": "password123"})
    owner_token = login_res.json()["access_token"]
    
    # Owner invites a client
    headers = {
        "Authorization": f"Bearer {owner_token}",
        "X-Gym-ID": gym_id
    }
    invite_payload = {
        "email": "invited_client@example.com",
        "phone": "9998887770",
        "role": "CLIENT"
    }
    
    invite_res = await client.post("/api/v1/auth/invite", json=invite_payload, headers=headers)
    assert invite_res.status_code == 201
    invite_data = invite_res.json()
    invite_token = invite_data["token"]
    assert invite_data["role"] == "CLIENT"
    assert invite_data["email"] == "invited_client@example.com"
    assert invite_data["gym_id"] == gym_id

    # Client accepts invite
    accept_payload = {
        "token": invite_token,
        "password": "clientsecurepwd",
        "first_name": "Invited",
        "last_name": "Client"
    }
    accept_res = await client.post("/api/v1/auth/accept-invite", json=accept_payload)
    assert accept_res.status_code == 200
    client_data = accept_res.json()
    assert client_data["email"] == "invited_client@example.com"
    assert client_data["first_name"] == "Invited"
    assert len(client_data["gym_roles"]) == 1
    assert client_data["gym_roles"][0]["role"] == "CLIENT"
    assert client_data["gym_roles"][0]["gym_id"] == gym_id

    # Trying to accept same invitation again should fail
    re_accept_res = await client.post("/api/v1/auth/accept-invite", json=accept_payload)
    assert re_accept_res.status_code == 400

