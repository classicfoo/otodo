const CACHE_NAME = 'otodo-cache-v4';
const URLS_TO_CACHE = [
  '/',
  '/login.php',
  '/register.php',
  '/settings.php',
  '/sync-status.js',
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
    event.respondWith(
      fetch(request).catch(() => caches.match(request))
    );
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
      }).catch(() => caches.match('/'));
    })
  );
});

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
