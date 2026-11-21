import { firstError, sendJson } from '../http';

/**
 * Spreadsheet-style score entry for one subject in one class.
 *
 * - Enter / ↓ / ↑ move between students; Tab moves across components.
 * - Pasting a block copied from Excel fills cells to the right and below.
 * - Changes save automatically in small batches; a failed save is kept and retried.
 */
export default ({ rows, components, bands, url, locked }) => ({
    rows,
    components,
    bands,
    locked,
    dirty: {},
    saving: false,
    savedAt: null,
    error: null,
    timer: null,

    init() {
        window.addEventListener('beforeunload', (event) => {
            if (Object.keys(this.dirty).length || this.saving) {
                event.preventDefault();
            }
        });
    },

    value(row, component) {
        return row.scores[component.id] ?? '';
    },

    input(row, component, event) {
        const raw = event.target.value.trim();
        const score = raw === '' ? null : Number(raw);

        if (score !== null && (Number.isNaN(score) || score < 0 || score > component.max)) {
            event.target.setAttribute('aria-invalid', 'true');
            this.error = `${component.short} must be a number from 0 to ${component.max}.`;

            return;
        }

        event.target.removeAttribute('aria-invalid');
        this.error = null;
        row.scores[component.id] = score;
        this.dirty[`${row.enrolment}:${component.id}`] = { enrolment: row.enrolment, component: component.id, score };
        this.queueSave();
    },

    total(row) {
        const values = Object.values(row.scores).filter((score) => score !== null && score !== '');

        return values.length ? Math.round(values.reduce((sum, score) => sum + Number(score), 0) * 10) / 10 : null;
    },

    grade(row) {
        const total = this.total(row);

        if (total === null || ! this.bands.length) {
            return '';
        }

        return (this.bands.find((band) => total >= band.min) ?? this.bands[this.bands.length - 1]).grade;
    },

    get entered() {
        return this.rows.reduce((count, row) => count + Object.values(row.scores).filter((score) => score !== null).length, 0);
    },

    get pending() {
        return Object.keys(this.dirty).length;
    },

    queueSave() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.save(), 700);
    },

    async save() {
        const cells = Object.values(this.dirty);

        if (! cells.length || this.saving) {
            return;
        }

        this.dirty = {};
        this.saving = true;
        const requeue = () => cells.forEach((cell) => {
            this.dirty[`${cell.enrolment}:${cell.component}`] ??= cell;
        });

        try {
            const response = await sendJson(url, 'PUT', { scores: cells });

            if (! response.ok) {
                this.error = await firstError(response);
                requeue();

                return;
            }

            this.savedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch {
            this.error = 'You seem to be offline. Your scores are kept and will save when you reconnect.';
            requeue();
            setTimeout(() => this.save(), 5000);
        } finally {
            this.saving = false;

            if (Object.keys(this.dirty).length && ! this.error) {
                this.queueSave();
            }
        }
    },

    move(event, rowIndex, columnIndex, step) {
        const target = this.$root.querySelector(`[data-cell="${rowIndex + step}-${columnIndex}"]`);

        if (target) {
            event.preventDefault();
            target.focus();
            target.select();
        }
    },

    paste(event, rowIndex, columnIndex) {
        const text = event.clipboardData.getData('text');

        if (! text.includes('\n') && ! text.includes('\t')) {
            return;
        }

        event.preventDefault();
        text.trim().split(/\r?\n/).forEach((line, i) => {
            line.split('\t').forEach((cell, j) => {
                const target = this.$root.querySelector(`[data-cell="${rowIndex + i}-${columnIndex + j}"]`);

                if (target && ! target.disabled) {
                    target.value = cell.trim();
                    target.dispatchEvent(new Event('change'));
                }
            });
        });
    },
});
