from __future__ import annotations

import math
from typing import Any


CROPS: dict[str, dict[str, Any]] = {
    "wheat": {
        "category": "cereal",
        "kc_mid": 1.15,
        "season_days": 150,
        "root_depth_m": 1.2,
        "nitrogen_fixer": False,
        "drought_tolerance": "moderate",
        "rotation_groups": ["cereal"],
        "soil_preferences": ["clay", "loam", "silt"],
        "water_range_mm": [450, 650],
    },
    "corn": {
        "category": "cereal",
        "kc_mid": 1.20,
        "season_days": 125,
        "root_depth_m": 1.5,
        "nitrogen_fixer": False,
        "drought_tolerance": "low",
        "rotation_groups": ["cereal"],
        "soil_preferences": ["loam", "clay", "silt"],
        "water_range_mm": [500, 800],
    },
    "rice": {
        "category": "cereal",
        "kc_mid": 1.20,
        "season_days": 135,
        "root_depth_m": 0.6,
        "nitrogen_fixer": False,
        "drought_tolerance": "low",
        "rotation_groups": ["cereal"],
        "soil_preferences": ["clay"],
        "water_range_mm": [900, 1500],
    },
    "beans": {
        "category": "legume",
        "kc_mid": 1.05,
        "season_days": 100,
        "root_depth_m": 0.8,
        "nitrogen_fixer": True,
        "drought_tolerance": "moderate",
        "rotation_groups": ["legume"],
        "soil_preferences": ["loam", "clay", "silt"],
        "water_range_mm": [300, 500],
    },
    "soybeans": {
        "category": "legume",
        "kc_mid": 1.15,
        "season_days": 120,
        "root_depth_m": 1.0,
        "nitrogen_fixer": True,
        "drought_tolerance": "moderate",
        "rotation_groups": ["legume"],
        "soil_preferences": ["loam", "clay"],
        "water_range_mm": [450, 700],
    },
    "alfalfa": {
        "category": "forage_legume",
        "kc_mid": 1.05,
        "season_days": 180,
        "root_depth_m": 2.0,
        "nitrogen_fixer": True,
        "drought_tolerance": "moderate",
        "rotation_groups": ["legume", "forage"],
        "soil_preferences": ["loam", "silt"],
        "water_range_mm": [800, 1600],
    },
    "cotton": {
        "category": "fiber",
        "kc_mid": 1.15,
        "season_days": 160,
        "root_depth_m": 1.5,
        "nitrogen_fixer": False,
        "drought_tolerance": "moderate",
        "rotation_groups": ["fiber"],
        "soil_preferences": ["clay", "loam", "silt"],
        "water_range_mm": [700, 1300],
    },
    "sorghum": {
        "category": "cereal",
        "kc_mid": 1.00,
        "season_days": 110,
        "root_depth_m": 1.5,
        "nitrogen_fixer": False,
        "drought_tolerance": "high",
        "rotation_groups": ["cereal"],
        "soil_preferences": ["loam", "clay", "silt"],
        "water_range_mm": [350, 600],
    },
    "barley": {
        "category": "cereal",
        "kc_mid": 1.15,
        "season_days": 130,
        "root_depth_m": 1.2,
        "nitrogen_fixer": False,
        "drought_tolerance": "moderate",
        "rotation_groups": ["cereal"],
        "soil_preferences": ["clay", "loam", "silt"],
        "water_range_mm": [350, 550],
    },
    "sunflower": {
        "category": "oilseed",
        "kc_mid": 1.00,
        "season_days": 120,
        "root_depth_m": 1.5,
        "nitrogen_fixer": False,
        "drought_tolerance": "moderate",
        "rotation_groups": ["oilseed"],
        "soil_preferences": ["loam", "clay"],
        "water_range_mm": [400, 650],
    },
    "clover": {
        "category": "forage_legume",
        "kc_mid": 1.05,
        "season_days": 120,
        "root_depth_m": 0.8,
        "nitrogen_fixer": True,
        "drought_tolerance": "low",
        "rotation_groups": ["legume", "forage"],
        "soil_preferences": ["clay", "loam"],
        "water_range_mm": [400, 700],
    },
    "berseem_clover": {
        "category": "forage_legume",
        "kc_mid": 1.05,
        "season_days": 120,
        "root_depth_m": 0.8,
        "nitrogen_fixer": True,
        "drought_tolerance": "low",
        "rotation_groups": ["legume", "forage"],
        "soil_preferences": ["clay", "loam"],
        "water_range_mm": [400, 700],
    },
}

