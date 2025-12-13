class ViewRouter {
  constructor(rootSelector = '[data-view-root]') {
    this.rootSelector = rootSelector;
    this.root = document.querySelector(rootSelector);
    this.currentPath = window.location.pathname.replace(/\/$/, '') || '/';
    this.specialRouteHandler = null;
    this.bindNav();
    window.addEventListener('popstate', () => {
      const path = window.location.pathname.replace(/\/$/, '') || '/';
      this.navigate(path, false);
    });
  }

  bindNav() {
    document.querySelectorAll('[data-route]').forEach(link => {
      link.addEventListener('click', evt => {
        const path = link.getAttribute('href');
        if (!path || path === '#') return;
        evt.preventDefault();
        this.navigate(path, true);
      });
    });
  }

  async navigate(path, pushState = true) {
    if (!this.root || path === this.currentPath) {
      if (pushState && path !== this.currentPath) window.history.pushState({}, '', path);
      return;
    }

    if (this.specialRouteHandler && this.specialRouteHandler(path, { pushState })) {
      if (pushState) window.history.pushState({}, '', path);
      return;
    }

    const result = await ApiClient.requestText(path, {
      headers: { Accept: 'text/html', 'X-Requested-With': 'spa' },
    });

    if (!result.ok || typeof result.data !== 'string') {
      console.warn('Navigation failed; keeping current view');
      return;
    }

    const parser = new DOMParser();
    const doc = parser.parseFromString(result.data, 'text/html');
    const newRoot = doc.querySelector(this.rootSelector);

    if (!newRoot) return;

    this.root.replaceWith(newRoot);
    this.root = newRoot;
    this.currentPath = path;

    if (pushState) {
      window.history.pushState({}, '', path);
    }

    this.executeScripts(doc);
    this.bindNav();
    document.dispatchEvent(new CustomEvent('view:changed', { detail: { path } }));
  }

  executeScripts(scope) {
    scope.querySelectorAll('script').forEach(oldScript => {
      const script = document.createElement('script');
      if (oldScript.src) {
        script.src = oldScript.src;
      } else {
        script.textContent = oldScript.textContent;
      }
      document.body.appendChild(script);
      script.remove();
    });
  }

  setSpecialRouteHandler(handler) {
    this.specialRouteHandler = handler;
  }
}

window.ViewRouter = ViewRouter;
