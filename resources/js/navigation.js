import Alpine from 'alpinejs';

/**
 * In-app navigation without full page reloads.
 *
 * Inside the signed-in app (any page whose body holds [data-app-shell]),
 * same-origin links and forms are fetched in the background. When both pages
 * share the app chrome, only [data-app-main] (and the sidebar nav) are swapped
 * so the shell does not flash. Hash-only links update the URL in place for the
 * tabs component — they never fetch. Unusual cases fall back to a normal load:
 *
 *  - links with target, download, data-native or to a different origin
 *  - pages that are not part of the app shell (print views, marketing, errors)
 *  - forms marked data-native (e.g. payments that redirect to Paystack)
 *
 * Hovering a link starts fetching it, so most clicks feel instant.
 */

const progress = document.createElement('div');
progress.className = 'nav-progress';
progress.setAttribute('aria-hidden', 'true');

let controller = null;
let onAfterSwap = () => {};
const prefetched = new Map();

const isAppPage = (doc) => doc.body?.hasAttribute('data-app-shell');

function startProgress() {
    document.documentElement.append(progress);
    progress.style.transition = 'none';
    progress.style.transform = 'scaleX(0.05)';
    requestAnimationFrame(() => {
        progress.style.transition = 'transform 8s cubic-bezier(0.1, 0.7, 0.1, 1)';
        progress.style.transform = 'scaleX(0.9)';
    });
}

function finishProgress() {
    progress.style.transition = 'transform 0.2s ease-out, opacity 0.3s ease 0.2s';
    progress.style.transform = 'scaleX(1)';
    progress.style.opacity = '0';
    setTimeout(() => {
        progress.remove();
        progress.style.opacity = '1';
    }, 500);
}

function runScripts(root) {
    root?.querySelectorAll('script').forEach((old) => {
        const script = document.createElement('script');
        [...old.attributes].forEach((attribute) => script.setAttribute(attribute.name, attribute.value));
        script.textContent = old.textContent;
        old.replaceWith(script);
    });
}

function updateChrome(doc) {
    document.title = doc.title;
    const token = doc.querySelector('meta[name="csrf-token"]')?.content;

    if (token) {
        document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
    }
}

/**
 * Same-document hash change: drive the tabs component without fetching.
 */
function applyHash(url) {
    if (url.hash === location.hash) {
        window.dispatchEvent(new Event('hashchange'));

        return;
    }

    history.replaceState({ ...(history.state ?? {}), navigation: true }, '', url.pathname + url.search + url.hash);
    window.dispatchEvent(new Event('hashchange'));
}

function linkFor(event) {
    const link = event.target.closest('a[href]');

    if (! link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
        return null;
    }

    if (link.target || link.hasAttribute('download') || link.closest('[data-native]')) {
        return null;
    }

    const url = new URL(link.href, location.href);

    if (url.origin !== location.origin || url.protocol.startsWith('mailto') || url.protocol.startsWith('tel')) {
        return null;
    }

    return url;
}

function fetchPage(url, options = {}) {
    const requestUrl = new URL(url.href);
    requestUrl.hash = '';

    return fetch(requestUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'text/html, application/xhtml+xml', 'X-Navigation': 'swap' },
        ...options,
    });
}

function syncNav(doc) {
    const current = document.querySelector('#sidebar-nav');
    const next = doc.querySelector('#sidebar-nav');

    if (! current || ! next) {
        return;
    }

    const top = current.scrollTop;
    Alpine.destroyTree(current);
    Alpine.mutateDom(() => current.replaceWith(document.adoptNode(next)));
    const nav = document.querySelector('#sidebar-nav');
    runScripts(nav);
    Alpine.initTree(nav);

    if (nav) {
        nav.scrollTop = top;
    }
}

function syncTabbar(doc) {
    const current = document.querySelector('[data-app-tabbar]');
    const next = doc.querySelector('[data-app-tabbar]');
    const shell = document.querySelector('[data-app-shell-root]');

    if (current && next) {
        current.replaceWith(document.adoptNode(next));
    } else if (current && ! next) {
        current.remove();
    } else if (! current && next && shell) {
        shell.appendChild(document.adoptNode(next));
    }
}

function syncFlash(doc) {
    document.querySelectorAll('[data-app-flash]').forEach((element) => element.remove());
    doc.querySelectorAll('[data-app-flash]').forEach((element) => {
        document.body.appendChild(document.adoptNode(element));
        Alpine.initTree(document.body.querySelector('[data-app-flash]:last-of-type'));
    });
}

/**
 * Swap in a fetched page. Returns false if it is not an app page, so the caller can fall back.
 */
