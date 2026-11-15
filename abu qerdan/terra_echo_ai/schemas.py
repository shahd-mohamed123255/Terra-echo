from typing import Any

from pydantic import BaseModel, Field


class AgentStartRequest(BaseModel):
    summary: dict[str, Any] = Field(default_factory=dict)


class ChatRequest(BaseModel):
    message: str = Field(min_length=1, max_length=10000)
    conversation_id: str | None = None
    summary: dict[str, Any] | None = None


class AnalysisRequest(BaseModel):
    summary: dict[str, Any]


class SimulationRequest(BaseModel):
    summary: dict[str, Any]
    strategy: dict[str, Any] | None = None
    weather: dict[str, Any] = Field(default_factory=dict)
    scenario: dict[str, float] = Field(default_factory=dict)


class CompareRequest(BaseModel):
    summary: dict[str, Any]
    strategies: list[dict[str, Any]] | None = None
    weather: dict[str, Any] = Field(default_factory=dict)


class PredictionRequest(BaseModel):
    dataset_id: str | None = None
    values: list[dict[str, Any]] = Field(default_factory=list)
    target: str | None = None
    date_column: str | None = None


class IngestRequest(BaseModel):
    file_path: str
    name: str | None = None
    source: str | None = None
    category: str = "agricultural"


class EnvironmentRequest(BaseModel):
    summary: dict[str, Any]
    start_date: str | None = None
    end_date: str | None = None
    parameters: list[str] = Field(
        default_factory=lambda: [
            "T2M",
            "PRECTOTCORR",
            "ALLSKY_SFC_SW_DWN",
            "RH2M",
            "WS2M",
        ]
    )

