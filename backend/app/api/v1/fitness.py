from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy.orm import selectinload
import uuid
from datetime import date
from typing import List, Optional
from decimal import Decimal

from app.core import deps
from app.models.auth import User, UserGymRole
from app.models.trainer import TrainerProfile
from app.models.fitness import Exercise, WorkoutPlan, WorkoutDay, WorkoutDayExercise, DietPlan, DietMeal, ProgressLog
from app.schemas.fitness import (
    ExerciseCreate,
    ExerciseResponse,
    WorkoutPlanCreate,
    WorkoutPlanResponse,
    DietPlanCreate,
    DietPlanResponse,
    ProgressLogCreate,
    ProgressLogResponse
)

router = APIRouter()


@router.post("/exercises", response_model=ExerciseResponse, status_code=status.HTTP_201_CREATED)
async def create_exercise(
    exercise_in: ExerciseCreate,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER", "GYM_MANAGER", "TRAINER"]))
):
    # Verify exercise unique name
    query = select(Exercise).where(Exercise.name == exercise_in.name)
    result = await db.execute(query)
    if result.scalar_one_or_none():
        raise HTTPException(status_code=400, detail="Exercise name already exists")

    exercise = Exercise(
        name=exercise_in.name,
        muscle_group=exercise_in.muscle_group,
        description=exercise_in.description,
        video_url=exercise_in.video_url
    )
    db.add(exercise)
    await db.flush()
    return exercise


@router.get("/exercises", response_model=List[ExerciseResponse])
async def list_exercises(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user)
):
    query = select(Exercise)
    result = await db.execute(query)
    return result.scalars().all()


@router.post("/workouts", response_model=WorkoutPlanResponse, status_code=status.HTTP_201_CREATED)
async def create_workout_plan(
    plan_in: WorkoutPlanCreate,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER", "GYM_MANAGER", "TRAINER"]))
):
    # Validate target client exists
    client_q = select(User).where(User.id == plan_in.client_id)
    client_res = await db.execute(client_q)
    if not client_res.scalar_one_or_none():
        raise HTTPException(status_code=404, detail="Target client not found")

    # Resolve trainer profile if creator has trainer role
    trainer_id = None
    is_trainer = any(r.role == "TRAINER" for r in current_user.gym_roles)
    if is_trainer:
        t_q = select(TrainerProfile).where(TrainerProfile.user_id == current_user.id)
        t_res = await db.execute(t_q)
        trainer = t_res.scalar_one_or_none()
        if trainer:
            trainer_id = trainer.id

    # Create plan
    plan = WorkoutPlan(
        trainer_id=trainer_id,
        client_id=plan_in.client_id,
        name=plan_in.name,
        description=plan_in.description
    )
    db.add(plan)
    await db.flush()

    # Create days and nested exercises
    for day_in in plan_in.days:
        day = WorkoutDay(
            plan_id=plan.id,
            day_name=day_in.day_name,
            order=day_in.order
        )
        db.add(day)
        await db.flush()

        for ex_in in day_in.exercises:
            ex = WorkoutDayExercise(
                day_id=day.id,
                exercise_id=ex_in.exercise_id,
                sets=ex_in.sets,
                reps=ex_in.reps,
                rest_seconds=ex_in.rest_seconds,
                notes=ex_in.notes,
                order=ex_in.order
            )
            db.add(ex)
            await db.flush()

    # Reload detailed hierarchy for response
    query_reload = (
        select(WorkoutPlan)
        .where(WorkoutPlan.id == plan.id)
        .options(
            selectinload(WorkoutPlan.days)
            .selectinload(WorkoutDay.exercises)
            .selectinload(WorkoutDayExercise.exercise)
        )
    )
    res = await db.execute(query_reload)
    return res.scalar_one()


@router.get("/workouts", response_model=List[WorkoutPlanResponse])
async def list_workout_plans(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user)
):
    query = (
        select(WorkoutPlan)
        .options(
            selectinload(WorkoutPlan.days)
            .selectinload(WorkoutDay.exercises)
            .selectinload(WorkoutDayExercise.exercise)
        )
    )

    # Scoping: If caller is standard CLIENT, restrict list to their own plans
    is_staff = current_user.is_super_admin or any(
        r.role in ["GYM_OWNER", "GYM_MANAGER", "TRAINER", "RECEPTIONIST"]
        for r in current_user.gym_roles
    )

    if not is_staff:
        query = query.where(WorkoutPlan.client_id == current_user.id)

    result = await db.execute(query)
    return result.scalars().all()


