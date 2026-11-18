(() => {
    document.querySelectorAll('[data-percent]').forEach(bar => {
        bar.style.setProperty('--percent', `${bar.dataset.percent}%`);
    });
})();
