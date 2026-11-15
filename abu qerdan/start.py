from __future__ import annotations

import logging
import os

from terra_echo_ai.config import DATA_DIR
from terra_echo_ai.database import initialize_database


def main() -> None:
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s %(levelname)s %(name)s: %(message)s",
    )
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    (DATA_DIR / "incoming").mkdir(parents=True, exist_ok=True)
    initialize_database()
    try:
        import uvicorn
    except ImportError as error:
        raise RuntimeError("FastAPI and Uvicorn are required; install requirements.txt.") from error
    port = int(os.getenv("TERRA_ECHO_PORT", "8000"))
    host = os.getenv("TERRA_ECHO_HOST", "0.0.0.0")
    print(f"Field Shift AI listening on {host}:{port}")
    print(f"Agent API documentation: http://<server-host>:{port}/docs")
    uvicorn.run("terra_echo_ai.api:app", host=host, port=port, reload=False)


if __name__ == "__main__":
    main()
