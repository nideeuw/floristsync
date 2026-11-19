const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---- Angka count-up: elemen dengan atribut data-countup ---- */
const countUp = (el) => {
    const m = el.textContent.trim().match(/^(\D*?)(\d{1,3}(?:[.,]\d{3})+|\d+)(\D*)$/);
    if (! m) return;

    const prefix = m[1];
    const sep = (m[2].match(/[.,]/) || ['.'])[0];
    const target = parseInt(m[2].replace(/[.,]/g, ''), 10);
    const suffix = m[3];
    if (target < 2) return;

    const format = (n) => n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, sep);
    const duration = 900;
    const start = performance.now();

    const tick = (now) => {
        const p = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        el.textContent = prefix + format(Math.round(target * eased)) + suffix;
        if (p < 1) requestAnimationFrame(tick);
    };

    el.textContent = prefix + '0' + suffix;
    requestAnimationFrame(tick);
};

if (! reduceMotion) {
    document.querySelectorAll('[data-countup]').forEach(countUp);
}

/* ---- Bar loading tipis di atas halaman ---- */
const bar = document.createElement('div');
bar.id = 'fs-progress';
document.body.appendChild(bar);

let barTimer;
const startBar = () => {
    clearTimeout(barTimer);
    bar.style.opacity = '1';
    bar.style.width = '0';
    requestAnimationFrame(() => { bar.style.width = '75%'; });
    barTimer = setTimeout(resetBar, 10000);
};
const resetBar = () => {
    bar.style.opacity = '0';
    setTimeout(() => { bar.style.width = '0'; }, 300);
};

document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

    const a = e.target.closest('a[href]');
    if (! a || a.target || a.hasAttribute('download') || a.dataset.noProgress !== undefined) return;

    const url = new URL(a.href, location.href);
    if (url.origin !== location.origin) return;
    if (url.pathname === location.pathname && url.search === location.search) return;
    if (/\.(pdf|xlsx?|csv|zip)$/i.test(url.pathname)) return;

    startBar();
});

/* ---- Spinner di tombol submit + cegah klik ganda ---- */
document.addEventListener('submit', (e) => {
    if (e.target.dataset && e.target.dataset.fsBusy) e.preventDefault();
}, true);

document.addEventListener('submit', (e) => {
    const form = e.target;

    setTimeout(() => {
        if (e.defaultPrevented || ! (form instanceof HTMLFormElement) || form.target === '_blank') return;

        form.dataset.fsBusy = '1';
        const btn = e.submitter || form.querySelector('button[type="submit"], button:not([type])');
        if (btn && btn.matches('.btn-primary, .btn-secondary, .btn-outline, .btn-light')) {
            btn.classList.add('is-loading');
        }
        startBar();

        // jaga-jaga untuk form yang mengunduh file (halaman tidak berpindah)
        setTimeout(() => {
            delete form.dataset.fsBusy;
            if (btn) btn.classList.remove('is-loading');
        }, 10000);
    }, 0);
});

window.addEventListener('pageshow', (e) => {
    resetBar();
    if (e.persisted) {
        document.querySelectorAll('[data-fs-busy]').forEach((f) => { delete f.dataset.fsBusy; });
        document.querySelectorAll('.is-loading').forEach((b) => b.classList.remove('is-loading'));
    }
});