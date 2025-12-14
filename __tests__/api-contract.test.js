const { Headers, Response } = require('node-fetch');

require('../app-api');

describe('ApiClient contract with PHP endpoints', () => {
  beforeEach(() => {
    jest.resetModules();
    require('../app-api');
    delete global.caches;
    document.cookie = '';
    global.fetch = jest.fn(async (url, options = {}) => ({
      ok: true,
      status: 200,
      headers: new Headers({ 'Content-Type': 'application/json' }),
      json: async () => ({ ok: true, url, headers: options.headers || {} }),
    }));
  });

  test('createTask posts description to PHP endpoint with JSON headers', async () => {
    const result = await window.ApiClient.createTask('Test task');

    expect(fetch).toHaveBeenCalledWith('add_task.php', expect.objectContaining({
      method: 'POST',
      headers: expect.objectContaining({
        Accept: 'application/json',
        'X-Requested-With': 'fetch',
      }),
    }));

    const body = fetch.mock.calls[0][1].body;
    expect(body.get('description')).toBe('Test task');
    expect(result.ok).toBe(true);
  });

  test('updateTaskMeta sends partial updates to PHP endpoint', async () => {
    await window.ApiClient.updateTaskMeta(5, { priority: 3, due_shortcut: 'tomorrow' });

    const [, options] = fetch.mock.calls[0];
    expect(fetch).toHaveBeenCalledWith('update_task_meta.php', expect.any(Object));
    expect(options.method).toBe('POST');
    expect(options.headers).toMatchObject({ Accept: 'application/json' });
    expect(options.body.get('id')).toBe('5');
    expect(options.body.get('priority')).toBe('3');
    expect(options.body.get('due_shortcut')).toBe('tomorrow');
  });

  test('toggleStar posts stateful toggle to PHP endpoint', async () => {
    await window.ApiClient.toggleStar(9, 1);

    const [, options] = fetch.mock.calls[0];
    expect(fetch).toHaveBeenCalledWith('toggle_star.php', expect.any(Object));
    expect(options.body.get('id')).toBe('9');
    expect(options.body.get('starred')).toBe('1');
  });

  test('hashtag endpoints preserve parity with PHP contracts', async () => {
    await window.ApiClient.manageHashtag('create', { name: 'productivity' });
    await window.ApiClient.fetchHashtags();

    expect(fetch).toHaveBeenNthCalledWith(1, 'manage_hashtags.php', expect.objectContaining({
      method: 'POST',
    }));
    expect(fetch).toHaveBeenNthCalledWith(2, 'manage_hashtags.php', expect.objectContaining({
      headers: expect.objectContaining({ Accept: 'application/json' }),
    }));

    const manageBody = fetch.mock.calls[0][1].body;
    expect(manageBody.get('action')).toBe('create');
    expect(manageBody.get('name')).toBe('productivity');
  });

  test('requestJson surfaces offline failures for resilience', async () => {
    fetch.mockImplementationOnce(() => Promise.reject(new Error('offline')));

    const result = await window.ApiClient.createTask('Offline task');

    expect(result.ok).toBe(false);
    expect(result.offline).toBe(true);
  });

  test('requestText surfaces offline HTML fallbacks for navigation', async () => {
    fetch.mockImplementationOnce(() => Promise.resolve(new Response('<html>offline copy</html>', {
      status: 503,
      headers: new Headers({ 'Content-Type': 'text/html' }),
    })));

    const result = await window.ApiClient.requestText('/completed.php');

    expect(result.ok).toBe(true);
    expect(result.offline).toBe(true);
    expect(result.data).toContain('offline copy');
  });

  test('requestText only reuses offline cache for the active session', async () => {
    const cacheA = new Map();
    const cacheB = new Map();
    cacheA.set('/task.php?id=1', new Response('<div>Session A</div>'));
    cacheB.set('/task.php?id=2', new Response('<div>Session B</div>'));

    document.cookie = 'PHPSESSID=session-b';
    global.caches = {
      keys: jest.fn(async () => ['otodo-cache-v10::session-a', 'otodo-cache-v10::session-b']),
      open: jest.fn(async key => ({
        match: async url => (key.endsWith('session-a') ? cacheA : cacheB).get(url) || null,
      })),
    };

    fetch.mockImplementationOnce(() => Promise.reject(new Error('offline')));

    const result = await window.ApiClient.requestText('/task.php?id=2');

    expect(result.offline).toBe(true);
    expect(result.ok).toBe(true);
    expect(result.data).toContain('Session B');
    expect(caches.open).toHaveBeenCalledWith('otodo-cache-v10::session-b');
  });
});
