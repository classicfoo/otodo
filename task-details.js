(function(global){
  function normalizeNewlines(text = '') {
    return text.replace(/\r\n/g, "\n").replace(/\r/g, "\n");
  }

  function insertTextAtSelection(text) {
    if (!text) return;
    const sel = window.getSelection && window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    const range = sel.getRangeAt(0);
    range.deleteContents();
    const textNode = document.createTextNode(text);
    range.insertNode(textNode);
    range.setStartAfter(textNode);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
  }

  function initTaskDetailsEditor(details, detailsField, scheduleSave) {
    if (!details || !detailsField) {
      return { updateDetails: function() {} };
    }

    const queueSave = typeof scheduleSave === 'function' ? scheduleSave : function() {};

    const updateDetails = function() {
      const source = details.textContent !== undefined ? details.textContent : details.innerText;
      const text = normalizeNewlines(source || '');
      detailsField.value = text;
      return text;
    };

    details.addEventListener('input', function() {
      updateDetails();
      queueSave();
    });

    details.addEventListener('paste', function(e) {
      e.preventDefault();
      const text = e.clipboardData ? e.clipboardData.getData('text/plain') : '';
      insertTextAtSelection(text);
      updateDetails();
      queueSave();
    });

    details.addEventListener('keydown', function(e) {
      if (e.key === 'Tab') {
        e.preventDefault();
        insertTextAtSelection('\t');
        updateDetails();
        queueSave();
      } else if (e.key === ' ') {
        const sel = window.getSelection ? window.getSelection() : null;
        if (sel && sel.rangeCount > 0) {
          const range = sel.getRangeAt(0);
          const node = range.startContainer;
          const offset = range.startOffset;
          if (node.nodeType === Node.TEXT_NODE && offset > 0 && node.textContent[offset - 1] === ' ') {
            e.preventDefault();
            range.setStart(node, offset - 1);
            range.deleteContents();
            insertTextAtSelection('\t');
            updateDetails();
            queueSave();
          }
        }
      } else if (e.key === 'Enter') {
        e.preventDefault();
        const sel = window.getSelection ? window.getSelection() : null;
        if (sel && sel.rangeCount > 0) {
          const range = sel.getRangeAt(0);
          const preRange = range.cloneRange();
          preRange.setStart(details, 0);
          const textBefore = preRange.toString();
          const lineStart = textBefore.lastIndexOf('\n') + 1;
          const currentLine = textBefore.slice(lineStart);
          const leading = (currentLine.match(/^[\t ]*/) || [''])[0];
          insertTextAtSelection('\n' + leading);
          updateDetails();
          queueSave();
        }
      }
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
