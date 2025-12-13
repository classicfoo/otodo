const CACHE_NAME = 'otodo-cache-v5';
const DB_NAME = 'otodo-offline';
const DB_STORE = 'requests';
const DB_VERSION = 1;

const SYNC_TAG = 'otodo-outbox-sync';
const URLS_TO_CACHE = [
  '/',
  '/index.php',
  '/task.php',
  '/completed.php',
  '/login.php',
  '/register.php',
  '/settings.php',
  '/sync-status.js',
  '/sw-register.js',
  // Removed dynamic-formatting.js as the app no longer uses dynamic line formatting
];

let isDrainingQueue = false;

function openDatabase() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onupgradeneeded = () => {
      const db = request.result;
      if (!db.objectStoreNames.contains(DB_STORE)) {
        db.createObjectStore(DB_STORE, { keyPath: 'id' }).createIndex('timestamp', 'timestamp');
      }
    };

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

function requestId() {
  if (self.crypto && 'randomUUID' in self.crypto) {
    return self.crypto.randomUUID();
  }
  return `req-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

async function enqueueRequest(request) {
  const db = await openDatabase();
  const cloned = request.clone();
  const headers = [...cloned.headers.entries()];
  const bodyBuffer = await cloned.arrayBuffer().catch(() => null);

  const entry = {
    id: requestId(),
    url: cloned.url,
    method: cloned.method,
    headers,
    body: bodyBuffer ? Array.from(new Uint8Array(bodyBuffer)) : null,
    timestamp: Date.now(),
  };

  await new Promise((resolve, reject) => {
    const tx = db.transaction(DB_STORE, 'readwrite');
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
    tx.objectStore(DB_STORE).put(entry);
  });

  return entry;
}

async function deleteRequest(id) {
  const db = await openDatabase();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(DB_STORE, 'readwrite');
    tx.oncomplete = () => resolve();
    tx.onerror = () => reject(tx.error);
    tx.objectStore(DB_STORE).delete(id);
  });
}

async function getQueuedRequests() {
  const db = await openDatabase();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(DB_STORE, 'readonly');
    const store = tx.objectStore(DB_STORE);
    const items = [];

    store.openCursor(null, 'next').onsuccess = event => {
      const cursor = event.target.result;
      if (cursor) {
        items.push(cursor.value);
        cursor.continue();
      }
    };

    tx.oncomplete = () => resolve(items.sort((a, b) => a.timestamp - b.timestamp));
    tx.onerror = () => reject(tx.error);
  });
}

async function registerSync() {
  if (!self.registration || !('sync' in self.registration)) {
    return;
  }

  try {
    await self.registration.sync.register(SYNC_TAG);
  } catch (error) {
    console.warn('Background sync registration failed', error);
  }
}

function toRequestInit(entry) {
  const headers = new Headers(entry.headers || []);
  const body = entry.body ? new Uint8Array(entry.body).buffer : undefined;

  return {
    method: entry.method,
    headers,
    body,
    credentials: 'include',
  };
}

function shouldDiscardOnConflict(status) {
  return status === 409 || status === 412;
}

async function sendWithRetry(entry, attempts = 3) {
  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    try {
      const response = await fetch(entry.url, toRequestInit(entry));

      if (response.ok) {
        return { outcome: 'success', response };
      }

      if (shouldDiscardOnConflict(response.status)) {
        console.warn('Discarding conflicting request from queue', { id: entry.id, url: entry.url, status: response.status });
        return { outcome: 'discard', response };
      }

      console.warn('Request returned non-OK status; will retry', { id: entry.id, url: entry.url, status: response.status, attempt });
    } catch (error) {
      console.error('Request failed; will retry', { id: entry.id, url: entry.url, attempt, error: error && error.message ? error.message : error });
    }

    await new Promise(resolve => setTimeout(resolve, attempt * 1000));
  }

  return { outcome: 'failed' };
}

async function drainQueue() {
  if (isDrainingQueue) {
    return;
  }

  isDrainingQueue = true;

  try {
    const requests = await getQueuedRequests();
    for (const entry of requests) {
      const result = await sendWithRetry(entry);

      if (result.outcome === 'success') {
        await deleteRequest(entry.id);
        console.info('Queued request sent successfully', { id: entry.id, url: entry.url });
      } else if (result.outcome === 'discard') {
        await deleteRequest(entry.id);
        console.warn('Removed conflicting queued request', { id: entry.id, url: entry.url });
      } else {
        console.error('Leaving request in queue after repeated failures', { id: entry.id, url: entry.url });
        break;
      }
    }
  } catch (error) {
    console.error('Error while draining queue', error);
  } finally {
    isDrainingQueue = false;
  }
}

async function handleNonGetRequest(event) {
  const queuedRequestClone = event.request.clone();

  try {
    const liveResponse = await fetch(event.request);
    return liveResponse;
  } catch (error) {
    const entry = await enqueueRequest(queuedRequestClone);
    event.waitUntil(registerSync());
    console.warn('Queued offline request', { id: entry.id, url: entry.url, method: entry.method, error });

    return new Response(
      JSON.stringify({
        queued: true,
        offline: true,
        placeholder: true,
        requestId: entry.id,
        message: 'Request saved for retry when back online.',
      }),
      {
        status: 202,
        headers: { 'Content-Type': 'application/json' },
      },
    );
  }
}

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(URLS_TO_CACHE))
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    Promise.all([
      caches.keys().then(keys => Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
      )),
      drainQueue().catch(() => {}),
    ])
  );
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') {
    event.respondWith(handleNonGetRequest(event));
    return;
  }

  const url = new URL(event.request.url);
  const isNavigational =
    event.request.mode === 'navigate' ||
    ['/index.php', '/task.php', '/completed.php'].some(path => url.pathname.endsWith(path));

  if (isNavigational) {
    event.respondWith(
      fetch(event.request)
        .then(networkResponse => {
          if (networkResponse && networkResponse.status === 200) {
            const copy = networkResponse.clone();
            event.waitUntil(
              caches.open(CACHE_NAME).then(cache => cache.put(event.request, copy)).catch(() => {})
            );
          }
          return networkResponse;
        })
        .catch(() => caches.match(event.request).then(cacheHit => cacheHit || caches.match('/')))
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

self.addEventListener('sync', event => {
  if (event.tag === SYNC_TAG) {
    event.waitUntil(drainQueue());
  }
});

self.addEventListener('online', () => {
  drainQueue();
});
