import imageCompression from 'browser-image-compression';
// Served from our own build: by default the library's worker downloads this file from a public CDN.
import workerLibUrl from 'browser-image-compression/dist/browser-image-compression.js?url';

/**
 * Vehicle photos can be claim evidence, so keep them sharp: longest side 2560 px,
 * JPEG quality 0.85, EXIF (capture time, GPS, orientation) preserved. A typical
 * 4–8 MB phone photo comes out around 0.8–1.2 MB.
 */
const OPTIONS = {
    maxWidthOrHeight: 2560,
    initialQuality: 0.85,
    maxSizeMB: 20,
    fileType: 'image/jpeg',
    preserveExif: true,
    useWebWorker: true,
};

/** Photos already this small upload quickly as they are. */
const SKIP_BELOW_BYTES = 1024 * 1024;

/**
 * Returns a smaller copy of a JPEG, or the original if it is already small,
 * compression doesn't help, or the browser can't compress it.
 */
export default async function compressImage(file) {
    if (file.size < SKIP_BELOW_BYTES) return file;

    try {
        const compressed = await imageCompression(file, {
            ...OPTIONS,
            // The worker resolves relative URLs against its blob: address, so pass an absolute one.
            libURL: new URL(workerLibUrl, window.location.origin).href,
        });
        if (compressed.size >= file.size) return file;
        return new File([compressed], file.name, { type: 'image/jpeg', lastModified: file.lastModified });
    } catch {
        return file;
    }
}
