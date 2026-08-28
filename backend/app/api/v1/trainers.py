from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.future import select
from sqlalchemy.orm import selectinload
import uuid
import math
from typing import List, Optional
from decimal import Decimal

from app.core import deps
from app.models.auth import User, UserGymRole
from app.models.trainer import (
    Specialization,
    TrainerProfile,
    TrainerService,
    TrainerServicePackage,
    TrainerBooking,
    trainer_specialization_association
)
from app.schemas.trainer import (
    SpecializationResponse,
    TrainerProfileCreate,
    TrainerProfileResponse,
    TrainerServiceCreate,
    TrainerServiceResponse,
    TrainerServicePackageCreate,
    TrainerServicePackageResponse,
    TrainerBookingCreate,
    TrainerBookingResponse,
    TrainerMarketplaceResponse
)

router = APIRouter()


def calculate_haversine(lat1: float, lon1: float, lat2: float, lon2: float) -> float:
    # Earth radius in KM
    R = 6371.0
    
    dlat = math.radians(lat2 - lat1)
    dlon = math.radians(lon2 - lon1)
    
    a = (math.sin(dlat / 2) ** 2 + 
         math.cos(math.radians(lat1)) * math.cos(math.radians(lat2)) * math.sin(dlon / 2) ** 2)
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    
    return R * c


@router.get("/specializations", response_model=List[SpecializationResponse])
async def list_specializations(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user)
):
    query = select(Specialization)
    result = await db.execute(query)
    return result.scalars().all()


@router.post("/profile", response_model=TrainerProfileResponse)
async def create_or_update_trainer_profile(
    profile_in: TrainerProfileCreate,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["TRAINER"]))
):
    # Verify that the user has a trainer role
    query_profile = (
        select(TrainerProfile)
        .where(TrainerProfile.user_id == current_user.id)
        .options(selectinload(TrainerProfile.specializations))
    )
    result = await db.execute(query_profile)
    profile = result.scalar_one_or_none()

    # Load requested specializations from DB
    specializations = []
    if profile_in.specializations:
        spec_query = select(Specialization).where(Specialization.id.in_(profile_in.specializations))
        spec_result = await db.execute(spec_query)
        specializations = spec_result.scalars().all()

    if profile:
        profile.bio = profile_in.bio
        profile.experience_years = profile_in.experience_years
        profile.certifications = profile_in.certifications
        profile.languages = profile_in.languages
        profile.city = profile_in.city
        profile.area = profile_in.area
        profile.is_online = profile_in.is_online
        profile.is_offline = profile_in.is_offline
        profile.latitude = profile_in.latitude
        profile.longitude = profile_in.longitude
        profile.gym_id = profile_in.gym_id
        profile.specializations = specializations
    else:
        profile = TrainerProfile(
            user_id=current_user.id,
            gym_id=profile_in.gym_id,
            bio=profile_in.bio,
            experience_years=profile_in.experience_years,
            certifications=profile_in.certifications,
            languages=profile_in.languages,
            city=profile_in.city,
            area=profile_in.area,
            is_online=profile_in.is_online,
            is_offline=profile_in.is_offline,
            latitude=profile_in.latitude,
            longitude=profile_in.longitude,
            specializations=specializations
        )
        db.add(profile)
        
    await db.flush()

    # Reload profile for response eager loading
    query_reload = (
        select(TrainerProfile)
        .where(TrainerProfile.id == profile.id)
        .options(selectinload(TrainerProfile.specializations))
    )
    res = await db.execute(query_reload)
    return res.scalar_one()


@router.post("/services", response_model=TrainerServiceResponse, status_code=status.HTTP_201_CREATED)
async def create_trainer_service(
    service_in: TrainerServiceCreate,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["TRAINER"]))
):
    # Fetch trainer profile
    profile_query = select(TrainerProfile).where(TrainerProfile.user_id == current_user.id)
    profile_res = await db.execute(profile_query)
    profile = profile_res.scalar_one_or_none()
    if not profile:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Trainer profile must be created before adding services"
        )

    service = TrainerService(
        trainer_id=profile.id,
        name=service_in.name,
        description=service_in.description,
        price=service_in.price
    )
    db.add(service)
    await db.flush()
    
    # Reload for response
    query_reload = (
        select(TrainerService)
        .where(TrainerService.id == service.id)
        .options(selectinload(TrainerService.packages))
    )
    res = await db.execute(query_reload)
    return res.scalar_one()


