import sys
import asyncio

if sys.platform == "win32":
    asyncio.set_event_loop_policy(asyncio.WindowsSelectorEventLoopPolicy())

from fastapi import FastAPI, Depends
from fastapi.middleware.cors import CORSMiddleware
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.sql import text

from app.core.config import settings
from app.core import deps
from app.api.v1.auth import router as auth_router
from app.api.v1.gyms import router as gyms_router
from app.api.v1.clients import router as clients_router
from app.api.v1.memberships import router as memberships_router
from app.api.v1.payments import router as payments_router
from app.api.v1.attendance import router as attendance_router
from app.api.v1.notifications import router as notifications_router
from app.api.v1.trainers import router as trainers_router
from app.api.v1.payments_integration import router as payments_integration_router
from app.api.v1.admin_dashboard import router as admin_dashboard_router
from app.api.v1.fitness import router as fitness_router
from app.core.middleware import SecurityHeadersMiddleware, RateLimitingMiddleware

app = FastAPI(
    title=settings.PROJECT_NAME,
    openapi_url=f"/openapi.json",
    docs_url="/docs",
    redoc_url="/redoc"
)

# Register Security and Rate-Limiting Middlewares
app.add_middleware(SecurityHeadersMiddleware)
app.add_middleware(RateLimitingMiddleware, limit=5, window_seconds=60)

# CORS Middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=settings.BACKEND_CORS_ORIGINS,
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Include Routers
app.include_router(auth_router, prefix="/api/v1/auth", tags=["Authentication"])
app.include_router(gyms_router, prefix="/api/v1/gyms", tags=["Gyms"])
app.include_router(clients_router, prefix="/api/v1/clients", tags=["Clients"])
app.include_router(memberships_router, prefix="/api/v1/memberships", tags=["Memberships"])
app.include_router(payments_router, prefix="/api/v1/payments", tags=["Payments"])
app.include_router(attendance_router, prefix="/api/v1/attendance", tags=["Attendance"])
app.include_router(notifications_router, prefix="/api/v1/notifications", tags=["Notifications"])
app.include_router(trainers_router, prefix="/api/v1/trainers", tags=["Trainers"])
app.include_router(payments_integration_router, prefix="/api/v1/payments-integration", tags=["Payments Integration"])
app.include_router(admin_dashboard_router, prefix="/api/v1/admin/dashboard", tags=["Admin Dashboard"])
app.include_router(fitness_router, prefix="/api/v1/fitness", tags=["Fitness"])
@app.get("/health", tags=["Health"])
async def health_check(db: AsyncSession = Depends(deps.get_db)):
    try:
        # Verify database connection
        await db.execute(text("SELECT 1"))
        return {
            "status": "healthy",
            "database": "connected",
            "project": settings.PROJECT_NAME
        }
    except Exception as e:
        return {
            "status": "unhealthy",
            "database": f"error: {str(e)}",
            "project": settings.PROJECT_NAME
        }
