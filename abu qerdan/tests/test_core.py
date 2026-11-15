import json
from urllib.error import URLError

import pytest

from terra_echo_ai import database as db_module
from terra_echo_ai import data_ingestion, environment
from terra_echo_ai.agriculture import (
    compare_scenarios,
    generate_rotation_strategies,
    simulate_rotation,
)
from terra_echo_ai.assistant import answer_question, select_tools
from terra_echo_ai.memory import (
    add_message,
    get_conversation,
    start_conversation,
    update_farm_context,
)
from terra_echo_ai.prediction import predict_next


SAMPLE_SUMMARY = {
    "farm_id": "farm-1",
    "location": {"latitude": 30.56, "longitude": 31.16},
    "farm_area_hectares": 10,
    "soil": {"type": "clay"},
    "current_crop": "wheat",
    "planting_date": "2026-11-01",
    "analysis_period": {"start_date": "2024-01-01", "end_date": "2026-11-14"},
}


@pytest.fixture
def temporary_database(tmp_path, monkeypatch):
    database_path = tmp_path / "agent.sqlite3"
    monkeypatch.setattr(db_module, "DATABASE_PATH", database_path)
    return database_path


def test_simulation_uses_documented_water_balance_and_scenario(temporary_database):
    result = simulate_rotation(
        SAMPLE_SUMMARY,
        {"sequence": ["wheat"]},
        {
            "seasonal_et0_mm": 600,
            "seasonal_precipitation_mm": 80,
            "seasonal_irrigation_available_mm": 500,
        },
        {
            "rainfall_change_pct": -20,
            "temperature_change_c": 1,
            "water_availability_change_pct": -10,
        },
    )
    wheat = result["sequence"][0]
    assert wheat["seasonal_etc_mm"] == pytest.approx(703.8)
    assert wheat["effective_rainfall_mm"] == 51.2
    assert wheat["estimated_net_irrigation_demand_mm"] == pytest.approx(652.6)
    assert result["adjusted_available_irrigation_mm"] == 450
    assert result["estimated_water_deficit_mm"] == pytest.approx(202.6)
    assert result["estimated_net_irrigation_demand_m3"] == pytest.approx(65260)


def test_simulation_marks_irrigation_unknown_without_rainfall(temporary_database):
    result = simulate_rotation(SAMPLE_SUMMARY, {"sequence": ["wheat"]})
    assert result["sequence"][0]["seasonal_etc_mm"] == 862.5
    assert result["estimated_net_irrigation_demand_mm"] is None
    assert "No precipitation observation supplied" in result["assumptions"][1]


def test_plot_simulation_aggregates_area_weighted_demand(temporary_database):
    result = simulate_rotation(
        SAMPLE_SUMMARY,
        {
            "duration_days": 120,
            "plots": [
                {"plot_id": 1, "name": "North", "crop": "wheat", "area_hectares": 1},
                {"plot_id": 2, "name": "South", "crop": "beans", "area_hectares": 2},
            ],
        },
        {
            "seasonal_et0_mm": 600,
            "seasonal_precipitation_mm": 100,
            "seasonal_irrigation_available_mm": 500,
        },
    )

    assert result["farm_area_hectares"] == 3
    assert result["sequence"][0]["season_days"] == 120
    assert result["sequence"][0]["estimated_net_irrigation_demand_m3"] == 6100
    assert result["sequence"][1]["estimated_net_irrigation_demand_m3"] == 11000
    assert result["estimated_net_irrigation_demand_m3"] == 17100
    assert result["estimated_net_irrigation_demand_mm"] == 570
    assert result["estimated_water_deficit_m3"] == 2100


def test_scenario_limits_and_unknown_crops_are_explicit():
    with pytest.raises(ValueError, match="between -40 and 40"):
        simulate_rotation(SAMPLE_SUMMARY, scenario={"rainfall_change_pct": 45})
    with pytest.raises(ValueError, match="Unknown crop"):
        simulate_rotation(SAMPLE_SUMMARY, {"sequence": ["unknown crop"]})


def test_rotation_planner_returns_three_distinct_screening_options():
    strategies = generate_rotation_strategies(SAMPLE_SUMMARY)
    assert [strategy["strategy_id"] for strategy in strategies] == [
        "baseline",
        "soil_health",
        "water_resilience",
    ]
    assert len({tuple(strategy["sequence"]) for strategy in strategies}) == 3
    assert len(compare_scenarios(SAMPLE_SUMMARY)) == 3


