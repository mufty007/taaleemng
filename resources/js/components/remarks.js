import { firstError, sendJson } from '../http';

/**
 * The form teacher's console. One slide-in panel edits a student's attendance,
 * trait ratings and comments; "Save & next" walks down the class without closing it.
 */
export default ({ rows, url, locked }) => ({
    rows,
    locked,
    index: null,
    form: { traits: {} },
    saving: false,
    error: null,

    get current() {
        return this.index === null ? null : this.rows[this.index];
    },

    get completed() {
        return this.rows.filter((row) => row.complete).length;
    },

    get hasNext() {
        return this.index !== null && this.index < this.rows.length - 1;
    },

    open(index) {
        const row = this.rows[index];

        this.index = index;
        this.error = null;
        this.form = {
            days_present: row.days_present ?? '',
            traits: { ...row.traits },
            teacher_comment: row.teacher_comment,
            principal_comment: row.principal_comment,
            promotion_decision: row.promotion_decision ?? row.suggested_decision ?? '',
        };
        this.$dispatch('open-drawer', 'remarks');
    },

    rate(traitId, rating) {
        this.form.traits[traitId] = this.form.traits[traitId] === rating ? null : rating;
    },

    async save(next = false) {
        const row = this.current;

        if (! row || this.saving || this.locked) {
            return;
        }

        const payload = {
            ...this.form,
            days_present: this.form.days_present === '' ? null : Number(this.form.days_present),
            promotion_decision: this.form.promotion_decision || null,
        };

        this.saving = true;
        this.error = null;

        try {
            const response = await sendJson(url.replace('__ENROLMENT__', row.enrolment), 'PUT', payload);

            if (! response.ok) {
                this.error = await firstError(response);

                return;
            }

            const { complete } = await response.json();
            Object.assign(row, { ...payload, traits: { ...payload.traits }, complete });

            if (next && this.hasNext) {
                this.open(this.index + 1);
            } else {
                this.$dispatch('close-drawer', 'remarks');
            }
        } catch {
            this.error = 'You seem to be offline. Nothing was lost; try saving again.';
        } finally {
            this.saving = false;
        }
    },
});
