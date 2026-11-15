# Abu Qerdan integration in Terra Echo

## Connection map

```text
Blade pages / browser JavaScript
  -> authenticated Terra Echo routes
  -> ChatController / SimulationController
  -> AgentService + SimulationAgentMapper
  -> private FastAPI Agent API
  -> Abu Qerdan agricultural tools and results
  -> Terra Echo database and UI
```

Laravel owns users, farm records, fields/crops, agricultural plan snapshots, simulation runs, and authorization. Abu Qerdan owns its agricultural calculation and Agent logic. Its SQLite database remains separate and stores Agent-managed conversation history, datasets, and environmental response cache; it is not used as the user/farm/simulation database. Only Laravel calls the private Agent API.

The connected surfaces are the existing My Farm/Simulation page and Abu Qerdan chat. The Agent's standalone dashboard, localStorage plan, and standalone simulation remain development interfaces and are not the integrated source of truth.

## Simulation and Agent context

1. A logged-in user submits a farm's selected crops/fields and scenario inputs from the existing Simulation page.
2. Laravel authorizes the farm, saves a plan snapshot and a pending run linked to the authenticated user, farm, and plan.
3. `RunSimulation` checks the saved plot/crop/area snapshot before calling the Agent. `SimulationAgentMapper` maps each plot and its measured inputs into Abu Qerdan's existing API schema.
4. The Agent runs the crop suitability and water-balance screening calculations. Laravel saves the complete response, including inputs, tools, sources, assumptions, and limitations.
5. Farm chat requests build their context from records authorized to that user, including the latest completed, non-stale simulation. The Agent's saved run explanation uses that exact result snapshot and does not replace it.
6. Plan changes/new runs and relevant field crop changes mark prior results stale. Chat doesn't treat a stale result as current.

No separate Laravel simulation engine has been added. Abu Qerdan's existing crop catalog/calculation is authoritative. The calculation remains screening-level: generic crop parameters and assumed ET0 may be used, precipitation/irrigation balance depends on supplied measurements, and no crop yield is inferred. Repository agricultural CSVs are not yet used by this engine because their provenance/units are not sufficiently verified.

## Configure the Agent host

The actual Agent IP is deployment-specific and must be provided by the system owner. Replace `NEW_LAPTOP_IP` in the Laravel `.env`; do not commit a real token:

```dotenv
AGENT_BASE_URL=http://NEW_LAPTOP_IP:8000
AGENT_API_TOKEN=<same-private-random-secret-as-agent>
AGENT_SIMULATION_TIMEOUT=60
```

On the Agent host, set:

```dotenv
TERRA_ECHO_HOST=0.0.0.0
TERRA_ECHO_PORT=8000
TERRA_ECHO_API_TOKEN=<same-private-random-secret-as-laravel>
TERRA_ECHO_REQUIRE_API_TOKEN=true
```

The Agent's `/health` endpoint is available for health checks. `/api/agent/*` requires the shared Bearer token when configured; if token enforcement is enabled without a token, the service returns an explicit 503. Allow inbound Agent port 8000 only from the Laravel backend host. Use a private network or TLS termination across untrusted networks. Never place the shared token in browser JavaScript, Vite environment variables, or committed source.

## Windows start commands

Agent host (new terminal):

```powershell
Set-Location 'C:\path\to\abu qerdan'
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt
Copy-Item .env.example .env
# Edit .env with the host's network settings and shared token.
python start.py
```

Terra Echo host:

```powershell
Set-Location 'C:\path\to\field-shift-fullstack'
Copy-Item .env.example .env # only if no .env exists
# Edit .env with the actual AGENT_BASE_URL and matching token.
composer install
npm install
npm run build
php artisan migrate
php artisan config:clear
php artisan queue:work --tries=1
```

In another Terra Echo terminal, run:

```powershell
Set-Location 'C:\path\to\field-shift-fullstack'
php artisan serve --host=0.0.0.0 --port=8001
```

The development server is not a production web server. The queue worker must remain active to execute simulation jobs. Tests: `php artisan test`; Agent tests: `python -m pytest -q` after installing its test requirements. The current local database already contains account/farm records; use an authorized existing account for a browser login test. Live browser verification against real credentials and the actual remote host has not been performed.
