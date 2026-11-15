from __future__ import annotations

from contextlib import asynccontextmanager
import hmac
import logging
from typing import Any

from fastapi import FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from fastapi.responses import FileResponse
from fastapi.staticfiles import StaticFiles

from terra_echo_ai.agriculture import (
    analyze_farm,
    compare_scenarios,
    evaluate_crop_suitability,
    generate_rotation_strategies,
    missing_farm_fields,
    simulate_rotation,
)
from terra_echo_ai.assistant import answer_question, search_internal_knowledge
from terra_echo_ai.config import ALLOWED_ORIGINS, API_TOKEN, REQUIRE_API_TOKEN, ROOT_DIR
from terra_echo_ai.data_ingestion import (
    dataset_records,
    ingest_dataset,
    inspect_dataset,
    list_datasets,
)
from terra_echo_ai.database import initialize_database
from terra_echo_ai.environment import DataSourceUnavailable, retrieve_environment
from terra_echo_ai.llm import LLMUnavailable
from terra_echo_ai.memory import (
    add_message,
    get_conversation,
    start_conversation,
    update_farm_context,
)
from terra_echo_ai.prediction import predict_next
from terra_echo_ai.research import SearchUnavailable, search_external
from terra_echo_ai.schemas import (
    AgentStartRequest,
    AnalysisRequest,
    ChatRequest,
    CompareRequest,
    EnvironmentRequest,
    IngestRequest,
    PredictionRequest,
    SimulationRequest,
)

logger = logging.getLogger(__name__)
@asynccontextmanager
async def lifespan(_: FastAPI):
    initialize_database()
    yield


app = FastAPI(
    title="Terra Echo AI Agent",
    description="Independent agricultural intelligence service; not the Terra Echo platform backend.",
    version="0.1.0",
    lifespan=lifespan,
)
app.add_middleware(
    CORSMiddleware,
    allow_origins=ALLOWED_ORIGINS,
    allow_credentials=False,
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)


@app.middleware("http")
async def require_service_authentication(request: Request, call_next):
    if request.url.path.startswith("/api/agent/"):
        if not API_TOKEN and REQUIRE_API_TOKEN:
            return JSONResponse(
                status_code=503,
                content={
                    "success": False,
                    "error": {
                        "code": "SERVICE_AUTH_NOT_CONFIGURED",
                        "message": "Agent service authentication is required but no token is configured.",
                    },
                },
            )
        if API_TOKEN and not hmac.compare_digest(
            request.headers.get("authorization", ""),
            f"Bearer {API_TOKEN}",
        ):
            return JSONResponse(
                status_code=401,
                content={
                    "success": False,
                    "error": {
                        "code": "UNAUTHORIZED",
                        "message": "A valid service credential is required.",
                    },
                },
            )

    return await call_next(request)


@app.get("/", include_in_schema=False)
def application_frontend() -> FileResponse:
    return FileResponse(ROOT_DIR / "files" / "index.html")


@app.get("/simulation", include_in_schema=False)
def simulation_frontend() -> FileResponse:
    return FileResponse(ROOT_DIR / "farm-simulation.html")


@app.get("/agent-lab", include_in_schema=True)
def testing_frontend() -> FileResponse:
    return FileResponse(ROOT_DIR / "testing_frontend" / "index.html")


app.mount(
    "/sample",
    StaticFiles(directory=ROOT_DIR / "data" / "sample"),
    name="sample-data",
)
app.mount(
    "/files",
    StaticFiles(directory=ROOT_DIR / "files"),
    name="application-assets",
)

PROJECT_DATASETS = {
    "terra_echo_ml_clean_8zones.csv": ROOT_DIR / "terra_echo_ml_clean_8zones.csv",
    "fao_national_yield_by_crop_year.csv": ROOT_DIR / "fao_national_yield_by_crop_year.csv",
}


@app.get("/project-datasets/{filename}", include_in_schema=False)
def project_dataset(filename: str) -> FileResponse:
    path = PROJECT_DATASETS.get(filename)
    if path is None or not path.is_file():
        raise KeyError(f"Unknown project dataset: {filename}")
    return FileResponse(path, media_type="text/csv")


@app.exception_handler(RequestValidationError)
async def validation_error_handler(
    request: Request, error: RequestValidationError
) -> JSONResponse:
    return JSONResponse(
        status_code=422,
        content={
            "success": False,
            "error": {"code": "INVALID_REQUEST", "message": str(error)},
        },
    )


