import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import prefixSelector from 'postcss-prefix-selector';
import { fileURLToPath, URL } from 'node:url';

/**
 * The designer stylesheets in resources/js/legacy/<form>/style.css were written for
 * standalone pages, so they style `body`, `:root`, `header`, etc. globally. Scope each
 * one to its page wrapper (`.legacy-<form>`) so the forms can't leak into each other or
 * into the rest of the app during client-side navigation.
 */
function scopeLegacyCss() {
    return prefixSelector({
        prefix: '',
        includeFiles: [/resources[\\/]js[\\/]legacy[\\/]/],
        transform(_prefix, selector, _prefixed, filePath) {
            const form = filePath.match(/legacy[\\/]([\w-]+)[\\/]/)?.[1];
            const scope = `.legacy-${form}`;

            // Toggled on <body> by the prototype script to lock scrolling behind its modal.
            if (selector === '.modal-open') return 'body.modal-open';
            if (selector === ':root' || selector === 'html' || selector === 'body') return scope;
            if (/^(html|body)(?=[\s>:.[])/.test(selector)) return selector.replace(/^(html|body)/, scope);

            return `${scope} ${selector}`;
        },
    });
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: { base: null, includeAbsolute: false },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    css: {
        postcss: {
            plugins: [scopeLegacyCss()],
        },
    },
});
