# Terra Echo and Abu Qerdan integration

## Architecture and data ownership

The Laravel Terra Echo application is the platform and source of truth for authenticated users, farms, fields, crops, plans, simulations, and farm ownership. The FastAPI Abu Qerdan service is a private agricultural calculation and Agent API. The Agent's standalone dashboard and simulation page remain available for local development and API testing; they are not the integrated platform UI and must not be used as a second source of farm or simulation records.

```text
Terra Echo Blade/browser UI
  -> authenticated Laravel routes
  -> SimulationController / ChatController
  -> SimulationAgentMapper / AgentService (server-to-server)
  -> FastAPI /api/agent/*
  -> Abu Qerdan agriculture and assistant tools
  -> Laravel saves run results and returns them to the UI
```

| Component | Responsibility and boundary |
|---|---|
| Terra Echo Laravel application | Authenticates users; authorizes farm, plan, run, and conversation access; persists farm, plan, simulation, and local conversation ownership records. |
| `AgentService` | Central private HTTP client for the Agent API; sends the configured bearer token; handles health, timeout, transport, and response-contract failures. |
| `SimulationAgentMapper` | Converts the selected, authorized farm/plan and optional measured weather/scenario inputs to the Agent request contract, then attaches run and ownership metadata to returned results. |
| Abu Qerdan FastAPI service | Runs the existing screening calculations, crop tools, recommendations, conversation tools, and optional environment/data tools. Its API does not authenticate Terra Echo users or authorize farm ownership. |
| Abu Qerdan SQLite database | Stores Agent conversation memory, dataset metadata, and environmental response cache. It is not a shared user/farm/simulation database. |
| Terra Echo SQLite/database | Stores the authoritative user/farm/plan/simulation records and run snapshots. Agent results are copied into the associated simulation record. |

The two databases do not communicate directly. Laravel sends authorized context to the Agent over the API and persists the returned result. The browser never calls the private Agent API directly.

## Integrated simulation and chat workflow

1. A signed-in user selects a farm and selected field/crop set in Terra Echo.
2. The existing Simulation page posts the plan and validated scenario inputs to Laravel. Laravel stores a crop-plan snapshot and a pending simulation linked to its owner, farm, and plan.
3. Laravel queues the existing `RunSimulation` job. It verifies the plan snapshot still matches the farm fields and sends the selected plot areas, soil/farm information, plan, and only the weather measurements the user supplied.
4. The Agent API uses the existing crop catalog and area-weighted agricultural calculation. Laravel stores the complete Agent result and marks earlier completed runs stale when a new plan/run is selected or relevant farm crop inputs change.
5. Chat is started and retrieved through Laravel with the authenticated owner and selected farm. Laravel sends the current farm/soil/crop context, current plan, and latest completed non-stale simulation.
6. When asked to explain that simulation, Abu Qerdan explains the persisted result snapshot. A supported what-if is recalculated from that snapshot; it does not overwrite the saved run.

Simulation results include their run, farm, plan, and conversation identifiers, as well as assumptions, tools, missing information, and inputs. A user cannot use another user's farm/run/conversation through Laravel routes. The Agent itself is not a substitute for these authorization checks.

## Agricultural data and scientific limits

- The main platform's farm, soil, fields/crops, and saved plan values are passed as actual authorized database records. Plot areas are converted from feddan to hectares using the existing project conversion.
- Weather values are not invented. The Simulation UI accepts optional ET0, precipitation, and irrigation-availability measurements; if absent, the Agent reports the corresponding missing water-balance result/limitation.
- The Agent's environmental retrieval tool is available through its API, but the platform does not silently fetch or label external environmental values as farm observations during simulation.
- Repository CSV datasets are not currently consumed by the Agent's simulation calculations. Their units/provenance remain insufficiently verified for that use. The simulator uses generic screening coefficients and clearly reported assumptions; it does not make yield or crop-success predictions.
- An illustrative farm file or the Agent's standalone browser local-storage plan is not a substitute for an authenticated Terra Echo farm/plan.

## Service configuration and deployment

Set `AGENT_BASE_URL` in the Laravel server's `.env` to the actual reachable Agent host. The address is intentionally not guessed:

```dotenv
AGENT_BASE_URL=http://NEW_LAPTOP_IP:8000
AGENT_API_TOKEN=use-the-same-long-random-secret-on-both-servers
AGENT_SIMULATION_TIMEOUT=60
```

On the Agent host, configure `.env`:

```dotenv
TERRA_ECHO_HOST=0.0.0.0
TERRA_ECHO_PORT=8000
TERRA_ECHO_API_TOKEN=use-the-same-long-random-secret-on-both-servers
TERRA_ECHO_REQUIRE_API_TOKEN=true
```

Replace the placeholder with the actual private/reachable address and a privately generated secret. Do not commit either value or expose the token in browser/Vite variables. `/health` is the health-check endpoint. The Agent binds to the configured interface (default `0.0.0.0`); firewall policy must allow port 8000 only from the Laravel backend host. Use a private network or TLS termination for traffic crossing an untrusted network. CORS is not an authentication mechanism.

Agent setup (PowerShell):

```powershell
Set-Location 'C:\path\to\abu qerdan'
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
Copy-Item .env.example .env
# Edit .env: set the real shared token and host/port values, then:
python start.py
```

Terra Echo setup/start (PowerShell):

```powershell
Set-Location 'C:\path\to\field-shift-fullstack'
Copy-Item .env.example .env # only if .env does not already exist
# Edit .env: set AGENT_BASE_URL and the same AGENT_API_TOKEN.
composer install
npm install
npm run build
php artisan migrate
php artisan config:clear
php artisan queue:work --tries=1
```

In a second Terra Echo terminal, run `php artisan serve --host=0.0.0.0 --port=8001`. On a deployment, use the configured web server/HTTPS setup instead of the development server. Keep the queue worker running because simulation execution is queued. Run the existing tests with `php artisan test`; run Agent tests with `python -m pytest -q`.

The actual Agent IP/token, a populated local Terra Echo account/farm, firewall access, and any production TLS/process supervision remain deployment-specific. Until those are supplied, a live user login and cross-host workflow cannot be claimed.