@app.exception_handler(ValueError)
async def value_error_handler(request: Request, error: ValueError) -> JSONResponse:
    return JSONResponse(
        status_code=400,
        content={"success": False, "error": {"code": "INVALID_INPUT", "message": str(error)}},
    )


def _success(data: Any) -> dict[str, Any]:
    return {"success": True, "data": data}


def _recommendations(summary: dict[str, Any]) -> list[dict[str, Any]]:
    strategies = generate_rotation_strategies(summary)
    return [
        {
            "recommendation_id": f"rec_{strategy['strategy_id']}",
            "title": strategy["title"],
            "explanation": strategy["focus"],
            "recommended_action": f"Review candidate crop sequence: {' → '.join(strategy['sequence'])}.",
            "supporting_evidence": strategy["evidence"],
            "data_sources": ["Farmer-provided Summary JSON", "Internal generic crop catalog"],
            "expected_benefits": [strategy["focus"]],
            "potential_risks": strategy["risks"],
            "confidence_level": strategy["confidence"],
            "missing_information": strategy["missing_information"],
            "assumptions": strategy["assumptions"],
            "suggested_next_steps": [
                "Confirm the local planting window and cultivar with an agricultural extension specialist."
            ],
        }
        for strategy in strategies
    ]


@app.get("/health")
def health() -> dict[str, Any]:
    return _success({"status": "ok", "service": "terra-echo-ai-agent"})


@app.post("/api/agent/start")
def agent_start(request: AgentStartRequest) -> dict[str, Any]:
    conversation = start_conversation(request.summary)
    return _success(
        {
            **conversation,
            "missing_fields": analyze_farm(request.summary)["missing_fields"],
        }
    )


@app.post("/api/agent/chat")
def agent_chat(request: ChatRequest) -> dict[str, Any]:
    if request.conversation_id:
        conversation = get_conversation(request.conversation_id)
        summary = request.summary if request.summary is not None else conversation["summary"]
        if request.summary is not None:
            conversation = update_farm_context(request.conversation_id, request.summary)
            summary = conversation["summary"]
        conversation_id = request.conversation_id
    else:
        summary = request.summary or {}
        conversation_id = start_conversation(summary)["conversation_id"]
    add_message(conversation_id, "user", request.message)
    response = answer_question(conversation_id, request.message, summary)
    add_message(conversation_id, "assistant", response["reply"])
    return _success({"conversation_id": conversation_id, **response})


@app.get("/api/agent/conversations/{conversation_id}")
def conversation_read(conversation_id: str) -> dict[str, Any]:
    return _success(get_conversation(conversation_id))


@app.post("/api/agent/analyze")
def agent_analyze(request: AnalysisRequest) -> dict[str, Any]:
    result = analyze_farm(request.summary)
    result["recommendations"] = _recommendations(request.summary)
    return _success(result)


@app.post("/api/agent/recommend")
def agent_recommend(request: AnalysisRequest) -> dict[str, Any]:
    return _success({"recommendations": _recommendations(request.summary)})


@app.post("/api/agent/plan")
def agent_plan(request: AnalysisRequest) -> dict[str, Any]:
    return _success({"strategies": generate_rotation_strategies(request.summary)})


@app.post("/api/agent/simulate")
def agent_simulate(request: SimulationRequest) -> dict[str, Any]:
    result = simulate_rotation(
        request.summary,
        request.strategy,
        request.weather,
        request.scenario,
    )
    strategy = request.strategy or {}
    crops = strategy.get("plots") or [
        {"crop": crop}
        for crop in strategy.get("sequence", [request.summary.get("current_crop")])
        if crop
    ]
    suitability = [
        {
            "plot_id": plot.get("plot_id"),
            "crop": plot.get("crop"),
            **evaluate_crop_suitability(
                str(plot.get("crop") or ""),
                request.summary.get("soil") or {},
            ),
        }
        for plot in crops
    ]
    suitability_by_plot = {
        item["plot_id"]: item for item in suitability if item["plot_id"] is not None
    }
    for item in result["sequence"]:
        if item.get("plot_id") in suitability_by_plot:
            item["suitability"] = suitability_by_plot[item["plot_id"]]
    missing = missing_farm_fields(request.summary)
    if request.weather.get("seasonal_precipitation_mm") is None:
        missing.append("seasonal_precipitation_mm")
    if request.weather.get("seasonal_irrigation_available_mm") is None:
        missing.append("seasonal_irrigation_available_mm")
    data_sources = [
        "Authenticated Terra Echo farm and selected-plan records",
        "Abu Qerdan generic crop parameter catalog",
    ]
    if any(
        request.weather.get(name) is not None
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
                    "estimated_net_irrigation_demand_mm": result[
                        "estimated_net_irrigation_demand_mm"
                    ],
                    "estimated_net_irrigation_demand_m3": result[
                        "estimated_net_irrigation_demand_m3"
                    ],
                    "adjusted_available_irrigation_mm": result[
                        "adjusted_available_irrigation_mm"
                    ],
                    "estimated_water_deficit_mm": result[
                        "estimated_water_deficit_mm"
                    ],
                },
                "evaluate_crop_suitability": suitability,
            },
            "crop_suitability": suitability,
            "data_sources": data_sources,
            "missing_information": list(dict.fromkeys(missing)),
        }
    )
    return _success(result)


