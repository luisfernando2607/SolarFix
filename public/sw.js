// SolarFix: permite instalar la app. No guarda páginas en caché (tienen datos privados);
// solo muestra un aviso si no hay conexión.
const OFFLINE_CACHE = 'solarfix-offline-v2';
const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(OFFLINE_CACHE).then((cache) => cache.addAll([OFFLINE_URL, '/icons/icon-192.png'])));
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== OFFLINE_CACHE).map((key) => caches.delete(key))))
    );
    self.clients.claim();
});

self.addEventListener('fetch', (event) => {
    if (event.request.mode !== 'navigate') {
        return;
    }
    event.respondWith(fetch(event.request).catch(() => caches.match(OFFLINE_URL)));
});
