(function(global){
  function normalizeNewlines(text = '') {
    return text.replace(/\r\n/g, "\n").replace(/\r/g, "\n");
  }

  function escapeHtml(text = '') {
    return text
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function highlightHtml(text = '') {
    const escaped = escapeHtml(text);
    return escaped.replace(/(&lt;\/?)([a-zA-Z0-9-]+)([^&]*?)(&gt;)/g, function(_, open, tag, attrs, close) {
      const highlightedAttrs = (attrs || '').replace(/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(\s*=\s*)("[^"]*"|[^\s"'<>]+)/g, '<span class="token attr-name">$1</span>$2<span class="token attr-value">$3</span>');
      return '<span class="token tag">' + open + '<span class="token tag-name">' + tag + '</span>' + highlightedAttrs + close + '</span>';
    });
  }

  function wrapLinesWithColors(highlightedHtml, rawText) {
    const highlightedLines = highlightedHtml.split('\n');
    const rawLines = rawText.split('\n');

    return highlightedLines.map(function(line, index) {
      const raw = rawLines[index] || '';
      const classes = ['code-line'];
      const trimmed = raw.replace(/^[\t ]+/, '');
      if (trimmed.startsWith('T ')) {
        classes.push('code-line-task');
      } else if (trimmed.startsWith('N ')) {
        classes.push('code-line-note');
      } else if (trimmed.startsWith('M ')) {
        classes.push('code-line-milestone');
      } else if (trimmed.startsWith('# ')) {
        classes.push('code-line-heading');
      } else if (trimmed.startsWith('X ')) {
        classes.push('code-line-done');
      }
      const content = line === '' ? '&#8203;' : line;
      return '<div class="' + classes.join(' ') + '">' + content + '</div>';
    }).join('');
  }

  function initTaskDetailsEditor(details, detailsField, scheduleSave) {
    if (!details || !detailsField) {
      return { updateDetails: function() { return ''; } };
    }

    const textarea = details.querySelector('textarea');
    const preview = details.querySelector('code');
    if (!textarea || !preview) {
      return { updateDetails: function() { return ''; } };
    }

    const queueSave = typeof scheduleSave === 'function' ? scheduleSave : function() {};

    function renderPreview(text) {
      const highlighted = highlightHtml(text);
      preview.innerHTML = wrapLinesWithColors(highlighted, text);
    }

    function syncDetails() {
      const text = normalizeNewlines(textarea.value || '');
      detailsField.value = text;
      renderPreview(text);
      return text;
    }

    function insertAtSelection(text) {
      const start = textarea.selectionStart;
      const end = textarea.selectionEnd;
      const value = textarea.value || '';
      const nextValue = value.slice(0, start) + text + value.slice(end);
      textarea.value = nextValue;
      const nextPos = start + text.length;
      textarea.selectionStart = textarea.selectionEnd = nextPos;
      return nextValue;
    }

    textarea.addEventListener('input', function() {
      syncDetails();
      queueSave();
    });

    textarea.addEventListener('scroll', function() {
      preview.parentElement.scrollTop = textarea.scrollTop;
      preview.parentElement.scrollLeft = textarea.scrollLeft;
    });

    textarea.addEventListener('paste', function(e) {
      e.preventDefault();
      const text = e.clipboardData ? e.clipboardData.getData('text/plain') : '';
      insertAtSelection(text);
      syncDetails();
      queueSave();
    });

    textarea.addEventListener('keydown', function(e) {
      if (e.key === 'Tab') {
        e.preventDefault();
        insertAtSelection('\t');
        syncDetails();
        queueSave();
      } else if (e.key === 'Home' && !e.ctrlKey && !e.metaKey && !e.altKey) {
        e.preventDefault();
        const caret = textarea.selectionStart;
        const value = textarea.value || '';
        const lineStart = value.lastIndexOf('\n', caret - 1) + 1;
        const lineEnd = value.indexOf('\n', lineStart);
        const endIndex = lineEnd === -1 ? value.length : lineEnd;
        let firstVisible = lineStart;
        while (firstVisible < endIndex && (value[firstVisible] === ' ' || value[firstVisible] === '\t')) {
          firstVisible += 1;
        }
        const target = caret !== firstVisible ? firstVisible : lineStart;

        if (e.shiftKey) {
          const anchor = textarea.selectionEnd;
          textarea.selectionStart = Math.min(anchor, target);
          textarea.selectionEnd = Math.max(anchor, target);
        } else {
          textarea.selectionStart = textarea.selectionEnd = target;
        }
      } else if (e.key === ' ') {
        const start = textarea.selectionStart;
        if (start > 0 && textarea.selectionStart === textarea.selectionEnd && textarea.value[start - 1] === ' ') {
          e.preventDefault();
          textarea.selectionStart = start - 1;
          textarea.selectionEnd = start;
          insertAtSelection('\t');
          syncDetails();
          queueSave();
        }
      } else if (e.key === 'Enter') {
        e.preventDefault();
        const start = textarea.selectionStart;
        const value = textarea.value || '';
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        const currentLine = value.slice(lineStart, start);
        const leading = (currentLine.match(/^[\t ]*/) || [''])[0];
        insertAtSelection('\n' + leading);
        syncDetails();
        queueSave();
      }
    });

    syncDetails();

    return { updateDetails: syncDetails };
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