@router.post("/services/{service_id}/packages", response_model=TrainerServicePackageResponse, status_code=status.HTTP_201_CREATED)
async def create_service_package(
    service_id: uuid.UUID,
    package_in: TrainerServicePackageCreate,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["TRAINER"]))
):
    # Verify service exists and belongs to logged in trainer
    profile_query = select(TrainerProfile).where(TrainerProfile.user_id == current_user.id)
    profile_res = await db.execute(profile_query)
    profile = profile_res.scalar_one_or_none()
    if not profile:
        raise HTTPException(status_code=400, detail="Trainer profile not found")

    service_query = select(TrainerService).where(
        TrainerService.id == service_id,
        TrainerService.trainer_id == profile.id
    )
    service_res = await db.execute(service_query)
    service = service_res.scalar_one_or_none()
    if not service:
        raise HTTPException(status_code=404, detail="Trainer service not found")

    package = TrainerServicePackage(
        service_id=service_id,
        name=package_in.name,
        price=package_in.price,
        sessions_count=package_in.sessions_count,
        duration_days=package_in.duration_days
    )
    db.add(package)
    await db.flush()
    return package


@router.get("/marketplace", response_model=List[TrainerMarketplaceResponse])
async def search_trainers(
    city: Optional[str] = None,
    area: Optional[str] = None,
    specialization_id: Optional[uuid.UUID] = None,
    min_rating: Optional[float] = None,
    min_experience: Optional[int] = None,
    is_online: Optional[bool] = None,
    is_offline: Optional[bool] = None,
    latitude: Optional[float] = None,
    longitude: Optional[float] = None,
    max_distance: Optional[float] = None,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user)
):
    query = (
        select(TrainerProfile)
        .options(
            selectinload(TrainerProfile.user).selectinload(User.gym_roles),
            selectinload(TrainerProfile.specializations)
        )
    )

    # Apply filters
    if city:
        query = query.where(TrainerProfile.city == city)
    if area:
        query = query.where(TrainerProfile.area == area)
    if min_rating:
        query = query.where(TrainerProfile.rating >= Decimal(str(min_rating)))
    if min_experience:
        query = query.where(TrainerProfile.experience_years >= min_experience)
    if is_online is not None:
        query = query.where(TrainerProfile.is_online == is_online)
    if is_offline is not None:
        query = query.where(TrainerProfile.is_offline == is_offline)
    if specialization_id:
        # Join many-to-many link table
        query = query.join(
            trainer_specialization_association,
            TrainerProfile.id == trainer_specialization_association.c.trainer_id
        ).where(trainer_specialization_association.c.specialization_id == specialization_id)

    result = await db.execute(query)
    profiles = result.scalars().all()

    marketplace_results = []

    for profile in profiles:
        dist = None
        # Proximity distance filter
        if latitude is not None and longitude is not None and profile.latitude and profile.longitude:
            dist = calculate_haversine(
                latitude,
                longitude,
                float(profile.latitude),
                float(profile.longitude)
            )
            
            if max_distance is not None and dist > max_distance:
                # Exclude since it exceeds max distance bounds
                continue

        p_response = TrainerProfileResponse.model_validate(profile)

        marketplace_results.append(
            TrainerMarketplaceResponse(
                id=profile.user.id,
                first_name=profile.user.first_name,
                last_name=profile.user.last_name,
                email=profile.user.email,
                phone=profile.user.phone,
                profile=p_response,
                distance_km=dist
            )
        )

    # Sort results by distance if search coords were provided
    if latitude is not None and longitude is not None:
        marketplace_results.sort(key=lambda x: x.distance_km if x.distance_km is not None else float("inf"))

    return marketplace_results


