/**
 * App-wide confirm dialog. Forms opt in with data-confirm="…" (and optional
 * data-confirm-title / data-confirm-action / data-confirm-tone). On submit the
 * dialog opens; Confirm sets dataset.confirmed and re-submits.
 */
export default () => ({
    open: false,
    title: 'Are you sure?',
    message: '',
    action: 'Continue',
    tone: 'danger',
    form: null,

    init() {
        this.listener = (event) => this.ask(event.detail ?? {});
        window.addEventListener('ask-confirm', this.listener);
    },

    destroy() {
        window.removeEventListener('ask-confirm', this.listener);
    },

    ask({ form = null, title = null, message = null, action = null, tone = null } = {}) {
        this.form = form instanceof HTMLFormElement ? form : null;
        this.title = title || this.form?.dataset.confirmTitle || 'Are you sure?';
        this.message = message || this.form?.dataset.confirm || 'This cannot be undone.';
        this.action = action || this.form?.dataset.confirmAction || 'Continue';
        this.tone = tone || this.form?.dataset.confirmTone || 'danger';
        this.open = true;
    },

    cancel() {
        this.open = false;
        this.form = null;
    },

    proceed() {
        const form = this.form;
        this.open = false;
        this.form = null;

        if (! form) {
            return;
        }

        form.dataset.confirmed = '1';
        form.requestSubmit();
    },
});

/**
 * Intercept submit on a form that has data-confirm, unless already confirmed
 * (e.g. quiz timer auto-hand-in sets dataset.confirmed = '1').
 */
export function confirmSubmit(event) {
    const form = event.target;

    if (! (form instanceof HTMLFormElement) || ! form.dataset.confirm || form.dataset.confirmed === '1') {
        return;
    }

    event.preventDefault();
    event.stopPropagation();
    window.dispatchEvent(new CustomEvent('ask-confirm', { detail: { form } }));
}
