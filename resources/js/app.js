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

    init() {
        this.closeSidebar = () => {
            this.sidebar = false;
        };
        window.addEventListener('app:close-sidebar', this.closeSidebar);
        // After the page swap has put back the sidebar's old scroll position.
        requestAnimationFrame(() => this.revealActive());
    },

    destroy() {
        window.removeEventListener('app:close-sidebar', this.closeSidebar);
    },

    /** Scroll the sidebar (never the page) so the current page's item is in view. */
    revealActive() {
        const nav = this.$refs.nav;
        const active = nav?.querySelector('[aria-current="page"]');

        if (! nav || ! active || nav.scrollHeight <= nav.clientHeight) {
            return;
        }

        const navBox = nav.getBoundingClientRect();
        const box = active.getBoundingClientRect();

        if (box.top >= navBox.top && box.bottom <= navBox.bottom) {
            return;
        }

        nav.scrollTop += box.top - navBox.top - (nav.clientHeight - box.height) / 2;
    },

    toggleRail() {
        this.rail = ! this.rail;
        store.set('sidebar.rail', this.rail);
        document.documentElement.classList.toggle('sidebar-rail', this.rail);
    },
}));

/** A sidebar group that folds, remembered per browser. It stays open while it holds the current page. */
Alpine.data('navGroup', (key, hasActive = false) => ({
    open: hasActive || store.get(`nav.${key}`, true),

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
        this.onHashChange = () => {
            const name = window.location.hash.slice(1);

            if (! this.names.includes(name) || name === this.tab) {
                return;
            }

            this.tab = name;
            this.$nextTick(() => {
                this.reveal();
                this.$root.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        };

        this.$nextTick(() => {
            this.syncFromHash();
            this.reveal();
            this.measure();
        });

        // Links elsewhere on the page (e.g. "Edit" pointing at #settings) switch tabs too.
        window.addEventListener('hashchange', this.onHashChange);
    },

    destroy() {
        window.removeEventListener('hashchange', this.onHashChange);
    },

    syncFromHash() {
        const name = window.location.hash.slice(1);

        if (this.names.includes(name)) {
            this.tab = name;
        }
    },

    select(name) {
        if (! this.names.includes(name) || name === this.tab) {
            return;
        }

        this.tab = name;
        const url = new URL(window.location.href);
        url.hash = name;
        history.replaceState({ ...(history.state ?? {}), navigation: true }, '', url.pathname + url.search + url.hash);
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
