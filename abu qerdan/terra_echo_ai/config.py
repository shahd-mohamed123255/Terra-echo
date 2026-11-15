import os
from pathlib import Path


ROOT_DIR = Path(__file__).resolve().parents[1]


def _load_local_env() -> None:
    env_file = ROOT_DIR / ".env"
    if not env_file.is_file():
        return
    for line in env_file.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip().strip("\"'")
        if key:
            os.environ.setdefault(key, value)


_load_local_env()
DATA_DIR = Path(os.getenv("TERRA_ECHO_DATA_DIR", ROOT_DIR / "data")).resolve()
DATABASE_PATH = Path(
    os.getenv("TERRA_ECHO_DATABASE_PATH", DATA_DIR / "terra_echo.sqlite3")
).resolve()
LLM_API_KEY = os.getenv("TERRA_ECHO_LLM_API_KEY")
LLM_BASE_URL = os.getenv(
    "TERRA_ECHO_LLM_BASE_URL", "https://api.openai.com/v1"
).rstrip("/")
LLM_MODEL = os.getenv("TERRA_ECHO_LLM_MODEL", "gpt-4o-mini")
LLM_TIMEOUT_SECONDS = float(os.getenv("TERRA_ECHO_LLM_TIMEOUT_SECONDS", "30"))
NASA_POWER_BASE_URL = os.getenv(
    "NASA_POWER_BASE_URL", "https://power.larc.nasa.gov/api/temporal/daily/point"
)
NASA_POWER_TIMEOUT_SECONDS = float(
    os.getenv("NASA_POWER_TIMEOUT_SECONDS", "20")
)
ALLOWED_ORIGINS = [
    origin.strip()
    for origin in os.getenv("TERRA_ECHO_ALLOWED_ORIGINS", "*").split(",")
    if origin.strip()
]
API_TOKEN = os.getenv("TERRA_ECHO_API_TOKEN", "")
REQUIRE_API_TOKEN = os.getenv("TERRA_ECHO_REQUIRE_API_TOKEN", "false").strip().lower() in {
    "1",
    "true",
    "yes",
}