PARAMETER_BASIS = (
    "Illustrative generic crop coefficients and season lengths; verify cultivars, "
    "planting dates, and local recommendations. ETc method follows FAO-56 "
    "(Kc × reference ET0); listed water ranges are broad screening ranges, "
    "not farm observations or guaranteed local values."
)


def _crop_name(value: Any) -> str:
    return str(value or "").strip().lower().replace(" ", "_").replace("-", "_")


def crop_information(crop: str) -> dict[str, Any] | None:
    name = _crop_name(crop)
    if name not in CROPS:
        return None
    return {"name": name, **CROPS[name], "parameter_basis": PARAMETER_BASIS}


def get_farm_context(summary: dict[str, Any]) -> dict[str, Any]:
    location = summary.get("location") or {}
    soil = summary.get("soil") or {}
    crops = summary.get("crops") or []
    return {
        "farm_id": summary.get("farm_id"),
        "location": location,
        "farm_area_hectares": summary.get("farm_area_hectares"),
        "soil": soil,
        "current_crop": summary.get("current_crop"),
        "crop_history": summary.get("crop_history") or crops,
        "planting_date": summary.get("planting_date"),
        "analysis_period": summary.get("analysis_period") or {},
        "irrigation": summary.get("irrigation"),
        "priorities": summary.get("priorities") or [],
    }


def missing_farm_fields(summary: dict[str, Any]) -> list[str]:
    missing: list[str] = []
    location = summary.get("location") or {}
    if location.get("latitude") is None or location.get("longitude") is None:
        missing.append("location.coordinates")
    if not summary.get("soil"):
        missing.append("soil")
    if not summary.get("current_crop"):
        missing.append("current_crop")
    if not summary.get("farm_area_hectares"):
        missing.append("farm_area_hectares")
    if not summary.get("planting_date"):
        missing.append("planting_date")
    return missing


def evaluate_crop_suitability(crop: str, soil: dict[str, Any]) -> dict[str, Any]:
    name = _crop_name(crop)
    profile = CROPS.get(name)
    if profile is None:
        return {
            "crop": name,
            "status": "insufficient_information",
            "score": None,
            "reason": "Crop is not in the current editable crop catalog.",
        }
    soil_type = _crop_name(soil.get("type") or soil.get("texture"))
    if not soil_type:
        return {
            "crop": name,
            "status": "insufficient_information",
            "score": None,
            "reason": "Soil type or texture is needed for this screening.",
        }
    suitable = soil_type in profile["soil_preferences"]
    return {
        "crop": name,
        "status": "screening_suitable" if suitable else "screening_concern",
        "score": 1.0 if suitable else 0.4,
        "soil_type": soil_type,
        "basis": "Generic soil preference screening; not a local cultivar or field assessment.",
    }


def generate_rotation_strategies(
    summary: dict[str, Any], weather: dict[str, Any] | None = None
) -> list[dict[str, Any]]:
    current = _crop_name(summary.get("current_crop") or "wheat")
    if current not in CROPS:
        current = "wheat"
    recent = [
        _crop_name(crop.get("name") if isinstance(crop, dict) else crop)
        for crop in (summary.get("crop_history") or [])
    ]
    soil = summary.get("soil") or {}
    candidates = [
        crop
        for crop in CROPS
        if crop != current and crop not in recent[-2:]
    ]
    legumes = [crop for crop in candidates if CROPS[crop]["nitrogen_fixer"]]
    low_water = sorted(
        candidates,
        key=lambda crop: CROPS[crop]["water_range_mm"][1],
    )
    legume = legumes[0] if legumes else "clover"
    alternative = next((crop for crop in low_water if crop != legume), "sorghum")
    baseline = [current, current]
    strategies = [
        {
            "strategy_id": "baseline",
            "title": "Existing Rotation — Baseline",
            "sequence": baseline,
            "focus": "Reference the current crop pattern; repeated crop may increase rotation-related risks.",
        },
        {
            "strategy_id": "soil_health",
            "title": "Soil Health Optimization",
            "sequence": [current, legume],
            "focus": "Add a legume phase for crop diversity and potential biological nitrogen fixation.",
        },
        {
            "strategy_id": "water_resilience",
            "title": "Water Efficiency and Climate Resilience",
            "sequence": [current, alternative],
            "focus": "Screen a crop with a lower broad water-demand range or higher drought tolerance.",
        },
    ]
    for strategy in strategies:
        strategy["crops"] = [
            {
                "crop": crop,
                "water_requirement_range_mm": CROPS[crop]["water_range_mm"],
                "season_days": CROPS[crop]["season_days"],
                "planting_date": summary.get("planting_date"),
            }
            for crop in strategy["sequence"]
        ]
        strategy["soil_screening"] = [
            evaluate_crop_suitability(crop, soil) for crop in strategy["sequence"]
        ]
        strategy["evidence"] = [PARAMETER_BASIS]
        strategy["assumptions"] = [
            "Generic crop parameters are used; local cultivar, planting window, and field measurements were not supplied."
        ]
        strategy["risks"] = [
            "Crop rotation suitability depends on local pest history, market access, seed availability, water rights, and planting calendar."
        ]
        strategy["confidence"] = "low_to_moderate_screening"
        strategy["missing_information"] = missing_farm_fields(summary)
    return strategies


