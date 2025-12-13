class ApiClient {
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
