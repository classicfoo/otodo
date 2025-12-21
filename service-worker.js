const CACHE_BASE = 'otodo-cache-v10';
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
  '/sync-queue-ui.js',
  '/sw-register.js',
  '/assets/styles/vanilla.css',
  '/assets/vanilla-ui.js',
  // Removed dynamic-formatting.js as the app no longer uses dynamic line formatting
];

let isDrainingQueue = false;
let activeUserScope = {
  sessionId: null,
  userId: null,
  offlineMode: false,
};
let lastClientOnline = null;

function updateOfflineFromClient(onlineValue, offlineModeFlag) {
  if (typeof onlineValue === 'boolean') {
    lastClientOnline = onlineValue;
  }

  if (typeof onlineValue === 'boolean' || typeof offlineModeFlag === 'boolean') {
    activeUserScope.offlineMode = offlineModeFlag === true || onlineValue === false;
  }
}

function markOnlineFromNetwork() {
  updateOfflineFromClient(true, false);
}

function isOffline() {
  if (activeUserScope.offlineMode === true) return true;
  if (lastClientOnline === false) return true;
  if (lastClientOnline === true) return false;
  return false;
}

function offlineNavigationResponse() {
  return new Response(
    `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Offline</title>` +
    `<meta name="viewport" content="width=device-width, initial-scale=1">` +
    `<style>body{font-family:system-ui,-apple-system,sans-serif;background:#f8f9fa;color:#212529;` +
    `display:flex;align-items:center;justify-content:center;height:100vh;margin:0;padding:1rem;text-align:center;}` +
    `.card{background:#fff;border:1px solid #dee2e6;border-radius:12px;padding:24px;box-shadow:0 4px 12px rgba(0,0,0,0.05);max-width:480px;}` +
    `.badge{display:inline-block;padding:0.35rem 0.75rem;border-radius:999px;font-weight:600;font-size:0.9rem;}` +
    `.badge.offline{background:#fff4e6;color:#d9480f;border:1px solid #ffd8a8;}</style></head>` +
    `<body><div class="card"><div class="badge offline">Offline</div><h1 style="margin:12px 0 8px;">No offline copy yet</h1>` +
    `<p style="margin:0 0 12px;font-size:0.95rem;">You're offline and this page wasn't saved for offline use. ` +
    `Reconnect to load it, then it will be available even without a connection.</p>` +
    `<p style="margin:0;font-size:0.9rem;color:#6c757d;">Tip: open tasks while online to refresh the offline cache.</p></div></body></html>`,
    {
      status: 200,
      headers: { 'Content-Type': 'text/html', 'Cache-Control': 'no-store' },
    },
  );
}

async function precacheCoreAssets() {
  try {
    const cache = await getUserCache();
    const addToCache = (cache && typeof cache.add === 'function')
      ? cache.add.bind(cache)
      : (cache && typeof cache.addAll === 'function'
        ? url => cache.addAll([url])
        : null);

    if (!addToCache) {
      console.warn('Precache skipped: cache.add unavailable');
      return;
    }
    const results = await Promise.all(
      URLS_TO_CACHE.map(url => addToCache(url).catch(error => ({ error, url }))),
    );

    const failures = results.filter(result => result && result.error);
    if (failures.length) {
      console.warn('Some core assets failed to cache', failures);
    }
  } catch (error) {
    console.warn('Precache failed', error);
  }
}

function cacheNameForSession(sessionId) {
  return `${CACHE_BASE}::${sessionId || 'anon'}`;
}

function parseSessionId(cookieHeader = '') {
  const match = cookieHeader.match(/PHPSESSID=([^;]+)/);
  if (!match) return null;
  return match[1];
}

function deriveSessionIdFromRequest(request) {
  if (!request || !request.headers) return null;
  const cookieHeader = request.headers.get('cookie') || '';
  return parseSessionId(cookieHeader);
}

function resolveSessionId(request) {
  return activeUserScope.sessionId || deriveSessionIdFromRequest(request) || 'anon';
}

