import '../css/app.css';
import axios from 'axios';
import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';
import { initSentry } from './sentry';

// Laravel sets the XSRF-TOKEN cookie; axios echoes it back as X-XSRF-TOKEN automatically.
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios = axios;

createInertiaApp({
    title: (title) => (title ? `IV Auto | ${title}` : 'IV Auto'),
    // Each page is its own chunk, so the large form pages only load when visited.
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const page = pages[`./Pages/${name}.vue`];
        if (!page) throw new Error(`Unknown page: ${name}`);
        return page();
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue);

        initSentry(app, props.initialPage);
        app.mount(el);
    },
    progress: {
        color: '#cf933e',
    },
});