def _as_float(value: Any, label: str) -> float | None:
    if value is None:
        return None
    if isinstance(value, bool):
        raise ValueError(f"{label} must be a number")
    try:
        result = float(value)
    except (TypeError, ValueError) as error:
        raise ValueError(f"{label} must be a number") from error
    if not math.isfinite(result):
        raise ValueError(f"{label} must be finite")
    if result < 0:
        raise ValueError(f"{label} cannot be negative")
    return result


def _simulate_plot_strategy(
    summary: dict[str, Any],
    strategy: dict[str, Any],
    weather: dict[str, Any],
    scenario: dict[str, float],
) -> dict[str, Any]:
    plot_inputs = strategy.get("plots")
    if not isinstance(plot_inputs, list) or not plot_inputs:
        raise ValueError("strategy.plots must be a non-empty list")

    plot_results: list[dict[str, Any]] = []
    assumptions: list[str] = []
    total_area = 0.0
    total_demand_m3 = 0.0
    total_deficit_m3 = 0.0
    has_demand = True
    has_deficit = True
    template: dict[str, Any] | None = None

    for plot in plot_inputs:
        if not isinstance(plot, dict):
            raise ValueError("Each strategy plot must be an object")
        crop = _crop_name(plot.get("crop"))
        area = _as_float(plot.get("area_hectares"), "plot.area_hectares")
        if not crop or area is None or area <= 0:
            raise ValueError("Each strategy plot needs a crop and a positive area_hectares")

        plot_summary = {**summary, "current_crop": crop, "farm_area_hectares": area}
        plot_strategy: dict[str, Any] = {"sequence": [crop]}
        if strategy.get("duration_days") is not None:
            plot_strategy["duration_days"] = strategy["duration_days"]
        result = simulate_rotation(plot_summary, plot_strategy, weather, scenario)
        template = result
        crop_result = {
            **result["sequence"][0],
            "plot_id": plot.get("plot_id"),
            "plot_name": plot.get("name"),
            "area_hectares": area,
        }
        demand_mm = crop_result["estimated_net_irrigation_demand_mm"]
        deficit_mm = result["estimated_water_deficit_mm"]
        crop_result["estimated_net_irrigation_demand_m3"] = (
            round(demand_mm * area * 10, 1) if demand_mm is not None else None
        )
        crop_result["estimated_water_deficit_m3"] = (
            round(deficit_mm * area * 10, 1) if deficit_mm is not None else None
        )

        if crop_result["estimated_net_irrigation_demand_m3"] is None:
            has_demand = False
        else:
            total_demand_m3 += crop_result["estimated_net_irrigation_demand_m3"]
        if crop_result["estimated_water_deficit_m3"] is None:
            has_deficit = False
        else:
            total_deficit_m3 += crop_result["estimated_water_deficit_m3"]
        total_area += area
        assumptions.extend(result["assumptions"])
        plot_results.append(crop_result)

    assert template is not None
    return {
        "scenario": template["scenario"],
        "farm_area_hectares": round(total_area, 4),
        "sequence": plot_results,
        "estimated_net_irrigation_demand_mm": (
            round(total_demand_m3 / (total_area * 10), 1)
            if has_demand and total_area > 0
            else None
        ),
        "estimated_net_irrigation_demand_m3": round(total_demand_m3, 1) if has_demand else None,
        "adjusted_available_irrigation_mm": template["adjusted_available_irrigation_mm"],
        "estimated_water_deficit_mm": (
            round(total_deficit_m3 / (total_area * 10), 1)
            if has_deficit and total_area > 0
            else None
        ),
        "estimated_water_deficit_m3": round(total_deficit_m3, 1) if has_deficit else None,
        "relative_water_stress_indicator": template["relative_water_stress_indicator"],
        "water_availability_factor": template["water_availability_factor"],
        "calculation_method": template["calculation_method"],
        "assumptions": list(dict.fromkeys(assumptions)),
        "limitations": template["limitations"],
    }


