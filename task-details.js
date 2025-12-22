(function(global){
  const DEFAULT_RULES = [
    { prefix: 'T ', className: 'code-line-task', color: '#1D4ED8', capitalize: true },
    { prefix: 'N ', className: 'code-line-note', color: '#1E7A3E', capitalize: true },
    { prefix: 'M ', className: 'code-line-milestone', color: '#800000' },
    { prefix: '# ', className: 'code-line-heading', color: '#212529', weight: '700' },
    { prefix: 'X ', className: 'code-line-done', color: '#6C757D' }
  ];

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

  function escapeRegex(text = '') {
    return text.replace(/[.*+?^${}()|[\\]\\]/g, '\\$&');
  }

  function buildDateRegexes(dateFormats = []) {
    if (!Array.isArray(dateFormats)) {
      return [];
    }

    const tokenMap = {
      'DD': '(0?[1-9]|[12][0-9]|3[01])',
      'D': '(0?[1-9]|[12][0-9]|3[01])',
      'MMMM': '(January|February|March|April|May|June|July|August|September|October|November|December)',
      'MMM': '(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)',
      'MM': '(0?[1-9]|1[0-2])',
      'M': '(0?[1-9]|1[0-2])',
      'YYYY': '\\d{4}',
      'YY': '\\d{2}'
    };

    function toRegexString(format) {
      if (typeof format !== 'string' || !format.trim()) {
        return null;
      }

      const trimmed = format.trim();
      const tokenPattern = /(DD|D|MMMM|MMM|MM|M|YYYY|YY)/g;
      let lastIndex = 0;
      let match;
      const parts = [];

      while ((match = tokenPattern.exec(trimmed))) {
        const textBefore = trimmed.slice(lastIndex, match.index);
        if (textBefore) {
          parts.push(escapeRegex(textBefore));
        }

        const replacement = tokenMap[match[0]];
        if (!replacement) {
          return null;
        }

        parts.push(replacement);
        lastIndex = tokenPattern.lastIndex;
      }

      const trailing = trimmed.slice(lastIndex);
      if (trailing) {
        parts.push(escapeRegex(trailing));
      }

      if (!parts.length) {
        return null;
      }

      return '\\b' + parts.join('') + '\\b';
    }

    return dateFormats
      .map(toRegexString)
      .filter(Boolean)
      .map(function(pattern) {
        return new RegExp(pattern, 'giu');
      });
  }

  function capitalizeMonths(text = '') {
    const monthPattern = /\b(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec)\b/gi;
    return text.replace(monthPattern, function(match) {
      return match.charAt(0).toUpperCase() + match.slice(1).toLowerCase();
    });
  }

  function highlightHtml(text = '', dateRegexes = []) {
    const escaped = escapeHtml(text);
    const withHashtags = escaped.replace(/#([\p{L}\p{N}_-]+)(?=$|[^\p{L}\p{N}_-])/gu, '<span class="inline-hashtag">#$1</span>');
    const withDates = Array.isArray(dateRegexes) && dateRegexes.length
      ? dateRegexes.reduce(function(prev, regex) {
          if (!(regex instanceof RegExp)) {
            return prev;
          }
          return prev.replace(regex, function(match) {
            const normalized = capitalizeMonths(match);
            return '<span class="inline-date">' + normalized + '</span>';
          });
        }, withHashtags)
      : withHashtags;
    return withDates.replace(/(&lt;\/?)([a-zA-Z0-9-]+)([^&]*?)(&gt;)/g, function(_, open, tag, attrs, close) {
      const highlightedAttrs = (attrs || '').replace(/([a-zA-Z_:][-a-zA-Z0-9_:.]*)(\s*=\s*)("[^"]*"|[^\s"'<>]+)/g, '<span class="token attr-name">$1</span>$2<span class="token attr-value">$3</span>');
      return '<span class="token tag">' + open + '<span class="token tag-name">' + tag + '</span>' + highlightedAttrs + close + '</span>';
    });
  }

  function pickRules(lineRules) {
    if (Array.isArray(lineRules) && lineRules.length) {
      return lineRules;
    }
    return DEFAULT_RULES;
  }

  function wrapLinesWithColors(highlightedHtml, rawText, lineRules) {
    const rulesToUse = pickRules(lineRules);
    const highlightedLines = highlightedHtml.split('\n');
    const rawLines = rawText.split('\n');

    return highlightedLines.map(function(line, index) {
      const raw = rawLines[index] || '';
      const classes = ['code-line'];
      const styles = [];
      const trimmed = raw.replace(/^[\t ]+/, '');
      const matchedRule = rulesToUse.find(function(rule) {
        return rule && typeof rule.prefix === 'string' && trimmed.startsWith(rule.prefix);
      });

      if (matchedRule) {
        if (matchedRule.className) {
          classes.push(matchedRule.className);
        }
        if (matchedRule.color) {
          styles.push('color: ' + matchedRule.color + ';');
        }
        if (matchedRule.weight) {
          styles.push('font-weight: ' + matchedRule.weight + ';');
        }
      }
      const content = line === '' ? '&#8203;' : line;
      const styleAttr = styles.length ? ' style="' + styles.join(' ') + '"' : '';
      return '<div class="' + classes.join(' ') + '"' + styleAttr + '>' + content + '</div>';
    }).join('');
  }

  function applyCapitalization(text, shouldCapitalize, lineRules) {
    if (!shouldCapitalize) {
      return text;
    }

    const rulesToUse = pickRules(lineRules);
    const lines = text.split('\n');

    const updatedLines = lines.map(function(line) {
      const trimmed = line.replace(/^[\t ]+/, '');
      const matchesRule = rulesToUse.some(function(rule) {
        return rule && typeof rule.prefix === 'string' && trimmed.startsWith(rule.prefix);
      });

      if (!matchesRule) {
        return line;
      }

      const leadingMatch = line.match(/^[\t ]*/);
      const leading = leadingMatch ? leadingMatch[0] : '';
      const content = line.slice(leading.length);
      let updated = content;

      const firstLetterIndex = updated.search(/[A-Za-z]/);
      if (firstLetterIndex !== -1) {
        updated =
          updated.slice(0, firstLetterIndex) +
          updated[firstLetterIndex].toUpperCase() +
          updated.slice(firstLetterIndex + 1);
      }

      const prefixSpaceIndex = updated.indexOf(' ');
      if (prefixSpaceIndex !== -1) {
        const afterPrefix = updated.slice(prefixSpaceIndex + 1);
        const contentLetterIndex = afterPrefix.search(/[A-Za-z]/);

        if (contentLetterIndex !== -1) {
          const absoluteIndex = prefixSpaceIndex + 1 + contentLetterIndex;
          updated =
            updated.slice(0, absoluteIndex) +
            updated[absoluteIndex].toUpperCase() +
            updated.slice(absoluteIndex + 1);
        }
      }

      return leading + updated;
    });

    return updatedLines.join('\n');
  }

  function initTaskDetailsEditor(details, detailsField, scheduleSave, options = {}) {
    if (!details || !detailsField) {
      return { updateDetails: function() { return ''; } };
    }

    const editable = details.querySelector('[contenteditable="true"]');
    const preview = details.querySelector('code');
    if (!editable || !preview) {
      return { updateDetails: function() { return ''; } };
    }

    const queueSave = typeof scheduleSave === 'function' ? scheduleSave : function() {};
    const lineRules = pickRules(options.lineRules);
    const capitalizeSentences = !!options.capitalizeSentences;
    const dateRegexes = buildDateRegexes(options.dateFormats);

    if (options.textColor) {
      details.style.setProperty('--details-text-color', options.textColor);
    }

    function htmlToText(html) {
      const container = document.createElement('div');
      container.innerHTML = html || '';
      const parts = [];

      function walk(node) {
        if (!node) return;
        if (node.nodeType === 3) {
          parts.push(node.nodeValue);
          return;
        }
        if (node.nodeName === 'BR') {
          parts.push('\n');
          return;
        }
        if (node.nodeName === 'A') {
          const href = node.getAttribute('href') || '';
          const text = node.textContent || '';
          if (href) {
            parts.push('[' + text + '](' + href + ')');
          } else {
            parts.push(text);
          }
          return;
        }

        const isBlock = node.nodeName === 'DIV' || node.nodeName === 'P';
        if (isBlock) {
          parts.push('\n');
        }
        Array.prototype.forEach.call(node.childNodes || [], walk);
        if (isBlock) {
          parts.push('\n');
        }
      }

      Array.prototype.forEach.call(container.childNodes || [], walk);
      return normalizeNewlines(parts.join('')).replace(/\n{3,}/g, '\n\n');
    }

    function textToHtml(text) {
      const normalized = normalizeNewlines(text || '');
      const linkPattern = /\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)|(https?:\/\/[^\s]+)/g;

      function linkify(segment) {
        let lastIndex = 0;
        let result = '';
        let match;
        while ((match = linkPattern.exec(segment))) {
          const before = segment.slice(lastIndex, match.index);
          result += escapeHtml(before);
          if (match[1] && match[2]) {
            result += '<a href="' + escapeHtml(match[2]) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(match[1]) + '</a>';
          } else if (match[3]) {
            result += '<a href="' + escapeHtml(match[3]) + '" target="_blank" rel="noopener noreferrer">' + escapeHtml(match[3]) + '</a>';
          }
          lastIndex = match.index + match[0].length;
        }
        result += escapeHtml(segment.slice(lastIndex));
        return result;
      }

      return normalized.split('\n').map(linkify).join('<br>');
    }

    function renderPreview(text, rawText) {
      const highlighted = highlightHtml(text, dateRegexes);
      preview.innerHTML = wrapLinesWithColors(highlighted, rawText, lineRules);
    }

    function stripMarkdownLinks(text) {
      if (!text) return '';
      return text.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '$1');
    }

    function getCaretIndex() {
      const selection = window.getSelection();
      if (!selection || selection.rangeCount === 0) return null;
      const range = selection.getRangeAt(0);
      const preRange = range.cloneRange();
      preRange.selectNodeContents(editable);
      preRange.setEnd(range.startContainer, range.startOffset);
      return preRange.toString().length;
    }

    function restoreCaret(index) {
      if (index === null || index === undefined) return;
      const selection = window.getSelection();
      if (!selection) return;

      const walker = document.createTreeWalker(
        editable,
        NodeFilter.SHOW_TEXT | NodeFilter.SHOW_ELEMENT,
        {
          acceptNode: function(node) {
            if (node.nodeType === 3) return NodeFilter.FILTER_ACCEPT;
            if (node.nodeName === 'BR') return NodeFilter.FILTER_ACCEPT;
            return NodeFilter.FILTER_SKIP;
          }
        }
      );

      let remaining = index;
      let node;
      while ((node = walker.nextNode())) {
        if (node.nodeType === 3) {
          const len = node.nodeValue.length;
          if (remaining <= len) {
            const range = document.createRange();
            range.setStart(node, remaining);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
            return;
          }
          remaining -= len;
        } else if (node.nodeName === 'BR') {
          if (remaining === 0) {
            const range = document.createRange();
            range.setStartBefore(node);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
            return;
          }
          remaining -= 1;
        }
      }

      const fallbackRange = document.createRange();
      fallbackRange.selectNodeContents(editable);
      fallbackRange.collapse(false);
      selection.removeAllRanges();
      selection.addRange(fallbackRange);
    }

    function syncDetails() {
      const wasFocused = document.activeElement === editable;
      const caretIndex = wasFocused ? getCaretIndex() : null;
      const text = htmlToText(editable.innerHTML);
      const capitalized = applyCapitalization(text, capitalizeSentences, lineRules);
      const renderedHtml = textToHtml(capitalized);

      if (editable.innerHTML !== renderedHtml) {
        editable.innerHTML = renderedHtml;
        if (wasFocused) {
          restoreCaret(caretIndex);
        }
      }

      detailsField.value = capitalized;
      const previewText = stripMarkdownLinks(capitalized);
      renderPreview(previewText, previewText);
      return capitalized;
    }

    function openAnchorFromEvent(event) {
      const target = event.target;
      if (!target || target.nodeName !== 'A') return;
      if (event.metaKey || event.ctrlKey) {
        event.preventDefault();
        window.open(target.getAttribute('href'), '_blank', 'noopener');
      }
    }

    function editAnchor(event) {
      const target = event.target;
      if (!target || target.nodeName !== 'A') return;
      event.preventDefault();
      const currentText = target.textContent || '';
      const currentHref = target.getAttribute('href') || '';
      const nextText = window.prompt('Link text', currentText);
      if (nextText === null) return;
      const nextHref = window.prompt('Link URL', currentHref);
      if (nextHref === null) return;
      target.textContent = nextText;
      target.setAttribute('href', nextHref);
      target.setAttribute('target', '_blank');
      target.setAttribute('rel', 'noopener noreferrer');
      syncDetails();
      queueSave();
    }

    function handleInput() {
      syncDetails();
      queueSave();
    }

    editable.addEventListener('input', handleInput);
    editable.addEventListener('blur', handleInput);
    editable.addEventListener('click', openAnchorFromEvent);
    editable.addEventListener('contextmenu', editAnchor);
    editable.addEventListener('paste', function(e) {
      if (!e.clipboardData) return;
      e.preventDefault();
      const text = e.clipboardData.getData('text/plain');
      if (typeof document.execCommand === 'function') {
        document.execCommand('insertText', false, text);
      } else {
        const selection = window.getSelection();
        if (selection && selection.rangeCount > 0) {
          const range = selection.getRangeAt(0);
          range.deleteContents();
          range.insertNode(document.createTextNode(text));
          selection.collapseToEnd();
        } else {
          editable.textContent = (editable.textContent || '') + text;
        }
      }
      handleInput();
    });

    const initialValue = detailsField.value || '';
    editable.innerHTML = textToHtml(initialValue);
    syncDetails();

    return { updateDetails: syncDetails };
  }

  const api = { initTaskDetailsEditor: initTaskDetailsEditor, normalizeNewlines: normalizeNewlines, buildDateRegexes: buildDateRegexes };
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  if (global) {
    global.initTaskDetailsEditor = initTaskDetailsEditor;
    global.normalizeDetailsNewlines = normalizeNewlines;
  }
})(typeof window !== 'undefined' ? window : globalThis);
