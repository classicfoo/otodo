describe('service worker registration bootstrap', () => {
  beforeEach(() => {
    jest.resetModules();
    jest.restoreAllMocks();
    document.body.innerHTML = '<span id="service-worker-status" class="badge"></span>';
    Object.defineProperty(global.navigator, 'serviceWorker', {
      value: {
        register: jest.fn().mockResolvedValue({ scope: '/', addEventListener: jest.fn(), active: {}, installing: null }),
        ready: Promise.resolve({ scope: '/', addEventListener: jest.fn(), active: {}, installing: null }),
        controller: {},
        addEventListener: jest.fn(),
      },
      writable: true,
      configurable: true,
    });
    jest.spyOn(window, 'addEventListener');
  });

  test('registers service worker on load when supported', async () => {
    require('../sw-register');

    expect(window.addEventListener).toHaveBeenCalledWith('load', expect.any(Function));
    const loadHandler = window.addEventListener.mock.calls.find(([event]) => event === 'load')[1];
    await loadHandler();

    expect(navigator.serviceWorker.register).toHaveBeenCalledWith('/service-worker.js');
    expect(document.getElementById('service-worker-status').textContent).toBe('Active');
  });

  test('shows unsupported badge when service workers are not available', () => {
    delete navigator.serviceWorker;
    document.body.innerHTML = '<span id="service-worker-status" class="badge"></span>';

    require('../sw-register');

    expect(document.getElementById('service-worker-status').textContent).toBe('Not supported');
  });
});
