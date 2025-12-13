(function(){
  const statusEl = document.getElementById('sync-status');
  if (!statusEl) return;

  let hideTimer;
  const messageEl = document.createElement('span');
  messageEl.className = 'sync-status-text';
  const badgesEl = document.createElement('span');
  badgesEl.className = 'sync-status-badges d-inline-flex align-items-center gap-1 ms-2';

  if (statusEl.childNodes.length === 1 && statusEl.firstChild.nodeType === Node.TEXT_NODE) {
    messageEl.textContent = statusEl.textContent;
    statusEl.textContent = '';
  }

  if (!statusEl.querySelector('.sync-status-text')) {
    statusEl.appendChild(messageEl);
  }
  if (!statusEl.querySelector('.sync-status-badges')) {
    statusEl.appendChild(badgesEl);
  }

  function renderBadges(badges = []) {
    badgesEl.innerHTML = '';
    badges.forEach(({ text, variant }) => {
      const badge = document.createElement('span');
      badge.className = `badge rounded-pill bg-${variant || 'secondary'} bg-opacity-75 text-body-secondary fw-semibold`;
      badge.textContent = text;
      badgesEl.appendChild(badge);
    });
  }

  function setState(state, message) {
    if (hideTimer) clearTimeout(hideTimer);
    statusEl.dataset.state = state;
    messageEl.textContent = message;
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

  window.setSyncBadges = function(badges) {
    renderBadges(badges);
  };

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
