# Terra Echo AI Agent

Independent agricultural intelligence service integrated server-to-server with the Terra Echo Laravel application. This service implements agricultural calculations and Agent APIs; it does not provide Terra Echo platform authentication, farm ownership authorization, or the source-of-truth farm/plan/simulation database.

## Run locally

Requires Python 3.10+ (developed and checked with Python 3.14).

```powershell
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
Copy-Item .env.example .env
python start.py
```

The standalone development dashboard, local simulation page, Agent API test bench, and OpenAPI/Swagger are available at `/`, `/simulation`, `/agent-lab`, and `/docs`. They are not the authenticated Terra Echo platform pages. The integrated Terra Echo UI calls Laravel; Laravel calls this Agent API privately. SQLite and Agent-managed data are stored under `data/` by default.

If port 8000 is already in use, set `$env:TERRA_ECHO_PORT=8001` in PowerShell before `python start.py`.

The sample farm is illustrative only: `data/sample/farm_summary.json`. Sample weather rows are explicitly illustrative and must not be used as observations.

For the Laravel connection, configure matching `AGENT_BASE_URL`/`AGENT_API_TOKEN` and `TERRA_ECHO_API_TOKEN` values on the two servers. See [docs/integration.md](docs/integration.md) and the main application integration guide for deployment, firewall, and workflow details. The server binds to `0.0.0.0` by default; restrict network access to the Laravel backend and configure a private deployment network/TLS.

## Capabilities

- FastAPI endpoints for conversations, farm analysis, recommendations, crop-rotation screening, simulation, dataset registration, and environmental data.
- SQLite persistence for conversations, farm context, dataset metadata, and NASA POWER response cache.
- A transparent, deterministic ETc calculation (`ETc = Kc × ET0`), scenario inputs, effective-rainfall estimate, screening-level crop profiles, and three candidate rotation strategies.
- Public NASA POWER daily point retrieval with coordinates, units, retrieval metadata, a small retry policy, and SQLite caching.
- CSV, JSON, and GeoJSON inspection/ingestion with schema, missing-value, duplicate-row, timestamp, and unit-hint reporting. Unknown fields are retained. Data files must be placed in `data/incoming/`.
- Optional Excel, Parquet, NetCDF, and GeoTIFF readers. Install the matching optional packages listed in `requirements.txt`; errors identify unavailable readers rather than pretending to process the file.
- A chronological linear-trend prediction baseline only when at least eight numeric observations are available. Holdout metrics and a previous-value baseline are included; no accuracy claim or interval is fabricated.
- OpenAI-compatible LLM integration and external search are independently configurable. Without an LLM key, the assistant uses a clearly labeled limited fallback. Without a configured search endpoint, no web search is claimed.
- Testing frontend whose calculations are served by the Agent API.
- Standalone development dashboard and planning page using the local crop-zone and national-yield CSV files in the browser. This localStorage workflow is separate from the integrated Terra Echo farm/plan/simulation records.
- Server-side simulation API used by Laravel; calculation results are saved by Laravel against the authenticated user, farm, and plan.

## Scientific and integration limits

The built-in crop catalog contains generic screening parameters, not validated local cultivar recommendations. Crop water ranges and Kc values are broad; confirm the local crop calendar, crop stage, actual weather, soil, irrigation method, and water quality with regional agronomy sources. The simulator states its ET0 and effective-rainfall assumptions, excludes several field processes, and never derives yield or disease claims from these values.

NASA POWER output is a gridded product, not a field measurement. SMAP, GPM, Landsat, MODIS, ECOSTRESS, Sentinel-2, and other products are not represented as integrated unless a connector has actually been configured and tested. ESA/Copernicus requires account credentials and is explicitly reported unavailable here. No observations are generated as fallbacks.

Predictions are not available without adequate valid data. This implementation supplies a basic evaluated baseline pipeline, not a validated operational crop-yield model. The assistant is decision support, not a substitute for an agricultural expert.

## Data ingestion

1. Copy a file into `data/incoming/`.
2. Inspect it using `POST /api/agent/data/inspect` or the test bench.
3. Register/process it using `POST /api/agent/data/ingest`.
4. Review the catalog using `GET /api/agent/data/catalog`.

The source file is preserved under `data/raw/<category>/`; tabular records are also written as JSON under `data/processed/<category>/`. The registry captures schema and processing/validation metadata. Scientific rasters/cubes get metadata registration; they are not silently flattened into invented field observations.

## Tests

Install test tools with `pip install -r requirements-dev.txt`, then run:

```powershell
python -m pytest -q
```

Tests use mocked NASA responses and local temporary datasets; they do not depend on external services.

## Configuration

Copy `.env.example` to `.env`. Environment variables already present in the process take precedence. Configure the LLM only if desired. The search endpoint must support `?q=<query>&limit=<n>` and return a JSON `results` array with title, URL, snippet, source, and date fields.

See [docs/project-map.md](docs/project-map.md), [docs/api.md](docs/api.md), [docs/integration.md](docs/integration.md), and [docs/datasets.md](docs/datasets.md).
