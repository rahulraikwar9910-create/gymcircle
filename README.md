# GymCircle

GymCircle is a multi-tenant Gym Management + Client CRM + Trainer Marketplace + Fitness Services + Payment Platform. It is designed to replace manual fee registers and diaries with a simple, digital, multi-tenant solution.

## Core Stack

- **Backend**: FastAPI (Python 3.12+), SQLAlchemy 2.x, Pydantic v2, Alembic, PostgreSQL, JWT, Redis
- **Web App**: React, Vite, TypeScript, TanStack Query, TailwindCSS
- **Mobile App**: React Native, Expo, TypeScript
- **Payments**: Razorpay
- **Containerization**: Docker & Docker Compose

## Repository Structure

- `backend/` - FastAPI service, API endpoints, Alembic migrations, schemas, models, and tests.
- `web/` - React frontend for Gym Owners, Staff, and Administrators.
- `mobile/` - Expo React Native app for Clients, Trainers, and Owners.
- `docs/` - Comprehensive architecture, database, API, and setup documentation.
- `docker-compose.yml` - Docker compose setup for development environment.

## Running Locally

Refer to the documentation in [docs/Architecture.md](file:///D:/Projects/GymCircle/docs/Architecture.md) and [docs/Development-Roadmap.md](file:///D:/Projects/GymCircle/docs/Development-Roadmap.md) for details on setting up, running, and testing the system.
