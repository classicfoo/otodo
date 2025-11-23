(function(){
  const statusEl = document.getElementById('sync-status');
  if (!statusEl) return;

  let hideTimer;
  function setState(state, message) {
    statusEl.dataset.state = state;
    statusEl.textContent = message;
    statusEl.classList.remove('text-success', 'text-warning', 'text-danger');
    if (state === 'synced') {
      statusEl.classList.add('text-success');
      if (hideTimer) clearTimeout(hideTimer);
      hideTimer = setTimeout(() => {
        statusEl.classList.add('opacity-75');
      }, 3000);
    } else if (state === 'syncing') {
      statusEl.classList.remove('opacity-75');
      statusEl.classList.add('text-warning');
    } else if (state === 'error') {
      statusEl.classList.remove('opacity-75');
      statusEl.classList.add('text-danger');
    }
  }

  window.updateSyncStatus = function(state, message) {
    setState(state, message || defaultMessages[state] || '');
  };

  const defaultMessages = {
    synced: 'All changes saved',
    syncing: 'Syncing changes…',
    error: 'Sync issue. Will retry when online.'
  };

  window.trackBackgroundSync = function(promise, messages = {}) {
    setState('syncing', messages.syncing || defaultMessages.syncing);
    return Promise.resolve(promise)
      .then(result => {
        setState('synced', messages.synced || defaultMessages.synced);
        return result;
      })
      .catch(err => {
        setState('error', messages.error || defaultMessages.error);
        throw err;
      });
  };

  window.addEventListener('online', () => setState('synced', 'Back online. Synced'));
  window.addEventListener('offline', () => setState('error', 'Offline. Changes will sync later'));

  setState(navigator.onLine ? 'synced' : 'error', navigator.onLine ? defaultMessages.synced : 'Offline. Changes will sync later');
})();
