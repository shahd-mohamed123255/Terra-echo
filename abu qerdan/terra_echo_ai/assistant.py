from __future__ import annotations

import re
from typing import Any

from terra_echo_ai.agriculture import (
    CROPS,
    analyze_observations,
    crop_information,
    evaluate_crop_suitability,
    generate_rotation_strategies,
    get_farm_context,
    missing_farm_fields,
    simulate_rotation,
)
from terra_echo_ai.datasets import query_dataset_summary
from terra_echo_ai.environment import DataSourceUnavailable, retrieve_environment
from terra_echo_ai.llm import configured, generate_reply
from terra_echo_ai.memory import recent_messages
from terra_echo_ai.research import search_external


KEYWORDS = {
    "rotation": ("rotation", "crop after", "plant after", "زرع بعد", "دورة", "محصول بعد"),
    "water": ("water", "irrigation", "irrigate", "rain", "precipitation", "مياه", "ري", "مطر", "أمطار"),
    "climate": ("climate", "temperature", "rainfall", "weather", "heat", "drought", "حرارة", "مناخ", "جفاف"),
    "environment": ("satellite", "ndvi", "soil moisture", "latest", "current observations", "حالي", "الأقمار", "رطوبة التربة"),
    "crop": ("crop", "plant", "wheat", "corn", "rice", "bean", "soil", "محصول", "أزرع", "قمح", "ذرة", "أرض"),
    "search": ("latest research", "source", "citation", "recent study", "ابحث", "مصدر", "دراسة حديثة", "آخر تحديث"),
    "dataset": ("dataset", "historical", "yield", "fao", "stat", "إنتاجية", "إحصاءات", "بيانات تاريخية", "متوسط المحصول"),
}
SIMULATION_KEYWORDS = (
    "simulation",
    "latest run",
    "water demand",
    "water deficit",
    "water balance",
    "water requirement",
    "crop suitability",
    "assumption",
    "scenario",
    "محاكاة",
    "العجز المائي",
    "احتياجات المياه",
    "نتائج المحاكاة",
    "افتراضات المحاكاة",
)


def _contains(text: str, phrases: tuple[str, ...]) -> bool:
    lowered = text.lower()
    return any(phrase in lowered for phrase in phrases)


def select_tools(question: str, summary: dict[str, Any]) -> list[str]:
    tools: list[str] = []
    if _contains(question, KEYWORDS["rotation"]):
        tools.append("generate_rotation_strategies")
    if _contains(question, KEYWORDS["water"]):
        tools.append("calculate_water_balance")
    if _contains(question, KEYWORDS["climate"]) or _contains(question, KEYWORDS["environment"]):
        if summary.get("location") and summary.get("analysis_period"):
            tools.append("retrieve_environmental_data")
        else:
            tools.append("identify_missing_environment_context")
    if _contains(question, KEYWORDS["crop"]):
        tools.append("evaluate_crop_suitability")
        if summary.get("current_crop"):
            tools.append("get_crop_information")
    if _contains(question, KEYWORDS["dataset"]):
        tools.append("query_project_datasets")
    if _contains(question, KEYWORDS["search"]):
        tools.append("search_external_sources")
    if not tools:
        tools.append("retrieve_farm_context")
    return list(dict.fromkeys(tools))


def _scenario_from_question(question: str) -> dict[str, float]:
    match = re.search(r"(\d+(?:\.\d+)?)\s*%", question)
    if match is None:
        return {}
    amount = float(match.group(1))
    lowered = question.lower()
    if any(word in lowered for word in ("rain", "precipitation", "rainfall", "مطر", "أمطار")):
        decreasing = any(word in lowered for word in ("decrease", "less", "drop", "انخفاض", "يقل"))
        return {"rainfall_change_pct": -amount if decreasing else amount}
    if any(word in lowered for word in ("water availability", "available water", "المياه المتاحة")):
        decreasing = any(word in lowered for word in ("decrease", "less", "drop", "انخفاض", "يقل"))
        return {"water_availability_change_pct": -amount if decreasing else amount}
    return {}


def _display_value(value: Any, unit: str = "") -> str:
    if value is None:
        return "unavailable (not supplied or not calculated)"
    if isinstance(value, float):
        return f"{value:.1f}{unit}"
    return f"{value}{unit}"