async function getUserCache(request) {
  const sessionId = resolveSessionId(request);
  return caches.open(cacheNameForSession(sessionId));
}

async function matchUserCache(request) {
  const cache = await getUserCache(request);
  if (cache && typeof cache.match === 'function') return cache.match(request);
  if (typeof caches?.match === 'function') return caches.match(request);
  return null;
}

async function deleteOtherUserCaches(currentSessionId) {
  const keys = await caches.keys();
  const expectedName = cacheNameForSession(currentSessionId);
  const deletions = keys
    .filter(key => key.startsWith(`${CACHE_BASE}::`) && key !== expectedName)
    .map(key => caches.delete(key));
  await Promise.all(deletions);
}

async function migrateSessionCache(fromSessionId, toSessionId) {
  const normalizedFrom = fromSessionId || 'anon';
  const normalizedTo = toSessionId || 'anon';

  if (normalizedFrom === normalizedTo) return;

  const [sourceCache, targetCache] = await Promise.all([
    caches.open(cacheNameForSession(normalizedFrom)),
    caches.open(cacheNameForSession(normalizedTo)),
  ]);

  const requests = await sourceCache.keys();
  if (!requests.length) return;

  await Promise.all(requests.map(async request => {
    const response = await sourceCache.match(request);
    if (response) {
      await targetCache.put(request, response.clone());
    }
  }));
}

async function notifyClients(payload) {
  const clients = await self.clients.matchAll({ includeUncontrolled: true });
  clients.forEach(client => client.postMessage(payload));
}

async function broadcastQueueState(extra = {}) {
  const queue = await getQueuedRequests();
  await notifyClients({ type: 'queue-state', queue, ...extra });
}

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

  await notifyClients({ type: 'queue-event', event: 'queued', entry });
  await broadcastQueueState();
  return entry;
}

