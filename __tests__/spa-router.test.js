const { TextEncoder, TextDecoder } = require('util');
global.TextEncoder = TextEncoder;
global.TextDecoder = TextDecoder;

function createResponse(html) {
  return {
    ok: true,
    data: html,
  };
}

describe('ViewRouter SPA navigation', () => {
  let ViewRouter;

  beforeEach(() => {
    jest.resetModules();
    document.body.innerHTML = '<div data-view-root>Home</div><a data-route href="/task.php">Task</a>';
    window.history.replaceState({}, '', '/');
    global.ApiClient = { requestText: jest.fn() };
    window.ApiClient = global.ApiClient;
    delete window.ViewRouter;
    ViewRouter = require('../app-router');
  });

  test('navigates between SPA routes and executes returned scripts', async () => {
    const newView = `
      <div data-view-root>
        <p>Task List</p>
        <script>window.__routerTestHook__()</script>
      </div>
    `;
    window.ApiClient.requestText.mockResolvedValue(createResponse(newView));

    const viewChanged = jest.fn();
    document.addEventListener('view:changed', viewChanged);

    const router = new window.ViewRouter();
    expect(router.root).toBeTruthy();
    expect(router.currentPath).toBe('/');
    await router.navigate('/task.php');

    expect(window.ApiClient.requestText).toHaveBeenCalledWith('/task.php', expect.objectContaining({
      headers: expect.objectContaining({
        Accept: 'text/html',
        'X-Requested-With': 'spa',
      }),
    }));

    expect(document.querySelector('[data-view-root]').textContent).toContain('Task List');
    expect(window.location.pathname).toBe('/task.php');
    expect(viewChanged).toHaveBeenCalledWith(expect.objectContaining({ detail: { path: '/task.php' } }));
  });

  test('respects special route handler before fetching', async () => {
    const router = new window.ViewRouter();
    const handler = jest.fn(() => true);
    router.setSpecialRouteHandler(handler);

    await router.navigate('/settings.php');

    expect(handler).toHaveBeenCalledWith('/settings.php', { pushState: true });
    expect(window.ApiClient.requestText).not.toHaveBeenCalled();
  });
});
