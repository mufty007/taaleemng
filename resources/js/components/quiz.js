import { firstError, sendJson } from '../http';

const letters = 'ABCDEFGH';

/**
 * Teacher's question editor. The whole question list is saved at once.
 */
export const quizBuilder = ({ questions, url, locked }) => ({
    questions,
    locked,
    saving: false,
    savedAt: null,
    error: null,

    get totalMarks() {
        return this.questions.reduce((sum, question) => sum + Number(question.marks || 0), 0);
    },

    add(type = 'single') {
        const options = type === 'single' || type === 'multiple'
            ? [0, 1, 2, 3].map((index) => ({ key: letters[index], text: '' }))
            : [];

        this.questions.push({ type, prompt: '', options, answer: type === 'true_false' ? ['true'] : [], marks: 1 });
        this.$nextTick(() => this.$root.querySelector(`[data-question="${this.questions.length - 1}"] textarea`)?.focus());
    },

    changeType(question) {
        if ((question.type === 'single' || question.type === 'multiple') && question.options.length < 2) {
            question.options = [0, 1, 2, 3].map((index) => ({ key: letters[index], text: '' }));
        }

        question.answer = question.type === 'true_false' ? ['true'] : [];
    },

    addOption(question) {
        if (question.options.length < 8) {
            question.options.push({ key: letters[question.options.length], text: '' });
        }
    },

    removeOption(question, index) {
        question.options.splice(index, 1);
        question.options.forEach((option, position) => (option.key = letters[position]));
        question.answer = question.answer.filter((key) => question.options.some((option) => option.key === key));
    },

    toggleAnswer(question, key) {
        if (question.type === 'multiple') {
            question.answer = question.answer.includes(key) ? question.answer.filter((value) => value !== key) : [...question.answer, key];
        } else {
            question.answer = [key];
        }
    },

    move(index, step) {
        const target = index + step;

        if (target >= 0 && target < this.questions.length) {
            [this.questions[index], this.questions[target]] = [this.questions[target], this.questions[index]];
        }
    },

    remove(index) {
        this.questions.splice(index, 1);
    },

    async save() {
        this.saving = true;
        this.error = null;

        try {
            const response = await sendJson(url, 'PUT', {
                questions: this.questions.map((question) => ({
                    ...question,
                    answer: question.type === 'short'
                        ? String(question.answer[0] ?? '').split('|').map((value) => value.trim()).filter(Boolean)
                        : question.answer,
                })),
            });

            if (! response.ok) {
                this.error = await firstError(response);

                return;
            }

            this.savedAt = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } catch {
            this.error = 'Could not save. Check your connection and try again.';
        } finally {
            this.saving = false;
        }
    },
});

/**
 * Student's quiz page: answers autosave, a countdown submits when time is up.
 */
export const quizTaker = ({ answers, url, deadline }) => ({
    answers,
    remaining: null,
    timer: null,
    saveTimer: null,
    status: '',

    init() {
        if (deadline) {
            const tick = () => {
                this.remaining = Math.max(0, Math.floor((new Date(deadline) - new Date()) / 1000));

                if (this.remaining === 0) {
                    clearInterval(this.timer);
                    this.$refs.form.dataset.confirmed = '1';
                    this.$refs.form.requestSubmit();
                }
            };

            tick();
            this.timer = setInterval(tick, 1000);
        }
    },

    destroy() {
        clearInterval(this.timer);
    },

    get clock() {
        if (this.remaining === null) {
            return '';
        }

        const minutes = Math.floor(this.remaining / 60);
        const seconds = String(this.remaining % 60).padStart(2, '0');

        return `${minutes}:${seconds}`;
    },

    get answered() {
        return Object.values(this.answers).filter((value) => (Array.isArray(value) ? value.length : value !== '' && value !== null)).length;
    },

    changed() {
        clearTimeout(this.saveTimer);
        this.saveTimer = setTimeout(async () => {
            try {
                const response = await sendJson(url, 'PUT', { answers: this.answers });
                this.status = response.ok ? 'Saved' : 'Not saved';
            } catch {
                this.status = 'Offline: answers kept on this page';
            }
        }, 700);
    },
});
