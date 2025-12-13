(function() {
  if (!('serviceWorker' in navigator)) {
    return;
  }

  const PREFETCH_INTERVAL_MS = 5 * 60 * 1000; // every 5 minutes at most
  const PREFETCH_KEY = 'otodoOfflinePrefetchAt';
  let lastProgress = { total: 0, completed: 0 };

  function friendlyUrl(url) {
    if (!url) return '';
    try {
      const parsed = new URL(url, window.location.origin);
      return parsed.pathname + parsed.search;
    } catch (e) {
      return url;
    }
  }

  function shouldPrefetch(total) {
    if (!navigator.onLine || total === 0) return false;
    const lastRun = Number(localStorage.getItem(PREFETCH_KEY) || 0);
    return Date.now() - lastRun > PREFETCH_INTERVAL_MS;
  }

  function gatherPrefetchUrls() {
    const urls = new Set([
      '/',
      '/index.php',
      '/completed.php',
      window.location.pathname + window.location.search,
    ]);

    document.querySelectorAll('[data-task-id]').forEach(el => {
      const href = el.getAttribute('href');
      if (!href) return;
      try {
        const url = new URL(href, window.location.origin);
        urls.add(url.pathname + url.search);
      } catch (e) {
        // ignore bad URLs
      }
    });

    return Array.from(urls).map(u => new URL(u, window.location.origin).toString());
  }

  function updateStatus(status, total = 0, completed = 0, context = {}) {
    lastProgress = { total, completed };
    const friendlyTotal = total || lastProgress.total;
    const friendlyCompleted = completed || lastProgress.completed;
    const progressPercent = friendlyTotal ? Math.round((friendlyCompleted / friendlyTotal) * 100) : 0;

    const currentUrl = context.currentUrl ? friendlyUrl(context.currentUrl) : '';

    const messageMap = {
      waiting: 'Preparing offline caching…',
      start: 'Caching tasks for offline use…',
      progress: `Caching tasks for offline use (${friendlyCompleted}/${friendlyTotal}, ${progressPercent}% complete)`,
      done: 'Offline copy ready',
      error: 'Could not refresh offline copy',
    };

    const detailMap = {
      waiting: 'Waiting for the offline worker to take control so caching can start.',
      start: `Preparing offline copies of your tasks and pages so they open even without a connection.${friendlyTotal ? ` ${friendlyTotal} pages queued.` : ''}`,
      progress: `Saving task pages for offline use (${friendlyCompleted} of ${friendlyTotal} cached, ${progressPercent}% done).${currentUrl ? ` Currently caching: ${currentUrl}` : ''}`,
      done: 'Task pages cached. You can open them without a connection.',
      error: 'Could not cache tasks right now. Will retry when online.',
    };

    const badgeMap = {
      waiting: [{ text: 'Waiting for worker', variant: 'secondary' }],
      start: [{ text: 'Caching', variant: 'info' }],
      progress: [{ text: `${friendlyCompleted}/${friendlyTotal} cached`, variant: 'info' }],
      done: [{ text: 'Offline ready', variant: 'success' }],
      error: [{ text: 'Cache issue', variant: 'danger' }],
    };

    if (typeof window.updateSyncStatus === 'function') {
      const state = status === 'error' ? 'error' : (status === 'done' ? 'synced' : 'syncing');
      window.updateSyncStatus(state, messageMap[status] || '');
    }

    if (typeof window.setSyncBadges === 'function') {
      const badges = badgeMap[status] || [];
      window.setSyncBadges(badges);
    }

    if (typeof window.setSyncDetail === 'function') {
      const tone = status === 'error' ? 'danger' : (status === 'done' ? 'success' : (status === 'waiting' ? 'info' : 'progress'));
      const progress = status === 'progress' || status === 'start'
        ? { total: friendlyTotal, completed: friendlyCompleted }
        : null;
      window.setSyncDetail({ message: detailMap[status], tone, progress });

      if (status === 'done' || status === 'error') {
        setTimeout(() => window.setSyncDetail(null), status === 'done' ? 4000 : 6000);
      }
    }
  }

  function handlePrefetchMessage(payload = {}) {
    if (payload.type !== 'prefetch-progress') return;
    const { status, total = 0, completed = 0, currentUrl = '' } = payload;

    updateStatus(status, total, completed, { currentUrl });

    if (status === 'done') {
      localStorage.setItem(PREFETCH_KEY, Date.now().toString());
      setTimeout(() => {
        if (typeof window.updateSyncStatus === 'function') {
          window.updateSyncStatus('synced', 'All changes saved');
        }
      }, 2000);
    }
  }

  let controllerListenerAttached = false;

  async function startPrefetch(force = false) {
    const urls = gatherPrefetchUrls();
    if (!force && !shouldPrefetch(urls.length)) {
      return;
    }

    await navigator.serviceWorker.ready;
    if (!navigator.serviceWorker.controller) {
      updateStatus('waiting', urls.length, 0);

      if (!controllerListenerAttached) {
        navigator.serviceWorker.addEventListener('controllerchange', () => {
          controllerListenerAttached = false;
          startPrefetch(true).catch(() => {});
        }, { once: true });
        controllerListenerAttached = true;
      }
      return;
    }

    updateStatus('start', urls.length, 0);
    navigator.serviceWorker.controller.postMessage({ type: 'prefetch-urls', urls });
  }

  navigator.serviceWorker.addEventListener('message', event => handlePrefetchMessage(event.data || {}));
  window.addEventListener('online', () => startPrefetch().catch(() => {}));
  window.addEventListener('load', () => startPrefetch().catch(() => {}));
})();
