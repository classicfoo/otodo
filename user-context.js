(function() {
  if (!('serviceWorker' in navigator)) {
    return;
  }

  function readSessionId() {
    const match = document.cookie.match(/PHPSESSID=([^;]+)/);
    return match ? match[1] : null;
  }

  function postConnectivity(onlineState = navigator.onLine) {
    if (!navigator.serviceWorker.controller) return;
    navigator.serviceWorker.controller.postMessage({
      type: 'client-connectivity',
      online: onlineState,
    });
  }

  function postUserScope() {
    if (!navigator.serviceWorker.controller) return;
    navigator.serviceWorker.controller.postMessage({
      type: 'set-user',
      sessionId: readSessionId(),
      userId: typeof window !== 'undefined' ? (window.otodoUserId ?? null) : null,
      online: navigator.onLine,
    });
  }

  navigator.serviceWorker.ready.then(() => postUserScope());

  window.addEventListener('online', () => postConnectivity(true));
  window.addEventListener('offline', () => postConnectivity(false));

  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (navigator.serviceWorker.controller) {
      postUserScope();
      postConnectivity();
    }
  });
})();
