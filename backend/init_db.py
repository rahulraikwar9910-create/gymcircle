import asyncio
from app.models.base import BaseModel
from app.core.database import engine, AsyncSessionLocal
from app.services import auth as auth_service
from app.schemas.auth import OwnerRegisterRequest

# Make sure all models are imported so they register on BaseModel.metadata
from app.models.auth import User, UserGymRole, UserRefreshToken, UserInvitation
from app.models.gym import ClientProfile, MembershipPlan, ClientGymMembership, FeeLedger, Attendance
from app.models.trainer import Specialization, TrainerProfile, TrainerService, TrainerServicePackage, TrainerBooking
from app.models.payments import PaymentTransaction
from app.models.fitness import Exercise, WorkoutPlan, WorkoutDay, WorkoutDayExercise, DietPlan, DietMeal, ProgressLog

async def init_db():
    print("Creating all tables in database...")
    async with engine.begin() as conn:
        await conn.run_sync(BaseModel.metadata.create_all)
    print("Database tables created successfully!")

    async with AsyncSessionLocal() as db:
        from sqlalchemy import select
        res = await db.execute(select(User).where(User.email == "owner_a@gymcircle.com"))
        user = res.scalar_one_or_none()
        if not user:
            print("Seeding default gym owner and gym...")
            reg_data = OwnerRegisterRequest(
                email="owner_a@gymcircle.com",
                password="password123",
                first_name="Owner",
                last_name="A",
                gym_name="Gym Alpha",
                gym_slug="gym-alpha"
            )
            await auth_service.register_owner_and_gym(db, reg_data)
            await db.commit()
            print("Seeding completed successfully!")
        else:
            print("Default owner already exists.")

if __name__ == "__main__":
    asyncio.run(init_db())
