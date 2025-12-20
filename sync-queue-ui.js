(function() {
  if (!('serviceWorker' in navigator)) {
    return;
  }

  const statusEl = document.getElementById('sync-status');
  const state = {
    items: [],
    draining: false,
  };

  const styles = document.createElement('style');
  styles.textContent = `
    .queue-offcanvas .queue-item { transition: opacity 0.25s ease, transform 0.25s ease; }
    .queue-offcanvas .queue-item.queue-item-clearing { opacity: 0; transform: translateX(12px); }
    .queue-offcanvas .queue-empty { background: #f8f9fa; border: 1px dashed #dee2e6; }
    .queue-offcanvas .queue-pill { font-size: 0.75rem; }
  `;
  document.head.appendChild(styles);

  const template = document.createElement('div');
  template.innerHTML = `
  <div class="offcanvas offcanvas-end queue-offcanvas" tabindex="-1" id="queueOffcanvas" aria-labelledby="queueOffcanvasLabel">
    <div class="offcanvas-header border-bottom">
      <div>
        <h5 class="offcanvas-title" id="queueOffcanvasLabel">Queued actions</h5>
        <p class="mb-0 text-muted small">Review offline changes, retry, or discard as needed.</p>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="badge text-bg-secondary" id="queueTotalBadge">0</span>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
      </div>
    </div>
    <div class="offcanvas-body">
      <div class="alert alert-info d-flex align-items-center gap-2 py-2 small">
        <span class="badge bg-primary-subtle text-primary queue-pill" id="queueDrainBadge" hidden>Resyncing…</span>
        <div>Queued items will retry automatically when you reconnect. You can also retry or discard them manually.</div>
      </div>
      <div id="queueList" class="d-flex flex-column gap-2" aria-live="polite"></div>
    </div>
  </div>`;
  const offcanvasEl = template.firstElementChild;
  document.body.appendChild(offcanvasEl);

  const queueListEl = offcanvasEl.querySelector('#queueList');
  const queueTotalBadge = offcanvasEl.querySelector('#queueTotalBadge');
  const queueDrainBadge = offcanvasEl.querySelector('#queueDrainBadge');

  let menuBadge;
  function ensureMenuEntry() {
    const menuList = document.querySelector('#menu .list-group');
    if (!menuList) return;
    const existing = document.getElementById('queueMenuTrigger');
    if (existing) {
      menuBadge = existing.querySelector('.queue-menu-badge');
      return;
    }

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'queueMenuTrigger';
    btn.className = 'list-group-item list-group-item-action d-flex align-items-center justify-content-between';
    btn.setAttribute('data-bs-toggle', 'offcanvas');
    btn.setAttribute('data-bs-target', '#queueOffcanvas');
    btn.innerHTML = `<span>Sync queue</span><span class="badge bg-secondary queue-menu-badge">0</span>`;
    menuBadge = btn.querySelector('.queue-menu-badge');

    menuList.insertBefore(btn, menuList.querySelector('a[href="logout.php"]'));
  }

  ensureMenuEntry();
  updateBadges();

  function describeEntry(entry) {
    try {
      const url = new URL(entry.url);
      const path = url.pathname.split('/').pop();
      const id = url.searchParams.get('id');
      const labelMap = {
        'add_task.php': 'Add task',
        'delete_task.php': 'Delete task',
        'delete_completed.php': 'Delete completed',
        'update_task_meta.php': 'Update task details',
        'toggle_star.php': 'Toggle star',
        'manage_hashtags.php': 'Hashtag change',
        'settings.php': 'Settings update',
      };
      const action = labelMap[path] || `${entry.method} ${path || url.pathname}`;
      const taskHint = id ? `Task #${id}` : (url.searchParams.get('task_id') ? `Task #${url.searchParams.get('task_id')}` : '');
      return { action, taskHint };
    } catch (e) {
      return { action: entry.method || 'Request', taskHint: '' };
    }
  }

  function formatTime(timestamp) {
    const date = new Date(timestamp);
    return isNaN(date.getTime()) ? 'Pending' : date.toLocaleString();
  }

  function postToServiceWorker(payload) {
    if (navigator.serviceWorker.controller) {
      navigator.serviceWorker.controller.postMessage(payload);
    }
  }

  function updateBadges() {
    const count = state.items.length;
    queueTotalBadge.textContent = count;
    queueDrainBadge.hidden = !state.draining;
    if (menuBadge) {
      menuBadge.textContent = count;
      menuBadge.classList.toggle('bg-warning', count > 0);
      menuBadge.classList.toggle('bg-secondary', count === 0);
    }

    if (typeof window.setSyncBadges === 'function') {
      const badges = [];
      if (count > 0) {
        badges.push({ text: `${count} queued`, variant: state.draining ? 'primary' : 'warning' });
      }
      if (state.draining) {
        badges.push({ text: 'Resyncing', variant: 'info' });
      }
      window.setSyncBadges(badges);
    }

    if (typeof window.updateSyncStatus === 'function' && statusEl) {
      if (count > 0) {
        const message = state.draining ? 'Resyncing queued actions…' : (navigator.onLine ? 'Queued actions pending sync' : 'Offline. Actions queued');
        window.updateSyncStatus(state.draining || navigator.onLine ? 'syncing' : 'error', message);
      } else if (navigator.onLine && statusEl.dataset.state !== 'error') {
        window.updateSyncStatus('synced', 'All changes saved');
      }
    }
  }

  function renderEmptyState() {
    const empty = document.createElement('div');
    empty.className = 'queue-empty rounded-3 p-3 text-muted text-center';
    empty.innerHTML = '<div class="fw-semibold">Nothing queued</div><div class="small">Add or edit tasks while offline to see them here.</div>';
    queueListEl.appendChild(empty);
  }

  function renderQueue() {
    queueListEl.innerHTML = '';
    if (!state.items.length) {
      renderEmptyState();
      updateBadges();
      return;
    }

    state.items.forEach(entry => {
      const wrapper = document.createElement('div');
      wrapper.className = 'queue-item border rounded-3 p-3';
      wrapper.dataset.queueId = entry.id;

      const meta = describeEntry(entry);
      const timestamp = formatTime(entry.timestamp);

      wrapper.innerHTML = `
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="fw-semibold">${meta.action}</div>
          <span class="badge text-bg-light text-uppercase queue-pill">${entry.method || 'POST'}</span>
        </div>
        <div class="text-muted small mb-2">${meta.taskHint || entry.url}</div>
        <div class="d-flex justify-content-between align-items-center">
          <div class="text-muted small">Queued ${timestamp}</div>
          <div class="btn-group btn-group-sm" role="group" aria-label="Queue controls">
            <button type="button" class="btn btn-outline-primary" data-queue-action="retry" data-queue-id="${entry.id}">Retry</button>
            <button type="button" class="btn btn-outline-secondary" data-queue-action="discard" data-queue-id="${entry.id}">Discard</button>
          </div>
        </div>
      `;

      queueListEl.appendChild(wrapper);
    });

    updateBadges();
  }

  queueListEl.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    const action = target.getAttribute('data-queue-action');
    const id = target.getAttribute('data-queue-id');
    if (!action || !id) return;

    if (action === 'retry') {
      postToServiceWorker({ type: 'retry-item', id });
    } else if (action === 'discard') {
      postToServiceWorker({ type: 'discard-item', id });
    }
  });

  function handleRemoval(id) {
    const safeId = typeof CSS !== 'undefined' && CSS.escape ? CSS.escape(id) : id;
    const row = queueListEl.querySelector(`[data-queue-id="${safeId}"]`);
    state.items = state.items.filter(item => item.id !== id);
    if (row) {
      row.classList.add('queue-item-clearing');
      setTimeout(() => renderQueue(), 250);
    } else {
      renderQueue();
    }
  }

  function handleQueued(entry) {
    if (!entry || !entry.id) return;
    const exists = state.items.some(item => item.id === entry.id);
    if (!exists) {
      state.items.push(entry);
    }
    renderQueue();
  }

  function handleMessage(event) {
    const data = event.data || {};
    if (data.type === 'queue-state') {
      state.items = Array.isArray(data.queue) ? data.queue : [];
      state.draining = !!data.draining;
      renderQueue();
      return;
    }

    if (data.type === 'queue-drain-start') {
      state.draining = true;
      updateBadges();
      return;
    }

    if (data.type === 'queue-event') {
      const evt = data.event;
      const entry = data.entry || {};
      if (evt === 'queued') {
        state.draining = false;
        handleQueued(entry);
      } else if (evt === 'retrying') {
        state.draining = true;
        updateBadges();
      } else if (evt === 'sent' || evt === 'discarded') {
        state.draining = evt === 'sent' ? state.draining : false;
        handleRemoval(entry.id || data.id);
      } else if (evt === 'failed') {
        state.draining = false;
        updateBadges();
      }
      return;
    }
  }

  navigator.serviceWorker.addEventListener('message', handleMessage);

  function requestQueueState() {
    postToServiceWorker({ type: 'get-queue' });
  }

  navigator.serviceWorker.ready.then(reg => {
    if (navigator.serviceWorker.controller) {
      requestQueueState();
    }
  });

  navigator.serviceWorker.addEventListener('controllerchange', () => {
    if (navigator.serviceWorker.controller) {
      requestQueueState();
    }
  });

  window.addEventListener('online', () => {
    updateBadges();
    navigator.serviceWorker.ready
      .then(reg => reg.active || navigator.serviceWorker.controller)
      .then(worker => {
        if (worker) {
          worker.postMessage({ type: 'drain-queue' });
        }
      })
      .catch(() => {});
  });
  window.addEventListener('offline', () => {
    updateBadges();
  });
})();
