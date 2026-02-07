(function(){
  const statusEl = document.getElementById('sync-status');
  if (!statusEl) return;

  let hideTimer;
  function setState(state, message) {
    if (hideTimer) clearTimeout(hideTimer);
    statusEl.dataset.state = state;
    statusEl.textContent = message;
    statusEl.classList.remove('text-success', 'text-warning', 'text-danger', 'opacity-75');
    if (state === 'synced') {
      statusEl.classList.add('text-success');
      hideTimer = setTimeout(() => {
        statusEl.classList.add('opacity-75');
      }, 3000);
    } else if (state === 'syncing') {
      statusEl.classList.add('text-warning');
    } else if (state === 'error') {
      statusEl.classList.add('text-danger');
    }
  }

  window.updateSyncStatus = function(state, message) {
    setState(state, message || defaultMessages[state] || '');
  };

  function setSharedState(state, message, extra = {}) {
    sessionStorage.setItem('sharedSyncStatus', JSON.stringify({
      state,
      message,
      extra,
      at: Date.now()
    }));
    setState(state, message || defaultMessages[state] || '');
  }

  window.updateSharedSyncStatus = function(state, message, extra = {}) {
    setSharedState(state, message, extra);
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

const sharedRaw = sessionStorage.getItem('sharedSyncStatus');
  if (sharedRaw) {
    try {
      const shared = JSON.parse(sharedRaw);
      if (shared && shared.state) {
        setState(shared.state, shared.message || defaultMessages[shared.state] || '');
        if (shared.state === 'syncing' && shared.extra && shared.extra.followUpUrl) {
          const followUp = fetch(shared.extra.followUpUrl, {
            method: 'GET',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'fetch'},
            credentials: 'same-origin'
          });
          window.trackBackgroundSync(followUp, {
            syncing: shared.message || defaultMessages.syncing,
            synced: 'Task deleted',
            error: 'Delete failed. Check connection.'
          }).then(resp => resp && resp.ok ? resp : Promise.reject()).then(() => {
            sessionStorage.removeItem('sharedSyncStatus');
          }).catch(() => {
            setSharedState('error', 'Delete failed. Check connection.');
          });
        } else if (shared.state !== 'syncing') {
          sessionStorage.removeItem('sharedSyncStatus');
        }
      } else {
        setState(navigator.onLine ? 'synced' : 'error', navigator.onLine ? defaultMessages.synced : 'Offline. Changes will sync later');
      }
    } catch (e) {
      setState(navigator.onLine ? 'synced' : 'error', navigator.onLine ? defaultMessages.synced : 'Offline. Changes will sync later');
    }
  } else {
    setState(navigator.onLine ? 'synced' : 'error', navigator.onLine ? defaultMessages.synced : 'Offline. Changes will sync later');
  }
})();
