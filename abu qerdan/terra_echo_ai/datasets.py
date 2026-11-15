from __future__ import annotations

import csv
import math
from functools import lru_cache
from pathlib import Path
from typing import Any

from terra_echo_ai.config import ROOT_DIR

ML_CSV_PATH = ROOT_DIR / "terra_echo_ml_clean_8zones.csv"
FAO_CSV_PATH = ROOT_DIR / "fao_national_yield_by_crop_year.csv"

ZONE_COORDINATES = {
    "assiut": (27.18, 31.18),
    "beni_suef": (29.07, 31.10),
    "dakahlia": (31.04, 31.38),
    "fayoum": (29.31, 30.84),
    "gharbia": (30.80, 31.00),
    "kafr_el_sheikh": (31.11, 30.94),
    "monufia": (30.56, 31.16),
    "sharqia": (30.59, 31.50),
}


def find_nearest_zone(latitude: float | None, longitude: float | None) -> str:
    if latitude is None or longitude is None:
        return "monufia"
    best_zone = "monufia"
    best_dist = float("inf")
    for zone, (lat, lon) in ZONE_COORDINATES.items():
        dist = math.hypot(lat - latitude, lon - longitude)
        if dist < best_dist:
            best_dist = dist
            best_zone = zone
    return best_zone


@lru_cache(maxsize=1)
def load_fao_yields() -> dict[str, dict[str, Any]]:
    if not FAO_CSV_PATH.is_file():
        return {}
    crops_data: dict[str, list[dict[str, Any]]] = {}
    with open(FAO_CSV_PATH, mode="r", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            crop = (row.get("crop") or "").strip().lower()
            try:
                year = int(row.get("harvest_year") or 0)
                yield_val = float(row.get("national_yield_t_ha") or 0)
                area = float(row.get("national_area_ha") or 0)
            except ValueError:
                continue
            if crop not in crops_data:
                crops_data[crop] = []
            crops_data[crop].append({"year": year, "yield_t_ha": yield_val, "area_ha": area})

    stats: dict[str, dict[str, Any]] = {}
    for crop, rows in crops_data.items():
        rows.sort(key=lambda r: r["year"])
        yields = [r["yield_t_ha"] for r in rows]
        stats[crop] = {
            "crop": crop,
            "mean_yield_t_ha": round(sum(yields) / len(yields), 2) if yields else 0.0,
            "min_yield_t_ha": min(yields) if yields else 0.0,
            "max_yield_t_ha": max(yields) if yields else 0.0,
            "latest_yield_t_ha": yields[-1] if yields else 0.0,
            "latest_year": rows[-1]["year"] if rows else None,
            "years_recorded": len(yields),
        }
    return stats


@lru_cache(maxsize=1)
def load_zone_seasonal_profiles() -> dict[tuple[str, str, str], dict[str, Any]]:
    if not ML_CSV_PATH.is_file():
        return {}
    aggregated: dict[tuple[str, str, str], list[dict[str, float]]] = {}
    with open(ML_CSV_PATH, mode="r", encoding="utf-8") as f:
        reader = csv.DictReader(f)
        for row in reader:
            zone = (row.get("zone") or "").strip().lower()
            crop = (row.get("crop") or "").strip().lower()
            season = (row.get("season") or "").strip().lower()
            if not zone or not crop or not season:
                continue
            key = (zone, crop, season)
            if key not in aggregated:
                aggregated[key] = []
            try:
                aggregated[key].append({
                    "temp": float(row.get("t2m_mean") or 0),
                    "precip": float(row.get("precip_mm") or 0),
                    "et0": float(row.get("et0_mm_month") or 0),
                    "ndvi": float(row.get("ndvi_crop") or 0) if row.get("ndvi_crop") else 0.0,
                    "soil_ph": float(row.get("soil_ph") or 0) if row.get("soil_ph") else 7.8,
                })
            except ValueError:
                continue

    profiles: dict[tuple[str, str, str], dict[str, Any]] = {}
    for (zone, crop, season), rows in aggregated.items():
        if not rows:
            continue
        n = len(rows)
        profiles[(zone, crop, season)] = {
            "zone": zone,
            "crop": crop,
            "season": season,
            "mean_temp_c": round(sum(r["temp"] for r in rows) / n, 1),
            "mean_precip_mm": round(sum(r["precip"] for r in rows) / n, 1),
            "mean_et0_mm": round(sum(r["et0"] for r in rows) / n, 1),
            "mean_ndvi": round(sum(r["ndvi"] for r in rows) / n, 3),
            "soil_ph": round(sum(r["soil_ph"] for r in rows) / n, 2),
            "records_count": n,
        }
    return profiles


def query_dataset_summary(zone: str | None, crop: str | None, season: str | None) -> dict[str, Any]:
    fao_stats = load_fao_yields()
    profiles = load_zone_seasonal_profiles()

    crop_clean = (crop or "wheat").lower().replace(" ", "_")
    zone_clean = (zone or "monufia").lower()
    season_clean = (season or "winter").lower()

    # Crop alias handling
    aliases = {
        "faba_bean": ["faba_bean", "beans"],
        "beans": ["faba_bean", "beans"],
        "maize": ["maize", "corn"],
        "corn": ["maize", "corn"],
        "berseem_clover": ["berseem_clover", "clover"],
        "clover": ["berseem_clover", "clover"],
    }
    crop_keys = aliases.get(crop_clean, [crop_clean])

    fao_entry = None
    for k in crop_keys:
        if k in fao_stats:
            fao_entry = fao_stats[k]
            break

    profile_entry = None
    for k in crop_keys:
        if (zone_clean, k, season_clean) in profiles:
            profile_entry = profiles[(zone_clean, k, season_clean)]
            break
    if profile_entry is None:
        # Fallback to any zone
        for (z, c, s), p in profiles.items():
            if c in crop_keys and s == season_clean:
                profile_entry = p
                break

    return {
        "zone": zone_clean,
        "crop": crop_clean,
        "season": season_clean,
        "profile": profile_entry,
        "fao_yield": fao_entry,
    }
