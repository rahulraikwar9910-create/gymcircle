import pytest
from httpx import AsyncClient
from sqlalchemy import inspect
from sqlalchemy.ext.asyncio import AsyncSession
from app.models.auth import UserGymRole
from app.models.gym import ClientProfile


@pytest.mark.anyio
async def test_security_headers_middleware(client: AsyncClient):
    response = await client.get("/health")
    assert response.status_code == 200
    
    # Assert OWASP security headers presence
    headers = response.headers
    assert headers.get("X-Frame-Options") == "DENY"
    assert headers.get("X-Content-Type-Options") == "nosniff"
    assert headers.get("X-XSS-Protection") == "1; mode=block"
    assert "max-age=31536000" in headers.get("Strict-Transport-Security", "")
    assert "default-src 'self'" in headers.get("Content-Security-Policy", "")


@pytest.mark.anyio
async def test_rate_limiting_authentication(db: AsyncSession):
    from httpx import ASGITransport, AsyncClient
    from app.main import app
    from app.core.deps import get_db

    async def _override_get_db():
        yield db

    app.dependency_overrides[get_db] = _override_get_db
    
    async with AsyncClient(
        transport=ASGITransport(app=app),
        base_url="http://test"
    ) as local_client:
        # Make 5 requests to auth login (exceeding window limits)
        payload = {"email": "bad_email@example.com", "password": "wrong_password"}
        
        for i in range(5):
            res = await local_client.post("/api/v1/auth/login", json=payload)
            # Should be a bad request/unauthorized since it is bad credentials
            assert res.status_code in [400, 401]

        # The 6th request should hit the rate limit and return 429 Too Many Requests
        limit_res = await local_client.post("/api/v1/auth/login", json=payload)
        assert limit_res.status_code == 429
        assert "Too many requests" in limit_res.json()["detail"]

    app.dependency_overrides.clear()


@pytest.mark.anyio
async def test_database_performance_indexes():
    # Verify index configuration on foreign keys
    role_inspector = inspect(UserGymRole)
    assert role_inspector.columns["user_id"].index is True
    assert role_inspector.columns["gym_id"].index is True

    profile_inspector = inspect(ClientProfile)
    assert profile_inspector.columns["gym_id"].index is True
