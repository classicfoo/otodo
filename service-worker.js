const CACHE_NAME = 'otodo-cache-v4';
const URLS_TO_CACHE = [
  '/',
  '/login.php',
  '/register.php',
  '/settings.php',
  '/sync-status.js',
  '/sw-register.js',
  // Removed dynamic-formatting.js as the app no longer uses dynamic line formatting
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(URLS_TO_CACHE))
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
    ))
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') {
    return;
  }

  const url = new URL(event.request.url);
  const isNavigational =
    event.request.mode === 'navigate' ||
    ['/index.php', '/task.php', '/completed.php'].some(path => url.pathname.endsWith(path));

  if (isNavigational) {
    event.respondWith(
      caches.match(event.request).then(cacheHit => {
        const fetchPromise = fetch(event.request)
          .then(networkResponse => {
            if (networkResponse && networkResponse.status === 200) {
              const copy = networkResponse.clone();
              caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy));
            }
            return networkResponse;
          })
          .catch(() => cacheHit || caches.match('/'));

        event.waitUntil(fetchPromise.catch(() => {}));
        return cacheHit || fetchPromise;
      })
    );
    return;
  }

  event.respondWith(
    caches.match(event.request).then(response => {
      const fetchPromise = fetch(event.request)
        .then(networkResponse => {
          if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
            const responseClone = networkResponse.clone();
            caches.open(CACHE_NAME).then(cache => cache.put(event.request, responseClone));
          }
          return networkResponse;
        })
        .catch(() => caches.match('/'));

      event.waitUntil(fetchPromise.catch(() => {}));
      return response || fetchPromise;
    })
  );
});
