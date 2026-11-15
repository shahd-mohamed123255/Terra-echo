from __future__ import annotations

import json
import os
import sys
from pathlib import Path
from typing import Any

# Ensure workspace root is in sys.path
root_dir = Path(__file__).resolve().parent
if str(root_dir) not in sys.path:
    sys.path.insert(0, str(root_dir))

from terra_echo_ai.agriculture import (
    analyze_farm,
    compare_scenarios,
    evaluate_crop_suitability,
    generate_rotation_strategies,
    missing_farm_fields,
    simulate_rotation,
)
from terra_echo_ai.assistant import answer_question, search_internal_knowledge
from terra_echo_ai.database import initialize_database
from terra_echo_ai.memory import (
    add_message,
    get_conversation,
    start_conversation,
    update_farm_context,
)
from terra_echo_ai.prediction import predict_next


def run_action(action: str, payload: dict[str, Any]) -> dict[str, Any]:
    initialize_database()

    if action == "health":
        return {"status": "ok", "service": "terra-echo-ai-agent", "mode": "in-process"}

    elif action == "start":
        summary = payload.get("summary") or {}
        conversation = start_conversation(summary)
        return {
            **conversation,
            "missing_fields": analyze_farm(summary)["missing_fields"],
        }

    elif action == "chat":
        conversation_id = payload.get("conversation_id")
        message = payload.get("message", "")
        summary = payload.get("summary")
        if conversation_id:
            conv = get_conversation(conversation_id)
            if summary is not None:
                conv = update_farm_context(conversation_id, summary)
            summary = conv.get("summary") or {}
        else:
            summary = summary or {}
            conversation_id = start_conversation(summary)["conversation_id"]

        add_message(conversation_id, "user", message)
        response = answer_question(conversation_id, message, summary)
        add_message(conversation_id, "assistant", response["reply"])
        return {"conversation_id": conversation_id, **response}

    elif action == "history":
        conversation_id = payload.get("conversation_id", "")
        return get_conversation(conversation_id)

    elif action == "plan":
        summary = payload.get("summary") or {}
        weather = payload.get("weather")
        return {"strategies": generate_rotation_strategies(summary, weather)}

    elif action == "analyze":
        summary = payload.get("summary") or {}
        result = analyze_farm(summary)
        result["recommendations"] = generate_rotation_strategies(summary)
        return result

    elif action == "recommend":
        summary = payload.get("summary") or {}
        return {"recommendations": generate_rotation_strategies(summary)}

    elif action == "simulate":
        summary = payload.get("summary") or {}
        strategy = payload.get("strategy") or {}
        weather = payload.get("weather") or {}
        scenario = payload.get("scenario") or {}

        result = simulate_rotation(summary, strategy, weather, scenario)
        crops = strategy.get("plots") or [
            {"crop": crop}
            for crop in strategy.get("sequence", [summary.get("current_crop")])
            if crop
        ]
        suitability = [
            {
                "plot_id": plot.get("plot_id"),
                "crop": plot.get("crop"),
                **evaluate_crop_suitability(
                    str(plot.get("crop") or ""),
                    summary.get("soil") or {},
                ),
            }
            for plot in crops
        ]
        suitability_by_plot = {
            item["plot_id"]: item for item in suitability if item["plot_id"] is not None
        }
        for item in result.get("sequence", []):
            if item.get("plot_id") in suitability_by_plot:
                item["suitability"] = suitability_by_plot[item["plot_id"]]

        missing = missing_farm_fields(summary)
        if weather.get("seasonal_precipitation_mm") is None:
            missing.append("seasonal_precipitation_mm")
        if weather.get("seasonal_irrigation_available_mm") is None:
            missing.append("seasonal_irrigation_available_mm")

        data_sources = [
            "Authenticated Terra Echo farm and selected-plan records",
            "Abu Qerdan generic crop parameter catalog",
        ]
        if any(
            weather.get(name) is not None
            for name in (
                "seasonal_et0_mm",
                "seasonal_precipitation_mm",
                "seasonal_irrigation_available_mm",
            )
        ):
            data_sources.append("User-supplied seasonal simulation inputs")
        else:
            data_sources.append("No historical or live weather observations supplied")

        result.update(
            {
                "tools_used": ["calculate_water_balance", "evaluate_crop_suitability"],
                "tool_results": {
                    "calculate_water_balance": {
                        "estimated_net_irrigation_demand_mm": result.get(
                            "estimated_net_irrigation_demand_mm"
                        ),
                        "estimated_net_irrigation_demand_m3": result.get(
                            "estimated_net_irrigation_demand_m3"
                        ),
                        "adjusted_available_irrigation_mm": result.get(
                            "adjusted_available_irrigation_mm"
                        ),
                        "estimated_water_deficit_mm": result.get(
                            "estimated_water_deficit_mm"
                        ),
                    },
                    "evaluate_crop_suitability": suitability,
                },
                "crop_suitability": suitability,
                "data_sources": data_sources,
                "missing_information": list(dict.fromkeys(missing)),
            }
        )
        return result

    elif action == "compare":
        summary = payload.get("summary") or {}
        strategies = payload.get("strategies") or []
        weather = payload.get("weather")
        return {"comparisons": compare_scenarios(summary, strategies, weather)}

    elif action == "predict":
        records = payload.get("values") or []
        target = payload.get("target")
        date_col = payload.get("date_column")
        if not target:
            raise ValueError("A target column is required.")
        return predict_next(records, target, date_col)

    elif action == "knowledge":
        query = payload.get("query", "")
        return {"internal": search_internal_knowledge(query)}

    else:
        raise ValueError(f"Unknown action: {action}")


def main() -> None:
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": {"message": "Action required"}}))
        sys.exit(1)

    action = sys.argv[1]
    payload: dict[str, Any] = {}
    if len(sys.argv) > 2:
        arg = sys.argv[2]
        if arg == "--stdin":
            raw = sys.stdin.read().strip()
            if raw:
                payload = json.loads(raw)
        elif arg.startswith("@"):
            file_path = Path(arg[1:])
            if file_path.is_file():
                payload = json.loads(file_path.read_text(encoding="utf-8"))
        else:
            try:
                payload = json.loads(arg)
            except Exception:
                payload = {}

    try:
        data = run_action(action, payload)
        print(json.dumps({"success": True, "data": data}, ensure_ascii=False))
    except Exception as exc:
        print(json.dumps({"success": False, "error": {"code": "RUNNER_ERROR", "message": str(exc)}}))
        sys.exit(1)


if __name__ == "__main__":
    main()