def _simulation_explanation(
    question: str,
    summary: dict[str, Any],
    tools: list[str],
) -> dict[str, Any] | None:
    latest = summary.get("latest_simulation")
    if not isinstance(latest, dict):
        return None

    input_parameters = latest.get("input_parameters") or {}
    simulation_results = latest.get("simulation_results") or {}
    if not isinstance(input_parameters, dict) or not isinstance(simulation_results, dict):
        return None

    recalculation = _scenario_from_question(question)
    what_if_requested = bool(recalculation)
    if recalculation:
        input_summary = input_parameters.get("summary") or {}
        input_strategy = input_parameters.get("strategy") or {}
        input_weather = input_parameters.get("weather") or {}
        if not input_summary or not input_strategy:
            recalculation = {}
        else:
            input_scenario = {
                **(input_parameters.get("scenario") or {}),
                **recalculation,
            }
            simulation_results = simulate_rotation(
                input_summary,
                input_strategy,
                input_weather,
                input_scenario,
            )
            tools = [*tools, "calculate_water_balance"]

    lines = [
        f"Saved completed simulation #{latest.get('simulation_id')} for farm #{latest.get('farm_id')} "
        f"(plan #{latest.get('selected_plan_id')})."
    ]
    plots = simulation_results.get("sequence") or []
    for plot in plots:
        name = plot.get("plot_name") or plot.get("crop") or "Selected crop"
        lines.append(
            f"- {name} ({plot.get('crop')}, {_display_value(plot.get('area_hectares'), ' ha')}): "
            f"ETc {_display_value(plot.get('seasonal_etc_mm'), ' mm')}; "
            f"net irrigation {_display_value(plot.get('estimated_net_irrigation_demand_mm'), ' mm')} "
            f"({_display_value(plot.get('estimated_net_irrigation_demand_m3'), ' m³')}); "
            f"suitability {((plot.get('suitability') or {}).get('status') or 'not assessed')}."
        )
    lines.append(
        "Farm water demand: "
        f"{_display_value(simulation_results.get('estimated_net_irrigation_demand_mm'), ' mm')} "
        f"({_display_value(simulation_results.get('estimated_net_irrigation_demand_m3'), ' m³')})."
    )
    lines.append(
        "Available irrigation: "
        f"{_display_value(simulation_results.get('adjusted_available_irrigation_mm'), ' mm')}; "
        f"estimated deficit: "
        f"{_display_value(simulation_results.get('estimated_water_deficit_mm'), ' mm')} "
        f"({_display_value(simulation_results.get('estimated_water_deficit_m3'), ' m³')})."
    )
    if recalculation:
        adjustments = ", ".join(f"{key}={value:g}" for key, value in recalculation.items())
        lines.append(f"Requested what-if scenario recalculated from saved inputs: {adjustments}.")
    elif what_if_requested:
        lines.append("The requested what-if could not be recalculated because the saved run has no complete input snapshot.")
    method = simulation_results.get("calculation_method")
    if method:
        lines.append(f"Method: {method}")
    for label, key in (
        ("Assumptions", "assumptions"),
        ("Limitations", "limitations"),
        ("Missing information", "missing_information"),
    ):
        values = simulation_results.get(key) or []
        if values:
            lines.append(f"{label}: {'; '.join(str(value) for value in values)}")
    sources = simulation_results.get("data_sources") or []
    if sources:
        lines.append(f"Data sources: {'; '.join(str(value) for value in sources)}")

    is_arabic = any("\u0600" <= character <= "\u06ff" for character in question)
    if is_arabic:
        lines = [
            "اعتمادًا على نتائج المحاكاة المحفوظة، وليس على تقدير عام:",
            *lines[1:],
        ]
    result_tools = list(dict.fromkeys([*tools, "retrieve_latest_simulation_results"]))

    return {
        "reply": "\n".join(lines),
        "tools_used": result_tools,
        "tool_results": {
            "latest_simulation": latest,
            **({"what_if_simulation": simulation_results} if recalculation else {}),
        },
        "collected_data": get_farm_context(summary),
        "missing_fields": missing_farm_fields(summary),
        "assistant_mode": "deterministic_simulation_explanation",
        "next_question": None,
    }


