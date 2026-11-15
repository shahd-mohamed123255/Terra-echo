# Terra Echo & Abu Qerdan — Unified System Integration

## Architecture Overview

```text
Unified Frontend (Blade Views / JavaScript)
   ├── Home & Navigation (navbar.blade.php, home.blade.php)
   ├── Farm Setup Wizard (/farm/explore -> /farm/details -> /farm/water -> /farm/summary)
   ├── Farming Plans Engine (/plans & POST /plans)
   ├── My Farm & Simulation (/my-farm & /farm/simulations/*)
   └── Abu Qerdan Chatbot Widget (farm/partials/chat-widget.blade.php)
           │
           ▼
Central Backend (Laravel 12 / PHP 8.3)
   ├── Auth & Session Management
   ├── SQLite Unified Database (users, farms, fields, crops, crop_plans, simulations, chat_conversations)
   ├── PlanController (4 Archetypes: Water-Saving, Balanced, Soil-Health, High-Yield)
   ├── SimulationController & RunSimulation Job
   ├── ChatController
   └── AgentService & SimulationAgentMapper
           │
           ├── [Dual-Mode Communication]
           ├── 1. Primary: Authenticated HTTP to FastAPI (http://127.0.0.1:8000)
           └── 2. Resilient In-Process Runner (abu qerdan/runner.py)
                   │
                   ▼
Abu Qerdan AI Intelligence Engine (Python 3.14)
   ├── Agricultural Calculation & Water Balance Engine (FAO-56 ETc = Kc × ET0)
   ├── Crop Suitability & Soil Evaluation
   ├── Historical & Regional Dataset Module (datasets.py)
   │     ├── FAOSTAT Egyptian National Crop Yields (fao_national_yield_by_crop_year.csv)
   │     └── 8 Agro-Climatic Zones Monthly Weather & ET0 (terra_echo_ml_clean_8zones.csv)
   └── Abu Qerdan Assistant & Chat Context Routing (assistant.py)
```

---

## Complete User Workflow

1. **User Onboarding & Farm Setup**:
   - User signs in and enters farm location via interactive Leaflet map pin (`/farm/explore`).
   - User specifies farm area and soil report (`/farm/details`).
   - User defines irrigation source, farm priorities, and target season (`/farm/water`).
   - On confirmation (`/farm/summary`), the farm is activated and automatically routes to `/plans`.

2. **AI Agricultural Planning**:
   - The system analyzes farm soil, location, and season.
   - Generates 4 customized scientific plan archetypes:
     - **Water-Saving Strategy**: Optimized for deficit irrigation, drought-tolerant crops (-22% water).
     - **Balanced Production Plan**: Proven Egyptian staple crop rotation with split fertigation.
     - **Soil Health & Legume Integration**: Incorporates biological nitrogen fixers (clover, faba beans).
     - **High-Yield Intensive Production**: Maximizes harvest tonnage with 100% ETc satisfaction.
   - User reviews comparative metrics (water mm/m³, yield t/ha, suitability score, pros/cons).
   - User selects a plan -> automatically assigns crops to plots P1..P4 and provisions a simulation scenario.

3. **Field Simulation & Analysis**:
   - User arrives at `/my-farm`, viewing the active plan banner and assigned plots.
   - User clicks **Run Simulation**.
   - The simulation engine executes real FAO-56 water-balance calculations using plot-specific Kc curves and climate adjustments.
   - Results are persisted to the database and rendered live in the Simulation Insights panel.

4. **Abu Qerdan Agricultural Chatbot**:
   - Floating chat assistant available across farm pages.
   - Understands farm coordinates, soil parameters, active plan, and simulation results.
   - References authenticated FAOSTAT national yield trends and regional weather data.

---

## Running the Application

### 1. Environment Configuration

Laravel (`field-shift-fullstack/.env`):
```dotenv
APP_ENV=local
APP_DEBUG=true
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
QUEUE_CONNECTION=sync
AGENT_BASE_URL=http://127.0.0.1:8000
AGENT_API_TOKEN=35fa016abefd4a20b0805177265a6f23
AGENT_LOCAL_FALLBACK=true
```

Abu Qerdan (`abu qerdan/.env`):
```dotenv
TERRA_ECHO_HOST=127.0.0.1
TERRA_ECHO_PORT=8000
TERRA_ECHO_API_TOKEN=35fa016abefd4a20b0805177265a6f23
TERRA_ECHO_REQUIRE_API_TOKEN=true
```

### 2. Starting Services

To run both services in development:

**Terminal 1 (Abu Qerdan AI Service)**:
```powershell
cd "abu qerdan"
python start.py
```
*(Note: If FastAPI server is not running, Laravel transparently falls back to `runner.py` with zero downtime).*

**Terminal 2 (Terra Echo Laravel Web App)**:
```powershell
cd "field-shift-fullstack"
php artisan serve --port=8000
```

### 3. Running Verification Tests

```powershell
# Run full Laravel test suite (40 passed)
cd "field-shift-fullstack"
php artisan test

# Run Python AI agent test suite (14 passed)
cd "abu qerdan"
python -m pytest tests/test_core.py
```
