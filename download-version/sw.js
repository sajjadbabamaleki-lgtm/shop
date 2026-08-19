/*
 * The service worker that exists so the shop can be installed, and does
 * nothing else on purpose.
 *
 * Chrome will not offer «نصب برنامه» on Android unless the site registers a
 * service worker with a fetch handler. That is the whole reason this file is
 * here — with no `start_url`, no 512px icon and no worker, Chrome fell back to
 * minting a throwaway APK, which Google Play Protect then refused with «This
 * app was built for an older version of Android». The client photographed it.
 *
 * **It caches nothing, and that is a decision rather than an omission.** This
 * site's whole appearance lives in one stylesheet, and it has already gone
 * live once looking like somebody else's template because that file did not
 * arrive — see «قالب قبلی» in CLAUDE.md. A cache-first worker is a machine for
 * making exactly that failure permanent: it would serve yesterday's CSS from
 * disk with no network involved and no way for a deploy to correct it. The
 * fetch handler below is a pass-through, so every request behaves exactly as
 * it does today.
 *
 * If offline support is ever wanted, it has to be built deliberately —
 * versioned, with the design gate's signature checked before anything is
 * served from disk — not by adding a cache to this file.
 */
const VERSION = 'vikyplus-passthrough-1';

self.addEventListener('install', () => {
    // No waiting: there is nothing to warm up, and a worker stuck in
    // `installed` is a worker that never takes over.
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        // Anything a previous version of this file may have stored is removed.
        // Written now rather than when it is needed: by the time somebody adds
        // a cache and regrets it, the browsers holding it are not reachable.
        for (const key of await caches.keys()) {
            if (key !== VERSION) await caches.delete(key);
        }

        await self.clients.claim();
    })());
});

// The handler Chrome requires. It answers nothing itself.
self.addEventListener('fetch', () => {});