def test_conversation_persists_context_and_rejects_cross_farm_reassignment(
    temporary_database,
):
    conversation = start_conversation(SAMPLE_SUMMARY)
    add_message(conversation["conversation_id"], "user", "How is my wheat?")
    loaded = get_conversation(conversation["conversation_id"])
    assert loaded["summary"]["farm_id"] == "farm-1"
    assert loaded["messages"][0]["content"] == "How is my wheat?"
    with pytest.raises(ValueError, match="different farm"):
        update_farm_context(conversation["conversation_id"], {"farm_id": "farm-2"})


def test_csv_ingestion_registers_schema_and_preserves_unknown_fields(
    tmp_path, monkeypatch, temporary_database
):
    data_dir = tmp_path / "data"
    incoming = data_dir / "incoming"
    incoming.mkdir(parents=True)
    source = incoming / "observations.csv"
    source.write_text(
        "date,temperature_c,custom_measure\n2026-01-01,18.5,alpha\n"
        "bad-date,,beta\n",
        encoding="utf-8",
    )
    monkeypatch.setattr(data_ingestion, "DATA_DIR", data_dir)
    inspected = data_ingestion.inspect_dataset(source)
    assert inspected["metadata"]["quality"]["missing_values_by_column"]["temperature_c"] == 1
    assert inspected["metadata"]["quality"]["invalid_timestamps_by_column"]["date"] == 1
    assert "custom_measure" in inspected["columns_or_variables"]

    record = data_ingestion.ingest_dataset(source, source="test fixture")
    assert record["metadata"]["validation_status"] == "warnings"
    assert record["metadata"]["temporal_coverage"]["start"] == "2026-01-01T00:00:00+00:00"
    assert (data_dir / "raw" / "agricultural" / "observations.csv").is_file()
    processed = json.loads(
        (data_dir / "processed" / "agricultural" / f"{record['dataset_id']}.json").read_text(
            encoding="utf-8"
        )
    )
    assert processed["records"][0]["custom_measure"] == "alpha"
    assert data_ingestion.list_datasets()[0]["dataset_id"] == record["dataset_id"]


def test_geojson_inspection_reports_coordinate_bounds(tmp_path, monkeypatch):
    data_dir = tmp_path / "data"
    incoming = data_dir / "incoming"
    incoming.mkdir(parents=True)
    source = incoming / "field.geojson"
    source.write_text(
        json.dumps(
            {
                "type": "FeatureCollection",
                "features": [
                    {
                        "type": "Feature",
                        "properties": {"field_name": "A"},
                        "geometry": {
                            "type": "Polygon",
                            "coordinates": [[[31.1, 30.5], [31.2, 30.6], [31.1, 30.5]]],
                        },
                    }
                ],
            }
        ),
        encoding="utf-8",
    )
    monkeypatch.setattr(data_ingestion, "DATA_DIR", data_dir)
    metadata = data_ingestion.inspect_dataset(source)["metadata"]
    assert metadata["spatial_coverage"]["bounds_lon_lat"] == [31.1, 30.5, 31.2, 30.6]


def test_nasa_power_retrieval_caches_provenanced_observation(
    monkeypatch, tmp_path, temporary_database
):
    monkeypatch.setattr(environment, "DATABASE_PATH", tmp_path / "agent.sqlite3")
    payload = {
        "header": {"units": {"T2M": "C"}},
        "parameters": {"T2M": "Temperature at 2 Meters"},
        "properties": {
            "parameter": {
                "T2M": {"20260101": 18.5, "20260102": -999},
                "PRECTOTCORR": {"20260101": 1.2},
            }
        },
    }

    class Response:
        def __enter__(self):
            return self

        def __exit__(self, *args):
            return False

        def read(self):
            return json.dumps(payload).encode("utf-8")

    calls = []

    def fake_urlopen(request, timeout):
        calls.append(request.full_url)
        return Response()

    monkeypatch.setattr(environment, "urlopen", fake_urlopen)
    first = environment.retrieve_nasa_power(
        SAMPLE_SUMMARY,
        start_date="2026-01-01",
        end_date="2026-01-01",
    )
    second = environment.retrieve_nasa_power(
        SAMPLE_SUMMARY,
        start_date="2026-01-01",
        end_date="2026-01-01",
    )
    assert first["observations"]["parameters"]["T2M"]["20260101"] == 18.5
    assert first["observations"]["parameters"]["T2M"]["20260102"] is None
    assert first["provenance"]["parameter_descriptions"]["T2M"] == "Temperature at 2 Meters"
    assert first["provenance"]["source"] == "NASA POWER"
    assert second["provenance"]["cached"] is True
    assert len(calls) == 1


