(() => {
    document.querySelectorAll('[data-percent]').forEach(bar => {
        bar.style.setProperty('--percent', `${bar.dataset.percent}%`);
    });

    const toggle = document.querySelector('.password-toggle');
    const password = document.querySelector('#admin-password');
    const eyeOpen = toggle && toggle.querySelector('.eye-open');
    const eyeOff = toggle && toggle.querySelector('.eye-off');
    if (!toggle || !password || !eyeOpen || !eyeOff) return;

    toggle.addEventListener('click', () => {
        const visible = password.type === 'password';
        password.type = visible ? 'text' : 'password';
        const label = visible ? toggle.dataset.hideLabel : toggle.dataset.showLabel;
        eyeOpen.classList.toggle('is-hidden', visible);
        eyeOff.classList.toggle('is-hidden', !visible);
        toggle.setAttribute('aria-label', label);
        toggle.title = label;
        toggle.setAttribute('aria-pressed', String(visible));
    });
})();
