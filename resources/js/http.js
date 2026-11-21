/**
 * JSON requests to the app with the CSRF token. Returns the Response; callers read errors from it.
 */
export const sendJson = (url, method, body) => fetch(url, {
    method,
    headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
    },
    body: JSON.stringify(body),
});

/**
 * The first validation message from a failed JSON response.
 */
export const firstError = async (response, fallback = 'Could not save. Please try again.') => {
    const body = await response.json().catch(() => ({}));

    return Object.values(body.errors ?? {})[0]?.[0] ?? body.message ?? fallback;
};
