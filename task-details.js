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
      return text;
    };

    details.addEventListener('input', function() {
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

  const api = { initTaskDetailsEditor: initTaskDetailsEditor, normalizeNewlines: normalizeNewlines };
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  if (global) {
    global.initTaskDetailsEditor = initTaskDetailsEditor;
    global.normalizeDetailsNewlines = normalizeNewlines;
  }
})(typeof window !== 'undefined' ? window : globalThis);
