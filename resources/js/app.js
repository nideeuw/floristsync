import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import pos from './pos';

window.Alpine = Alpine;
window.Chart = Chart;

Alpine.data('pos', pos);

Alpine.start();
