(function() {
  if (!('serviceWorker' in navigator)) {
    return;
  }

  function readSessionId() {
    const match = document.cookie.match(/PHPSESSID=([^;]+)/);
    return match ? match[1] : null;
  }

  let lastKnownOnline = null;

  function postConnectivity(onlineState = lastKnownOnline ?? true) {
    if (!navigator.serviceWorker.controller) return;
    navigator.serviceWorker.controller.postMessage({
      type: 'client-connectivity',
      online: onlineState,
      offlineMode: onlineState === false,
    });
  }

  function updateConnectivity(onlineState) {
    if (typeof onlineState !== 'boolean') return;
    if (onlineState === lastKnownOnline) return;
    lastKnownOnline = onlineState;
    postConnectivity(onlineState);
  }

  async function checkConnectivity() {
    try {
      await fetch('/sync-status.js', { method: 'HEAD', cache: 'no-store', credentials: 'include' });
      updateConnectivity(true);
      return true;
    } catch (error) {
      updateConnectivity(false);
      return false;
    }
  }

  function postUserScope() {
    if (!navigator.serviceWorker.controller) return;
    navigator.serviceWorker.controller.postMessage({
      type: 'set-user',
      sessionId: readSessionId(),
      userId: typeof window !== 'undefined' ? (window.otodoUserId ?? null) : null,
      online: lastKnownOnline,
      offlineMode: lastKnownOnline === false,
    });
  }

  navigator.serviceWorker.ready.then(() => {
    postUserScope();
    checkConnectivity();
  });

  if (navigator.serviceWorker.controller) {
    postUserScope();
    checkConnectivity();
  }

  window.addEventListener('online', () => checkConnectivity());
  window.addEventListener('offline', () => updateConnectivity(false));

  navigator.serviceWorker.addEventListener('message', event => {
    if (!event.data || !event.data.type) return;
    if (event.data.type === 'request-client-connectivity') {
      postUserScope();
      checkConnectivity();
    }
  });

  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (navigator.serviceWorker.controller) {
      postUserScope();
      checkConnectivity();
    }
  });
})();
