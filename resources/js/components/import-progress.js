/**
 * Polls a queued import's progress and reloads the page when it is done.
 */
export default ({ url, initial }) => ({
    ...initial,
    timer: null,

    init() {
        this.timer = setInterval(() => this.poll(), 1500);
    },

    destroy() {
        clearInterval(this.timer);
    },

    async poll() {
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });

            if (! response.ok) {
                return;
            }

            Object.assign(this, await response.json());

            if (this.status === 'completed' || this.status === 'failed') {
                clearInterval(this.timer);
                window.location.reload();
            }
        } catch {
            // Offline for a moment; the next poll will catch up.
        }
    },
});
