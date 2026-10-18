import js from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';
import prettier from 'eslint-config-prettier';
import globals from 'globals';

export default [
    {
        // data/ holds the designer's files as delivered; resources/js/legacy is generated from them by `npm run legacy:sync`.
        ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**', 'bootstrap/**', 'data/**', 'resources/js/legacy/**'],
    },
    js.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        files: ['resources/js/**/*.{js,vue}'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
                route: 'readonly',
            },
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'off',
            'vue/require-default-prop': 'off',
            'no-empty': ['error', { allowEmptyCatch: true }],
            'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
        },
    },
    {
        files: ['*.js', 'scripts/**/*.{js,mjs}'],
        languageOptions: {
            globals: globals.node,
        },
    },
    // Formatting is Prettier's job; turn off any rule that would fight it.
    prettier,
];
