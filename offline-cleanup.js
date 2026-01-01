(() => {
  const cleanupKey = 'otodo-offline-cleanup-v1';
  try {
    if (localStorage.getItem(cleanupKey) === 'done') {
      return;
    }
  } catch (err) {
  }

  const tasks = [];

  if ('serviceWorker' in navigator && navigator.serviceWorker.getRegistrations) {
    tasks.push(
      navigator.serviceWorker.getRegistrations()
        .then((registrations) => Promise.all(registrations.map((registration) => registration.unregister())))
        .catch(() => {})
    );
  }

  if ('caches' in window && caches.keys) {
    tasks.push(
      caches.keys()
        .then((keys) => Promise.all(
          keys
            .filter((key) => key.startsWith('otodo-cache-'))
            .map((key) => caches.delete(key))
        ))
        .catch(() => {})
    );
  }

  if ('indexedDB' in window && indexedDB.deleteDatabase) {
    tasks.push(new Promise((resolve) => {
      const request = indexedDB.deleteDatabase('otodo-offline');
      request.onsuccess = () => resolve();
      request.onerror = () => resolve();
      request.onblocked = () => resolve();
    }));
  }

  Promise.allSettled(tasks).then(() => {
    try {
      localStorage.setItem(cleanupKey, 'done');
    } catch (err) {
    }
  });
})();
