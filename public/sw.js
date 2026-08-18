/* KupiTelefon PWA service worker */
const CACHE_VERSION = 'kt-pwa-v2';
const SHELL = [
  '/offline.html',
  '/manifest.webmanifest',
  '/assets/css/style.css',
  '/assets/img/pwa-192.png',
  '/assets/img/pwa-512.png',
  '/assets/img/favicon.png',
  '/assets/img/logo-mark.png',
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_VERSION).then((cache) => cache.addAll(SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== CACHE_VERSION).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

function isStaticAsset(url) {
  return /\/assets\/(css|js|img)\//.test(url.pathname)
    || url.pathname === '/manifest.webmanifest'
    || url.pathname.endsWith('.webp')
    || url.pathname.endsWith('.png')
    || url.pathname.endsWith('.jpg')
    || url.pathname.endsWith('.jpeg')
    || url.pathname.endsWith('.svg')
    || url.pathname.endsWith('.woff2');
}

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;

  let url;
  try {
    url = new URL(req.url);
  } catch (e) {
    return;
  }
  if (url.origin !== self.location.origin) return;

  // Navigacije: network first, offline fallback
  if (req.mode === 'navigate') {
    event.respondWith(
      fetch(req)
        .then((res) => res)
        .catch(() => caches.match('/offline.html'))
    );
    return;
  }

  // Statički asseti: cache first
  if (isStaticAsset(url)) {
    event.respondWith(
      caches.match(req).then((cached) => {
        const fetchPromise = fetch(req)
          .then((res) => {
            if (res && res.ok) {
              const clone = res.clone();
              caches.open(CACHE_VERSION).then((cache) => cache.put(req, clone));
            }
            return res;
          })
          .catch(() => cached);
        return cached || fetchPromise;
      })
    );
  }
});
