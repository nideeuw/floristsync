import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import pos from './pos';
import locationPicker from './location-picker';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.data('pos', pos);
Alpine.data('locationPicker', locationPicker);

Alpine.start();

const initReveal = () => {
    const items = document.querySelectorAll('[data-reveal]');

    if (! items.length) {
        return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduceMotion || ! ('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    document.documentElement.classList.add('js-reveal');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

    items.forEach((el) => observer.observe(el));
};

initReveal();
