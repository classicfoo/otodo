(function() {
  if (!('serviceWorker' in navigator)) {
    return;
  }

  function readSessionId() {
    const match = document.cookie.match(/PHPSESSID=([^;]+)/);
    return match ? match[1] : null;
  }

  function postUserScope() {
    if (!navigator.serviceWorker.controller) return;
    navigator.serviceWorker.controller.postMessage({
      type: 'set-user',
      sessionId: readSessionId(),
      userId: typeof window !== 'undefined' ? (window.otodoUserId ?? null) : null,
    });
  }

  navigator.serviceWorker.ready.then(() => postUserScope());

  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (navigator.serviceWorker.controller) {
      postUserScope();
    }
  });
})();
