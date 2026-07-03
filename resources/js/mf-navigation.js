const ADMIN_PATH_PREFIXES = [
    '/centers',
    '/users',
    '/settings',
    '/security',
    '/audit-logs',
];

const isAdministrationPath = (pathname) =>
    ADMIN_PATH_PREFIXES.some(
        (prefix) => pathname === prefix || pathname.startsWith(`${prefix}/`),
    );

/**
 * Flux form fields (ui-field, data-flux-input) do not survive Livewire SPA morphing
 * reliably. Force full page loads for administration routes even when a link still
 * carries wire:navigate (e.g. center selection → manage centers).
 */
document.addEventListener(
    'click',
    (event) => {
        const anchor = event.target.closest('a[href][wire\\:navigate], a[href][wire-navigate]');

        if (!anchor) {
            return;
        }

        const url = new URL(anchor.getAttribute('href'), window.location.origin);

        if (url.origin !== window.location.origin || !isAdministrationPath(url.pathname)) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        window.location.assign(url.href);
    },
    true,
);
