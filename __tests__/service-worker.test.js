const { Response, Request } = require('node-fetch');

const listeners = {};

beforeEach(() => {
  jest.resetModules();
  Object.keys(listeners).forEach(key => delete listeners[key]);

  Object.defineProperty(global, 'self', {
    value: {
      addEventListener: (event, handler) => {
        listeners[event] = handler;
      },
      clients: { matchAll: jest.fn(() => Promise.resolve([])) },
      registration: { sync: { register: jest.fn() } },
      crypto: { randomUUID: () => 'id-123' },
    },
    writable: true,
  });

  const cacheStore = new Map();
  global.caches = {
    open: jest.fn(async () => ({
      addAll: jest.fn(async urls => {
        urls.forEach(url => cacheStore.set(url, new Response('cached:' + url)));
      }),
      put: jest.fn(async (request, response) => {
        cacheStore.set(request.url || request, response.clone());
      }),
    })),
    keys: jest.fn(async () => ['old-cache']),
    delete: jest.fn(async () => true),
    match: jest.fn(async request => cacheStore.get(request.url || request) || null),
  };

  global.Response = Response;
  global.Request = Request;

  const dbStore = new Map();
  const fakeDB = {
    objectStoreNames: { contains: () => true },
    createObjectStore: () => ({ createIndex: () => {} }),
    transaction: () => {
      const tx = {
        oncomplete: null,
        onerror: null,
        objectStore: () => ({
          put: entry => {
            dbStore.set(entry.id, entry);
            setTimeout(() => tx.oncomplete && tx.oncomplete());
          },
          delete: id => {
            dbStore.delete(id);
            setTimeout(() => tx.oncomplete && tx.oncomplete());
          },
          get: id => {
            const req = { result: null, onsuccess: null, onerror: null };
            setTimeout(() => {
              req.result = dbStore.get(id) || null;
              req.onsuccess && req.onsuccess({ target: { result: req.result } });
            });
            return req;
          },
          openCursor: () => {
            const req = { onsuccess: null };
            setTimeout(() => {
              const entries = Array.from(dbStore.values());
              if (entries.length === 0) {
                req.onsuccess && req.onsuccess({ target: { result: null } });
                tx.oncomplete && tx.oncomplete();
                return;
              }

              let index = 0;
              const cursor = {
                value: entries[index],
                continue: () => {
                  index += 1;
                  if (index < entries.length) {
                    cursor.value = entries[index];
                    req.onsuccess && req.onsuccess({ target: { result: cursor } });
                  } else {
                    req.onsuccess && req.onsuccess({ target: { result: null } });
                    tx.oncomplete && tx.oncomplete();
                  }
                },
              };

              req.onsuccess && req.onsuccess({ target: { result: cursor } });
            });
            return req;
          },
        }),
      };
      return tx;
    },
  };

  global.indexedDB = {
    open: () => {
      const request = { result: fakeDB, onupgradeneeded: null, onsuccess: null, onerror: null };
      setTimeout(() => {
        request.onupgradeneeded && request.onupgradeneeded();
        request.onsuccess && request.onsuccess();
      });
      return request;
    },
  };

  require('../service-worker');
  expect(Object.keys(listeners)).toEqual(expect.arrayContaining(['install', 'fetch']));
});

test('installs and primes cache list', async () => {
  const waitUntil = jest.fn(promise => promise);
  expect(typeof listeners.install).toBe('function');
  await listeners.install({ waitUntil });

  expect(waitUntil).toHaveBeenCalled();
  const cacheOpen = caches.open.mock.results[0].value;
  await cacheOpen;
  expect(caches.open).toHaveBeenCalled();
});

test('falls back to cached shell when offline during navigation', async () => {
  const cached = new Response('cached home');
  caches.match.mockResolvedValueOnce(cached);
  global.fetch = jest.fn(() => Promise.reject(new Error('offline')));

  const respondWith = jest.fn(promise => promise);
  await listeners.fetch({
    request: new Request('https://example.com/index.php', { method: 'GET', mode: 'navigate' }),
    respondWith,
    waitUntil: jest.fn(),
  });

  const handled = respondWith.mock.calls[0][0];
  const response = await handled;
  expect(await response.text()).toBe('cached home');
});

test('queues non-GET requests when offline', async () => {
  global.fetch = jest.fn(() => Promise.reject(new Error('offline')));
  const respondWith = jest.fn(promise => promise);
  const request = new Request('https://example.com/add_task.php', { method: 'POST', body: 'demo' });

  await listeners.fetch({ request, respondWith, waitUntil: jest.fn() });

  const response = await respondWith.mock.calls[0][0];
  const payload = await response.json();
  expect(payload.offline).toBe(true);
  expect(payload.queued).toBe(true);
});
