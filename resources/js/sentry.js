import { router } from '@inertiajs/vue3';

/**
 * Browser error tracking. Does nothing unless VITE_SENTRY_DSN is set at build time,
 * and the SDK is fetched as a separate chunk so it never weighs down the main bundle.
 */
export function initSentry(app, initialPage) {
    const dsn = import.meta.env.VITE_SENTRY_DSN;
    if (!dsn) return;

    import('@sentry/vue').then((Sentry) => {
        Sentry.init({
            app,
            dsn,
            environment: import.meta.env.VITE_SENTRY_ENVIRONMENT || import.meta.env.MODE,
            tracesSampleRate: Number(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE || 0),
            sendDefaultPii: false,
        });

        // Tag errors with the account id only (no names or emails) so reports can be traced to a user.
        const identify = (page) => {
            const user = page?.props?.auth?.user;
            Sentry.setUser(user ? { id: String(user.id) } : null);
        };
        identify(initialPage);
        router.on('navigate', (event) => identify(event.detail.page));
    });
}
