# GymCircle - Development Roadmap

This roadmap details the sequential execution phases to build, verify, and launch the GymCircle SaaS platform.

## Phase 0: Scaffolding & Initial Foundation (Current)
- [x] Monorepo workspace initialization
- [x] Basic architectural documentation
- [x] Docker Compose local environment configuration (Postgres, Redis)
- [x] FastAPI skeleton, Pydantic configuration, SQLAlchemy initialization, Alembic setup
- [x] Core schema migrations (User, Gym, UserGymRole)
- [x] JWT verification and password hashing utilities
- [x] Auth endpoints (`/register-owner`, `/login`, `/me`)
- [x] Web (Vite/React) and Mobile (Expo/React Native) boilerplate configurations
- [x] Automation test setup with Pytest

## Phase 1: Authentication, Access Control & Multi-Tenancy
- Implement refresh token rotation.
- Create user invite codes & registration activation links.
- Create gym creation configuration API.
- Secure API endpoints using multi-tenant middleware (context managers limiting DB queries to active `gym_id`).

## Phase 2: Clients, Memberships, Attendance & Fee Ledger
- Client profiles: onboarding details, registration options.
- Membership Plans creation (duration, pricing).
- Digital Fee Ledger: list invoices, log cash/UPI payments, track collections.
- Attendance tracker: receptionist scanning logs, clients viewing history.

## Phase 3: Fee Due Alerts & Communication Engine
- Background cron runner to scan database for upcoming membership expirations.
- Notification service abstraction: integration points for WhatsApp, SMS, Email providers.
- Custom templates for payment due reminders and system transactional messages.

## Phase 4: Trainer Marketplace & Booking Engine
- Trainer profile configurations (bio, ratings, specializations).
- Location-based discovery search API.
- Trainer services packages, slot booking calendar, reservation updates.

## Phase 5: Workout & Diet Planners
- Exercises library database.
- Workout builder: Drag-and-drop days schedule, set targets (reps, duration, rest).
- Diet builder: meal allocations, timing, calorie mappings.
- Fitness progress log: height, weight, BMI metrics, and image attachment capability.

## Phase 6: Razorpay Integration & Platform Fees
- Gateway orchestration: creating orders, webhook receivers, signature validation.
- Automatic ₹10 flat platform fee deductions and ledger entries.
- Automated refund flows and transaction reconciliation trackers.

## Phase 7: Web App Polish & Dashboards
- Gym Owner Dashboard metrics (today's revenue, overdue accounts, attendance count).
- Receptionist interface (quick member lookups, check-in controls).
- Super Admin panels (gym management, system transaction reports).

## Phase 8: Mobile Application (Client & Trainer App)
- Expo app screens for Client (home, workouts, diet, payments list).
- Expo screens for Trainer (assigned clients, workouts builder, diet charts).
- Push notification handling on devices.

## Phase 9: Security Hardening, Performance & Deploy
- Rate limiting middleware.
- Secrets rotation, SSL enforcement, helmet headers.
- Docker containers production builds, CI/CD pipeline, Nginx configuration.
- Database indexing and query optimizations.
