(function() {
  function createOffcanvas(element, triggers = []) {
    const backdrop = document.createElement('div');
    backdrop.className = 'offcanvas-backdrop';
    let isOpen = false;

    function open() {
      if (isOpen) return;
      isOpen = true;
      element.classList.add('show');
      element.setAttribute('aria-hidden', 'false');
      document.body.appendChild(backdrop);
      requestAnimationFrame(() => backdrop.classList.add('show'));
      document.body.classList.add('no-scroll');
      triggers.forEach(trigger => trigger.setAttribute('aria-expanded', 'true'));
    }

    function close() {
      if (!isOpen) return;
      isOpen = false;
      element.classList.remove('show');
      element.setAttribute('aria-hidden', 'true');
      backdrop.classList.remove('show');
      document.body.classList.remove('no-scroll');
      triggers.forEach(trigger => trigger.setAttribute('aria-expanded', 'false'));
      setTimeout(() => backdrop.parentElement === document.body && document.body.removeChild(backdrop), 200);
    }

    backdrop.addEventListener('click', close);
    element.addEventListener('click', (event) => {
      if (event.target === element) close();
    });

    element.querySelectorAll('[data-offcanvas-close]').forEach(btn => {
      btn.addEventListener('click', close);
    });

    return { open, close, isOpen: () => isOpen };
  }

  const offcanvasControllers = new Map();
  const triggerMap = new Map();

  document.querySelectorAll('[data-offcanvas-target]').forEach(trigger => {
    const target = trigger.getAttribute('data-offcanvas-target');
    const list = triggerMap.get(target) || [];
    list.push(trigger);
    triggerMap.set(target, list);
  });

  document.querySelectorAll('.offcanvas').forEach(panel => {
    const key = '#' + panel.id;
    const controller = createOffcanvas(panel, triggerMap.get(key) || []);
    offcanvasControllers.set(key, controller);
  });

  document.querySelectorAll('[data-offcanvas-target]').forEach(trigger => {
    const target = trigger.getAttribute('data-offcanvas-target');
    const controller = offcanvasControllers.get(target);
    if (!controller) return;
    trigger.addEventListener('click', (event) => {
      event.preventDefault();
      controller.open();
    });
  });

  document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') return;
    offcanvasControllers.forEach(controller => {
      if (controller.isOpen()) controller.close();
    });
  });
})();
