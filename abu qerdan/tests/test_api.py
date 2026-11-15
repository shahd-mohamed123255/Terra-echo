from fastapi.testclient import TestClient

from terra_echo_ai import database as db_module
from terra_echo_ai import api as api_module
from terra_echo_ai.api import app


def test_api_serves_testbench_and_persists_agent_conversation(tmp_path, monkeypatch):
    monkeypatch.setattr(db_module, "DATABASE_PATH", tmp_path / "api.sqlite3")
    summary = {
        "farm_id": "integration-farm",
        "location": {"latitude": 30.56, "longitude": 31.16},
        "soil": {"type": "clay"},
        "current_crop": "wheat",
        "farm_area_hectares": 10,
        "planting_date": "2026-11-01",
    }
    with TestClient(app) as client:
        page = client.get("/")
        simulation_page = client.get("/simulation")
        agent_lab = client.get("/agent-lab")
        logo = client.get("/files/logo.png")
        project_dataset = client.get("/project-datasets/fao_national_yield_by_crop_year.csv")
        rejected_project_dataset = client.get("/project-datasets/README.md")
        sample = client.get("/sample/farm_summary.json")
        openapi = client.get("/openapi.json").json()
        started = client.post("/api/agent/start", json={"summary": summary})
        conversation_id = started.json()["data"]["conversation_id"]
        response = client.post(
            "/api/agent/chat",
            json={
                "conversation_id": conversation_id,
                "message": "What should I plant after wheat?",
            },
        )
        resumed = client.get(f"/api/agent/conversations/{conversation_id}")
        rejected_path = client.post(
            "/api/agent/data/inspect", json={"file_path": "C:\\Windows\\win.ini"}
        )

    assert page.status_code == 200 and "Field Shift AI" in page.text
    assert simulation_page.status_code == 200 and "Farm Simulation" in simulation_page.text
    assert agent_lab.status_code == 200 and "Farm Summary JSON" in agent_lab.text
    assert logo.status_code == 200 and logo.headers["content-type"].startswith("image/")
    assert project_dataset.status_code == 200 and "national_yield_t_ha" in project_dataset.text
    assert rejected_project_dataset.status_code == 404
    assert sample.status_code == 200 and sample.json()["farm_id"] == "sample_farm_001"
    assert started.json()["success"] is True
    assert response.json()["data"]["assistant_mode"] == "deterministic_fallback"
    assert "generate_rotation_strategies" in response.json()["data"]["tools_used"]
    assert len(resumed.json()["data"]["messages"]) == 2
    assert "/api/agent/simulate" in openapi["paths"]
    assert rejected_path.status_code == 400
    assert rejected_path.json()["error"]["code"] == "INVALID_INPUT"


def test_simulation_api_rejects_out_of_range_scenario(tmp_path, monkeypatch):
    monkeypatch.setattr(db_module, "DATABASE_PATH", tmp_path / "api.sqlite3")
    with TestClient(app) as client:
        response = client.post(
            "/api/agent/simulate",
            json={
                "summary": {"current_crop": "wheat"},
                "scenario": {"rainfall_change_pct": 45},
            },
        )
    assert response.status_code == 400
    assert response.json()["error"]["code"] == "INVALID_INPUT"


def test_simulation_api_returns_area_weighted_results_and_metadata(tmp_path, monkeypatch):
    monkeypatch.setattr(db_module, "DATABASE_PATH", tmp_path / "simulation.sqlite3")
    with TestClient(app) as client:
        response = client.post(
            "/api/agent/simulate",
            json={
                "summary": {
                    "farm_id": "farm-1",
                    "farm_area_hectares": 3,
                    "location": {"latitude": 30.5, "longitude": 31.2},
                    "soil": {"type": "clay"},
                    "current_crop": "wheat",
                    "planting_date": "2026-01-01",
                },
                "strategy": {
                    "duration_days": 120,
                    "plots": [
                        {"plot_id": 1, "name": "P1", "crop": "wheat", "area_hectares": 1},
                        {"plot_id": 2, "name": "P2", "crop": "beans", "area_hectares": 2},
                    ],
                },
                "weather": {
                    "seasonal_et0_mm": 600,
                    "seasonal_precipitation_mm": 100,
                    "seasonal_irrigation_available_mm": 500,
                },
            },
        )

    data = response.json()["data"]
    assert response.status_code == 200
    assert response.json()["success"] is True
    assert data["estimated_net_irrigation_demand_m3"] == 17100
    assert data["sequence"][0]["plot_id"] == 1
    assert data["sequence"][0]["suitability"]["status"] == "screening_suitable"
    assert "calculate_water_balance" in data["tools_used"]
    assert "evaluate_crop_suitability" in data["tools_used"]
    assert data["missing_information"] == []


def test_agent_api_requires_service_token_when_configured(tmp_path, monkeypatch):
    monkeypatch.setattr(db_module, "DATABASE_PATH", tmp_path / "secured.sqlite3")
    monkeypatch.setattr(api_module, "API_TOKEN", "test-service-token")
    monkeypatch.setattr(api_module, "REQUIRE_API_TOKEN", True)

    with TestClient(app) as client:
        health = client.get("/health")
        unauthorized = client.post("/api/agent/start", json={"summary": {}})
        authorized = client.post(
            "/api/agent/start",
            json={"summary": {}},
            headers={"Authorization": "Bearer test-service-token"},
        )

    assert health.status_code == 200
    assert unauthorized.status_code == 401
    assert authorized.status_code == 200
    assert authorized.json()["success"] is True
