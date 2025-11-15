const CACHE_NAME = 'otodo-cache-v6';
const URLS_TO_CACHE = [
  '/',
  '/index.php',
  '/task.php',
  '/completed.php',
  '/settings.php',
  '/login.php',
  '/register.php',
  '/sync-status.js',
  '/sw-register.js',
  // Removed dynamic-formatting.js as the app no longer uses dynamic line formatting
];

const QUEUE_DB = 'otodo-request-queue';
const QUEUE_STORE = 'requests';

function openQueueDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(QUEUE_DB, 1);
    request.onupgradeneeded = event => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains(QUEUE_STORE)) {
        db.createObjectStore(QUEUE_STORE, { keyPath: 'id', autoIncrement: true });
      }
    };
    request.onsuccess = event => resolve(event.target.result);
    request.onerror = () => reject(request.error);
  });
}

async function enqueueRequest(request) {
  const db = await openQueueDb();
  const tx = db.transaction(QUEUE_STORE, 'readwrite');
  const store = tx.objectStore(QUEUE_STORE);
  const headers = {};
  for (const [key, value] of request.headers.entries()) {
    headers[key] = value;
  }
  let body = null;
  if (request.method !== 'GET' && request.method !== 'HEAD') {
    try {
      body = await request.clone().arrayBuffer();
    } catch (err) {
      body = null;
    }
  }
  store.add({
    url: request.url,
    method: request.method,
    headers,
    body,
    timestamp: Date.now(),
  });
  await new Promise((resolve, reject) => {
    tx.oncomplete = resolve;
    tx.onerror = () => reject(tx.error);
    tx.onabort = () => reject(tx.error);
  });
  db.close();
}

async function getQueuedRequests() {
  const db = await openQueueDb();
  const tx = db.transaction(QUEUE_STORE, 'readonly');
  const store = tx.objectStore(QUEUE_STORE);
  const request = store.getAll();
  const requests = await new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result || []);
    request.onerror = () => reject(request.error);
  });
  await new Promise((resolve, reject) => {
    tx.oncomplete = resolve;
    tx.onerror = () => reject(tx.error);
    tx.onabort = () => reject(tx.error);
  });
  db.close();
  return requests;
}

async function removeQueuedRequest(id) {
  const db = await openQueueDb();
  const tx = db.transaction(QUEUE_STORE, 'readwrite');
  const store = tx.objectStore(QUEUE_STORE);
  const request = store.delete(id);
  await new Promise((resolve, reject) => {
    request.onsuccess = resolve;
    request.onerror = () => reject(request.error);
  });
  await new Promise((resolve, reject) => {
    tx.oncomplete = resolve;
    tx.onerror = () => reject(tx.error);
    tx.onabort = () => reject(tx.error);
  });
  db.close();
}

async function getQueueCount() {
  const db = await openQueueDb();
  const tx = db.transaction(QUEUE_STORE, 'readonly');
  const store = tx.objectStore(QUEUE_STORE);
  const request = store.count();
  const count = await new Promise((resolve, reject) => {
    request.onsuccess = () => resolve(request.result || 0);
    request.onerror = () => reject(request.error);
  });
  await new Promise((resolve, reject) => {
    tx.oncomplete = resolve;
    tx.onerror = () => reject(tx.error);
    tx.onabort = () => reject(tx.error);
  });
  db.close();
  return count;
}

async function notifyClients(pending) {
  const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
  for (const client of clients) {
    client.postMessage({ type: 'sync-status', pending });
  }
}

async function processQueue() {
  const queued = await getQueuedRequests();
  for (const entry of queued) {
    try {
      const headers = new Headers(entry.headers || {});
      const body = entry.body ? entry.body : undefined;
      const request = new Request(entry.url, {
        method: entry.method,
        headers,
        body,
        credentials: 'include',
      });
      const response = await fetch(request);
      if (!response || !response.ok) {
        throw new Error('Request failed');
      }
      await removeQueuedRequest(entry.id);
    } catch (err) {
      // Stop processing to retry later.
      break;
    }
  }
  const pending = await getQueueCount();
  await notifyClients(pending);
  if (pending > 0 && self.registration.sync && 'sync' in self.registration) {
    try {
      await self.registration.sync.register('sync-requests');
    } catch (err) {
      // ignore sync registration errors
    }
  }
}

self.addEventListener('install', event => {
  event.waitUntil(precacheEssentialShell().then(() => self.skipWaiting()));
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
    )).then(() => self.clients.claim())
  );
});

async function precacheEssentialShell() {
  const cache = await caches.open(CACHE_NAME);

  await Promise.allSettled(URLS_TO_CACHE.map(async url => {
    try {
      const response = await fetch(new Request(url, {
        credentials: 'include',
        cache: 'no-cache',
        redirect: 'follow',
      }));

      if (response && response.ok) {
        const request = new Request(url, { credentials: 'include' });
        await storeNavigationResponse(cache, request, response.clone());

        const resolvedUrl = response.url ? new URL(response.url) : null;
        const originalUrl = new URL(url, self.location.origin);
        if (resolvedUrl && resolvedUrl.href !== originalUrl.href) {
          await storeNavigationResponse(cache, resolvedUrl.href, response.clone());
        }
      }
    } catch (error) {
      // Ignore individual precache failures so one bad response does not prevent install.
    }
  }));
}

