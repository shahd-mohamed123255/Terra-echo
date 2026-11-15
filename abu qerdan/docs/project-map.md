# Field Shift AI project map

The existing repository is a Terra Echo agricultural intelligence service with a separate branded farm simulation page. It already contains a FastAPI Agent API, local agricultural datasets, and a small API test bench; it is not a blank frontend project.

## Frontend, pages, and visual assets

| Path | Responsibility |
|---|---|
| `files/index.html` | Branded dashboard, farm context editor, crop planning view, local-data assistant, and dataset summary. The former subscription-plan cards were placeholders; they now show historical crop profiles. |
| `files/app.js` | Shared frontend utilities: CSV parsing, dataset loading, complete-season grouping, zone lookup, crop-season profiles, local evidence retrieval, and Agent API calls. |
| `files/planning.js` | Dashboard interactions: crop/season comparisons, saving the plan in browser storage, assistant conversation UX, data evidence, and optional historical-yield trend request. |
| `farm-simulation.html` | Existing visual My Farm/simulation interface, now served at `/simulation`. The map is explicitly an illustrative layout, not field geometry or satellite imagery. |
| `files/simulation.js` | Reads the saved planning selection; displays matching historical profiles; computes scenario-adjusted net irrigation and farm water totals; exposes the simulation context to Abu Qerdan. |
| `testing_frontend/index.html` | Existing Agent API test bench, retained at `/agent-lab` for API-level experiments. |
| `files/logo.png` | Existing Terra Echo logo, reused in both application pages. |
| `files/earth.jpg`, `files/stars.jpg` | Existing backgrounds used by the dashboard. |
| `files/` | Contains the logo and two background images above; there is no separate `assets/` directory or RCBG resource in this workspace. Paths use the actual `/files/` resource route. |

## Datasets and relationships

| Path | Responsibility and important interpretation |
|---|---|
| `terra_echo_ml_clean_8zones.csv` | 3,745 monthly records, 62 columns, 8 zones, 8 crop labels, two seasons, and dates April 2015–June 2025. Contains climate, crop ETc/irrigation, vegetation, soil, and crop requirement fields. The frontend creates seasonal summaries by grouping `zone + crop + season + harvest_year` and only includes `season_complete=1`. |
| `fao_national_yield_by_crop_year.csv` | 66 national crop/year rows with national area, yield in t/ha by column name, and a source flag. Exact `crop + harvest_year` keys align with 65 monthly-data rows; only 62 yield rows have a complete crop-season counterpart. This is national context, never a field yield observation. |
| `data/sample/farm_summary.json` | Illustrative editable farm context: coordinates, 10 ha, clay, wheat, crop history, dates, and priorities. Coordinates map to the nearest crop-data zone; the file itself explicitly says it is illustrative. |
| `data/sample/weather_observations.csv` | Eight illustrative daily rows with temperature, precipitation, reference ET0, and a test-only source label. It is not treated as actual weather or used in the simulation. |
| `data/incoming/`, `data/raw/`, `data/processed/`, `data/external/`, `data/metadata/` | Empty data-management locations with `.gitkeep` placeholders; no additional uploaded datasets were found in them. |

The monthly and national datasets share crop labels and harvest years, but not farm identifiers. The app does not infer a farm-level yield relationship from that join. There is no source data dictionary for the 62-column file; field units and provenance still require verification.

### Identified alignment gaps

- The monthly data uses `maize` and `faba_bean`; the existing generic backend crop catalog instead uses names such as `corn` and `beans`. The monthly file also includes potato and sugar beet, which are absent from that catalog, while the catalog has crops absent from the monthly data. The local frontend keeps dataset crop IDs intact and does not silently translate them into backend crop IDs.
- The illustrative farm summary says 10 ha, while the old simulation markup showed a 24 ha four-plot example and hardcoded water, soil, and crop scores. The integrated pages now use the editable sample area and derived data profiles; the four map plots remain explicitly illustrative.
- The sample weather dates do not align with the monthly crop dataset's full historical coverage and are test-only; the application does not combine them.
- `faostat_flag` codes and source/unit metadata for the principal monthly dataset are not explained in the workspace.

