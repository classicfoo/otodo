/*!
 * autosize 4.0.4 (custom packaged)
 * https://github.com/jackmoore/autosize
 * Released under the MIT license
 */
(function (global, factory) {
  if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    global.autosize = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  function resize(el) {
    if (!el || el.nodeName !== 'TEXTAREA') return;
    const prevHeight = el.style.height;
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
    if (!el.dataset.autosizeInitialHeight) {
      el.dataset.autosizeInitialHeight = prevHeight || '';
    }
  }

  function setOverflow(el) {
    if (!el || el.nodeName !== 'TEXTAREA') return;
    const style = window.getComputedStyle ? window.getComputedStyle(el, null) : null;
    const resizeStyle = style ? style.getPropertyValue('resize') : null;
    if (resizeStyle === 'vertical') {
      el.style.resize = 'none';
    } else if (resizeStyle === 'both') {
      el.style.resize = 'horizontal';
    }
    el.style.overflowY = 'hidden';
  }

  function attach(el) {
    if (!el || el.nodeName !== 'TEXTAREA' || el.dataset.autosizeAttached === 'true') return;
    setOverflow(el);
    const handler = function () { resize(el); };
    const updateHandler = function () { resize(el); };
    const destroyHandler = function () {
      el.removeEventListener('input', handler);
      el.removeEventListener('autosize:update', updateHandler);
      el.removeEventListener('autosize:destroy', destroyHandler);
      if (el.dataset.autosizeInitialHeight !== undefined) {
        el.style.height = el.dataset.autosizeInitialHeight;
        delete el.dataset.autosizeInitialHeight;
      }
      el.style.overflowY = '';
      el.style.resize = '';
      el.dataset.autosizeAttached = 'false';
    };

    el.addEventListener('input', handler, false);
    el.addEventListener('autosize:update', updateHandler, false);
    el.addEventListener('autosize:destroy', destroyHandler, false);
    el.dataset.autosizeAttached = 'true';
    resize(el);
  }

  function process(elements) {
    if (!elements) return elements;
    if (elements instanceof Element) {
      attach(elements);
      return elements;
    }
    if (typeof elements.length === 'number') {
      Array.prototype.forEach.call(elements, attach);
    }
    return elements;
  }

  process.update = function (elements) {
    if (!elements) return;
    if (elements instanceof Element) {
      resize(elements);
      return;
    }
    if (typeof elements.length === 'number') {
      Array.prototype.forEach.call(elements, resize);
    }
  };

  process.destroy = function (elements) {
    if (!elements) return;
    const destroyOne = function (el) {
      if (!el || el.nodeName !== 'TEXTAREA') return;
      el.dispatchEvent(new Event('autosize:destroy'));
    };
    if (elements instanceof Element) {
      destroyOne(elements);
      return;
    }
    if (typeof elements.length === 'number') {
      Array.prototype.forEach.call(elements, destroyOne);
    }
  };

  return process;
}));