self.addEventListener('fetch', event => {
  const { request } = event;
  if (request.method !== 'GET' && request.method !== 'HEAD') {
    event.respondWith(handleQueueableRequest(event));
    return;
  }

  const url = new URL(request.url);
  const isNavigational =
    request.mode === 'navigate' ||
    ['/index.php', '/task.php', '/completed.php', '/settings.php'].some(path => url.pathname.endsWith(path));

  if (isNavigational) {
    event.respondWith(handleNavigationRequest(event));
    return;
  }

  event.respondWith(
    caches.match(request).then(response => {
      if (response) {
        return response;
      }
      return fetch(request).then(networkResponse => {
        if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
          const responseClone = networkResponse.clone();
          caches.open(CACHE_NAME).then(cache => cache.put(request, responseClone));
        }
        return networkResponse;
      }).catch(() => caches.match(request)
        .then(match => match || caches.match('/index.php'))
        .then(match => match || caches.match('/')));
    })
  );
});

async function handleNavigationRequest(event) {
  const { request } = event;
  const url = new URL(request.url);
  const cache = await caches.open(CACHE_NAME);

  try {
    const networkResponse = await fetch(request);
    if (networkResponse) {
      if (networkResponse.type === 'opaqueredirect') {
        cacheRedirectTarget(cache, url).catch(() => {});
      } else if (networkResponse.ok) {
        await storeNavigationResponse(cache, request, networkResponse.clone());
      }
    }
    return networkResponse;
  } catch (error) {
    const exactMatch = await caches.match(request);
    if (exactMatch) {
      return exactMatch;
    }

    const pathMatch = await caches.match(url.pathname, { ignoreSearch: true });
    if (pathMatch) {
      return pathMatch;
    }

    const cachedIndex = await caches.match('/index.php');
    if (cachedIndex) {
      return cachedIndex;
    }

    const cachedRoot = await caches.match('/');
    if (cachedRoot) {
      return cachedRoot;
    }

    return new Response('<!DOCTYPE html><html><body><h1>Offline</h1><p>The app is unavailable offline because this page was not cached yet.</p></body></html>', {
      status: 503,
      headers: { 'Content-Type': 'text/html' },
    });
  }
}

async function storeNavigationResponse(cache, request, response) {
  const url = typeof request === 'string' ? new URL(request, self.location.origin) : new URL(request.url);
  const variants = [];
  const seen = new Set();

  const addVariant = variant => {
    if (!variant) {
      return;
    }
    const key = variant instanceof Request ? `${variant.method || 'GET'}:${variant.url}` : `string:${variant}`;
    if (seen.has(key)) {
      return;
    }
    seen.add(key);
    variants.push(variant);
  };

  if (typeof request === 'string') {
    addVariant(request);
  } else if (request instanceof Request && request.mode !== 'navigate') {
    addVariant(request);
  }

  addVariant(url.pathname || '/');

  addVariant(url.href);

  if (url.pathname === '/' || url.pathname === '') {
    addVariant('/index.php');
  }

  if (url.pathname === '/index.php') {
    addVariant('/');
  }

  addVariant(new Request(url.href, { credentials: 'include' }));

  for (const variant of variants) {
    try {
      await cache.put(variant, response.clone());
    } catch (error) {
      // Ignore cache.put errors so that unsupported variants do not stop caching.
    }
  }
}

async function cacheRedirectTarget(cache, url) {
  try {
    const redirectedResponse = await fetch(new Request(url.href, {
      credentials: 'include',
      cache: 'no-cache',
      redirect: 'follow',
    }));

    if (!redirectedResponse || !redirectedResponse.ok) {
      return;
    }

    await storeNavigationResponse(cache, url.href, redirectedResponse.clone());

    const finalUrl = redirectedResponse.url ? new URL(redirectedResponse.url) : null;
    if (finalUrl && finalUrl.href !== url.href) {
      await storeNavigationResponse(cache, finalUrl.href, redirectedResponse.clone());
    }
  } catch (error) {
    // Ignore redirect caching failures to avoid breaking the main response flow.
  }
}

async function handleQueueableRequest(event) {
  const url = new URL(event.request.url);
  const allowQueue = !['/login.php', '/register.php'].some(path => url.pathname.endsWith(path));
  const requestClone = event.request.clone();
  try {
    const response = await fetch(event.request);
    const pending = await getQueueCount();
    await notifyClients(pending);
    return response;
  } catch (error) {
    if (!allowQueue) {
      return new Response('<!DOCTYPE html><html><body><p>Request failed while offline.</p></body></html>', {
        status: 503,
        headers: { 'Content-Type': 'text/html' },
      });
    }
    await enqueueRequest(requestClone);
    const pending = await getQueueCount();
    await notifyClients(pending);
    if (self.registration.sync && 'sync' in self.registration) {
      try {
        await self.registration.sync.register('sync-requests');
      } catch (err) {
        // ignore sync registration errors
      }
    }
    const headers = new Headers({ 'Content-Type': 'application/json' });
    return new Response(JSON.stringify({ queued: true }), { status: 202, headers });
  }
}

self.addEventListener('sync', event => {
  if (event.tag === 'sync-requests') {
    event.waitUntil(processQueue());
  }
});

self.addEventListener('message', event => {
  if (event.data && event.data.type === 'get-sync-status') {
    event.waitUntil(
      getQueueCount().then(pending => notifyClients(pending))
    );
  } else if (event.data && event.data.type === 'process-queue') {
    event.waitUntil(processQueue());
  }
});