@router.post("/bookings", response_model=TrainerBookingResponse, status_code=status.HTTP_201_CREATED)
async def create_booking(
    booking_in: TrainerBookingCreate,
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.RoleChecker(allowed_roles=["CLIENT"]))
):
    # Fetch package
    pack_query = select(TrainerServicePackage).where(TrainerServicePackage.id == booking_in.package_id)
    pack_res = await db.execute(pack_query)
    package = pack_res.scalar_one_or_none()
    if not package:
        raise HTTPException(status_code=404, detail="Trainer service package not found")

    # Fetch trainer profile
    trainer_query = select(TrainerProfile).where(TrainerProfile.id == booking_in.trainer_id)
    trainer_res = await db.execute(trainer_query)
    trainer = trainer_res.scalar_one_or_none()
    if not trainer:
        raise HTTPException(status_code=404, detail="Trainer profile not found")

    booking = TrainerBooking(
        client_id=current_user.id,
        trainer_id=booking_in.trainer_id,
        package_id=booking_in.package_id,
        booking_date=booking_in.booking_date,
        status="PENDING",
        price_paid=package.price
    )
    db.add(booking)
    await db.flush()

    # Reload details for response
    query_reload = (
        select(TrainerBooking)
        .where(TrainerBooking.id == booking.id)
        .options(
            selectinload(TrainerBooking.client).selectinload(User.gym_roles),
            selectinload(TrainerBooking.trainer).selectinload(TrainerProfile.specializations),
            selectinload(TrainerBooking.package)
        )
    )
    res = await db.execute(query_reload)
    return res.scalar_one()


@router.get("/bookings", response_model=List[TrainerBookingResponse])
async def list_bookings(
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user)
):
    # Eager load relationships
    base_query = (
        select(TrainerBooking)
        .options(
            selectinload(TrainerBooking.client).selectinload(User.gym_roles),
            selectinload(TrainerBooking.trainer).selectinload(TrainerProfile.specializations),
            selectinload(TrainerBooking.package)
        )
    )

    # Filter based on role
    # If Super Admin, return all bookings
    if current_user.is_super_admin:
        result = await db.execute(base_query)
        return result.scalars().all()

    # Determine if they are a Trainer vs Client
    trainer_profile_query = select(TrainerProfile).where(TrainerProfile.user_id == current_user.id)
    tp_res = await db.execute(trainer_profile_query)
    trainer_profile = tp_res.scalar_one_or_none()

    if trainer_profile:
        # User is a Trainer: show bookings requested of them
        query = base_query.where(TrainerBooking.trainer_id == trainer_profile.id)
    else:
        # Default Client: show bookings they requested
        query = base_query.where(TrainerBooking.client_id == current_user.id)

    result = await db.execute(query)
    return result.scalars().all()


@router.patch("/bookings/{booking_id}/status", response_model=TrainerBookingResponse)
async def update_booking_status(
    booking_id: uuid.UUID,
    status: str,  # CONFIRMED, CANCELLED, COMPLETED
    db: AsyncSession = Depends(deps.get_db),
    current_user: User = Depends(deps.get_current_active_user)
):
    allowed_statuses = ["CONFIRMED", "CANCELLED", "COMPLETED"]
    if status not in allowed_statuses:
        raise HTTPException(status_code=400, detail="Invalid booking status requested")

    # Fetch booking
    query = select(TrainerBooking).where(TrainerBooking.id == booking_id)
    result = await db.execute(query)
    booking = result.scalar_one_or_none()
    if not booking:
        raise HTTPException(status_code=404, detail="Trainer booking not found")

    # Verify authorization: only the assigned trainer can change status (or client can cancel)
    # Check if current user is the trainer
    trainer_profile_query = select(TrainerProfile).where(
        TrainerProfile.id == booking.trainer_id,
        TrainerProfile.user_id == current_user.id
    )
    tp_res = await db.execute(trainer_profile_query)
    is_assigned_trainer = tp_res.scalar_one_or_none() is not None

    is_requesting_client = booking.client_id == current_user.id

    if not is_assigned_trainer:
        # If they are the client, they can only CANCEL a PENDING booking
        if is_requesting_client and status == "CANCELLED":
            pass
        else:
            raise HTTPException(
                status_code=403,
                detail="Not authorized to update this booking status"
            )

    booking.status = status
    await db.flush()

    # Reload relationships
    query_reload = (
        select(TrainerBooking)
        .where(TrainerBooking.id == booking.id)
        .options(
            selectinload(TrainerBooking.client).selectinload(User.gym_roles),
            selectinload(TrainerBooking.trainer).selectinload(TrainerProfile.specializations),
            selectinload(TrainerBooking.package)
        )
    )
    res = await db.execute(query_reload)
    return res.scalar_one()