def _fallback_reply(
    question: str, summary: dict[str, Any], tools: list[str], results: dict[str, Any]
) -> str:
    missing = missing_farm_fields(summary)
    is_arabic = any("\u0600" <= character <= "\u06ff" for character in question)
    parts: list[str] = []
    if is_arabic:
        parts.append("أنا أبو قردان، المساعد الزراعي الذكي (تحليل بيانات المشروع المحلية):")
        if "rotation_strategies" in results:
            strategies = results["rotation_strategies"]
            seqs = [f"{s['title']}: ({' ← '.join(s['sequence'])})" for s in strategies[:2]]
            parts.append("• استراتيجيات الدورة الزراعية المقترحة: " + " | ".join(seqs))
        if "water_balance" in results:
            wb = results["water_balance"]
            demand = wb.get("estimated_net_irrigation_demand_mm")
            m3 = wb.get("estimated_net_irrigation_demand_m3")
            if demand is not None:
                parts.append(f"• تقدير صافي ري المحصول: {demand} مم ({m3 or 0} م³ للمزرعة).")
        if "crop_suitability" in results:
            cs = results["crop_suitability"]
            parts.append(f"• فحص ملاءمة التربة للمحصول: {cs.get('status')} (نوع التربة: {cs.get('soil_type')}).")
        if "crop_information" in results and results["crop_information"]:
            ci = results["crop_information"]
            parts.append(f"• بيانات المحصول ({ci.get('name')}): موسم النمو {ci.get('season_days')} يومًا، نطاق استهلاك المياه {ci.get('water_range_mm')} مم.")
        if "dataset_insights" in results and results["dataset_insights"]:
            ds = results["dataset_insights"]
            fao = ds.get("fao_yield") or {}
            prof = ds.get("profile") or {}
            y_mean = fao.get("mean_yield_t_ha")
            temp = prof.get("mean_temp_c")
            et0 = prof.get("mean_et0_mm")
            det = []
            if y_mean:
                det.append(f"متوسط إنتاجية مصر القومية: {y_mean} طن/هكتار (FAOSTAT)")
            if temp:
                det.append(f"متوسط حرارة المنطقة تاريخيًا: {temp} °م")
            if et0:
                det.append(f"البخر-نتح: {et0} مم/موسم")
            if det:
                parts.append("• مؤشرات السجلات التاريخية: " + "، ".join(det) + ".")
        if missing:
            parts.append("البيانات الناقصة لتحليل أدق: " + "، ".join(missing) + ".")
        parts.append("ملاحظة: هذا التحليل استرشادي حتمي مبني على بيانات وحسابات المشروع الموثقة دون استدعاء نموذج لغوي خارجي.")
    else:
        parts.append("I’m Abu Qerdan, your agricultural intelligence assistant (local deterministic data analysis):")
        if "rotation_strategies" in results:
            strategies = results["rotation_strategies"]
            seqs = [f"{s['title']}: ({' → '.join(s['sequence'])})" for s in strategies[:2]]
            parts.append("• Recommended crop rotation sequences: " + " | ".join(seqs))
        if "water_balance" in results:
            wb = results["water_balance"]
            demand = wb.get("estimated_net_irrigation_demand_mm")
            m3 = wb.get("estimated_net_irrigation_demand_m3")
            if demand is not None:
                parts.append(f"• Estimated net crop irrigation demand: {demand} mm ({m3 or 0} m³ total for farm).")
        if "crop_suitability" in results:
            cs = results["crop_suitability"]
            parts.append(f"• Crop soil suitability screening: {cs.get('status')} for soil type '{cs.get('soil_type')}'.")
        if "crop_information" in results and results["crop_information"]:
            ci = results["crop_information"]
            parts.append(f"• Crop catalog profile ({ci.get('name')}): duration {ci.get('season_days')} days, generic water range {ci.get('water_range_mm')} mm.")
        if "dataset_insights" in results and results["dataset_insights"]:
            ds = results["dataset_insights"]
            fao = ds.get("fao_yield") or {}
            prof = ds.get("profile") or {}
            y_mean = fao.get("mean_yield_t_ha")
            temp = prof.get("mean_temp_c")
            et0 = prof.get("mean_et0_mm")
            det = []
            if y_mean:
                det.append(f"FAOSTAT Egyptian national mean yield: {y_mean} t/ha")
            if temp:
                det.append(f"historical regional seasonal temp: {temp} °C")
            if et0:
                det.append(f"reference ET0: {et0} mm")
            if det:
                parts.append("• Verified Project Dataset Benchmarks: " + ", ".join(det) + ".")
        if missing:
            parts.append("Additional farm fields that would refine this screening: " + ", ".join(missing) + ".")
        parts.append("Note: This response is generated by the local deterministic rules layer using verified project data. No external LLM is configured.")
    if "retrieve_environmental_data" in tools and "environment" not in results:
        parts.append("Environmental retrieval did not complete; no live observation is being claimed.")
    return "\n".join(parts)


