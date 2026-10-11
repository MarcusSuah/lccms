/* LCCMS field application service worker. Generated per build: {{ $version }} */
const CACHE = 'lccms-field-{{ $version }}';
const SHELL = '/field';
const ASSETS = @json($assets);

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then(async (cache) => {
            await cache.addAll(ASSETS);
            try {
                const response = await fetch(new Request(SHELL, { credentials: 'same-origin' }));

                // Never cache a redirect to the sign-in page as the field shell.
                if (response.ok && !response.redirected) {
                    await cache.put(SHELL, response);
                }
            } catch (error) {
                // The shell is cached on the next online visit.
            }
        }).then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key.startsWith('lccms-field-') && key !== CACHE).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // The field shell: network first so updates arrive, cache when offline.
    if (request.mode === 'navigate' && url.pathname === SHELL) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok && !response.redirected) {
                        const copy = response.clone();
                        caches.open(CACHE).then((cache) => cache.put(SHELL, copy));
                    }

                    return response;
                })
                .catch(() => caches.match(SHELL).then((cached) => cached || Response.error())),
        );

        return;
    }

    // Built assets are immutable: cache first.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    const copy = response.clone();
                    caches.open(CACHE).then((cache) => cache.put(request, copy));
                }

                return response;
            })),
        );
    }

    // Everything else (API, admin pages) always goes to the network.
});

self.addEventListener('message', (event) => {
    if (event.data === 'wipe') {
        event.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((key) => key.startsWith('lccms-field-')).map((key) => caches.delete(key)))));
    }
});
