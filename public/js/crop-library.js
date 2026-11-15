document.addEventListener('DOMContentLoaded', () => {
    const library = document.querySelector('.crop-library');

    if (!library) {
        return;
    }

    const search = library.querySelector('.crop-library-search');
    const cards = Array.from(library.querySelectorAll('.crop-card'));
    const filters = Array.from(library.querySelectorAll('[data-category-filter]'));
    const emptyMessage = library.querySelector('.crop-library-empty');
    const cropButtons = Array.from(library.querySelectorAll('.crop-card-add'));
    const selectedCropsList = library.querySelector('#selected-crops-list');
    const requestMessage = document.querySelector('#plot-request-message');
    const selectionHint = document.querySelector('#plot-selection-hint');
    const summary = document.querySelector('#farm-map-summary');
    const mapElement = document.querySelector('#farm-map');
    const mapMessage = document.querySelector('#farm-map-message');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const mapData = window.myFarmMapData || {};
    const scenarioData = window.myFarmScenarioData || {};
    const scenarioForm = document.querySelector('#scenario-form');
    const scenarioPlotsList = document.querySelector('#scenario-selected-plots');
    const scenarioMessage = document.querySelector('#scenario-message');
    const scenarioReadiness = document.querySelector('#scenario-readiness');
    const scenarioEndDate = document.querySelector('#scenario-end-date');
    const selectedCropsTotal = document.querySelector('#selected-crops-total');
    const boundaryToggle = document.querySelector('#farm-boundary-toggle');
    const plotDetailsCard = document.querySelector('#plot-details-card');
    const plotDetailsRemove = document.querySelector('#plot-details-remove');
    const scenarioRunButton = scenarioForm?.querySelector('.scenario-run');
    const scenarioOverlay = document.querySelector('#simulation-overlay');
    const scenarioOverlayLine = document.querySelector('#simulation-overlay-scenario');
    const scenarioOverlayError = document.querySelector('#simulation-overlay-error');
    const scenarioCancelButton = document.querySelector('#simulation-cancel');
    const scenarioCloseButton = document.querySelector('#simulation-close');
    const scenarioRetryButton = document.querySelector('#simulation-retry');
    const insightsEmpty = document.querySelector('#insights-empty');
    const insightsGrid = document.querySelector('#insights-grid');
    const insightsResults = window.myFarmScenarioData?.simulation?.results || null;
    const unitToSquareMeters = {
        feddan: 4200,
        hectare: 10000,
        acre: 4046.8564224,
    };
    const plotNames = ['P1', 'P2', 'P3', 'P4'];
    const plots = Array.isArray(mapData.plots) ? mapData.plots : [];
    const mapPlots = plotNames.map((name) => plots.find((plot) => plot.name === name));
    const simulationPlots = Array.isArray(mapData.simulationPlots) ? mapData.simulationPlots : [];
    const layers = new Map();
    let activeCategory = 'all';
    let selectedCropId = null;
    let selectedPlotId = mapPlots.find((plot) => plot)?.id ?? null;
    let map = null;

    function updateCards() {
        const query = search.value.trim().toLocaleLowerCase();
        let visibleCount = 0;

        cards.forEach((card) => {
            const matchesCategory = activeCategory === 'all' || card.dataset.category === activeCategory;
            const matchesSearch = card.dataset.cropName.includes(query);
            const isVisible = matchesCategory && matchesSearch;

            card.hidden = !isVisible;
            visibleCount += isVisible ? 1 : 0;
        });

        emptyMessage.hidden = visibleCount > 0;
    }

    function errorMessage(status) {
        if (status === 403) {
            return 'You do not have permission to change this plot.';
        }
        if (status === 404) {
            return 'Plot not found.';
        }
        if (status === 422) {
            return 'That crop could not be assigned.';
        }
        if (status === 500) {
            return 'Server error. Please try again.';
        }
        return 'Could not update the plot. Please try again.';
    }

    function formatArea(value) {
        if (value === null || value === undefined || value === '') {
            return 'Awaiting data';
        }
        const area = Number(value);
        return Number.isFinite(area)
            ? area.toLocaleString(undefined, { maximumFractionDigits: 2 })
            : 'Awaiting data';
    }

    function areaLabel(plot) {
        const area = formatArea(plot.area);
        return area === 'Awaiting data' ? area : `${area} ${mapData.areaUnit || ''}`.trim();
    }

    function areaInFeddan(area) {
        const numericArea = Number(area);
        if (area === null || area === undefined || !Number.isFinite(numericArea)) {
            return null;
        }

        const conversion = {
            feddan: 1,
            hectare: 10000 / 4200,
            acre: 4046.8564224 / 4200,
        }[String(mapData.areaUnit || '').toLowerCase()];

        return conversion === undefined ? null : numericArea * conversion;
    }

    function feddanLabel(area) {
        const value = areaInFeddan(area);
        return value === null
            ? 'Awaiting data'
            : `${value.toLocaleString(undefined, { maximumFractionDigits: 2 })} feddan`;
    }

    function plotCropLabel(plot) {
        return plot.crop?.name || (plot.status === 'fallow' ? 'Fallow' : 'Empty');
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        })[character]);
    }

    function updateSummary() {
        if (!summary) {
            return;
        }

        const selectedPlot = plots.find((plot) => plot.id === selectedPlotId);
        const selectedText = selectedPlot
            ? `${selectedPlot.name} · ${areaLabel(selectedPlot)}`
            : 'None';
        const plantedPlots = plots.filter((plot) => plot.crop !== null);
        const plantedArea = plantedPlots.reduce((total, plot) => total + Number(plot.area), 0);
        const hasValidPlantedArea = plantedPlots.every((plot) => plot.area !== null
            && plot.area !== undefined
            && plot.area !== ''
            && Number.isFinite(Number(plot.area)));
        const simulation = hasValidPlantedArea
            ? `${plantedPlots.length} planted ${plantedPlots.length === 1 ? 'plot' : 'plots'} · ${formatArea(plantedArea)} ${mapData.areaUnit || ''}`.trim()
            : `${plantedPlots.length} planted ${plantedPlots.length === 1 ? 'plot' : 'plots'} · Awaiting data`;

        summary.textContent = `Selected: ${selectedText} / Simulation: ${simulation}`;
    }

    function updateSelectedCrops() {
        if (!selectedCropsList) {
            return;
        }

        selectedCropsList.replaceChildren();
        const selectedPlots = plots.filter((plot) => plot.crop !== null);
        if (selectedCropsTotal) {
            const areas = selectedPlots.map((plot) => areaInFeddan(plot.area));
            const totalFeddan = areas.reduce((total, area) => total + (area ?? 0), 0);
            const areaText = areas.some((area) => area === null)
                ? 'Awaiting data'
                : `${totalFeddan.toLocaleString(undefined, { maximumFractionDigits: 2 })} feddan`;
            selectedCropsTotal.textContent = `${selectedPlots.length} ${selectedPlots.length === 1 ? 'crop' : 'crops'} · ${areaText}`;
        }

        if (selectedPlots.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'selected-crops-empty';
            empty.textContent = 'No crops selected yet.';
            selectedCropsList.append(empty);
            return;
        }

        selectedPlots.forEach((plot) => {
            const item = document.createElement('article');
            item.className = 'selected-crop-item';

            const details = document.createElement('div');
            const name = document.createElement('strong');
            name.textContent = `${plot.name} · ${plot.crop.name}`;
            details.append(name);

            const area = document.createElement('span');
            area.textContent = feddanLabel(plot.area);
            details.append(area);
            item.append(details);

            const removeButton = document.createElement('button');
            removeButton.className = 'selected-crop-remove';
            removeButton.type = 'button';
            removeButton.dataset.plotId = String(plot.id);
            removeButton.setAttribute('aria-label', `Remove ${plot.crop.name} from ${plot.name}`);
            removeButton.textContent = '×';
            removeButton.addEventListener('click', () => sendPlotRequest(plot, 'DELETE', null, removeButton));
            item.append(removeButton);

            selectedCropsList.append(item);
        });
    }

    function updatePlotDetails(plot) {
        if (!plotDetailsCard) {
            return;
        }

        plotDetailsCard.hidden = false;
        document.querySelector('#plot-details-name').textContent = plot.name || 'Awaiting data';
        document.querySelector('#plot-details-area').textContent = feddanLabel(plot.area);
        document.querySelector('#plot-details-crop').textContent = plot.crop?.name || 'Empty';
        document.querySelector('#plot-details-status').textContent = plot.status || 'Awaiting data';

        const insight = simulationPlots.find((item) => item?.name === plot.name);
        document.querySelector('#plot-details-suitability').textContent = insight?.suitability_pct ?? 'Awaiting data';
        document.querySelector('#plot-details-water').textContent = insight?.seasonal_water_demand_m3 ?? 'Awaiting data';
        document.querySelector('#plot-details-comment').textContent = insight?.comment ?? 'Awaiting data';
        plotDetailsRemove.hidden = !plot.crop;
        plotDetailsRemove.disabled = Boolean(plot.pending);
    }

    function updateScenarioPlots() {
        if (!scenarioPlotsList) {
            return;
        }

        scenarioPlotsList.replaceChildren();
        const selectedPlots = plots.filter((plot) => plot.crop !== null);

        if (selectedPlots.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'scenario-empty';
            empty.textContent = 'No plots with assigned crops.';
            scenarioPlotsList.append(empty);
            return;
        }

        selectedPlots.forEach((plot) => {
            const item = document.createElement('li');
            item.textContent = `${plot.name} · ${plot.crop.name}`;
            scenarioPlotsList.append(item);
        });
    }

    function clearScenarioErrors() {
        scenarioForm?.querySelectorAll('[data-error-for]').forEach((element) => {
            element.textContent = '';
        });
        scenarioForm?.querySelectorAll('[aria-invalid="true"]').forEach((element) => {
            element.removeAttribute('aria-invalid');
        });
    }

    function showScenarioErrors(errors) {
        clearScenarioErrors();
        const messages = [];

        Object.entries(errors || {}).forEach(([field, fieldMessages]) => {
            const message = Array.isArray(fieldMessages) ? fieldMessages[0] : String(fieldMessages);
            const fieldName = field.startsWith('plot_ids') ? 'plot_ids' : field;
            const fieldError = scenarioForm?.querySelector(`[data-error-for="${fieldName}"]`);
            const input = scenarioForm?.elements.namedItem(fieldName);

            if (fieldError) {
                fieldError.textContent = message;
            }
            if (input && 'setAttribute' in input) {
                input.setAttribute('aria-invalid', 'true');
            }
            messages.push(message);
        });

        if (scenarioMessage) {
            scenarioMessage.textContent = messages.join(' ');
            scenarioMessage.hidden = false;
        }
    }

    function scenarioFormValues() {
        return Object.fromEntries(new FormData(scenarioForm).entries());
    }

    function setScenarioFormValues(settings) {
        Object.entries(settings).forEach(([name, value]) => {
            const input = scenarioForm.elements.namedItem(name);
            if (input) {
                input.value = value ?? '';
            }
        });
    }

    let savedScenarioValues = scenarioForm ? scenarioFormValues() : {};
    let savedScenarioPlots = scenarioData.simulation?.plots || [];
    let activeSimulationId = scenarioData.simulation?.id || null;
    let pollTimer = null;
    let pollController = null;

    function currentScenarioPlots() {
        return plots
            .filter((plot) => plot.crop !== null)
            .map((plot) => ({
                id: String(plot.id),
                crop_id: plot.crop.id === undefined ? null : String(plot.crop.id),
            }))
            .sort((left, right) => left.id.localeCompare(right.id));
    }

    function scenarioPlotsAreSaved() {
        const savedPlots = (savedScenarioPlots || [])
            .map((plot) => ({
                id: String(plot.id),
                crop_id: plot.crop_id === undefined || plot.crop_id === null ? null : String(plot.crop_id),
            }))
            .sort((left, right) => left.id.localeCompare(right.id));
        const currentPlots = currentScenarioPlots();

        return savedPlots.length > 0
            && JSON.stringify(savedPlots) === JSON.stringify(currentPlots);
    }

    function scenarioFormIsSaved() {
        return JSON.stringify(scenarioFormValues()) === JSON.stringify(savedScenarioValues);
    }

    function updateScenarioReadiness() {
        if (!scenarioForm || !scenarioReadiness) {
            return;
        }

        const assignedCount = plots.filter((plot) => plot.crop !== null).length;
        const requiredInputs = [
            ['planting_season', 'Planting season'],
            ['start_date', 'Start date'],
            ['duration_days', 'Duration'],
            ['water_scenario', 'Water availability scenario'],
            ['climate_scenario', 'Climate scenario'],
        ];
        const missingInput = requiredInputs.find(([name]) => {
            const input = scenarioForm.elements.namedItem(name);
            return !input?.value || !input.checkValidity();
        })?.[1];
        const missing = assignedCount === 0
            ? 'Assign a crop to at least one plot'
            : missingInput;

        scenarioReadiness.textContent = `${assignedCount} ${assignedCount === 1 ? 'plot' : 'plots'} assigned · ${missing || 'All inputs ready'}`;

        const startDate = scenarioForm.elements.namedItem('start_date')?.value;
        const duration = Number(scenarioForm.elements.namedItem('duration_days')?.value);
        if (!startDate || !Number.isInteger(duration) || duration < 1 || !scenarioEndDate) {
            scenarioEndDate.textContent = 'Awaiting data';
            return;
        }

        const endDate = new Date(`${startDate}T00:00:00Z`);
        if (Number.isNaN(endDate.getTime())) {
            scenarioEndDate.textContent = 'Awaiting data';
            return;
        }

        endDate.setUTCDate(endDate.getUTCDate() + duration);
        scenarioEndDate.textContent = endDate.toISOString().slice(0, 10);
    }

    function updateRunAvailability() {
        if (!scenarioRunButton) {
            return;
        }

        const canRun = Boolean(activeSimulationId)
            && scenarioFormIsSaved()
            && scenarioPlotsAreSaved()
            && scenarioData.simulation?.status !== 'running';
        scenarioRunButton.disabled = !canRun;
        scenarioRunButton.title = canRun
            ? 'Run the saved scenario'
            : 'Save your scenario and selected plots before running it';
    }

    function routeFor(template, simulationId) {
        return template.replace('__simulation__', encodeURIComponent(simulationId));
    }

    function formatInsightValue(value) {
        if (value === null || value === undefined || value === '') {
            return 'Awaiting data';
        }
        if (typeof value === 'boolean' || typeof value === 'number' || typeof value === 'string') {
            return String(value);
        }
        return null;
    }

    function renderInsightValue(container, value) {
        container.replaceChildren();
        const scalar = formatInsightValue(value);

        if (scalar !== null) {
            const text = document.createElement('p');
            text.className = 'insight-value';
            text.textContent = scalar;
            container.append(text);
            return;
        }

        const entries = Array.isArray(value)
            ? value.map((item, index) => [String(index + 1), item])
            : Object.entries(value);
        if (entries.length === 0) {
            const empty = document.createElement('p');
            empty.className = 'insight-value';
            empty.textContent = 'Awaiting data';
            container.append(empty);
            return;
        }

        const list = document.createElement('dl');
        list.className = 'insight-data-list';
        entries.forEach(([key, item]) => {
            const row = document.createElement('div');
            const term = document.createElement('dt');
            term.textContent = key.replaceAll('_', ' ');
            const detail = document.createElement('dd');
            const itemText = formatInsightValue(item);
            if (itemText !== null) {
                detail.textContent = itemText;
            } else if (Array.isArray(item)) {
                detail.textContent = item.map((value) => formatInsightValue(value) ?? JSON.stringify(value)).join(', ');
            } else {
                detail.textContent = Object.entries(item)
                    .map(([itemKey, itemValue]) => `${itemKey.replaceAll('_', ' ')}: ${formatInsightValue(itemValue) ?? JSON.stringify(itemValue)}`)
                    .join(' · ');
            }
            row.append(term, detail);
            list.append(row);
        });
        container.append(list);
    }

    function renderWaterChart(container, value) {
        const monthly = Array.isArray(value)
            ? value
            : Array.isArray(value?.monthly)
                ? value.monthly
                : value && typeof value === 'object'
                    ? Object.entries(value).map(([month, demand]) => ({ month, demand }))
                    : [];
        const points = monthly.map((entry, index) => ({
            label: entry?.month ?? entry?.label ?? String(index + 1),
            value: Number(entry?.demand_m3 ?? entry?.demand ?? entry?.water_demand ?? entry?.value),
        })).filter((point) => Number.isFinite(point.value));

        if (points.length === 0) {
            renderInsightValue(container, value);
            return;
        }

        container.replaceChildren();
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('viewBox', '0 0 360 150');
        svg.setAttribute('role', 'img');
        svg.setAttribute('aria-label', 'Monthly water demand chart');
        svg.classList.add('water-demand-chart');
        const maximum = Math.max(...points.map((point) => Math.max(0, point.value)), 1);
        const columnWidth = 300 / points.length;

        points.forEach((point, index) => {
            const height = Math.max(0, point.value) / maximum * 96;
            const x = 30 + index * columnWidth + columnWidth * 0.15;
            const bar = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
            bar.setAttribute('x', String(x));
            bar.setAttribute('y', String(112 - height));
            bar.setAttribute('width', String(Math.max(4, columnWidth * 0.7)));
            bar.setAttribute('height', String(height));
            bar.setAttribute('rx', '3');
            bar.classList.add('water-demand-bar');
            svg.append(bar);

            const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
            label.setAttribute('x', String(x + columnWidth * 0.35));
            label.setAttribute('y', '134');
            label.setAttribute('text-anchor', 'middle');
            label.textContent = String(point.label);
            svg.append(label);
        });
        container.append(svg);
    }

    function renderInsights(results) {
        if (!insightsEmpty || !insightsGrid) {
            return;
        }
        if (!results || typeof results !== 'object') {
            insightsEmpty.hidden = false;
            insightsGrid.hidden = true;
            return;
        }

        insightsEmpty.hidden = true;
        insightsGrid.hidden = false;
        const summary = document.querySelector('#insight-summary');
        if (summary) {
            summary.textContent = formatInsightValue(results.summary) || '';
            summary.hidden = !summary.textContent;
        }
        insightsGrid.querySelectorAll('[data-insight-field]').forEach((container) => {
            const key = container.dataset.insightField;
            const value = ({
                water_demand: results.water?.monthly,
                soil_compatibility: results.soil?.compatibility,
                scenario_comparison: results.scenarios,
                climate_risk: results.climate,
                ai_recommendations: results.recommendations,
                crop_rotation: results.rotation,
            })[key] ?? results[key];

            if (key === 'water_demand') {
                renderWaterChart(container, value);
            } else {
                renderInsightValue(container, value);
            }
        });

        const soilValues = window.myFarmSoilValues || {};
        const conditionValues = {
            'soil.type': results.soil?.type ?? soilValues.soilType,
            'soil.ph': results.soil?.ph ?? soilValues.ph,
            'soil.salinity': results.soil?.salinity ?? soilValues.salinity,
            'soil.moisture_pct': results.soil?.moisture_pct,
            'soil.health_score': results.soil?.health_score,
            'soil.nitrogen': results.soil?.nitrogen ?? soilValues.nitrogen,
            'soil.phosphorus': results.soil?.phosphorus ?? soilValues.phosphorus,
            'soil.potassium': results.soil?.potassium ?? soilValues.potassium,
            'water.irrigation_source': results.water?.source ?? soilValues.irrigationSource,
            'water.available_m3': results.water?.available_m3,
            'water.required_m3': results.water?.required_m3,
            'water.balance_m3': results.water?.balance_m3,
            'climate.observations': [
                results.climate?.temperature_c === null || results.climate?.temperature_c === undefined
                    ? null
                    : `${results.climate.temperature_c} °C`,
                results.climate?.rainfall_mm === null || results.climate?.rainfall_mm === undefined
                    ? null
                    : `${results.climate.rainfall_mm} mm rainfall`,
                results.climate?.humidity_pct === null || results.climate?.humidity_pct === undefined
                    ? null
                    : `${results.climate.humidity_pct}% humidity`,
                results.climate?.risk_level,
                results.climate?.risk_note,
            ].filter((value) => value !== null && value !== undefined).join(' · ') || null,
        };
        Object.entries(conditionValues).forEach(([key, value]) => {
            const field = document.querySelector(`[data-condition-field="${key}"]`);
            if (field) {
                field.textContent = formatInsightValue(value) || 'Awaiting data';
            }
        });
    }

    function updateOverlayScenario() {
        if (!scenarioOverlayLine) {
            return;
        }
        const formValues = scenarioFormValues();
        const selectedPlots = plots.filter((plot) => plot.crop !== null);
        const plotNames = selectedPlots.map((plot) => `${plot.name} · ${plot.crop.name}`);
        const duration = formValues.duration_days ? `${formValues.duration_days} days` : 'Awaiting data';
        scenarioOverlayLine.textContent = `${scenarioData.farmName || 'Farm'} · ${plotNames.join(', ') || 'No planted plots'} · ${duration}`;
    }

    function showSimulationOverlay() {
        if (scenarioOverlay) {
            scenarioOverlay.hidden = false;
        }
        if (scenarioOverlayError) {
            scenarioOverlayError.hidden = true;
        }
        if (scenarioCancelButton) {
            scenarioCancelButton.hidden = false;
        }
        if (scenarioCloseButton) {
            scenarioCloseButton.hidden = true;
        }
        if (scenarioRetryButton) {
            scenarioRetryButton.hidden = true;
        }
        updateOverlayScenario();
    }

    function showSimulationStage(stage) {
        scenarioOverlay?.querySelectorAll('[data-stage]').forEach((item) => {
            item.classList.toggle('is-current', item.dataset.stage === stage);
        });
    }

    function stopPolling() {
        if (pollTimer !== null) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
        pollController?.abort();
        pollController = null;
    }

    function finishSimulation(state) {
        stopPolling();
        scenarioData.simulation = { ...(scenarioData.simulation || {}), ...state };
        if (state.status === 'completed') {
            renderInsights(state.results);
            if (scenarioOverlay) {
                scenarioOverlay.hidden = true;
            }
            updateRunAvailability();
            return;
        }
        if (state.status === 'cancelled') {
            if (scenarioOverlay) {
                scenarioOverlay.hidden = true;
            }
            updateRunAvailability();
            return;
        }

        if (scenarioOverlayError) {
            scenarioOverlayError.textContent = state.error || 'Simulation failed.';
            scenarioOverlayError.hidden = false;
        }
        if (scenarioCancelButton) {
            scenarioCancelButton.hidden = true;
        }
        if (scenarioCloseButton) {
            scenarioCloseButton.hidden = false;
        }
        if (scenarioRetryButton) {
            scenarioRetryButton.hidden = false;
        }
        updateRunAvailability();
    }

    async function pollSimulation() {
        if (!activeSimulationId) {
            return;
        }

        pollController?.abort();
        pollController = new AbortController();
        try {
            const response = await fetch(routeFor(scenarioData.showUrl, activeSimulationId), {
                headers: { Accept: 'application/json' },
                signal: pollController.signal,
            });
            if (!response.ok) {
                throw new Error('Could not check simulation status.');
            }

            const state = await response.json();
            scenarioData.simulation = { ...(scenarioData.simulation || {}), ...state, id: activeSimulationId };
            if (state.status === 'running') {
                showSimulationStage(state.stage);
                return;
            }
            if (['completed', 'failed', 'cancelled'].includes(state.status)) {
                finishSimulation(state);
            }
        } catch (error) {
            if (error.name !== 'AbortError' && scenarioOverlayError) {
                scenarioOverlayError.textContent = error.message;
                scenarioOverlayError.hidden = false;
            }
        }
    }

    function beginPolling() {
        stopPolling();
        pollSimulation();
        pollTimer = window.setInterval(pollSimulation, 2000);
    }

    async function runSimulation() {
        if (!activeSimulationId || !scenarioRunButton || scenarioRunButton.disabled || !csrfToken) {
            return;
        }

        showSimulationOverlay();
        showSimulationStage('validating');
        scenarioRunButton.disabled = true;
        renderInsights(null);
        try {
            const response = await fetch(routeFor(scenarioData.runUrl, activeSimulationId), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });
            const payload = await response.json();
            if (!response.ok) {
                throw new Error(payload.message || 'Could not start this simulation.');
            }

            scenarioData.simulation = { ...(scenarioData.simulation || {}), ...payload.simulation };
            beginPolling();
        } catch (error) {
            if (scenarioOverlayError) {
                scenarioOverlayError.textContent = error.message;
                scenarioOverlayError.hidden = false;
            }
            if (scenarioCancelButton) {
                scenarioCancelButton.hidden = true;
            }
            if (scenarioCloseButton) {
                scenarioCloseButton.hidden = false;
            }
            if (scenarioRetryButton) {
                scenarioRetryButton.hidden = false;
            }
            updateRunAvailability();
        }
    }

    async function cancelSimulation() {
        if (!activeSimulationId || !csrfToken) {
            return;
        }
        scenarioCancelButton.disabled = true;
        try {
            const response = await fetch(routeFor(scenarioData.cancelUrl, activeSimulationId), {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
            });
            const payload = await response.json();
            if (!response.ok) {
                throw new Error(payload.message || 'Could not cancel this simulation.');
            }
            finishSimulation({ status: 'cancelled' });
        } catch (error) {
            if (scenarioOverlayError) {
                scenarioOverlayError.textContent = error.message;
                scenarioOverlayError.hidden = false;
            }
        } finally {
            scenarioCancelButton.disabled = false;
        }
    }

    if (scenarioForm) {
        if (scenarioData.settings) {
            setScenarioFormValues(scenarioData.settings);
        }
        savedScenarioValues = scenarioFormValues();

        scenarioForm.addEventListener('reset', (event) => {
            event.preventDefault();
            setScenarioFormValues(savedScenarioValues);
            clearScenarioErrors();
            if (scenarioMessage) {
                scenarioMessage.hidden = true;
            }
            updateRunAvailability();
            updateScenarioReadiness();
        });
        scenarioForm.addEventListener('input', updateRunAvailability);
        scenarioForm.addEventListener('input', updateScenarioReadiness);
        scenarioForm.addEventListener('change', () => {
            updateRunAvailability();
            updateScenarioReadiness();
        });

        scenarioForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            clearScenarioErrors();
            if (scenarioMessage) {
                scenarioMessage.hidden = true;
            }
            if (!csrfToken) {
                showScenarioErrors({ form: ['Could not verify the request. Refresh the page and try again.'] });
                return;
            }

            const saveButton = scenarioForm.querySelector('.scenario-save');
            if (saveButton) {
                saveButton.disabled = true;
            }

            try {
                const response = await fetch(scenarioData.saveUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        ...scenarioFormValues(),
                        plot_ids: plots.filter((plot) => plot.crop !== null).map((plot) => plot.id),
                    }),
                });
                const payload = await response.json();

                if (!response.ok) {
                    showScenarioErrors(payload.errors || { form: [payload.message || 'Could not save this scenario.'] });
                    return;
                }
                if (!payload.success || !payload.simulation) {
                    throw new Error('The server returned an invalid scenario response.');
                }

                setScenarioFormValues(payload.simulation.settings);
                scenarioForm.elements.namedItem('name').value = payload.simulation.name ?? '';
                savedScenarioValues = scenarioFormValues();
                activeSimulationId = payload.simulation.id;
                savedScenarioPlots = payload.simulation.plots || [];
                scenarioData.simulation = {
                    ...payload.simulation,
                    status: payload.simulation.status,
                    results: null,
                };
                renderInsights(null);
                if (scenarioMessage) {
                    scenarioMessage.textContent = 'Scenario saved successfully.';
                    scenarioMessage.hidden = false;
                }
                updateRunAvailability();
                updateScenarioReadiness();
            } catch (error) {
                showScenarioErrors({ form: [error.message || 'Could not save this scenario. Please try again.'] });
            } finally {
                if (saveButton) {
                    saveButton.disabled = false;
                }
            }
        });
    }

    function selectPlot(plot) {
        selectedPlotId = plot.id;
        updatePlotStyles();
        updateSummary();
        updatePlotDetails(plot);
    }

    function updatePlotStyles() {
        layers.forEach(({ layer, plot }) => {
            const selected = plot.id === selectedPlotId;
            layer.setStyle({
                color: selected ? '#f5c542' : plot.crop ? '#43a5ff' : '#f2f5f7',
                weight: selected ? 3 : 2.5,
                fillColor: selected ? '#f5c542' : plot.crop ? '#2788ce' : '#dfe7ed',
                fillOpacity: selected ? 0.38 : 0.15,
            });
            layer.setTooltipContent(createPlotLabel(plot));
        });
    }

    function createPlotLabel(plot) {
        return `<strong>${escapeHtml(plot.name)}</strong><span>${escapeHtml(plotCropLabel(plot))}</span>`;
    }

    function createPopup(plot) {
        const content = document.createElement('div');
        content.className = 'farm-plot-popup';

        const title = document.createElement('strong');
        title.textContent = plot.name;
        content.append(title);

        const area = document.createElement('p');
        area.textContent = `Area: ${areaLabel(plot)}`;
        content.append(area);

        const crop = document.createElement('p');
        crop.textContent = `Crop: ${plot.crop?.name || 'Empty'}`;
        content.append(crop);

        if (plot.crop) {
            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'farm-plot-remove';
            removeButton.textContent = 'Remove crop';
            removeButton.addEventListener('click', () => sendPlotRequest(plot, 'DELETE', null, removeButton));
            content.append(removeButton);
        }

        return content;
    }

    function setMapError(message) {
        if (mapElement) {
            mapElement.hidden = true;
        }
        if (mapMessage) {
            mapMessage.textContent = message;
            mapMessage.hidden = false;
        }
    }

    function validCoordinate(value, minimum, maximum) {
        const coordinate = Number(value);
        return value !== null && value !== '' && Number.isFinite(coordinate)
            && coordinate >= minimum && coordinate <= maximum;
    }

    function initializeMap() {
        if (!mapElement) {
            return;
        }

        if (!window.L) {
            setMapError('The map could not be loaded. Please try again later.');
            return;
        }

        const latitude = Number(mapData.latitude);
        const longitude = Number(mapData.longitude);
        const farmArea = Number(mapData.area);
        const squareMetersPerUnit = unitToSquareMeters[String(mapData.areaUnit || '').toLowerCase()];
        const totalAreaSquareMeters = farmArea * squareMetersPerUnit;
        const longitudeScale = Math.cos(latitude * Math.PI / 180);

        if (!validCoordinate(mapData.latitude, -90, 90)
            || !validCoordinate(mapData.longitude, -180, 180)) {
            setMapError('A valid saved farm location is needed to show the map.');
            return;
        }
        if (!Number.isFinite(totalAreaSquareMeters) || totalAreaSquareMeters <= 0
            || !squareMetersPerUnit) {
            setMapError('A supported farm area is needed to calculate the plot layout.');
            return;
        }
        if (mapPlots.some((plot) => !plot)) {
            setMapError('The map needs the four saved plots before it can show their layout.');
            return;
        }
        if (Math.abs(longitudeScale) < 0.01) {
            setMapError('The plot layout cannot be estimated reliably at this latitude.');
            return;
        }

        const gridSideMeters = Math.sqrt(totalAreaSquareMeters);
        const plotSideMeters = gridSideMeters / 2;
        const halfGridSideMeters = gridSideMeters / 2;
        const metersPerLatitudeDegree = 111320;
        const metersPerLongitudeDegree = metersPerLatitudeDegree * longitudeScale;
        const bounds = [];

        map = window.L.map(mapElement).setView([latitude, longitude], 16);
        window.L.tileLayer(
            'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            {
                attribution: 'Tiles &copy; Esri — Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community',
                maxZoom: 19,
            },
        ).addTo(map);

        mapPlots.forEach((plot, index) => {
            const row = Math.floor(index / 2);
            const column = index % 2;
            const northMeters = halfGridSideMeters - row * plotSideMeters;
            const southMeters = northMeters - plotSideMeters;
            const westMeters = -halfGridSideMeters + column * plotSideMeters;
            const eastMeters = westMeters + plotSideMeters;
            const polygon = [
                [latitude + northMeters / metersPerLatitudeDegree, longitude + westMeters / metersPerLongitudeDegree],
                [latitude + northMeters / metersPerLatitudeDegree, longitude + eastMeters / metersPerLongitudeDegree],
                [latitude + southMeters / metersPerLatitudeDegree, longitude + eastMeters / metersPerLongitudeDegree],
                [latitude + southMeters / metersPerLatitudeDegree, longitude + westMeters / metersPerLongitudeDegree],
            ];
            const layer = window.L.polygon(polygon).addTo(map);

            layer.bindTooltip(createPlotLabel(plot), {
                permanent: true,
                direction: 'center',
                className: 'farm-plot-label',
                opacity: 1,
                interactive: false,
            });
            layer.bindPopup(() => createPopup(plot));
            layer.on('click', () => {
                if (selectedCropId !== null) {
                    selectPlot(plot);
                    sendPlotRequest(plot, 'POST', { crop_id: selectedCropId });
                    return;
                }
                selectPlot(plot);
                selectPlot(plot);
            });

            layers.set(plot.id, { layer, plot });
            bounds.push(...polygon);
        });

        selectedPlotId = mapPlots[0].id;
        updatePlotStyles();
        updateSummary();
        map.invalidateSize();
        map.fitBounds(window.L.latLngBounds(bounds), { padding: [40, 40], maxZoom: 18 });
    }

    plotDetailsRemove?.addEventListener('click', () => {
        const plot = plots.find((item) => item.id === selectedPlotId);
        if (plot) {
            sendPlotRequest(plot, 'DELETE', null, plotDetailsRemove);
        }
    });

    boundaryToggle?.addEventListener('click', () => {
        const visible = boundaryToggle.getAttribute('aria-pressed') === 'true';
        layers.forEach(({ layer }) => {
            if (visible) {
                layer.remove();
            } else {
                layer.addTo(map);
            }
        });
        boundaryToggle.setAttribute('aria-pressed', String(!visible));
        boundaryToggle.textContent = visible ? 'Show farm boundary' : 'Hide farm boundary';
    });

    async function sendPlotRequest(plot, method, body = null, trigger = null) {
        if (!csrfToken || plot.pending) {
            if (!csrfToken && requestMessage) {
                requestMessage.textContent = 'Could not verify the request. Refresh the page and try again.';
                requestMessage.hidden = false;
            }
            return;
        }

        if (requestMessage) {
            requestMessage.hidden = true;
        }
        plot.pending = true;
        if (trigger) {
            trigger.disabled = true;
        }

        try {
            const response = await fetch(method === 'POST' ? plot.assignUrl : plot.removeUrl, {
                method,
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: body === null ? null : JSON.stringify(body),
            });
            const payload = await response.json();

            if (!response.ok) {
                throw Object.assign(new Error('Plot request failed.'), { status: response.status });
            }
            if (!payload.success || !payload.plot) {
                throw new Error('Invalid plot response.');
            }

            Object.assign(plot, payload.plot);
            if (map) {
                updatePlotStyles();
            }
            if (plot.id === selectedPlotId && plotDetailsCard && !plotDetailsCard.hidden) {
                updatePlotDetails(plot);
            }
            updateSummary();
            updateSelectedCrops();
            updateScenarioPlots();
            updateScenarioReadiness();
            updateRunAvailability();
            const layer = layers.get(plot.id)?.layer;
            if (layer?.isPopupOpen()) {
                layer.setPopupContent(createPopup(plot));
            }
        } catch (error) {
            if (requestMessage) {
                requestMessage.textContent = errorMessage(error.status);
                requestMessage.hidden = false;
            }
        } finally {
            plot.pending = false;
            if (trigger) {
                trigger.disabled = false;
            }
        }
    }

    scenarioRunButton?.addEventListener('click', runSimulation);
    scenarioCancelButton?.addEventListener('click', cancelSimulation);
    scenarioCloseButton?.addEventListener('click', () => {
        if (scenarioOverlay) {
            scenarioOverlay.hidden = true;
        }
    });
    scenarioRetryButton?.addEventListener('click', runSimulation);
    window.addEventListener('beforeunload', stopPolling);

    function selectCrop(button) {
        selectedCropId = selectedCropId === button.dataset.cropId ? null : button.dataset.cropId;

        cropButtons.forEach((item) => {
            const isSelected = item.dataset.cropId === selectedCropId;
            item.classList.toggle('is-selected', isSelected);
            item.setAttribute('aria-pressed', String(isSelected));
            item.textContent = isSelected ? 'Selected' : 'Select for plot';
        });

        if (selectedCropId) {
            selectionHint.textContent = map
                ? `Selected ${button.dataset.cropName}. Now click a plot to assign it.`
                : 'Save your farm location before assigning crops from the map.';
        } else {
            selectionHint.textContent = map
                ? 'Choose a crop from the library, then select a plot, or click a plot to inspect it.'
                : 'Your farm map needs saved coordinates before plots can be selected.';
        }
    }

    search.addEventListener('input', updateCards);
    filters.forEach((filter) => {
        filter.addEventListener('click', () => {
            activeCategory = filter.dataset.categoryFilter;

            filters.forEach((item) => {
                const isActive = item === filter;
                item.classList.toggle('is-active', isActive);
                item.setAttribute('aria-pressed', String(isActive));
            });

            updateCards();
        });
    });
    cropButtons.forEach((button) => {
        button.addEventListener('click', () => selectCrop(button));
    });

    initializeMap();
    updateSummary();
    updateSelectedCrops();
    updateScenarioPlots();
    updateScenarioReadiness();
    updateRunAvailability();
    renderInsights(insightsResults);
    if (scenarioData.simulation?.status === 'running') {
        showSimulationOverlay();
        showSimulationStage(scenarioData.simulation.stage);
        beginPolling();
    } else if (scenarioData.simulation?.status === 'failed') {
        showSimulationOverlay();
        finishSimulation(scenarioData.simulation);
    }
    if (!map) {
        selectionHint.textContent = 'Your farm map needs saved coordinates before plots can be selected.';
    }
    updateCards();
});
