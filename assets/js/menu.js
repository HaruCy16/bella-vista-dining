(() => {
  const filters = document.querySelectorAll('[data-menu-filter]');
  const cards = document.querySelectorAll('[data-menu-card]');
  const empty = document.querySelector('[data-menu-empty]');
  if (!filters.length || !cards.length) return;
  filters.forEach((filter) => filter.addEventListener('click', () => {
    const category = filter.dataset.menuFilter;
    filters.forEach((item) => { item.classList.toggle('is-active', item === filter); item.setAttribute('aria-selected', String(item === filter)); });
    let visibleCount = 0;
    cards.forEach((card) => {
      const visible = category === 'all' || card.dataset.menuCategory === category;
      card.hidden = !visible;
      if (visible) visibleCount += 1;
    });
    if (empty) empty.hidden = visibleCount > 0;
  }));
})();
