import { firstError, sendJson } from '../http';

/**
 * The reminder panel on the debtors list. A row sends `remind` with its student's
 * details; email is sent by the server, while WhatsApp and SMS open on this phone
 * with the message written and the server just records that they were sent.
 */
export default () => ({
    busy: false,
    error: null,
    done: null,
    row: { id: null, name: '', parent: '', amount: '', url: '', link: '', whatsapp: null, sms: null, email: null, message: '' },

    show(row) {
        this.row = row;
        this.error = null;
        this.done = null;
    },

    async record(channel, label) {
        this.busy = true;
        this.error = null;

        try {
            const response = await sendJson(this.row.url, 'POST', { channel });

            if (response.ok) {
                this.done = label;
                window.dispatchEvent(new CustomEvent('reminded', { detail: { id: this.row.id, label } }));
            } else {
                this.error = await firstError(response, 'Could not send the reminder.');
            }
        } catch {
            this.error = 'No connection. Try again.';
        } finally {
            this.busy = false;
        }
    },

    async copy() {
        try {
            await navigator.clipboard.writeText(this.row.link);
        } catch {
            window.prompt('Copy this pay link:', this.row.link);
        }

        this.record('link', 'Pay link copied just now');
    },
});