async function deleteRequest(id) {
  const db = await openDatabase();
  await notifyClients({ type: 'debug-delete', id: id, timestamp: Date.now() });
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

async function getRequestById(id) {
  const db = await openDatabase();
  return new Promise((resolve, reject) => {
    const tx = db.transaction(DB_STORE, 'readonly');
    const store = tx.objectStore(DB_STORE);
    const request = store.get(id);
    request.onsuccess = () => resolve(request.result || null);
    request.onerror = () => reject(request.error);
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

async function retryQueuedRequest(id) {
  const entry = await getRequestById(id);
  if (!entry) {
    await broadcastQueueState();
    return;
  }

  await notifyClients({ type: 'queue-event', event: 'retrying', entry });
  const result = await sendWithRetry(entry);

  if (result.outcome === 'success') {
    await deleteRequest(id);
    await notifyClients({ type: 'queue-event', event: 'sent', entry });
  } else if (result.outcome === 'discard') {
    await deleteRequest(id);
    await notifyClients({ type: 'queue-event', event: 'discarded', entry });
  } else {
    await notifyClients({ type: 'queue-event', event: 'failed', entry });
  }

  await broadcastQueueState();
}

async function discardQueuedRequest(id) {
  const entry = await getRequestById(id);
  if (!entry) {
    await broadcastQueueState();
    return;
  }

  await deleteRequest(id);
  await notifyClients({ type: 'queue-event', event: 'discarded', entry });
  await broadcastQueueState();
}

self.addEventListener('message', event => {
  const data = event.data || {};
  if (!data || !data.type) return;

  if (data.type === 'get-queue') {
    event.waitUntil(broadcastQueueState());
  } else if (data.type === 'retry-item' && data.id) {
    event.waitUntil(retryQueuedRequest(data.id));
  } else if (data.type === 'discard-item' && data.id) {
    event.waitUntil(discardQueuedRequest(data.id));
  } else if (data.type === 'drain-queue') {
    event.waitUntil(drainQueue());
  } else if (data.type === 'prefetch-urls' && Array.isArray(data.urls)) {
    event.waitUntil(prefetchUrls(data.urls));
  } else if (data.type === 'queue-task-add' && data.payload) {
    const body = new FormData();
    body.append('description', data.payload.description);

    const request = new Request('/add_task.php', {
        method: 'POST',
        body: body,
    });

    event.waitUntil(handleNonGetRequest({ request: request }));
  } else if (data.type === 'set-user') {
    const previousSession = activeUserScope.sessionId;
    const previousUserId = activeUserScope.userId;
    updateOfflineFromClient(data.online, data.offlineMode);
    activeUserScope = {
      sessionId: data.sessionId || null,
      userId: data.userId || null,
      offlineMode: activeUserScope.offlineMode,
    };
    const currentSession = activeUserScope.sessionId || 'anon';

    const shouldMigrate = (!previousUserId && (previousSession || 'anon') === 'anon')
      && currentSession !== (previousSession || 'anon');
    const migration = shouldMigrate ? migrateSessionCache(previousSession, currentSession) : Promise.resolve();

    event.waitUntil(Promise.all([
      migration.catch(() => {}),
      deleteOtherUserCaches(currentSession),
    ]));

    if (previousSession && previousSession !== currentSession) {
      notifyClients({ type: 'user-session-changed', sessionId: currentSession }).catch(() => {});
    }
  } else if (data.type === 'client-connectivity') {
    if (typeof data.online === 'boolean') {
      updateOfflineFromClient(data.online, data.offlineMode);
    }
  }
});

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

async function prefetchUrls(urls = []) {
  const unique = [...new Set(urls)].filter(Boolean);
  if (!unique.length) return;

  try {
    await notifyClients({ type: 'prefetch-progress', status: 'start', total: unique.length, completed: 0 });
    const cache = await getUserCache();
    let completed = 0;

    for (const url of unique) {
      const request = new Request(url, { credentials: 'include' });
      try {
        const response = await fetch(request);
        if (response && response.ok) {
          await cache.put(request, response.clone());
        }
      } catch (error) {
        console.warn('Prefetch failed', { url, error });
      } finally {
        completed += 1;
        await notifyClients({ type: 'prefetch-progress', status: 'progress', total: unique.length, completed, currentUrl: url });
      }
    }

    await notifyClients({ type: 'prefetch-progress', status: 'done', total: unique.length, completed });
  } catch (error) {
    console.error('Prefetch error', error);
    await notifyClients({ type: 'prefetch-progress', status: 'error', total: urls.length || 0, completed: 0 });
  }
}

async function parseJsonResponse(response) {
  if (!response) return null;

  const contentType = response.headers?.get('content-type') || '';
  if (!contentType.toLowerCase().includes('application/json')) {
    return null;
  }

  try {
    const clone = response.clone();
    return await clone.json();
  } catch (error) {
    console.warn('Failed to parse queued response body', error);
    return null;
  }
}

async function sendWithRetry(entry, attempts = 3) {
  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    try {
      const response = await fetch(entry.url, toRequestInit(entry));

      if (response.ok) {
        const responseData = await parseJsonResponse(response);
        return { outcome: 'success', response, responseData };
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
    if (requests.length) {
      await notifyClients({ type: 'queue-drain-start', queueLength: requests.length });
    }
    for (const entry of requests) {
      const result = await sendWithRetry(entry);

      if (result.outcome === 'success') {
        await deleteRequest(entry.id);
        console.info('Queued request sent successfully', { id: entry.id, url: entry.url });
        await notifyClients({ type: 'queue-event', event: 'sent', entry, responseData: result.responseData });
        await broadcastQueueState({ draining: true });
      } else if (result.outcome === 'discard') {
        await deleteRequest(entry.id);
        console.warn('Removed conflicting queued request', { id: entry.id, url: entry.url });
        await notifyClients({ type: 'queue-event', event: 'discarded', entry });
        await broadcastQueueState({ draining: true });
      } else {
        console.error('Leaving request in queue after repeated failures', { id: entry.id, url: entry.url });
        break;
      }
    }
  } catch (error) {
    console.error('Error while draining queue', error);
  } finally {
    isDrainingQueue = false;
    await broadcastQueueState({ draining: false });
  }
}

async function handleNonGetRequest(event) {
  const queuedRequestClone = event.request.clone();

  const queueOfflineRequest = async (reason) => {
    try {
      const entry = await enqueueRequest(queuedRequestClone);
      event.waitUntil(registerSync());
      console.warn('Queued offline request', { id: entry.id, url: entry.url, method: entry.method, reason });

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
    } catch (queueError) {
      console.error('Failed to queue offline request', { reason, error: queueError });
      return new Response(
        JSON.stringify({ error: 'Unable to save request for retry', offline: true }),
        { status: 503, headers: { 'Content-Type': 'application/json' } },
      );
    }
  };

  try {
    if (isOffline()) {
      throw new Error('offline-mode');
    }
    const liveResponse = await fetch(event.request);
    if (liveResponse && liveResponse.ok) {
      markOnlineFromNetwork();
    }
    return liveResponse;
  } catch (error) {
    return queueOfflineRequest(error);
  }
}

self.addEventListener('install', event => {
  event.waitUntil(
    precacheCoreAssets()
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    Promise.all([
      caches.keys().then(keys => Promise.all(
        keys
          .filter(key => !key.startsWith(`${CACHE_BASE}::`))
          .map(key => caches.delete(key))
      )),
      drainQueue().catch(() => {}),
      self.clients.claim(),
      notifyClients({ type: 'request-client-connectivity' }),
    ])
  );
});

self.addEventListener('fetch', event => {
  notifyClients({ type: 'debug-fetch-event', method: event.request.method, url: event.request.url });
  if (event.request.method !== 'GET') {
    event.respondWith(handleNonGetRequest(event));
    return;
  }

  const url = new URL(event.request.url);
  const sessionFromRequest = deriveSessionIdFromRequest(event.request);
  if (sessionFromRequest && sessionFromRequest !== activeUserScope.sessionId) {
    activeUserScope.sessionId = sessionFromRequest;
    event.waitUntil(deleteOtherUserCaches(sessionFromRequest));
  }

  const isNavigational =
    event.request.mode === 'navigate' ||
    ['/index.php', '/task.php', '/completed.php'].some(path => url.pathname.endsWith(path));

  if (isNavigational) {
    if (isOffline()) {
      event.respondWith((async () => {
        const cachedPage = await matchUserCache(event.request);
        if (cachedPage) return cachedPage;

        const userCache = await getUserCache(event.request);
        const homeShell = await userCache.match('/');
        return homeShell || offlineNavigationResponse();
      })());
      return;
    }

    event.respondWith(
      fetch(event.request)
        .then(networkResponse => {
          if (networkResponse && networkResponse.ok) {
            markOnlineFromNetwork();
          }
          if (networkResponse && networkResponse.status === 200) {
            const copy = networkResponse.clone();
            event.waitUntil(
              getUserCache(event.request).then(cache => cache.put(event.request, copy)).catch(() => {})
            );
          }
          return networkResponse;
        })
        .catch(() => matchUserCache(event.request).then(cacheHit => cacheHit || matchUserCache('/')))
    );
    return;
  }

  if (isOffline()) {
    event.respondWith(
      matchUserCache(event.request).then(cacheHit => cacheHit || Response.error())
    );
    return;
  }

  event.respondWith(
    matchUserCache(event.request).then(response => {
      const fetchPromise = fetch(event.request)
        .then(networkResponse => {
          if (networkResponse && networkResponse.ok) {
            markOnlineFromNetwork();
          }
          if (networkResponse && networkResponse.status === 200 && networkResponse.type === 'basic') {
            const responseClone = networkResponse.clone();
            getUserCache(event.request).then(cache => cache.put(event.request, responseClone));
          }
          return networkResponse;
        })
        .catch(() => matchUserCache(event.request).then(cacheHit => cacheHit || Response.error()));

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
