(() => {
    const filter = document.querySelector('#tagasiside-filter');
    if (!filter) return;

    document.querySelectorAll('[data-percent]').forEach(bar => {
        bar.style.setProperty('--percent', `${bar.dataset.percent}%`);
    });

    filter.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', () => filter.requestSubmit());
    });
})();
