export async function getJson(url) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
        cache: 'no-store',
    });

    if (!response.ok) {
        const error = new Error('No se pudo completar la solicitud.');
        error.status = response.status;
        throw error;
    }

    return response.json();
}

export function dataStatus(root) {
    const paragraph = root.querySelector('[data-status]');
    const retry = root.querySelector('[data-retry]');
    const signIn = root.querySelector('[data-sign-in]');

    return {
        retry,

        show(message, { canRetry = false, sessionExpired = false, visuallyHidden = false, restoreFocus = false } = {}) {
            paragraph.parentElement.classList.toggle('sr-only', visuallyHidden);
            paragraph.textContent = message;

            if (restoreFocus || (document.activeElement === retry && !canRetry)) {
                paragraph.focus({ preventScroll: true });
            }

            retry.hidden = !canRetry;
            signIn.hidden = !sessionExpired;
        },
    };
}
