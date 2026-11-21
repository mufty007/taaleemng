/**
 * Per-browser preferences (sidebar rail, folded groups). Storage can be blocked
 * (private mode, strict settings), so every access is guarded and falls back.
 */
export const store = {
    get(key, fallback) {
        try {
            const value = window.localStorage.getItem(key);

            return value === null ? fallback : JSON.parse(value);
        } catch {
            return fallback;
        }
    },

    set(key, value) {
        try {
            window.localStorage.setItem(key, JSON.stringify(value));
        } catch {
            // Preference just won't be remembered.
        }
    },
};
