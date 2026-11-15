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
    const unitToSquareMeters = {
        feddan: 4200,
        hectare: 10000,
        acre: 4046.8564224,
    };
    const plotNames = ['P1', 'P2', 'P3', 'P4'];
    const plots = Array.isArray(mapData.plots) ? mapData.plots : [];
    const mapPlots = plotNames.map((name) => plots.find((plot) => plot.name === name));
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
        const area = Number(value);
        return Number.isFinite(area)
            ? area.toLocaleString(undefined, { maximumFractionDigits: 2 })
            : 'Awaiting data';
    }

    function areaLabel(plot) {
        return `${formatArea(plot.area)} ${mapData.areaUnit || ''}`.trim();
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
        const hasValidPlantedArea = plantedPlots.every((plot) => Number.isFinite(Number(plot.area)));
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
            area.textContent = `${formatArea(plot.area)} ${mapData.areaUnit || ''}`.trim();
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

    function selectPlot(plot) {
        selectedPlotId = plot.id;
        updatePlotStyles();
        updateSummary();
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
                    sendPlotRequest(plot, 'POST', { crop_id: selectedCropId });
                    return;
                }

                selectPlot(plot);
                layer.openPopup();
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
            updateSummary();
            updateSelectedCrops();
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
    if (!map) {
        selectionHint.textContent = 'Your farm map needs saved coordinates before plots can be selected.';
    }
    updateCards();
});
