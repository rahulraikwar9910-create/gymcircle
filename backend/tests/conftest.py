import os
from typing import AsyncGenerator
import pytest
from sqlalchemy.ext.asyncio import create_async_engine, async_sessionmaker, AsyncSession

from app.core.database import Base
from app.core.deps import get_db
from app.main import app

# Import all models to register them on Base.metadata
from app.models.auth import User, Gym, UserGymRole, UserRefreshToken, UserInvitation
from app.models.gym import ClientProfile, MembershipPlan, ClientGymMembership, FeeLedger, Attendance
from app.models.notifications import NotificationTemplate, NotificationLog
from app.models.trainer import Specialization, TrainerProfile, TrainerService, TrainerServicePackage, TrainerBooking
from app.models.payments import PaymentTransaction
from app.models.fitness import Exercise, WorkoutPlan, WorkoutDay, WorkoutDayExercise, DietPlan, DietMeal, ProgressLog

# Create test engine using a local SQLite file for tests
DB_FILE = "test.db"
test_engine = create_async_engine(
    f"sqlite+aiosqlite:///{DB_FILE}",
    echo=False,
    future=True
)

TestSessionLocal = async_sessionmaker(
    bind=test_engine,
    class_=AsyncSession,
    expire_on_commit=False
)


@pytest.fixture(autouse=True)
async def setup_test_db():
    # Make sure tables are created in the database
    async with test_engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)
    yield
    # Drop tables to clean up
    async with test_engine.begin() as conn:
        await conn.run_sync(Base.metadata.drop_all)
    
    # Try to delete the database file to keep workspace clean
    if os.path.exists(DB_FILE):
        try:
            os.remove(DB_FILE)
        except PermissionError:
            pass


@pytest.fixture
async def db() -> AsyncGenerator[AsyncSession, None]:
    async with TestSessionLocal() as session:
        yield session


@pytest.fixture
async def client(db: AsyncSession):
    # Override get_db dependency to use the test session
    async def _override_get_db():
        yield db

    app.dependency_overrides[get_db] = _override_get_db
    from httpx import ASGITransport, AsyncClient
    async with AsyncClient(
        transport=ASGITransport(app=app),
        base_url="http://test",
        headers={"X-Bypass-Rate-Limit": "true"}
    ) as ac:
        yield ac
    app.dependency_overrides.clear()


@pytest.fixture
def anyio_backend():
    return "asyncio"
