(function(){
  const statusEl = document.getElementById('sync-status');
  if (!statusEl) return;

  let hideTimer;
  const messageEl = document.createElement('span');
  messageEl.className = 'sync-status-text';
  const badgesEl = document.createElement('span');
  badgesEl.className = 'sync-status-badges d-inline-flex align-items-center gap-1 ms-2';
  const actionEl = document.createElement('button');
  actionEl.type = 'button';
  actionEl.className = 'btn btn-link btn-sm p-0 align-baseline ms-2';
  actionEl.hidden = true;
  actionEl.textContent = 'Sync now';
  const detailEl = document.createElement('div');
  detailEl.className = 'sync-status-detail small text-muted mt-1 d-flex flex-column gap-2';
  detailEl.hidden = true;
  const connectionBadge = document.getElementById('connection-status');

  const detailContentRow = document.createElement('div');
  detailContentRow.className = 'd-flex align-items-center gap-2 w-100';

  const detailIcon = document.createElement('span');
  detailIcon.setAttribute('aria-hidden', 'true');
  const detailText = document.createElement('span');
  detailText.className = 'flex-grow-1';
  const detailProgress = document.createElement('span');
  detailProgress.className = 'badge rounded-pill bg-light text-body-secondary';

  const detailProgressBarContainer = document.createElement('div');
  detailProgressBarContainer.className = 'progress w-100';
  detailProgressBarContainer.hidden = true;
  const detailProgressBar = document.createElement('div');
  detailProgressBar.className = 'progress-bar progress-bar-striped progress-bar-animated';
  detailProgressBar.setAttribute('role', 'progressbar');
  detailProgressBar.setAttribute('aria-valuemin', '0');
  detailProgressBar.setAttribute('aria-valuemax', '100');
  detailProgressBarContainer.appendChild(detailProgressBar);

  detailContentRow.appendChild(detailIcon);
  detailContentRow.appendChild(detailText);
  detailContentRow.appendChild(detailProgress);
  detailEl.appendChild(detailContentRow);
  detailEl.appendChild(detailProgressBarContainer);

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
  if (!statusEl.querySelector('.sync-status-action')) {
    actionEl.classList.add('sync-status-action');
    statusEl.appendChild(actionEl);
  }
  if (!statusEl.querySelector('.sync-status-detail')) {
    statusEl.appendChild(detailEl);
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

  function updateConnectionBadge(isOnline) {
    if (!connectionBadge) return;
    connectionBadge.textContent = isOnline ? 'Online' : 'Offline';
    connectionBadge.classList.remove('bg-success-subtle', 'text-success', 'bg-danger-subtle', 'text-danger');
    connectionBadge.classList.add(
      isOnline ? 'bg-success-subtle' : 'bg-danger-subtle',
      isOnline ? 'text-success' : 'text-danger'
    );
  }

  function setState(state, message, { isOnline } = {}) {
    if (hideTimer) clearTimeout(hideTimer);
    const onlineState = typeof isOnline === 'boolean' ? isOnline : navigator.onLine;
    updateConnectionBadge(onlineState);
    statusEl.dataset.state = state;
    messageEl.textContent = message;
    updateActionButton(state, message);
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

  window.setSyncDetail = function(detail) {
    if (!detail || !detail.message) {
      detailText.textContent = '';
      detailProgress.textContent = '';
      detailProgressBar.style.width = '0%';
      detailProgressBarContainer.hidden = true;
      detailIcon.className = '';
      detailIcon.innerHTML = '';
      detailEl.hidden = true;
      return;
    }

    const tone = detail.tone || 'info';
    detailEl.hidden = false;
    detailText.textContent = detail.message;

    if (detail.progress) {
      const total = Number(detail.progress.total || 0);
      const completed = Number(detail.progress.completed || 0);
      const percentage = total > 0 ? Math.round((completed / total) * 100) : 0;
      detailProgress.textContent = `${completed}/${total} cached (${percentage}%)`;
      detailProgress.hidden = false;

      detailProgressBar.style.width = `${percentage}%`;
      detailProgressBar.setAttribute('aria-valuenow', `${percentage}`);
      detailProgressBar.textContent = `${percentage}%`;
      detailProgressBarContainer.hidden = false;
    } else {
      detailProgress.textContent = '';
      detailProgress.hidden = true;
      detailProgressBar.style.width = '0%';
      detailProgressBar.textContent = '';
      detailProgressBarContainer.hidden = true;
    }

    if (detail.tone === 'progress' || tone === 'progress') {
      detailIcon.className = 'spinner-border spinner-border-sm text-warning';
      detailIcon.innerHTML = '';
    } else if (tone === 'success') {
      detailIcon.className = 'text-success fw-semibold';
      detailIcon.innerHTML = '&check;';
    } else if (tone === 'danger' || tone === 'error') {
      detailIcon.className = 'text-danger fw-semibold';
      detailIcon.innerHTML = '&#9888;';
    } else {
      detailIcon.className = 'text-info';
      detailIcon.innerHTML = '&#9432;';
    }
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

  function requestQueueDrain() {
    if (!('serviceWorker' in navigator)) return;

    const send = () => {
      if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({ type: 'drain-queue' });
        return true;
      }
      return false;
    };

    if (!send()) {
      navigator.serviceWorker.ready.then(() => send()).catch(() => {});
    }
  }

  function updateActionButton(state, message) {
    const shouldShow = state === 'syncing' && typeof message === 'string' && message.toLowerCase().includes('queued actions pending sync');
    actionEl.hidden = !shouldShow;
    actionEl.disabled = !navigator.onLine;
  }

  actionEl.addEventListener('click', () => {
    setState('syncing', 'Resyncing queued actions…');
    requestQueueDrain();
  });

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

  window.addEventListener('online', () => {
    updateConnectionBadge(true);
    setState('synced', 'Back online. Synced');
  });
  window.addEventListener('offline', () => {
    updateConnectionBadge(false);
    setState('error', 'Offline. Changes will sync later');
  });

  const sharedRaw = sessionStorage.getItem('sharedSyncStatus');
  if (sharedRaw) {
    try {
      const shared = JSON.parse(sharedRaw);
      if (shared && shared.state) {
        updateConnectionBadge(navigator.onLine);
        setState(shared.state, shared.message || defaultMessages[shared.state] || '');
        if (shared.state === 'syncing' && shared.extra && shared.extra.followUpUrl) {
          const runFollowUp = () => {
            const followUp = fetch(shared.extra.followUpUrl, {
              method: 'GET',
              headers: {'Accept': 'application/json', 'X-Requested-With': 'fetch'},
              credentials: 'same-origin'
            });
            return window.trackBackgroundSync(followUp, {
              syncing: shared.message || defaultMessages.syncing,
              synced: 'Task deleted',
              error: 'Delete failed. Check connection.'
            }).then(resp => resp && resp.ok ? resp : Promise.reject()).then(() => {
              sessionStorage.removeItem('sharedSyncStatus');
            }).catch(() => {
              setSharedState('error', 'Delete failed. Check connection.');
            });
          };

          if (navigator.onLine) {
            runFollowUp();
          } else {
            setState('error', 'Offline. Delete pending. Will retry when online.');
            const retryOnOnline = () => {
              window.removeEventListener('online', retryOnOnline);
              setSharedState('syncing', shared.message || defaultMessages.syncing, shared.extra);
              runFollowUp();
            };
            window.addEventListener('online', retryOnOnline);
          }
        } else if (shared.state !== 'syncing') {
          sessionStorage.removeItem('sharedSyncStatus');
        }
      } else {
        updateConnectionBadge(navigator.onLine);
        setState(navigator.onLine ? 'synced' : 'error', navigator.onLine ? defaultMessages.synced : 'Offline. Changes will sync later');
      }
    } catch (e) {
      updateConnectionBadge(navigator.onLine);
      setState(navigator.onLine ? 'synced' : 'error', navigator.onLine ? defaultMessages.synced : 'Offline. Changes will sync later');
    }
  } else {
    updateConnectionBadge(navigator.onLine);
    setState(navigator.onLine ? 'synced' : 'error', navigator.onLine ? defaultMessages.synced : 'Offline. Changes will sync later');
  }
  updateConnectionBadge(navigator.onLine);
})();
