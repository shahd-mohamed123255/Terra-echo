document.addEventListener('DOMContentLoaded', () => {
    const library = document.querySelector('.crop-library');

    if (!library) {
        return;
    }

    const search = library.querySelector('.crop-library-search');
    const cards = Array.from(library.querySelectorAll('.crop-card'));
    const filters = Array.from(library.querySelectorAll('[data-category-filter]'));
    const emptyMessage = library.querySelector('.crop-library-empty');
    let activeCategory = 'all';

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

    updateCards();
});
