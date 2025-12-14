class ApiClient {
  static readSessionId() {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(/PHPSESSID=([^;]+)/);
    return match ? match[1] : null;
  }

  static async matchSessionCache(url) {
    if (typeof caches === 'undefined' || !caches?.keys) {
      return null;
    }

    try {
      const sessionId = ApiClient.readSessionId() || 'anon';
      const cacheKeys = await caches.keys();
      const scopedKey = cacheKeys
        .filter(key => key.includes('otodo-cache-') && key.split('::')[1] === sessionId)
        .sort()
        .pop();

      if (!scopedKey) return null;

      const cache = await caches.open(scopedKey);
      return cache.match(url);
    } catch (error) {
      console.warn('Offline cache lookup failed', error);
      return null;
    }
  }

  static async requestJson(url, options = {}) {
    const merged = {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'fetch',
        ...(options.headers || {}),
      },
      ...options,
    };

    try {
      const response = await fetch(url, merged);
      const isJson = (response.headers.get('content-type') || '').includes('application/json');
      const data = isJson ? await response.json() : null;

      if (response.ok && response.status === 202 && data?.queued) {
        return { ok: true, queued: true, offline: true, status: response.status, data };
      }

      if (!response.ok) {
        return {
          ok: false,
          status: response.status,
          offline: false,
          error: data?.message || 'Request failed',
          data,
        };
      }

      return { ok: true, status: response.status, data };
    } catch (error) {
      return {
        ok: false,
        status: 0,
        offline: true,
        error: error instanceof Error ? error.message : 'Network error',
      };
    }
  }

  static createTask(description) {
    const body = new FormData();
    body.append('description', description);
    return this.requestJson('add_task.php', { method: 'POST', body });
  }

  static async requestText(url, options = {}) {
    const merged = {
      headers: {
        Accept: 'text/html',
        'X-Requested-With': 'fetch',
        ...(options.headers || {}),
      },
      ...options,
    };

    try {
      const response = await fetch(url, merged);
      const data = await response.text();
      if (!response.ok) {
        const isOfflineFallback = response.status === 503 && typeof data === 'string';
        if (isOfflineFallback) {
          return { ok: true, status: response.status, offline: true, data };
        }

        return {
          ok: false,
          status: response.status,
          offline: false,
          error: 'Request failed',
          data,
        };
      }

      return { ok: true, status: response.status, data };
    } catch (error) {
      if (typeof caches !== 'undefined' && caches?.keys) {
        try {
          const cached = await ApiClient.matchSessionCache(url);
          if (cached) {
            return { ok: true, status: 200, offline: true, data: await cached.text() };
          }
        } catch (cacheError) {
          console.warn('Offline cache lookup failed', cacheError);
        }
      }

      return { ok: false, status: 0, offline: true, error: 'Network error' };
    }
  }

  static updateTaskMeta(id, payload) {
    const body = new FormData();
    body.append('id', id);
    if (payload.priority !== undefined) body.append('priority', payload.priority);
    if (payload.due_shortcut !== undefined) body.append('due_shortcut', payload.due_shortcut);
    return this.requestJson('update_task_meta.php', { method: 'POST', body });
  }

  static toggleStar(id, starred) {
    const body = new FormData();
    body.append('id', id);
    if (starred !== undefined) body.append('starred', starred);
    return this.requestJson('toggle_star.php', { method: 'POST', body });
  }

  static manageHashtag(action, payload = {}) {
    const body = new FormData();
    body.append('action', action);
    Object.entries(payload).forEach(([key, value]) => body.append(key, value));
    return this.requestJson('manage_hashtags.php', { method: 'POST', body });
  }

  static fetchHashtags() {
    return this.requestJson('manage_hashtags.php');
  }

  static saveSettings(formData) {
    return this.requestJson('settings.php', { method: 'POST', body: formData });
  }
}

window.ApiClient = ApiClient;

class TaskDestroyer {
  static normalizeId(rawId) {
    if (rawId === undefined || rawId === null) return '';
    return String(rawId).trim();
  }