async function render(response, { push = true, scroll = 0, hash = '' } = {}) {
    const type = response.headers.get('content-type') ?? '';

    if (! type.includes('text/html')) {
        return false;
    }

    const html = await response.text();
    const doc = new DOMParser().parseFromString(html, 'text/html');

    if (! isAppPage(doc)) {
        return false;
    }

    const finalUrl = new URL(response.url);

    if (hash) {
        finalUrl.hash = hash.startsWith('#') ? hash : `#${hash}`;
    }

    if (push) {
        history.replaceState({ ...(history.state ?? {}), navigation: true, scroll: window.scrollY }, '');
        history.pushState({ navigation: true, scroll: 0 }, '', finalUrl.pathname + finalUrl.search + finalUrl.hash);
    } else {
        history.replaceState({ navigation: true, scroll }, '', finalUrl.pathname + finalUrl.search + finalUrl.hash);
    }

    updateChrome(doc);

    const currentMain = document.querySelector('[data-app-main]');
    const nextMain = doc.querySelector('[data-app-main]');
    const soft = Boolean(currentMain && nextMain && isAppPage(document));

    if (soft) {
        Alpine.destroyTree(currentMain);
        Alpine.mutateDom(() => currentMain.replaceWith(document.adoptNode(nextMain)));
        const main = document.querySelector('[data-app-main]');
        runScripts(main);
        Alpine.initTree(main);
        syncNav(doc);
        syncTabbar(doc);
        syncFlash(doc);
        document.dispatchEvent(new CustomEvent('app:close-sidebar'));
    } else {
        const keepScroll = {};
        document.querySelectorAll('[data-keep-scroll][id]').forEach((element) => {
            keepScroll[element.id] = element.scrollTop;
        });

        Alpine.destroyTree(document.body);
        Alpine.mutateDom(() => {
            document.body.replaceWith(document.adoptNode(doc.body));
        });
        runScripts(document.body);
        Alpine.initTree(document.body);

        Object.entries(keepScroll).forEach(([id, top]) => {
            const element = document.getElementById(id);

            if (element) {
                element.scrollTop = top;
            }
        });
    }

    const tab = finalUrl.hash.slice(1);
    const target = tab
        ? document.getElementById(`panel-${tab}`) || document.getElementById(tab)
        : null;

    if (target) {
        document.querySelector('[data-app-main]')?.scrollIntoView({ block: 'start' });
        window.dispatchEvent(new Event('hashchange'));
    } else {
        window.scrollTo(0, scroll);
    }

    onAfterSwap();
    document.dispatchEvent(new CustomEvent('app:navigated', { detail: { url: finalUrl.href } }));

    return true;
}

async function visit(url, { push = true, scroll = 0, request = null } = {}) {
    controller?.abort();
    controller = new AbortController();
    startProgress();

    try {
        const cached = ! request && prefetched.get(url.href.split('#')[0]);
        prefetched.clear();
        const response = cached ? await cached : await fetchPage(url, { signal: controller.signal, ...(request ?? {}) });

        if (! (await render(response.clone(), { push, scroll, hash: url.hash }))) {
            if (request && request.method !== 'GET') {
                document.open();
                document.write(await response.text());
                document.close();
            } else {
                location.href = response.url || url.href;
            }
        }
    } catch (error) {
        if (error.name !== 'AbortError') {
            location.href = url.href;
        }
    } finally {
        finishProgress();
    }
}

function onClick(event) {
    const url = linkFor(event);

    if (! url || ! isAppPage(document)) {
        return;
    }

    // Same path + query: never fetch — hash links drive the tabs component in place.
    if (url.pathname === location.pathname && url.search === location.search) {
        event.preventDefault();

        if (url.hash) {
            applyHash(url);
        } else if (location.hash) {
            history.replaceState({ ...(history.state ?? {}), navigation: true }, '', url.pathname + url.search);
            window.dispatchEvent(new Event('hashchange'));
        }

        return;
    }

    event.preventDefault();
    visit(url);
}

function onSubmit(event) {
    const form = event.target;

    if (event.defaultPrevented || ! isAppPage(document) || form.closest('[data-native]') || form.target) {
        return;
    }

    const submitter = event.submitter;
    const method = (submitter?.getAttribute('formmethod') || form.getAttribute('method') || 'GET').toUpperCase();
    const action = new URL(submitter?.getAttribute('formaction') || form.getAttribute('action') || location.href, location.href);

    if (action.origin !== location.origin || form.enctype === 'multipart/form-data' && method === 'GET') {
        return;
    }

    event.preventDefault();
    const data = new FormData(form, submitter ?? undefined);

    if (method === 'GET') {
        action.search = new URLSearchParams(data).toString();
        visit(action);

        return;
    }

    submitter?.setAttribute('disabled', '');
    visit(action, {
        request: {
            method: 'POST',
            body: data,
            headers: { Accept: 'text/html, application/xhtml+xml', 'X-Navigation': 'swap', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
        },
    }).finally(() => submitter?.removeAttribute('disabled'));
}

function onHover(event) {
    const link = event.target.closest?.('a[href]');

    if (! link || ! isAppPage(document)) {
        return;
    }

    const url = linkFor({ target: link, button: 0, defaultPrevented: false });

    if (! url || url.pathname === location.pathname && url.search === location.search || prefetched.has(url.href.split('#')[0])) {
        return;
    }

    const key = url.href.split('#')[0];
    const timer = setTimeout(() => prefetched.set(key, fetchPage(url)), 80);
    link.addEventListener('mouseleave', () => clearTimeout(timer), { once: true });
}

export function startNavigation(afterSwap) {
    onAfterSwap = afterSwap;
    history.scrollRestoration = 'manual';
    history.replaceState({ navigation: true, scroll: window.scrollY }, '');

    document.addEventListener('click', onClick);
    document.addEventListener('submit', onSubmit);
    document.addEventListener('mouseover', onHover, { passive: true });

    window.addEventListener('popstate', (event) => {
        if (event.state?.navigation) {
            visit(new URL(location.href), { push: false, scroll: event.state.scroll ?? 0 });
        }
    });
}
