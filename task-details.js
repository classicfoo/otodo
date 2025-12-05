(function(global){
  function normalizeNewlines(text = '') {
    return text.replace(/\r\n/g, "\n").replace(/\r/g, "\n");
  }

  function getDetailsText(details) {
    if (!details) return '';
    if ('value' in details) return details.value;
    if (details.textContent !== undefined) return details.textContent;
    if (details.innerText !== undefined) return details.innerText;
    return '';
  }

  function setDetailsText(details, text) {
    if (!details) return;
    if ('value' in details) {
      details.value = text;
      return;
    }
    if (details.textContent !== undefined) {
      details.textContent = text;
      return;
    }
    if (details.innerText !== undefined) {
      details.innerText = text;
    }
  }

  function maybeUpdateAutosize(target) {
    if (!target) return;
    const autosizeFn = (typeof global !== 'undefined' ? global.autosize : null) || (typeof window !== 'undefined' ? window.autosize : null);
    if (!autosizeFn) return;
    if (typeof autosizeFn.update === 'function') {
      autosizeFn.update(target);
      return;
    }
    if (typeof autosizeFn === 'function') {
      autosizeFn(target);
    }
  }

  function initTaskDetailsEditor(details, detailsField, scheduleSave) {
    if (!details) {
      return { updateDetails: function() {} };
    }

    const targetField = detailsField || details;
    const queueSave = typeof scheduleSave === 'function' ? scheduleSave : function() {};

    const updateDetails = function() {
      const source = getDetailsText(details);
      const text = normalizeNewlines(source || '');
      setDetailsText(targetField, text);
      maybeUpdateAutosize(details);
      return text;
    };

    details.addEventListener('input', function() {
      updateDetails();
      queueSave();
    });

    const autosizeFn = (typeof global !== 'undefined' ? global.autosize : null) || (typeof window !== 'undefined' ? window.autosize : null);
    if (autosizeFn && typeof autosizeFn === 'function') {
      autosizeFn(details);
    }

    details.addEventListener('keydown', function(event) {
      if (event.key !== 'Tab') return;
      if (typeof details.selectionStart !== 'number' || typeof details.selectionEnd !== 'number') return;

      event.preventDefault();

      const value = details.value || '';
      const start = details.selectionStart;
      const end = details.selectionEnd;
      const updated = value.slice(0, start) + '\t' + value.slice(end);

      details.value = updated;

      const cursor = start + 1;
      if (typeof details.setSelectionRange === 'function') {
        details.setSelectionRange(cursor, cursor);
      }

      updateDetails();
      queueSave();
    });

    details.addEventListener('paste', function() {
      setTimeout(function() {
        updateDetails();
        queueSave();
      }, 0);
    });

    updateDetails();

    return { updateDetails: updateDetails };
  }

  const api = { initTaskDetailsEditor: initTaskDetailsEditor, normalizeNewlines: normalizeNewlines, maybeUpdateAutosize: maybeUpdateAutosize };
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  if (global) {
    global.initTaskDetailsEditor = initTaskDetailsEditor;
    global.normalizeDetailsNewlines = normalizeNewlines;
    global.maybeUpdateDetailsAutosize = maybeUpdateAutosize;
  }
})(typeof window !== 'undefined' ? window : globalThis);