  static escapeSelectorValue(value) {
    if (typeof CSS !== 'undefined' && CSS.escape) return CSS.escape(value);
    return value.replace(/"/g, '\\"');
  }

  static removeOfflineQueuedTask(normalizedId) {
    if (typeof normalizedId !== 'string' || !normalizedId.startsWith('queued-')) {
      return { removedOfflineEntry: false, removedDomNodes: 0 };
    }

    let removedOfflineEntry = false;
    if (typeof localStorage !== 'undefined') {
      try {
        const stored = JSON.parse(localStorage.getItem('offlineQueuedTasks') || '[]');
        const filtered = Array.isArray(stored)
          ? stored.filter(entry => !entry || (
            entry.requestId !== normalizedId &&
            entry.id !== normalizedId &&
            entry.localId !== normalizedId
          ))
          : [];

        if (filtered.length !== stored.length) {
          localStorage.setItem('offlineQueuedTasks', JSON.stringify(filtered));
          removedOfflineEntry = true;
        }
      } catch (error) {
        console.warn('Could not prune offline queued task', error);
      }
    }

    let removedDomNodes = 0;
    if (typeof document !== 'undefined' && document.querySelectorAll) {
      const escaped = TaskDestroyer.escapeSelectorValue(normalizedId);
      const selector = [
        `[data-request-id="${escaped}"]`,
        `[data-local-id="${escaped}"]`,
        `[data-task-id="${escaped}"]`,
      ].join(', ');

      document.querySelectorAll(selector).forEach(node => {
        node.remove();
        removedDomNodes += 1;
      });
    }

    return { removedOfflineEntry, removedDomNodes };
  }

  static async purgeCaches(taskId) {
    if (typeof caches === 'undefined' || !caches?.keys) return { deleted: 0, checkedCaches: 0 };

    const normalizedId = TaskDestroyer.normalizeId(taskId);
    const cacheKeys = await caches.keys();
    let deleted = 0;
    let checkedCaches = 0;

    await Promise.all(
      cacheKeys
        .filter(key => key.startsWith('otodo-cache-'))
        .map(async key => {
          checkedCaches += 1;
          const cache = await caches.open(key);
          const requests = await cache.keys();

          await Promise.all(
            requests.map(async request => {
              try {
                const url = new URL(request.url);
                const isTaskDetail = url.pathname.endsWith('/task.php') &&
                  (!normalizedId || url.searchParams.get('id') === normalizedId);
                const isListPage = ['/', '/index.php', '/completed.php'].includes(url.pathname);

                if (isTaskDetail || isListPage) {
                  const removed = await cache.delete(request);
                  if (removed) deleted += 1;
                }
              } catch (error) {
                console.warn('Failed to inspect cached request', error);
              }
            })
          );
        })
    );

    return { deleted, checkedCaches };
  }

  static async purgeQueuedDeletes(taskId) {
    if (!('indexedDB' in window)) return { removed: 0, total: 0 };

    const normalizedId = TaskDestroyer.normalizeId(taskId);
    const db = await new Promise((resolve, reject) => {
      const request = indexedDB.open('otodo-offline', 1);
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
      request.onupgradeneeded = () => {
        const database = request.result;
        if (!database.objectStoreNames.contains('requests')) {
          database.createObjectStore('requests', { keyPath: 'id' }).createIndex('timestamp', 'timestamp');
        }
      };
    });

    const queued = await new Promise((resolve, reject) => {
      const tx = db.transaction('requests', 'readonly');
      const store = tx.objectStore('requests');
      const getAll = store.getAll();
      getAll.onsuccess = () => resolve(getAll.result || []);
      getAll.onerror = () => reject(getAll.error);
    });

    const related = queued.filter(entry => {
      if (!entry || !entry.url) return false;
      try {
        const url = new URL(entry.url);
        return url.pathname.endsWith('/delete_task.php') &&
          (!normalizedId || url.searchParams.get('id') === normalizedId);
      } catch (error) {
        return false;
      }
    });

    if (!related.length) return { removed: 0, total: queued.length };

    await new Promise((resolve, reject) => {
      const tx = db.transaction('requests', 'readwrite');
      const store = tx.objectStore('requests');
      related.forEach(entry => store.delete(entry.id));
      tx.oncomplete = () => resolve();
      tx.onerror = () => reject(tx.error);
    });

    return { removed: related.length, total: queued.length };
  }

  static async deleteTask(rawId) {
    const normalizedId = TaskDestroyer.normalizeId(rawId);
    if (!normalizedId) {
      throw new Error('A task id is required to delete a task.');
    }

    const apiResult = await ApiClient.requestJson(`delete_task.php?id=${encodeURIComponent(normalizedId)}`);
    const offlineCleanup = TaskDestroyer.removeOfflineQueuedTask(normalizedId);

    const hasErrorStatus = apiResult.data?.status && apiResult.data.status !== 'ok';
    const hasExplicitOkFlag = apiResult.data?.ok === false;

    if (!apiResult.ok || hasErrorStatus || hasExplicitOkFlag) {
      if (offlineCleanup?.removedOfflineEntry) {
        console.warn('Removed queued task locally because no server record existed.');
      }
      const message = apiResult.error || apiResult.data?.message || `Delete request failed (${apiResult.status})`;
      const error = new Error(message);
      error.result = apiResult;
      throw error;
    }

    const [cacheCleanup, queueCleanup] = await Promise.all([
      TaskDestroyer.purgeCaches(normalizedId).catch(error => ({ error })),
      TaskDestroyer.purgeQueuedDeletes(normalizedId).catch(error => ({ error })),
    ]);

    const summary = { id: normalizedId, apiResult, cacheCleanup, queueCleanup, offlineCleanup };
    console.info('delete_task summary', summary);
    return summary;
  }
}

window.TaskDestroyer = TaskDestroyer;
window.delete_task = id => TaskDestroyer.deleteTask(id);
