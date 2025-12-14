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
