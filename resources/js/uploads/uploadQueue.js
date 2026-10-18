import axios from 'axios';
import { route } from 'ziggy-js';

/** Parallel uploads per page: fast on good connections without saturating a phone's uplink. */
const CONCURRENCY = 3;
const MAX_ATTEMPTS = 4;
const RETRY_DELAYS_MS = [1000, 3000, 8000];

/** A failure with a message written for the user (shown as-is in the form). */
export class UploadError extends Error {}

/**
 * Uploads files in the background as soon as they are added to a form, so that by
 * the time the user submits, the files are usually already on the server.
 *
 *   const queue = createUploadQueue({ onChange: (stats) => … });
 *   queue.add(file, 'vehicle_image');          // start now
 *   const id = await queue.ensure(file, kind);  // at submit: wait for (or retry) it
 *
 * Network errors, 5xx and 429 responses are retried with backoff; validation
 * errors (wrong type, too large) are not, and surface with the server's message.
 */
export function createUploadQueue({ onChange = () => {} } = {}) {
    /** @type {Map<File, {kind: string, status: string, promise: Promise<string>, loaded: number, total: number, error?: string}>} */
    const jobs = new Map();
    const waiting = [];
    const controller = new AbortController();
    let active = 0;
    let compress = null;

    const notify = () => onChange(stats());

    function stats() {
        let pending = 0;
        let failed = 0;
        let loaded = 0;
        let total = 0;
        for (const job of jobs.values()) {
            if (job.status === 'failed') failed++;
            else if (job.status !== 'done') pending++;
            loaded += job.loaded;
            total += job.total;
        }
        return { pending, failed, done: jobs.size - pending - failed, loaded, total };
    }

    function add(file, kind) {
        const existing = jobs.get(file);
        if (existing && existing.kind === kind && existing.status !== 'failed') return existing.promise;

        const job = { kind, status: 'queued', loaded: 0, total: file.size };
        job.promise = new Promise((resolve, reject) => waiting.push({ file, job, resolve, reject }));
        // Callers that only start an upload don't await it; avoid unhandled-rejection noise.
        job.promise.catch(() => {});
        jobs.set(file, job);
        notify();
        pump();
        return job.promise;
    }

    function pump() {
        while (active < CONCURRENCY && waiting.length) {
            const next = waiting.shift();
            active++;
            run(next).finally(() => {
                active--;
                pump();
            });
        }
    }

    async function run({ file, job, resolve, reject }) {
        try {
            let body = file;
            if (job.kind === 'vehicle_image') {
                job.status = 'compressing';
                notify();
                compress ??= (await import('./compressImage.js')).default;
                body = await compress(file);
                job.total = body.size;
            }

            job.status = 'uploading';
            notify();
            resolve(await send(body, file.name, job));
            job.status = 'done';
            job.loaded = job.total;
        } catch (error) {
            job.status = 'failed';
            job.error = error.message;
            reject(error);
        } finally {
            notify();
        }
    }

    async function send(body, name, job) {
        for (let attempt = 1; ; attempt++) {
            const form = new FormData();
            form.append('kind', job.kind);
            form.append('file', body, name);
            try {
                const { data } = await axios.post(route('uploads.store'), form, {
                    signal: controller.signal,
                    onUploadProgress: (event) => {
                        job.loaded = event.loaded;
                        notify();
                    },
                });
                return data.id;
            } catch (error) {
                if (controller.signal.aborted) throw new UploadError('Upload cancelled.');
                const status = error.response?.status;
                if (status === 422) {
                    const messages = Object.values(error.response.data.errors ?? {}).flat();
                    throw new UploadError(`${name}: ${messages[0] ?? 'this file was not accepted.'}`);
                }
                const retryable = !status || status >= 500 || status === 429;
                if (!retryable || attempt >= MAX_ATTEMPTS) {
                    throw new UploadError(`${name} could not be uploaded. Check your connection and try again.`);
                }
                job.loaded = 0;
                await new Promise((wake) => setTimeout(wake, RETRY_DELAYS_MS[attempt - 1]));
            }
        }
    }

    return {
        add,
        /** Resolves with the upload id, starting or retrying the upload if needed. */
        ensure: add,
        stats,
        /** Stop in-flight uploads when the page is left; unclaimed server copies are pruned later. */
        dispose() {
            controller.abort();
            waiting.length = 0;
        },
    };
}
