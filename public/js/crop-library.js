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
    const plotCards = Array.from(document.querySelectorAll('.plot-card'));
    const requestMessage = document.querySelector('#plot-request-message');
    const selectionHint = document.querySelector('#plot-selection-hint');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    let activeCategory = 'all';
    let selectedCropId = null;

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

    function selectCrop(button) {
        selectedCropId = selectedCropId === button.dataset.cropId ? null : button.dataset.cropId;

        cropButtons.forEach((item) => {
            const isSelected = item.dataset.cropId === selectedCropId;
            item.classList.toggle('is-selected', isSelected);
            item.setAttribute('aria-pressed', String(isSelected));
            item.textContent = isSelected ? 'Selected' : 'Select for plot';
        });

        selectionHint.textContent = selectedCropId
            ? `Selected ${button.dataset.cropName}. Now choose a plot.`
            : 'Choose a crop from the library, then select a plot.';

        plotCards.forEach((plot) => {
            plot.classList.toggle('is-selectable', selectedCropId !== null);
            plot.querySelector('.plot-card-select').disabled = selectedCropId === null;
        });
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

    function updatePlot(plotCard, plot) {
        plotCard.querySelector('[data-plot-crop]').textContent = plot.crop?.name ?? 'Empty';
        plotCard.querySelector('.plot-card-remove').hidden = plot.crop === null;
    }

    async function sendPlotRequest(plotCard, method, body = null) {
        requestMessage.hidden = true;
        const controls = Array.from(plotCard.querySelectorAll('button'));
        controls.forEach((control) => {
            control.disabled = true;
        });

        try {
            const response = await fetch(
                method === 'POST' ? plotCard.dataset.assignUrl : plotCard.dataset.removeUrl,
                {
                    method,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: body === null ? null : JSON.stringify(body),
                },
            );
            let payload = null;
            try {
                payload = await response.json();
            } catch {
                payload = null;
            }

            if (!response.ok) {
                throw Object.assign(new Error('Plot request failed.'), { status: response.status });
            }

            if (!payload.success || !payload.plot) {
                throw new Error('Invalid plot response.');
            }

            updatePlot(plotCard, payload.plot);
        } catch (error) {
            requestMessage.textContent = errorMessage(error.status);
            requestMessage.hidden = false;
        } finally {
            controls.forEach((control) => {
                control.disabled = control.classList.contains('plot-card-select') && selectedCropId === null;
            });
        }
    }

    cropButtons.forEach((button) => {
        button.addEventListener('click', () => selectCrop(button));
    });

    plotCards.forEach((plotCard) => {
        const selectButton = plotCard.querySelector('.plot-card-select');
        const removeButton = plotCard.querySelector('.plot-card-remove');

        selectButton.addEventListener('click', () => {
            if (selectedCropId === null) {
                return;
            }

            sendPlotRequest(plotCard, 'POST', { crop_id: selectedCropId });
        });

        removeButton.addEventListener('click', () => {
            sendPlotRequest(plotCard, 'DELETE');
        });
    });

    updateCards();
});