def answer_question(
    conversation_id: str,
    question: str,
    summary: dict[str, Any],
    weather: dict[str, Any] | None = None,
) -> dict[str, Any]:
    tools = select_tools(question, summary)
    if summary.get("latest_simulation") and (
        _contains(question, SIMULATION_KEYWORDS) or _contains(question, KEYWORDS["water"])
    ):
        explanation = _simulation_explanation(question, summary, tools)
        if explanation is not None:
            return explanation

    results: dict[str, Any] = {}
    if "generate_rotation_strategies" in tools:
        results["rotation_strategies"] = generate_rotation_strategies(summary, weather)
    if "calculate_water_balance" in tools:
        scenario = _scenario_from_question(question)
        strategy = {"sequence": [summary.get("current_crop") or "wheat"]}
        results["water_balance"] = simulate_rotation(summary, strategy, weather, scenario)
    if "evaluate_crop_suitability" in tools:
        crop = summary.get("current_crop")
        if crop:
            results["crop_suitability"] = evaluate_crop_suitability(crop, summary.get("soil") or {})
        else:
            results["crop_suitability"] = {
                "status": "incomplete",
                "missing_fields": ["current_crop", "soil"],
            }
    if "get_crop_information" in tools and summary.get("current_crop"):
        results["crop_information"] = crop_information(summary["current_crop"])
    if "retrieve_environmental_data" in tools:
        try:
            retrieved = retrieve_environment(summary)
            results["environment"] = analyze_observations(retrieved)
        except DataSourceUnavailable as error:
            results["environment_error"] = {
                "source": "NASA POWER",
                "message": str(error),
            }
    if "identify_missing_environment_context" in tools:
        results["environment_context_missing"] = [
            field
            for field in ("location.coordinates", "analysis_period")
            if not summary.get("location") or not summary.get("analysis_period")
        ]
    if "search_external_sources" in tools:
        results["external_research"] = search_external(question)
    if "query_project_datasets" in tools:
        loc = summary.get("location") or {}
        zone = loc.get("governorate") or loc.get("city")
        crop = summary.get("current_crop") or "wheat"
        season = (summary.get("planning_context") or {}).get("target_season") or "winter"
        results["dataset_insights"] = query_dataset_summary(zone, crop, season)
    if "retrieve_farm_context" in tools:
        results["farm_context"] = get_farm_context(summary)
    history = recent_messages(conversation_id)
    if history and history[-1] == {"role": "user", "content": question}:
        history = history[:-1]
    reply = (
        generate_reply(question, get_farm_context(summary), results, history)
        if configured()
        else _fallback_reply(question, summary, tools, results)
    )
    return {
        "reply": reply,
        "tools_used": tools,
        "tool_results": results,
        "collected_data": get_farm_context(summary),
        "missing_fields": missing_farm_fields(summary),
        "assistant_mode": "llm" if configured() else "deterministic_fallback",
        "next_question": None,
    }


def search_internal_knowledge(query: str, limit: int = 5) -> dict[str, Any]:
    terms = set(re.findall(r"[\w\u0600-\u06ff]+", query.lower()))
    results = []
    for name, profile in CROPS.items():
        text = f"{name} {profile['category']} {' '.join(profile['rotation_groups'])} {' '.join(profile['soil_preferences'])}"
        score = len(terms.intersection(text.split()))
        if score:
            results.append(
                {
                    "title": f"Crop profile: {name}",
                    "content": crop_information(name),
                    "source": "Internal editable crop catalog",
                    "score": score,
                }
            )
    return {"results": sorted(results, key=lambda item: item["score"], reverse=True)[:limit]}
