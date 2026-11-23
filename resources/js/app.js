import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { initMotion } from './motion';
import { startNavigation } from './navigation';
import attendance from './components/attendance';
import broadsheet from './components/broadsheet';
import confirmDialog, { confirmSubmit } from './components/confirm';
import feeReminder from './components/fee-reminder';
import { quizBuilder, quizTaker } from './components/quiz';
import importProgress from './components/import-progress';
import remarks from './components/remarks';
import scorebook from './components/scorebook';
import timetableEditor from './components/timetable';
import { store } from './store';

Alpine.plugin(collapse);
Alpine.plugin(focus);

Alpine.data('attendanceRegister', attendance);
Alpine.data('timetableEditor', timetableEditor);
Alpine.data('broadsheet', broadsheet);
Alpine.data('confirmDialog', confirmDialog);
Alpine.data('feeReminder', feeReminder);
Alpine.data('quizBuilder', quizBuilder);
Alpine.data('quizTaker', quizTaker);
Alpine.data('importProgress', importProgress);
Alpine.data('remarksBook', remarks);
Alpine.data('scorebook', scorebook);

document.addEventListener('submit', confirmSubmit, true);

/**
 * App shell: mobile drawer and the desktop sidebar rail (remembered per browser).
 * The rail class is also set on <html> by an inline script before paint, so there is no flash.
 */
Alpine.data('shell', () => ({
    sidebar: false,
    rail: store.get('sidebar.rail', false),

    toggleRail() {
        this.rail = ! this.rail;
        store.set('sidebar.rail', this.rail);
        document.documentElement.classList.toggle('sidebar-rail', this.rail);
    },
}));

/** A sidebar group that folds, remembered per browser. */
Alpine.data('navGroup', (key) => ({
    open: store.get(`nav.${key}`, true),

    toggle() {
        this.open = ! this.open;
        store.set(`nav.${key}`, this.open);
    },
}));

/** Tabs that follow the URL hash, so a tab can be linked to and survives a reload. */
Alpine.data('tabs', (names, initial) => ({
    names,
    tab: names.includes(window.location.hash.slice(1)) ? window.location.hash.slice(1) : initial,
    fade: { start: false, end: false },

    init() {
        this.$nextTick(() => {
            this.reveal();
            this.measure();
        });

        // Links elsewhere on the page (e.g. "Edit" pointing at #settings) switch tabs too.
        window.addEventListener('hashchange', () => {
            const name = window.location.hash.slice(1);

            if (this.names.includes(name) && name !== this.tab) {
                this.tab = name;
                this.$nextTick(() => {
                    this.reveal();
                    this.$root.scrollIntoView({ behavior: 'smooth', block: 'start' });
                });
            }
        });
    },

    select(name) {
        this.tab = name;
        history.replaceState(null, '', `#${name}`);
        this.$nextTick(() => this.reveal());
    },

    /** Which edges of a sideways-scrolling tab strip have more tabs past them. */
    measure() {
        const strip = this.$refs.strip;

        if (! strip) {
            return;
        }

        this.fade.start = strip.scrollLeft > 4;
        this.fade.end = strip.scrollLeft + strip.clientWidth < strip.scrollWidth - 4;
    },

    edgeMask() {
        if (! this.fade.start && ! this.fade.end) {
            return '';
        }

        const gradient = `linear-gradient(to right, ${this.fade.start ? 'transparent, #000 2.5rem' : '#000'}, ${this.fade.end ? '#000 calc(100% - 2.5rem), transparent' : '#000'})`;

        return `mask-image: ${gradient}; -webkit-mask-image: ${gradient};`;
    },

    /** Scroll the strip (never the page) so the chosen tab is in view. */
    reveal() {
        const strip = this.$refs.strip;
        const button = strip?.querySelector(`[data-tab="${this.tab}"]`);

        if (! strip || ! button || strip.scrollWidth <= strip.clientWidth) {
            return;
        }

        strip.scrollTo({ left: button.offsetLeft - (strip.clientWidth - button.offsetWidth) / 2, behavior: 'smooth' });
    },

    move(step) {
        const next = this.names[(this.names.indexOf(this.tab) + step + this.names.length) % this.names.length];
        this.select(next);
        this.$nextTick(() => this.$root.querySelector(`[data-tab="${next}"]`)?.focus());
    },
}));

/**
 * One slide-in edit panel shared by every row of a table: a row dispatches
 * `$dispatch('edit-<name>', { action, values })` and the panel fills its form.
 */
Alpine.data('editPanel', (name, initial = {}) => ({
    action: initial.action ?? '',
    values: initial.values ?? {},

    init() {
        this.listener = (event) => {
            this.action = event.detail.action;
            this.values = { ...event.detail.values };
            this.$dispatch('open-drawer', name);
        };
        window.addEventListener(`edit-${name}`, this.listener);
    },

    // Pages are swapped without reloading, so stop listening when this one goes.
    destroy() {
        window.removeEventListener(`edit-${name}`, this.listener);
    },
}));

window.Alpine = Alpine;
Alpine.start();

initMotion();

// Inside the app, pages swap in place instead of reloading (see navigation.js).
startNavigation(() => {
    document.documentElement.style.removeProperty('overflow');
    document.documentElement.style.removeProperty('padding-right');
    ScrollTrigger.getAll().forEach((trigger) => trigger.kill());
    initMotion();
});