@app.post("/api/agent/compare")
def agent_compare(request: CompareRequest) -> dict[str, Any]:
    return _success(
        {"comparisons": compare_scenarios(request.summary, request.strategies, request.weather)}
    )


@app.post("/api/agent/predict")
def agent_predict(request: PredictionRequest) -> dict[str, Any]:
    if request.values:
        records = request.values
    elif request.dataset_id:
        records = dataset_records(request.dataset_id)
    else:
        return _success(
            {
                "available": False,
                "reason": "Provide a dataset_id or historical values before requesting a prediction.",
            }
        )
    target = request.target
    if not target:
        raise ValueError("A target column is required.")
    return _success(predict_next(records, target, request.date_column))


@app.post("/api/agent/data/inspect")
def data_inspect(request: IngestRequest) -> dict[str, Any]:
    return _success(inspect_dataset(request.file_path))


@app.post("/api/agent/data/ingest")
def data_ingest(request: IngestRequest) -> dict[str, Any]:
    return _success(
        ingest_dataset(request.file_path, request.name, request.source, request.category)
    )


@app.get("/api/agent/data/catalog")
def data_catalog() -> dict[str, Any]:
    return _success({"datasets": list_datasets()})


@app.post("/api/agent/environment/retrieve")
def environment_retrieve(request: EnvironmentRequest) -> dict[str, Any]:
    return _success(
        retrieve_environment(
            request.summary,
            start_date=request.start_date,
            end_date=request.end_date,
            parameters=request.parameters,
        )
    )


@app.post("/api/agent/knowledge/search")
def knowledge_search(request: dict[str, Any]) -> dict[str, Any]:
    query = request.get("query")
    if not isinstance(query, str) or not query.strip():
        raise ValueError("'query' must be a non-empty string.")
    internal = search_internal_knowledge(query)
    external = search_external(query) if request.get("external") else None
    return _success({"internal": internal, "external": external})


@app.exception_handler(KeyError)
async def not_found_handler(request: Request, error: KeyError) -> JSONResponse:
    return JSONResponse(
        status_code=404,
        content={
            "success": False,
            "error": {"code": "NOT_FOUND", "message": str(error).strip("'")},
        },
    )


@app.exception_handler(DataSourceUnavailable)
async def data_source_error_handler(
    request: Request, error: DataSourceUnavailable
) -> JSONResponse:
    logger.error("Environmental data source unavailable: %s", error)
    return JSONResponse(
        status_code=503,
        content={
            "success": False,
            "error": {"code": "DATA_SOURCE_UNAVAILABLE", "message": str(error)},
        },
    )


@app.exception_handler(LLMUnavailable)
async def llm_error_handler(request: Request, error: LLMUnavailable) -> JSONResponse:
    logger.error("Configured LLM provider unavailable: %s", error)
    return JSONResponse(
        status_code=503,
        content={"success": False, "error": {"code": "LLM_PROVIDER_UNAVAILABLE", "message": str(error)}},
    )


@app.exception_handler(SearchUnavailable)
async def search_error_handler(
    request: Request, error: SearchUnavailable
) -> JSONResponse:
    logger.error("Configured external search unavailable: %s", error)
    return JSONResponse(
        status_code=503,
        content={
            "success": False,
            "error": {"code": "SEARCH_SOURCE_UNAVAILABLE", "message": str(error)},
        },
    )


@app.exception_handler(Exception)
async def unhandled_error_handler(request: Request, error: Exception) -> JSONResponse:
    logger.exception("Unhandled Agent API error", exc_info=error)
    return JSONResponse(
        status_code=500,
        content={
            "success": False,
            "error": {"code": "INTERNAL_ERROR", "message": "The Agent request failed."},
        },
    )