## Agent API modules

| Path | Responsibility |
|---|---|
| `terra_echo_ai/api.py` | FastAPI routes for the application, simulation, Agent API, sample data, and allowlisted project CSV delivery. |
| `terra_echo_ai/agriculture.py` | Generic editable crop catalog, soil screening, crop-rotation suggestions, ETc simulation, and environmental summary functions. Catalog parameters are screening assumptions, not local observations. |
| `terra_echo_ai/assistant.py` | Question-to-tool routing, deterministic fallback, crop-catalog search, and optional environment/research tool execution. |
| `terra_echo_ai/prediction.py` | Chronological linear-trend baseline with holdout and previous-value comparison; requires at least eight numeric observations. |
| `terra_echo_ai/environment.py` | NASA POWER daily point retrieval, validation, provenance, retries, and response cache. Does not fabricate results when unavailable. |
| `terra_echo_ai/data_ingestion.py` | Dataset inspection/ingestion for CSV, JSON, GeoJSON, and optional scientific formats; preserves unknown attributes and source files. |
| `terra_echo_ai/memory.py` | SQLite conversation storage, farm context updates, and message history. |
| `terra_echo_ai/database.py` | SQLite schema and connection helpers for conversations, datasets, and environmental cache. |
| `terra_echo_ai/llm.py` | Optional OpenAI-compatible provider call; inactive unless a server-side key is configured. |
| `terra_echo_ai/research.py` | Optional configured external-search connector; no live search is claimed without configuration. |
| `terra_echo_ai/schemas.py` | Pydantic request schemas for Agent API endpoints. |
| `terra_echo_ai/config.py` | Environment configuration and workspace/data paths. |
| `terra_echo_ai/__init__.py` | Package metadata. |
| `start.py` | Local server startup; `TERRA_ECHO_PORT` allows choosing another port. |
| `requirements.txt` | FastAPI/Uvicorn requirements; optional data readers are listed as comments. |
| `.env.example` | Example API, LLM, NASA POWER, and search configuration. |
| `.gitignore` | Excludes credentials, virtualenv/cache, SQLite, and generated/ingested data files. |

## Tests and other documentation

| Path | Responsibility |
|---|---|
| `tests/test_core.py` | Agricultural calculations, data ingestion, NASA response handling, prediction, assistant routing, and persistence tests. |
| `tests/test_api.py` | API routes, local pages/assets/project data, conversations, and simulation request validation. |
| `README.md` | Setup, run instructions, limitations, and capability overview. |
| `docs/datasets.md` | Dataset inventory, field groups, relationships, and ingestion workflow. |
| `docs/integration.md` | Frontend-to-Agent workflow, data boundaries, and future deployment integration guidance. |
| `docs/api.md` | Agent API request/response examples and endpoint behavior. |

## Standalone and integrated workflows

The standalone development frontend still has its own localStorage demonstration flow:

`Abu Qerdan development dashboard → browser localStorage → local simulation page`

The production/platform integration is:

`Terra Echo UI → authenticated Laravel routes → AgentService → FastAPI Agent API → agricultural tools → Laravel-owned simulation result/chat context`

Laravel owns authentication, farm and plan authorization, simulation history, and persisted run snapshots. Abu Qerdan is a private calculation/chat service and its SQLite store remains limited to Agent-managed conversation/cache/dataset records. See [docs/integration.md](integration.md) for deployment and current limits.

The Agent may use a configured LLM/search integration; otherwise it reports deterministic fallback mode. The repository CSVs are not yet wired into the main application's simulation calculation because their units/provenance have not been verified. This is screening decision support, not a validated yield or crop-success model.
