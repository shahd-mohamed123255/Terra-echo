# Agent API

Local development base URL: `http://127.0.0.1:8000`; deployment base URL is configured as `AGENT_BASE_URL` in the Laravel server. OpenAPI UI: `/docs`. Responses use `{ "success": true, "data": ... }` on success and `{ "success": false, "error": { "code": ..., "message": ... } }` for handled errors.

The integrated Laravel client sends `Authorization: Bearer <AGENT_API_TOKEN>` server-to-server. Set the same secret in `TERRA_ECHO_API_TOKEN` on the Agent. When `TERRA_ECHO_REQUIRE_API_TOKEN=true`, an unset Agent token causes `/api/agent/*` to return 503; an invalid/missing credential returns 401. The `/health` endpoint is available for service health checks. Never send the service token from a browser.

## Conversations

### `POST /api/agent/start`

```json
{
  "summary": {
    "farm_id": "sample_farm_001",
    "location": { "latitude": 30.56, "longitude": 31.16 },
    "current_crop": "wheat"
  }
}
```

Returns a stable `conversation_id`, stored farm context, and missing fields.

### `POST /api/agent/chat`

```json
{
  "conversation_id": "conv_...",
  "message": "What should I plant after wheat?",
  "summary": { "farm_id": "sample_farm_001", "current_crop": "wheat", "soil": {"type": "clay"} }
}
```

`summary` can be omitted when continuing an existing conversation. A conversation bound to one `farm_id` cannot be reassigned to another. Response includes `reply`, `tools_used`, `tool_results`, `collected_data`, `missing_fields`, `next_question`, and `assistant_mode`. Laravel controls which authorized farm context and conversation ID are sent; the Agent API does not independently authorize platform users.

`GET /api/agent/conversations/{conversation_id}` resumes persisted context and messages.

### `POST /api/agent/analyze`, `/api/agent/recommend`, `/api/agent/plan`

Request: `{ "summary": <Terra Echo Summary JSON> }`. `analyze` returns missing fields, crop screening, rotation options, and structured recommendations. `plan` returns candidate strategies; these are not guaranteed local agronomic prescriptions.

## Simulation and prediction

### `POST /api/agent/simulate`

```json
{
  "summary": {"farm_id":"sample_farm_001","farm_area_hectares":10,"current_crop":"wheat"},
  "strategy": {"sequence":["wheat","beans"]},
  "weather": {
    "seasonal_et0_mm": 600,
    "seasonal_precipitation_mm": 80,
    "seasonal_irrigation_available_mm": 500
  },
  "scenario": {
    "rainfall_change_pct": -20,
    "temperature_change_c": 1,
    "water_availability_change_pct": -10
  }
}
```

Scenario bounds: rainfall `-40..+40%`, temperature `-4..+6°C`, and water availability `-50..+30%`. `seasonal_et0_mm`, `seasonal_precipitation_mm`, and `seasonal_irrigation_available_mm` are optional; missing precipitation means no irrigation-demand estimate is returned. Simulation outputs clearly mark generic assumptions.

The integrated Laravel workflow supplies the selected persisted farm plan and per-plot areas. The Agent response includes area-weighted plot `sequence`, water-balance fields, crop suitability, `tools_used`, `tool_results`, data sources, and missing information. Laravel stores this exact response with its simulation/user/farm/plan metadata; chat explanations use the latest completed, non-stale saved run context. No direct database sharing occurs.

`POST /api/agent/compare` accepts a summary, optional strategy array, and weather inputs.

`POST /api/agent/predict` accepts either `{ "values": [{"date":"...","value":12}, ...], "target":"value", "date_column":"date" }` or `{ "dataset_id":"...", "target":"value", "date_column":"date" }`. A date column is sorted lexically, so use ISO dates. Fewer than eight valid observations returns an explicit unavailable result.

## Data and environment

- `POST /api/agent/data/inspect`: `{ "file_path":"data/incoming/weather.csv" }`
- `POST /api/agent/data/ingest`: same plus optional `name`, `source`, `category`
- `GET /api/agent/data/catalog`
- `POST /api/agent/environment/retrieve`: `{ "summary": <Summary JSON>, "parameters":["T2M","PRECTOTCORR"] }`
- `POST /api/agent/knowledge/search`: `{ "query":"wheat soil", "external":false }`
- `GET /health`

Input files for data endpoints must be under the configured data directory's `incoming/` folder. NASA POWER date range defaults to `summary.analysis_period`. The API returns a `503 DATA_SOURCE_UNAVAILABLE` error on upstream failure rather than substituting made-up values.
