describe('service worker registration bootstrap', () => {
  beforeEach(() => {
    jest.resetModules();
    jest.restoreAllMocks();
    Object.defineProperty(global.navigator, 'serviceWorker', {
      value: { register: jest.fn().mockResolvedValue({ scope: '/' }) },
      writable: true,
    });
    jest.spyOn(window, 'addEventListener');
  });

  test('registers service worker on load when supported', async () => {
    require('../sw-register');

    expect(window.addEventListener).toHaveBeenCalledWith('load', expect.any(Function));
    const loadHandler = window.addEventListener.mock.calls.find(([event]) => event === 'load')[1];
    await loadHandler();

    expect(navigator.serviceWorker.register).toHaveBeenCalledWith('/service-worker.js');
  });
});