def simulate_rotation(
    summary: dict[str, Any],
    strategy: dict[str, Any] | None = None,
    weather: dict[str, Any] | None = None,
    scenario: dict[str, float] | None = None,
) -> dict[str, Any]:
    weather = weather or {}
    scenario = scenario or {}
    rainfall_change = float(scenario.get("rainfall_change_pct", 0))
    temperature_change = float(scenario.get("temperature_change_c", 0))
    water_change = float(scenario.get("water_availability_change_pct", 0))
    if not -40 <= rainfall_change <= 40:
        raise ValueError("rainfall_change_pct must be between -40 and 40")
    if not -4 <= temperature_change <= 6:
        raise ValueError("temperature_change_c must be between -4 and 6")
    if not -50 <= water_change <= 30:
        raise ValueError("water_availability_change_pct must be between -50 and 30")
    seasonal_et0 = _as_float(weather.get("seasonal_et0_mm"), "seasonal_et0_mm")
    daily_et0 = _as_float(weather.get("daily_et0_mm"), "daily_et0_mm")
    rainfall = _as_float(weather.get("seasonal_precipitation_mm"), "seasonal_precipitation_mm")
    if seasonal_et0 is not None:
        et0_basis = "Seasonal reference ET0 supplied in request; verify source and crop-season alignment."
    elif daily_et0 is not None:
        et0_basis = "Daily reference ET0 supplied in request and multiplied by each crop's generic season length."
    else:
        et0_basis = "Illustrative 5 mm/day reference ET0 assumption multiplied by each generic crop season; replace with local observed ET0."
    if rainfall is not None:
        rainfall *= 1 + rainfall_change / 100
        effective_rainfall = rainfall * 0.8
        rainfall_basis = "Effective rainfall estimated as 80% of supplied/scenario-adjusted seasonal precipitation."
    else:
        effective_rainfall = None
        rainfall_basis = "No precipitation observation supplied; effective rainfall and net irrigation demand are unavailable."
    strategy = strategy or {}
    if "plots" in strategy:
        return _simulate_plot_strategy(summary, strategy, weather, scenario)
    crops = strategy.get("sequence") or [summary.get("current_crop") or "wheat"]
    if not isinstance(crops, list) or not crops:
        raise ValueError("strategy.sequence must be a non-empty list of crop names")
    crop_results: list[dict[str, Any]] = []
    for crop in crops:
        name = _crop_name(crop)
        profile = CROPS.get(name)
        if profile is None:
            raise ValueError(f"Unknown crop in strategy: {name}")
        duration_value = strategy.get("duration_days", profile["season_days"])
        duration = int(duration_value)
        if duration < 1 or duration > 365:
            raise ValueError("strategy.duration_days must be between 1 and 365")
        kc = float(profile["kc_mid"])
        reference_et0 = (
            seasonal_et0 if seasonal_et0 is not None else (daily_et0 or 5.0) * duration
        )
        adjusted_et0 = reference_et0 * (1 + 0.02 * temperature_change)
        etc = kc * adjusted_et0
        effective = effective_rainfall
        irrigation = max(0.0, etc - effective) if effective is not None else None
        crop_results.append(
            {
                "crop": name,
                "season_days": duration,
                "kc_mid": kc,
                "seasonal_etc_mm": round(etc, 1),
                "effective_rainfall_mm": round(effective, 1) if effective is not None else None,
                "estimated_net_irrigation_demand_mm": round(irrigation, 1) if irrigation is not None else None,
                "generic_water_range_mm": profile["water_range_mm"],
                "nitrogen_fixing_potential": profile["nitrogen_fixer"],
                "drought_tolerance": profile["drought_tolerance"],
            }
        )
    area = _as_float(summary.get("farm_area_hectares"), "farm_area_hectares")
    total_water_m3 = (
        round(sum(item["estimated_net_irrigation_demand_mm"] or 0 for item in crop_results) * area * 10, 1)
        if area is not None and effective_rainfall is not None
        else None
    )
    total_irrigation_mm = (
        sum(item["estimated_net_irrigation_demand_mm"] or 0 for item in crop_results)
        if effective_rainfall is not None
        else None
    )
    available_irrigation = _as_float(
        weather.get("seasonal_irrigation_available_mm"),
        "seasonal_irrigation_available_mm",
    )
    if available_irrigation is not None:
        adjusted_available = available_irrigation * (1 + water_change / 100)
        water_deficit = (
            max(0.0, total_irrigation_mm - adjusted_available)
            if total_irrigation_mm is not None
            else None
        )
        water_stress_indicator = None
    else:
        adjusted_available = None
        water_deficit = None
        water_stress_indicator = max(0.0, -water_change / 100)
    if water_change < 0:
        for item in crop_results:
            sensitivity = {"high": "high", "moderate": "moderate", "low": "very_high"}[
                item["drought_tolerance"]
            ]
            item["water_supply_scenario_risk"] = sensitivity
    else:
        for item in crop_results:
            item["water_supply_scenario_risk"] = "not_assessed"
    return {
        "scenario": {
            "rainfall_change_pct": rainfall_change,
            "temperature_change_c": temperature_change,
            "water_availability_change_pct": water_change,
        },
        "sequence": crop_results,
        "estimated_net_irrigation_demand_mm": (
            round(sum(item["estimated_net_irrigation_demand_mm"] or 0 for item in crop_results), 1)
            if effective_rainfall is not None
            else None
        ),
        "estimated_net_irrigation_demand_m3": total_water_m3,
        "adjusted_available_irrigation_mm": (
            round(adjusted_available, 1) if adjusted_available is not None else None
        ),
        "estimated_water_deficit_mm": round(water_deficit, 1) if water_deficit is not None else None,
        "relative_water_stress_indicator": water_stress_indicator,
        "water_availability_factor": round(1 + water_change / 100, 2),
        "calculation_method": "ETc = Kc × ET0; temperature sensitivity uses an explicit 2% per °C scenario assumption. Effective rainfall = 0.8 × seasonal precipitation. Supplied irrigation availability is scenario-adjusted before calculating a deficit.",
        "assumptions": [et0_basis, rainfall_basis, PARAMETER_BASIS],
        "limitations": [
            "This is a screening simulation, not a calibrated field water-balance model; it excludes soil storage, runoff, conveyance losses, irrigation efficiency, and crop-stage Kc variation.",
            "No plant stress or yield prediction is inferred from this result.",
        ],
    }


