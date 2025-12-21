const swStatusBadge = document.getElementById('service-worker-status');

const updateSwBadge = (text, classNames) => {
  if (!swStatusBadge) return;
  swStatusBadge.textContent = text;
  swStatusBadge.className = `badge ${classNames}`;
};

const monitorRegistration = (registration) => {
  const refreshState = () => {
    const isActive = Boolean(registration.active && navigator.serviceWorker.controller);
    updateSwBadge(
      isActive ? 'Active' : 'Inactive',
      isActive ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'
    );
  };

  refreshState();

  registration.addEventListener('updatefound', () => {
    const installing = registration.installing;
    if (installing) {
      installing.addEventListener('statechange', refreshState);
    }
  });

  if (navigator.serviceWorker && navigator.serviceWorker.addEventListener) {
    navigator.serviceWorker.addEventListener('controllerchange', refreshState);
  }
};

if ('serviceWorker' in navigator) {
  updateSwBadge('Registering…', 'bg-warning-subtle text-warning');

  window.addEventListener('load', function() {
    navigator.serviceWorker
      .register('/service-worker.js')
      .then((registration) => {
        monitorRegistration(registration);

        if (navigator.serviceWorker.ready && navigator.serviceWorker.ready.then) {
          navigator.serviceWorker.ready.then((readyRegistration) => {
            monitorRegistration(readyRegistration);
          });
        }
      })
      .catch(() => {
        updateSwBadge('Registration failed', 'bg-danger-subtle text-danger');
      });
  });
} else {
  updateSwBadge('Not supported', 'bg-secondary-subtle text-secondary');
}