def test_nasa_power_validates_coordinates_and_period():
    with pytest.raises(ValueError, match="latitude and longitude"):
        environment.retrieve_nasa_power({"analysis_period": SAMPLE_SUMMARY["analysis_period"]})
    with pytest.raises(ValueError, match="earlier than or equal"):
        environment.retrieve_nasa_power(
            SAMPLE_SUMMARY, start_date="2026-02-01", end_date="2026-01-01"
        )


def test_prediction_requires_enough_data_and_reports_chronological_baseline():
    assert predict_next([{"x": index} for index in range(7)], "x")["available"] is False
    result = predict_next(
        [{"date": f"2026-01-{index:02}", "value": index * 2} for index in range(1, 11)],
        "value",
        "date",
    )
    assert result["available"] is True
    assert result["evaluation_metrics"]["beats_previous_value_baseline"] is True
    assert result["prediction"]["value"] == pytest.approx(22)


def test_chat_selects_relevant_tools_and_persists_answer(
    temporary_database, monkeypatch
):
    from terra_echo_ai import assistant

    monkeypatch.setattr(
        assistant,
        "retrieve_environment",
        lambda *args, **kwargs: (_ for _ in ()).throw(
            assistant.DataSourceUnavailable("not available in this test")
        ),
    )
    conversation = start_conversation(SAMPLE_SUMMARY)
    tools = select_tools("What happens if rainfall decreases by 20%?", SAMPLE_SUMMARY)
    assert "calculate_water_balance" in tools
    add_message(conversation["conversation_id"], "user", "What happens if rainfall decreases by 20%?")
    result = answer_question(
        conversation["conversation_id"],
        "What happens if rainfall decreases by 20%?",
        SAMPLE_SUMMARY,
        weather={"seasonal_precipitation_mm": 100},
    )
    assert result["tool_results"]["water_balance"]["scenario"]["rainfall_change_pct"] == -20
    assert result["assistant_mode"] == "deterministic_fallback"


def test_simulation_chat_explains_saved_result_and_recalculates_what_if(
    temporary_database,
):
    inputs = {
        "summary": SAMPLE_SUMMARY,
        "strategy": {
            "duration_days": 120,
            "plots": [{"plot_id": 10, "name": "P1", "crop": "wheat", "area_hectares": 1}],
        },
        "weather": {
            "seasonal_et0_mm": 600,
            "seasonal_precipitation_mm": 100,
            "seasonal_irrigation_available_mm": 500,
        },
        "scenario": {},
    }
    results = simulate_rotation(
        inputs["summary"], inputs["strategy"], inputs["weather"], inputs["scenario"]
    )
    run = {
        "simulation_id": 41,
        "farm_id": "farm-1",
        "selected_plan_id": 12,
        "status": "completed",
        "input_parameters": inputs,
        "simulation_results": {**results, "input_parameters": inputs},
    }
    summary = {**SAMPLE_SUMMARY, "latest_simulation": run}
    conversation = start_conversation(summary)

    explanation = answer_question(
        conversation["conversation_id"],
        "Explain my latest simulation",
        summary,
    )
    assert explanation["assistant_mode"] == "deterministic_simulation_explanation"
    assert "simulation #41" in explanation["reply"]
    assert "6100.0 m³" in explanation["reply"]

    what_if = answer_question(
        conversation["conversation_id"],
        "What happens if available water decreases by 20%?",
        summary,
    )
    assert what_if["tool_results"]["what_if_simulation"]["scenario"][
        "water_availability_change_pct"
    ] == -20
    assert what_if["tool_results"]["what_if_simulation"][
        "adjusted_available_irrigation_mm"
    ] == 400
    assert "recalculated from saved inputs" in what_if["reply"]


def test_llm_provider_retries_transient_failure_and_logs_usage(monkeypatch, caplog):
    from terra_echo_ai import llm

    caplog.set_level("INFO", logger="terra_echo_ai.llm")
    monkeypatch.setattr(llm, "LLM_API_KEY", "test-key")
    monkeypatch.setattr(llm.time, "sleep", lambda _: None)
    calls = []

    class Response:
        def __enter__(self):
            return self

        def __exit__(self, *args):
            return False

        def read(self):
            return json.dumps(
                {
                    "choices": [{"message": {"content": "Answer"}}],
                    "usage": {"prompt_tokens": 10, "completion_tokens": 3},
                }
            ).encode("utf-8")

    def fake_urlopen(request, timeout):
        calls.append(request)
        if len(calls) == 1:
            raise URLError("temporary network issue")
        return Response()

    monkeypatch.setattr(llm, "urlopen", fake_urlopen)
    reply = llm.generate_reply("Question", {}, {}, [])
    assert reply == "Answer"
    assert len(calls) == 2
    assert "LLM token usage" in caplog.text
