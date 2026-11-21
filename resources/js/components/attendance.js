import { firstError, sendJson } from '../http';

/**
 * Daily register (class or staff). Tapping P / L / A / E marks a row; changes
 * save automatically in small batches. idKey is the row/payload id field
 * (`enrolment` for students, `staff` for the staff register).
 */
export default ({ rows, url, date, locked, idKey = 'enrolment' }) => ({
    rows,
    locked,
    idKey,
    dirty: {},
    saving: false,
    savedAt: null,
    error: null,
    timer: null,

    count(status) {
        return this.rows.filter((row) => row.status === status).length;
    },

    get unmarked() {
        return this.rows.filter((row) => ! row.status).length;
    },

    mark(row, status) {
        if (this.locked) {
            return;
        }

        row.status = status;
        this.dirty[row[this.idKey]] = row;
        this.queueSave();
    },

    markAllPresent() {
        this.rows.filter((row) => ! row.status).forEach((row) => this.mark(row, 'present'));
    },

    note(row) {
        if (row.status) {
            this.dirty[row[this.idKey]] = row;
            this.queueSave();
        }
    },

    queueSave() {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.save(), 600);
    },

    async save() {
        const records = Object.values(this.dirty).map((row) => ({
            [this.idKey]: row[this.idKey],
            status: row.status,
            note: row.note || null,
        }));

        if (! records.length || this.saving) {
            return;
        }

        this.dirty = {};
        this.saving = true;

        try {
            const response = await sendJson(url, 'PUT', { date, records });

            if (! response.ok) {
                this.error = await firstError(response);

                return;
            }

            this.error = null;
            this.savedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch {
            this.error = 'You seem to be offline. Tap again to retry.';
        } finally {
            this.saving = false;
        }
    },
});
