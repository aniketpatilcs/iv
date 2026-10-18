<script setup>
/**
 * Hosts one of the designer's standalone form prototypes (resources/js/legacy/<name>)
 * inside the Inertia app: renders its markup, runs its script, uploads its files in
 * the background as they are added, connects its submit to the server, and removes
 * everything it attached globally when the page is left.
 */
import { computed, onBeforeUnmount, onMounted, ref, shallowRef } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { route } from 'ziggy-js';
import { createUploadQueue, UploadError } from '@/uploads/uploadQueue';

const props = defineProps({
    name: { type: String, required: true },
    markup: { type: String, required: true },
    mount: { type: Function, required: true },
    submitUrl: { type: String, required: true },
    /**
     * Where the files are in the prototype's payload and how to send them:
     * [{ from: 'images', field: 'images', kind: 'vehicle_image' }, …]
     */
    fileFields: { type: Array, default: () => [] },
    /** The prototype's own element for status/error messages. */
    statusId: { type: String, required: true },
    submitButton: { type: String, required: true },
    /** Window globals the prototype defines, removed on unmount. */
    globals: { type: Array, default: () => [] },
    initialType: { type: String, default: null },
});

const root = ref(null);
const user = usePage().props.auth.user;
let api = null;
let cleanup = () => {};
let submitting = false;

const uploadStats = shallowRef({ pending: 0, failed: 0, done: 0, loaded: 0, total: 0 });
const uploads = createUploadQueue({ onChange: (stats) => (uploadStats.value = stats) });
const uploadPercent = computed(() => {
    const { loaded, total } = uploadStats.value;
    return total ? Math.min(99, Math.round((loaded / total) * 100)) : 0;
});

/**
 * Runs `fn` while recording every window/document listener and IntersectionObserver
 * it creates, and returns a function that removes them all again.
 */
function runTracked(fn) {
    const listeners = [];
    const observers = [];
    const targets = [window, document];
    const NativeObserver = window.IntersectionObserver;

    for (const target of targets) {
        target.addEventListener = function (type, listener, options) {
            listeners.push([target, type, listener, options]);
            return EventTarget.prototype.addEventListener.call(this, type, listener, options);
        };
    }
    window.IntersectionObserver = class extends NativeObserver {
        constructor(...args) {
            super(...args);
            observers.push(this);
        }
    };

    try {
        return [
            fn(),
            () => {
                listeners.forEach(([target, type, listener, options]) => target.removeEventListener(type, listener, options));
                observers.forEach((observer) => observer.disconnect());
            },
        ];
    } finally {
        targets.forEach((target) => delete target.addEventListener);
        window.IntersectionObserver = NativeObserver;
    }
}

function el(id) {
    return document.getElementById(id);
}

