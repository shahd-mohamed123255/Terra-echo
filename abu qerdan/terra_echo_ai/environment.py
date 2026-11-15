from __future__ import annotations

import hashlib
import json
import logging
import math
import time
from datetime import date
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen

from terra_echo_ai.config import (
    DATABASE_PATH,
    NASA_POWER_BASE_URL,
    NASA_POWER_TIMEOUT_SECONDS,
)
from terra_echo_ai.database import database, json_dump

logger = logging.getLogger(__name__)

ALLOWED_POWER_PARAMETERS = {
    "T2M",
    "T2M_MAX",
    "T2M_MIN",
    "PRECTOTCORR",
    "ALLSKY_SFC_SW_DWN",
    "RH2M",
    "WS2M",
}


class DataSourceUnavailable(RuntimeError):
    pass


def retrieve_nasa_power(
    summary: dict[str, Any],
    start_date: str | None = None,
    end_date: str | None = None,
    parameters: list[str] | None = None,
    use_cache: bool = True,
) -> dict[str, Any]:
    location = summary.get("location") or {}
    latitude = location.get("latitude")
    longitude = location.get("longitude")
    if latitude is None or longitude is None:
        raise ValueError("Farm latitude and longitude are required for NASA POWER retrieval.")
    try:
        latitude = float(latitude)
        longitude = float(longitude)
    except (TypeError, ValueError) as error:
        raise ValueError("Farm coordinates must be numeric.") from error
    if not -90 <= latitude <= 90 or not -180 <= longitude <= 180:
        raise ValueError("Farm coordinates are outside valid latitude/longitude ranges.")
    period = summary.get("analysis_period") or {}
    start = start_date or period.get("start_date")
    end = end_date or period.get("end_date")
    if not start or not end:
        raise ValueError("start_date and end_date are required for environmental retrieval.")
    start_day, end_day = date.fromisoformat(start), date.fromisoformat(end)
    if start_day > end_day:
        raise ValueError("start_date must be earlier than or equal to end_date.")
    params = parameters or ["T2M", "PRECTOTCORR"]
    normalized_params = sorted({str(parameter).upper() for parameter in params})
    invalid_params = sorted(set(normalized_params) - ALLOWED_POWER_PARAMETERS)
    if invalid_params:
        raise ValueError(f"Unsupported NASA POWER parameters: {', '.join(invalid_params)}")
    request_data = {
        "latitude": latitude,
        "longitude": longitude,
        "start": start_day.strftime("%Y%m%d"),
        "end": end_day.strftime("%Y%m%d"),
        "parameters": normalized_params,
        "community": "AG",
        "format": "JSON",
    }
    cache_key = hashlib.sha256(json_dump(request_data).encode("utf-8")).hexdigest()
    if use_cache:
        with database(DATABASE_PATH) as connection:
            cached = connection.execute(
                "SELECT payload_json, retrieved_at FROM environment_cache WHERE cache_key = ?",
                (cache_key,),
            ).fetchone()
        if cached is not None:
            result = json.loads(cached["payload_json"])
            result["provenance"]["cached"] = True
            result["provenance"]["retrieved_at"] = cached["retrieved_at"]
            return result
    query = urlencode(
        {
            "parameters": ",".join(normalized_params),
            "community": "AG",
            "longitude": longitude,
            "latitude": latitude,
            "start": request_data["start"],
            "end": request_data["end"],
            "format": "JSON",
        }
    )
    url = f"{NASA_POWER_BASE_URL}?{query}"
    request = Request(url, headers={"User-Agent": "TerraEchoAI/0.1"})
    error: Exception | None = None
    for attempt in range(2):
        try:
            with urlopen(request, timeout=NASA_POWER_TIMEOUT_SECONDS) as response:
                body = response.read()
            payload = json.loads(body.decode("utf-8"))
            break
        except (HTTPError, URLError, TimeoutError, json.JSONDecodeError) as caught:
            error = caught
            logger.warning("NASA POWER request failed (attempt %s): %s", attempt + 1, caught)
            if attempt == 0:
                time.sleep(0.25)
    else:
        raise DataSourceUnavailable(f"NASA POWER is unavailable: {error}") from error
    if not isinstance(payload.get("properties"), dict):
        raise DataSourceUnavailable("NASA POWER returned an unexpected response schema.")
    raw_parameters = payload["properties"].get("parameter")
    if not isinstance(raw_parameters, dict) or not raw_parameters:
        raise DataSourceUnavailable("NASA POWER returned no parameter observations.")
    normalized_parameters: dict[str, dict[str, float | None]] = {}
    missing_values: dict[str, int] = {}
    for parameter, observations in raw_parameters.items():
        if not isinstance(observations, dict):
            raise DataSourceUnavailable(
                f"NASA POWER returned an invalid observation series for {parameter}."
            )
        normalized_series: dict[str, float | None] = {}
        missing_count = 0
        for observation_date, value in observations.items():
            if (
                not isinstance(value, (int, float))
                or isinstance(value, bool)
                or not math.isfinite(value)
                or value == -999
            ):
                normalized_series[str(observation_date)] = None
                missing_count += 1
            else:
                normalized_series[str(observation_date)] = float(value)
        normalized_parameters[str(parameter)] = normalized_series
        missing_values[str(parameter)] = missing_count
    result = {
        "observations": {"parameters": normalized_parameters},
        "observations_raw": {"parameters": raw_parameters},
        "provenance": {
            "source": "NASA POWER",
            "dataset": "POWER Daily Point, AG community",
            "retrieved_at": date.today().isoformat(),
            "coordinates": {"latitude": latitude, "longitude": longitude},
            "requested_period": {"start": start, "end": end},
            "units": payload.get("header", {}).get("units")
            or payload.get("header", {}).get("parameters", {}),
            "parameter_descriptions": payload.get("parameters", {}),
            "spatial_resolution": "NASA POWER gridded point product; not a field measurement",
            "temporal_resolution": "daily",
            "quality_metadata": payload.get("messages", []),
            "missing_values_by_parameter": missing_values,
            "cached": False,
        },
    }
    with database(DATABASE_PATH) as connection:
        connection.execute(
            """
            INSERT INTO environment_cache (cache_key, source, payload_json)
            VALUES (?, ?, ?)
            ON CONFLICT(cache_key) DO UPDATE SET
                payload_json=excluded.payload_json,
                retrieved_at=CURRENT_TIMESTAMP
            """,
            (cache_key, "NASA POWER", json_dump(result)),
        )
    return result


def retrieve_environment(
    summary: dict[str, Any],
    source: str = "nasa_power",
    start_date: str | None = None,
    end_date: str | None = None,
    parameters: list[str] | None = None,
) -> dict[str, Any]:
    if source.lower() in {"nasa", "nasa_power"}:
        return retrieve_nasa_power(summary, start_date, end_date, parameters)
    if source.lower() in {"esa", "copernicus"}:
        raise DataSourceUnavailable(
            "ESA/Copernicus retrieval is not configured. It requires a Copernicus Data Space account and OAuth credentials; none were supplied."
        )
    raise ValueError(f"Unknown environmental data source: {source}")
