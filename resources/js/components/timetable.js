import { firstError, sendJson } from '../http';

/**
 * The class timetable grid. Choosing a subject for a period saves it straight away;
 * if the teacher is busy in another class then, the choice is undone with a message.
 */
export default ({ url, slots }) => ({
    slots,
    previous: { ...slots },
    teachers: {},
    saving: false,
    savedAt: null,
    error: null,

    init() {
        const data = this.$root.querySelector('[data-teachers]');
        this.teachers = data ? JSON.parse(data.textContent) : {};
    },

    count(classSubjectId) {
        return Object.values(this.slots).filter((value) => value === classSubjectId).length;
    },

    async save(day, periodId, select) {
        const key = `${day}-${periodId}`;
        const value = select.value;
        this.saving = true;
        this.error = null;

        try {
            const response = await sendJson(url, 'PUT', { day, period_id: periodId, class_subject_id: value || null });

            if (! response.ok) {
                this.error = await firstError(response);
                this.slots[key] = this.previous[key] ?? '';
                select.value = this.slots[key];

                return;
            }

            this.previous[key] = value;
            this.savedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch {
            this.error = 'You seem to be offline. Choose the subject again to retry.';
            this.slots[key] = this.previous[key] ?? '';
            select.value = this.slots[key];
        } finally {
            this.saving = false;
        }
    },
});