function showStatus(message) {
    const status = el(props.statusId);
    if (!status) return;
    status.classList.add('error');
    status.style.display = 'block';
    status.textContent = message;
    status.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function errorMessage(error) {
    if (error instanceof UploadError) return error.message;
    const response = error.response;
    if (!response) return 'We could not reach the server. Check your connection and try again.';
    switch (response.status) {
        case 422:
            return (
                Object.values(response.data.errors ?? {})
                    .flat()
                    .slice(0, 4)
                    .join(' ') || 'Please review the form and try again.'
            );
        case 413:
            return 'The attached files are too large to upload together. Remove some files and try again.';
        case 419:
            return 'Your session has expired. Refresh the page and sign in again.';
        case 429:
            return 'Too many submissions in a short time. Wait a minute and try again.';
        default:
            return 'Something went wrong while submitting. Please try again.';
    }
}

async function submit() {
    if (submitting) return;
    submitting = true;

    const button = root.value.querySelector(props.submitButton);
    const label = button?.textContent;
    const setLabel = (text) => button && (button.textContent = text);
    if (button) button.disabled = true;

    try {
        const payload = api.getPayload();
        const body = {};

        // Swap File objects for upload ids. Most files finished uploading while the form was
        // being filled in; ensure() waits for any still in flight and retries any that failed.
        const files = props.fileFields.flatMap(({ from, field, kind }) => {
            const list = payload[from] ?? [];
            delete payload[from];
            body[field] = [];
            return list.map((file) => ({ file, field, kind }));
        });
        if (files.length) {
            let ready = 0;
            setLabel(`Uploading files… 0 of ${files.length}`);
            const ids = await Promise.all(
                files.map(({ file, kind }) =>
                    uploads.ensure(file, kind).then((id) => {
                        setLabel(`Uploading files… ${++ready} of ${files.length}`);
                        return id;
                    }),
                ),
            );
            files.forEach(({ field }, i) => body[field].push(ids[i]));
        }

        setLabel('Submitting…');
        body.payload = JSON.stringify(payload);
        const { data } = await axios.post(props.submitUrl, body);
        api.openConfirmation(data);
    } catch (error) {
        showStatus(errorMessage(error));
    } finally {
        submitting = false;
        if (button) {
            button.disabled = false;
            button.textContent = label;
        }
    }
}

/** Puts the signed-in account and app navigation into the prototype's header. */
function personalize() {
    const setText = (id, value) => el(id) && value && (el(id).textContent = value);
    const setValue = (id, value) => el(id) && value && (el(id).value = value);

    setText('signedInName', user.name);
    setValue('adjusterName', user.name);
    setValue('insuranceCompany', user.insurance_company);
    el('adjusterName')?.setAttribute('aria-label', `Adjuster signed in as ${user.name}`);

    const link = (href, text) => Object.assign(document.createElement('a'), { href, textContent: text });
    const signOut = Object.assign(document.createElement('button'), {
        type: 'button',
        className: 'popover-action',
        textContent: 'Sign Out',
    });
    signOut.addEventListener('click', () => router.post(route('logout')));
    const account = Object.assign(document.createElement('span'), { className: 'popover-account', textContent: user.name });

    el('userDropdown')?.replaceChildren(account, link(route('dashboard'), 'Dashboard'), signOut);
    el('portalMenu')?.replaceChildren(
        link(route('dashboard'), 'Dashboard'),
        link(route('assignments.create'), 'Create Assignment'),
        link(route('bid-requests.create'), 'Bid Request'),
    );

    // Route in-app links through Inertia instead of full page loads.
    for (const menu of [el('userDropdown'), el('portalMenu')]) {
        menu?.addEventListener('click', (event) => {
            const anchor = event.target.closest('a[href]');
            if (!anchor || anchor.origin !== window.location.origin) return;
            event.preventDefault();
            router.visit(anchor.href);
        });
    }
}

onMounted(() => {
    [api, cleanup] = runTracked(() =>
        props.mount({
            submit,
            initialType: props.initialType,
            fileAdded: (file, kind) => uploads.add(file, kind),
        }),
    );
    personalize();
    window.scrollTo(0, 0);
});

onBeforeUnmount(() => {
    uploads.dispose();
    cleanup();
    document.body.classList.remove('modal-open');
    props.globals.forEach((key) => delete window[key]);
});
</script>

<template>
    <div ref="root" class="legacy-page" :class="`legacy-${name}`" v-html="markup"></div>

    <div v-if="uploadStats.pending || uploadStats.failed" class="upload-indicator" role="status" aria-live="polite">
        <template v-if="uploadStats.pending">
            <span class="upload-spinner" aria-hidden="true"></span>
            Uploading {{ uploadStats.pending }} {{ uploadStats.pending === 1 ? 'file' : 'files' }}… {{ uploadPercent }}%
        </template>
        <template v-else>
            {{ uploadStats.failed }} {{ uploadStats.failed === 1 ? 'file' : 'files' }} didn’t upload. They will be retried when you submit.
        </template>
    </div>
</template>

<style>
.legacy-page {
    min-height: 100vh;
}

/* Background upload progress; the user can keep filling in the form. */
.upload-indicator {
    position: fixed;
    right: 16px;
    bottom: 16px;
    z-index: 50;
    display: flex;
    align-items: center;
    gap: 10px;
    max-width: calc(100vw - 32px);
    padding: 10px 14px;
    border: 1px solid #dce3eb;
    border-radius: 8px;
    background: #fff;
    box-shadow: 0 8px 24px #152b4425;
    color: #173a7d;
    font:
        600 13px 'DM Sans',
        Arial,
        sans-serif;
}

.upload-spinner {
    width: 14px;
    height: 14px;
    flex: none;
    border: 2px solid #d8e1ed;
    border-top-color: #173a7d;
    border-radius: 50%;
    animation: upload-spin 0.7s linear infinite;
}

@keyframes upload-spin {
    to {
        transform: rotate(360deg);
    }
}

@media (prefers-reduced-motion: reduce) {
    .upload-spinner {
        animation: none;
    }
}

/* Items injected into the prototype header popovers by personalize(). */
.header-popover .popover-account {
    color: var(--muted);
    font-size: 12px;
}

.header-popover .popover-action {
    padding: 0;
    border: 0;
    background: none;
    color: #173a7d;
    text-align: left;
}
</style>
