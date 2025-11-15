(function(){
  const statusElement = document.querySelector('[data-sync-status]');
  const offlineMessage = 'Changes will sync when you are back online';
  const syncingMessage = 'Syncing changes…';
  const idleMessage = 'All changes synced';
  let pendingCount = 0;

  function updateStatus() {
    if (!statusElement) return;
    if (pendingCount > 0) {
      statusElement.textContent = syncingMessage;
      statusElement.classList.remove('text-success');
      statusElement.classList.add('text-warning');
    } else if (navigator.onLine) {
      statusElement.textContent = idleMessage;
      statusElement.classList.remove('text-warning');
      statusElement.classList.add('text-success');
    } else {
      statusElement.textContent = offlineMessage;
      statusElement.classList.remove('text-success');
      statusElement.classList.add('text-warning');
    }
  }

  function requestStatus() {
    if (!navigator.serviceWorker || !navigator.serviceWorker.controller) return;
    navigator.serviceWorker.controller.postMessage({ type: 'get-sync-status' });
  }

  if (navigator.serviceWorker) {
    navigator.serviceWorker.addEventListener('message', event => {
      if (!event.data || event.data.type !== 'sync-status') return;
      pendingCount = event.data.pending || 0;
      updateStatus();
    });

    navigator.serviceWorker.ready.then(reg => {
      if (reg.active) {
        requestStatus();
      }
    });

    navigator.serviceWorker.addEventListener('controllerchange', () => {
      requestStatus();
    });
  }

  window.addEventListener('online', () => {
    updateStatus();
    if (navigator.serviceWorker && navigator.serviceWorker.controller) {
      navigator.serviceWorker.controller.postMessage({ type: 'process-queue' });
    }
  });

  window.addEventListener('offline', updateStatus);

  // Form helper
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.hasAttribute('data-sync-form')) return;

    const submitter = event.submitter;
    const action = form.getAttribute('action') || window.location.href;
    const method = (form.getAttribute('method') || 'POST').toUpperCase();
    const formData = new FormData(form);
    const confirmMessage = form.getAttribute('data-confirm');

    if (confirmMessage && !window.confirm(confirmMessage)) {
      event.preventDefault();
      return;
    }

    event.preventDefault();

    const options = {
      method,
      body: formData,
      credentials: 'include',
    };

    form.classList.add('sync-pending');
    if (submitter) {
      submitter.disabled = true;
    }

    fetch(action, options).then(response => {
      if (response.status === 202) {
        pendingCount += 1;
        updateStatus();
        return { queued: true };
      }
      if (response.redirected) {
        window.location.href = response.url;
        return null;
      }
      if (response.headers.get('content-type')?.includes('application/json')) {
        return response.json();
      }
      return response.text();
    }).then(data => {
      if (!data) return;
      if (typeof data === 'object' && data !== null && data.queued) {
        showQueuedToast();
      } else {
        if (typeof data === 'string') {
          document.open();
          document.write(data);
          document.close();
        } else {
          window.location.reload();
        }
      }
    }).catch(() => {
      pendingCount += 1;
      updateStatus();
      showQueuedToast();
    }).finally(() => {
      form.classList.remove('sync-pending');
      if (submitter) {
        submitter.disabled = false;
      }
    });
  });

  function showQueuedToast() {
    if (!statusElement) return;
    statusElement.textContent = offlineMessage;
    statusElement.classList.remove('text-success');
    statusElement.classList.add('text-warning');
  }

  updateStatus();
})();
