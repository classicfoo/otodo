(function() {
  function blockSaveShortcut(event) {
    if ((event.ctrlKey || event.metaKey) && (event.key === 's' || event.key === 'S')) {
      event.preventDefault();
      event.stopPropagation();
    }
  }

  window.addEventListener('keydown', blockSaveShortcut, { capture: true });
})();
