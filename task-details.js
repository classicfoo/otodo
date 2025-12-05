(function(global){
  function normalizeNewlines(text = '') {
    return text.replace(/\r\n/g, "\n").replace(/\r/g, "\n");
  }

  function insertTextAtSelection(text, target) {
    if (!text || !target) return;
    if (typeof target.selectionStart === 'number' && typeof target.selectionEnd === 'number' && 'value' in target) {
      const start = target.selectionStart;
      const end = target.selectionEnd;
      const before = target.value.slice(0, start);
      const after = target.value.slice(end);
      target.value = before + text + after;
      const nextPos = start + text.length;
      target.selectionStart = nextPos;
      target.selectionEnd = nextPos;
      return;
    }
    if (typeof document.execCommand === 'function') {
      document.execCommand('insertText', false, text);
      return;
    }
    const sel = window.getSelection && window.getSelection();
    if (!sel || sel.rangeCount === 0) return;
    const range = sel.getRangeAt(0);
    range.deleteContents();
    const textNode = document.createTextNode(text);
    range.insertNode(textNode);
    range.setStart(textNode, textNode.length);
    range.collapse(true);
    sel.removeAllRanges();
    sel.addRange(range);
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

    details.addEventListener('paste', function(e) {
      e.preventDefault();
      const text = e.clipboardData ? e.clipboardData.getData('text/plain') : '';
      insertTextAtSelection(text, details);
      updateDetails();
      queueSave();
    });

    details.addEventListener('keydown', function(e) {
      if (e.key === 'Tab') {
        e.preventDefault();
        insertTextAtSelection('\t', details);
        updateDetails();
        queueSave();
      } else if (e.key === ' ') {
        if (typeof details.selectionStart === 'number' && details.selectionStart > 0 && 'value' in details) {
          const start = details.selectionStart;
          if (details.value[start - 1] === ' ') {
            e.preventDefault();
            const before = details.value.slice(0, start - 1);
            const after = details.value.slice(details.selectionEnd);
            details.value = before + '\t' + after;
            const nextPos = before.length + 1;
            details.selectionStart = nextPos;
            details.selectionEnd = nextPos;
            updateDetails();
            queueSave();
          }
        } else {
          const sel = window.getSelection ? window.getSelection() : null;
          if (sel && sel.rangeCount > 0) {
            const range = sel.getRangeAt(0);
            const node = range.startContainer;
            const offset = range.startOffset;
            if (node.nodeType === Node.TEXT_NODE && offset > 0 && node.textContent[offset - 1] === ' ') {
              e.preventDefault();
              range.setStart(node, offset - 1);
              range.deleteContents();
              insertTextAtSelection('\t', details);
              updateDetails();
              queueSave();
            }
          }
        }
      } else if (e.key === 'Enter') {
        e.preventDefault();
        if (typeof details.selectionStart === 'number' && 'value' in details) {
          const start = details.selectionStart;
          const textBefore = details.value.slice(0, start);
          const lineStart = textBefore.lastIndexOf('\n') + 1;
          const currentLine = textBefore.slice(lineStart);
          const leading = (currentLine.match(/^[\t ]*/) || [''])[0];
          insertTextAtSelection('\n' + leading, details);
          updateDetails();
          queueSave();
        } else {
          const sel = window.getSelection ? window.getSelection() : null;
          if (sel && sel.rangeCount > 0) {
            const range = sel.getRangeAt(0);
            const preRange = range.cloneRange();
            preRange.setStart(details, 0);
            const textBefore = preRange.toString();
            const lineStart = textBefore.lastIndexOf('\n') + 1;
            const currentLine = textBefore.slice(lineStart);
            const leading = (currentLine.match(/^[\t ]*/) || [''])[0];
            insertTextAtSelection('\n' + leading, details);
            updateDetails();
            queueSave();
          }
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