@router.post("/diets", response_model=DietPlanResponse, status_code=status.HTTP_201_CREATED)
async def create_diet_plan(
    plan_in: DietPlanCreate,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["GYM_OWNER", "GYM_MANAGER", "TRAINER"]))
):
    # Validate target client exists
    client_q = select(User).where(User.id == plan_in.client_id)
    client_res = await db.execute(client_q)
    if not client_res.scalar_one_or_none():
        raise HTTPException(status_code=404, detail="Target client not found")

    # Resolve trainer profile
    trainer_id = None
    is_trainer = any(r.role == "TRAINER" for r in current_user.gym_roles)
    if is_trainer:
        t_q = select(TrainerProfile).where(TrainerProfile.user_id == current_user.id)
        t_res = await db.execute(t_q)
        trainer = t_res.scalar_one_or_none()
        if trainer:
            trainer_id = trainer.id

    # Compute nutritional sums
    total_cals = sum(meal.calories for meal in plan_in.meals)
    total_prot = sum(meal.protein_g for meal in plan_in.meals)
    total_carb = sum(meal.carbs_g for meal in plan_in.meals)
    total_fat = sum(meal.fat_g for meal in plan_in.meals)

    plan = DietPlan(
        trainer_id=trainer_id,
        client_id=plan_in.client_id,
        name=plan_in.name,
        description=plan_in.description,
        total_calories=total_cals,
        total_protein_g=total_prot,
        total_carbs_g=total_carb,
        total_fat_g=total_fat
    )
    db.add(plan)
    await db.flush()

    for meal_in in plan_in.meals:
        meal = DietMeal(
            plan_id=plan.id,
            name=meal_in.name,
            time=meal_in.time,
            calories=meal_in.calories,
            protein_g=meal_in.protein_g,
            carbs_g=meal_in.carbs_g,
            fat_g=meal_in.fat_g,
            items=meal_in.items,
            order=meal_in.order
        )
        db.add(meal)
        await db.flush()

    # Reload detailed hierarchy for response
    query_reload = (
        select(DietPlan)
        .where(DietPlan.id == plan.id)
        .options(selectinload(DietPlan.meals))
    )
    res = await db.execute(query_reload)
    return res.scalar_one()


@router.get("/diets", response_model=List[DietPlanResponse])
async def list_diet_plans(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user)
):
    query = select(DietPlan).options(selectinload(DietPlan.meals))

    is_staff = current_user.is_super_admin or any(
        r.role in ["GYM_OWNER", "GYM_MANAGER", "TRAINER", "RECEPTIONIST"]
        for r in current_user.gym_roles
    )

    if not is_staff:
        query = query.where(DietPlan.client_id == current_user.id)

    result = await db.execute(query)
    return result.scalars().all()


@router.post("/progress", response_model=ProgressLogResponse, status_code=status.HTTP_201_CREATED)
async def log_fitness_progress(
    log_in: ProgressLogCreate,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["CLIENT"]))
):
    # Calculate BMI: weight / (height_m ^ 2)
    height_m = float(log_in.height_cm) / 100.0
    weight_kg = float(log_in.weight_kg)
    bmi_val = weight_kg / (height_m * height_m)
    bmi_dec = Decimal(str(round(bmi_val, 2)))

    log = ProgressLog(
        client_id=current_user.id,
        date=date.today(),
        weight_kg=log_in.weight_kg,
        height_cm=log_in.height_cm,
        bmi=bmi_dec,
        body_fat_percentage=log_in.body_fat_percentage,
        notes=log_in.notes
    )
    db.add(log)
    await db.flush()
    return log


@router.get("/progress", response_model=List[ProgressLogResponse])
async def list_progress_logs(
    client_id: Optional[uuid.UUID] = None,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user)
):
    is_staff = current_user.is_super_admin or any(
        r.role in ["GYM_OWNER", "GYM_MANAGER", "TRAINER", "RECEPTIONIST"]
        for r in current_user.gym_roles
    )

    query = select(ProgressLog)

    if not is_staff:
        # Client can only see their own logs
        if client_id and client_id != current_user.id:
            raise HTTPException(status_code=403, detail="Not authorized to access progress logs for other clients")
        query = query.where(ProgressLog.client_id == current_user.id)
    else:
        # Staff can inspect log of any specific client
        if client_id:
            query = query.where(ProgressLog.client_id == client_id)
        else:
            raise HTTPException(status_code=400, detail="Must provide client_id parameter")

    result = await db.execute(query)
    return result.scalars().all()