def compare_scenarios(
    summary: dict[str, Any],
    strategies: list[dict[str, Any]] | None = None,
    weather: dict[str, Any] | None = None,
) -> list[dict[str, Any]]:
    strategies = strategies or generate_rotation_strategies(summary, weather)
    return [
        {
            "strategy_id": strategy.get("strategy_id", f"strategy_{index}"),
            "title": strategy.get("title", "Custom strategy"),
            "simulation": simulate_rotation(summary, strategy, weather),
        }
        for index, strategy in enumerate(strategies)
    ]


def analyze_farm(summary: dict[str, Any]) -> dict[str, Any]:
    crop = summary.get("current_crop")
    return {
        "farm_context": get_farm_context(summary),
        "missing_fields": missing_farm_fields(summary),
        "crop_screening": evaluate_crop_suitability(crop, summary.get("soil") or {}) if crop else None,
        "rotation_strategies": generate_rotation_strategies(summary),
        "data_limitations": [
            "No field soil test, irrigation measurements, or verified local crop calendar was provided."
        ],
    }


def analyze_observations(observations: dict[str, Any]) -> dict[str, Any]:
    parameters = observations.get("observations", {}).get("parameters", {})
    summary: dict[str, Any] = {}
    for parameter, values in parameters.items():
        valid = [float(value) for value in values.values() if isinstance(value, (int, float))]
        if valid:
            summary[parameter] = {
                "count": len(valid),
                "mean": round(sum(valid) / len(valid), 3),
                "minimum": min(valid),
                "maximum": max(valid),
            }
    return {
        "statistics": summary,
        "source": observations.get("provenance", {}),
        "interpretation_limits": [
            "Daily point-grid products are not field measurements; use source resolution and quality metadata.",
            "These descriptive statistics do not constitute a forecast or crop diagnosis.",
        ],
    }
