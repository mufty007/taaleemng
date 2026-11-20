import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import { initMotion } from './motion';
import broadsheet from './components/broadsheet';

Alpine.plugin(collapse);
Alpine.plugin(focus);

Alpine.data('broadsheet', broadsheet);

window.Alpine = Alpine;
Alpine.start();

initMotion();
