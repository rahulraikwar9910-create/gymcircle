from typing import List
from pydantic import AnyHttpUrl, BeforeValidator
from pydantic_settings import BaseSettings, SettingsConfigDict
from typing_extensions import Annotated


def parse_cors_origins(v: str | List[str]) -> List[str]:
    if isinstance(v, str) and not v.startswith("["):
        return [i.strip() for i in v.split(",")]
    elif isinstance(v, (list, str)):
        return v
    raise ValueError(v)


class Settings(BaseSettings):
    model_config = SettingsConfigDict(
        env_file=".env",
        env_file_encoding="utf-8",
        extra="ignore"
    )

    PROJECT_NAME: str = "GymCircle"
    ENVIRONMENT: str = "development"
    PORT: int = 8000

    # JWT Security Settings
    JWT_SECRET: str = "super_secret_jwt_signing_key_replace_in_production"
    JWT_REFRESH_SECRET: str = "super_secret_jwt_refresh_signing_key_replace_in_production"
    ACCESS_TOKEN_EXPIRE_MINUTES: int = 30
    REFRESH_TOKEN_EXPIRE_DAYS: int = 7

    # Databases
    DATABASE_URL: str = "postgresql+psycopg://postgres:postgres@localhost:5432/gymcircle"
    REDIS_URL: str = "redis://localhost:6379/0"

    # CORS configuration
    BACKEND_CORS_ORIGINS: Annotated[
        List[str], BeforeValidator(parse_cors_origins)
    ] = ["http://localhost:5173", "http://127.0.0.1:5173"]


settings = Settings()
