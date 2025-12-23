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

  function highlightInlineText(text = '', dateRegexes = []) {
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

  function highlightHtml(text = '', dateRegexes = []) {
    const linkPattern = /\[([^\]]+)\]\(([^)\s]+)\)/g;
    let lastIndex = 0;
    let output = '';
    let match;

    while ((match = linkPattern.exec(text))) {
      const before = text.slice(lastIndex, match.index);
      if (before) {
        output += highlightInlineText(before, dateRegexes);
      }

      const linkText = match[1];
      const href = match[2];
      const raw = match[0];
      const highlightedText = highlightInlineText(linkText, dateRegexes);
      const hiddenStyle = 'display:inline-block;width:0;height:0;overflow:hidden;pointer-events:none;position:absolute;';
      output += '<a class="details-link" href="' + escapeHtml(href) + '">' + highlightedText + '</a>' +
        '<span class="details-link-raw" hidden style="' + hiddenStyle + '">' + escapeHtml(raw) + '</span>';
      lastIndex = linkPattern.lastIndex;
    }

    const remaining = text.slice(lastIndex);
    if (remaining) {
      output += highlightInlineText(remaining, dateRegexes);
    }

    return output;
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

    const textarea = details.querySelector('textarea');
    const preview = details.querySelector('code');
    if (!textarea || !preview) {
      return { updateDetails: function() { return ''; } };
    }

    const queueSave = typeof scheduleSave === 'function' ? scheduleSave : function() {};
    const lineRules = pickRules(options.lineRules);
    const capitalizeSentences = !!options.capitalizeSentences;
    const dateRegexes = buildDateRegexes(options.dateFormats);
    const normalizedExpanders = Array.isArray(options.textExpanders)
      ? options.textExpanders.filter(function(entry) {
          return entry && typeof entry.shortcut === 'string' && typeof entry.expansion === 'string';
        }).map(function(entry) {
          return {
            shortcut: entry.shortcut.trim(),
            expansion: entry.expansion.trim()
          };
        }).filter(function(entry) {
          return entry.shortcut && entry.expansion;
        })
      : [];

    if (options.textColor) {
      details.style.setProperty('--details-text-color', options.textColor);
    }

    function renderPreview(text) {
      const highlighted = highlightHtml(text, dateRegexes);
      preview.innerHTML = wrapLinesWithColors(highlighted, text, lineRules);
    }

    function syncDetails() {
      const selectionStart = textarea.selectionStart;
      const selectionEnd = textarea.selectionEnd;
      const text = normalizeNewlines(textarea.value || '');
      const capitalized = applyCapitalization(text, capitalizeSentences, lineRules);

      if (capitalized !== text) {
        textarea.value = capitalized;
        if (typeof selectionStart === 'number' && typeof selectionEnd === 'number') {
          textarea.selectionStart = selectionStart;
          textarea.selectionEnd = selectionEnd;
        }
      }

      detailsField.value = capitalized;
      renderPreview(capitalized);
      return capitalized;
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

    const expanderSuggestions = document.createElement('div');
    expanderSuggestions.className = 'expander-suggestions d-none';
    details.appendChild(expanderSuggestions);
    let activeExpanderIndex = -1;

    function getCaretPositionRect(target) {
      if (!target || typeof target.selectionStart !== 'number') return null;

      const baseRect = target.getBoundingClientRect();
      const mirror = document.createElement('div');
      const computed = window.getComputedStyle(target);
      const properties = [
        'boxSizing', 'width', 'paddingTop', 'paddingRight', 'paddingBottom', 'paddingLeft',
        'borderTopWidth', 'borderRightWidth', 'borderBottomWidth', 'borderLeftWidth',
        'fontFamily', 'fontSize', 'fontWeight', 'fontStyle', 'letterSpacing', 'lineHeight',
        'textTransform', 'textDecoration', 'textAlign', 'tabSize', 'whiteSpace', 'wordBreak', 'overflowWrap'
      ];

      properties.forEach(function(prop) {
        mirror.style[prop] = computed[prop];
      });

      mirror.style.position = 'absolute';
      mirror.style.visibility = 'hidden';
      mirror.style.whiteSpace = 'pre-wrap';
      mirror.style.wordWrap = 'break-word';
      mirror.style.overflow = 'auto';
      mirror.style.left = (baseRect.left + window.scrollX) + 'px';
      mirror.style.top = (baseRect.top + window.scrollY) + 'px';
      mirror.textContent = (target.value || '').slice(0, target.selectionStart);

      const caretSpan = document.createElement('span');
      caretSpan.textContent = '\u200b';
      mirror.appendChild(caretSpan);

      document.body.appendChild(mirror);
      const caretRect = caretSpan.getBoundingClientRect();
      document.body.removeChild(mirror);

      return {
        left: caretRect.left + window.scrollX,
        right: caretRect.right + window.scrollX,
        top: caretRect.top + window.scrollY,
        bottom: caretRect.bottom + window.scrollY,
        width: baseRect.width
      };
    }

    function positionExpanderSuggestions() {
      const caretRect = getCaretPositionRect(textarea);
      if (!caretRect) return;
      const containerRect = details.getBoundingClientRect();
      const left = caretRect.left - (containerRect.left + window.scrollX);
      const top = caretRect.bottom - (containerRect.top + window.scrollY) + 6;
      expanderSuggestions.style.left = left + 'px';
      expanderSuggestions.style.top = top + 'px';
      const width = Math.max(240, Math.min(420, caretRect.width));
      expanderSuggestions.style.width = width + 'px';
    }

    function hideExpanderSuggestions() {
      expanderSuggestions.classList.add('d-none');
      activeExpanderIndex = -1;
    }

    function setActiveExpander(idx) {
      const buttons = Array.from(expanderSuggestions.querySelectorAll('button'));
      if (!buttons.length) return;
      activeExpanderIndex = Math.max(0, Math.min(idx, buttons.length - 1));
      buttons.forEach(function(btn, index) {
        btn.classList.toggle('active', index === activeExpanderIndex);
      });
    }

    function replaceTokenWithExpansion(tokenRange, expansion) {
      const value = textarea.value || '';
      const start = tokenRange.start;
      const end = tokenRange.end;
      textarea.value = value.slice(0, start) + expansion + value.slice(end);
      const nextPos = start + expansion.length;
      textarea.selectionStart = textarea.selectionEnd = nextPos;
      const updated = syncDetails();
      queueSave();
      return updated;
    }

    function acceptActiveExpander(tokenRange) {
      const buttons = Array.from(expanderSuggestions.querySelectorAll('button'));
      if (!buttons.length || activeExpanderIndex === -1) return false;
      const btn = buttons[Math.max(0, Math.min(activeExpanderIndex, buttons.length - 1))];
      const expansion = btn.dataset.expansion;
      if (typeof expansion !== 'string') return false;
      replaceTokenWithExpansion(tokenRange, expansion);
      hideExpanderSuggestions();
      return true;
    }

    function renderExpanderSuggestions(matches, tokenRange) {
      expanderSuggestions.innerHTML = '';
      if (!matches.length) {
        hideExpanderSuggestions();
        return;
      }
      matches.forEach(function(match, idx) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-light btn-sm w-100 text-start';
        btn.textContent = match.shortcut + ' → ' + match.expansion;
        btn.dataset.expansion = match.expansion;
        btn.addEventListener('click', function() {
          replaceTokenWithExpansion(tokenRange, match.expansion);
          hideExpanderSuggestions();
        });
        expanderSuggestions.appendChild(btn);
        if (idx === 0) {
          setActiveExpander(0);
        }
      });
      expanderSuggestions.classList.remove('d-none');
      positionExpanderSuggestions();
    }

    function findCurrentToken() {
      const pos = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : 0;
      const value = textarea.value || '';
      let start = pos;
      while (start > 0 && !/\s/.test(value[start - 1])) {
        start--;
      }
      let end = pos;
      while (end < value.length && !/\s/.test(value[end])) {
        end++;
      }
      const text = value.slice(start, end);
      return { text: text, start: start, end: end };
    }

    function updateExpanderSuggestions() {
      if (!normalizedExpanders.length) {
        hideExpanderSuggestions();
        return;
      }
      const token = findCurrentToken();
      const tokenText = token.text || '';
      if (!tokenText) {
        hideExpanderSuggestions();
        return;
      }
      const lower = tokenText.toLowerCase();
      const matches = normalizedExpanders
        .filter(function(entry) {
          return entry.shortcut.toLowerCase().startsWith(lower);
        })
        .sort(function(a, b) {
          const aCaseSensitivePrefix = a.shortcut.startsWith(tokenText);
          const bCaseSensitivePrefix = b.shortcut.startsWith(tokenText);
          if (aCaseSensitivePrefix !== bCaseSensitivePrefix) {
            return aCaseSensitivePrefix ? -1 : 1;
          }
          return a.shortcut.localeCompare(b.shortcut);
        });
      if (!matches.length || (matches.length === 1 && matches[0].shortcut.toLowerCase() === lower)) {
        hideExpanderSuggestions();
        return;
      }
      renderExpanderSuggestions(matches, token);
    }

    textarea.addEventListener('input', function() {
      syncDetails();
      queueSave();
      updateExpanderSuggestions();
    });
    textarea.addEventListener('blur', hideExpanderSuggestions);

    textarea.addEventListener('click', updateExpanderSuggestions);
    textarea.addEventListener('keyup', function(e) {
      if (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'ArrowUp' || e.key === 'ArrowDown') {
        updateExpanderSuggestions();
      }
    });
    textarea.addEventListener('blur', hideExpanderSuggestions);

    textarea.addEventListener('click', updateExpanderSuggestions);
    textarea.addEventListener('keyup', function(e) {
      if (e.key === 'ArrowLeft' || e.key === 'ArrowRight' || e.key === 'ArrowUp' || e.key === 'ArrowDown') {
        updateExpanderSuggestions();
      }
    });
    textarea.addEventListener('blur', hideExpanderSuggestions);

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
      if (!expanderSuggestions.classList.contains('d-none')) {
        const tokenRange = findCurrentToken();
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
          e.preventDefault();
          const delta = e.key === 'ArrowDown' ? 1 : -1;
          setActiveExpander(activeExpanderIndex + delta);
          return;
        }
        if (e.key === 'Enter' || e.key === 'Tab') {
          const accepted = acceptActiveExpander(tokenRange);
          if (accepted) {
            e.preventDefault();
            return;
          }
        }
        if (e.key === 'Escape' || e.key === 'Esc') {
          hideExpanderSuggestions();
          return;
        }
      }
      if (e.key === 'Tab') {
        e.preventDefault();
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;

        if (start === end && !e.shiftKey) {
          insertAtSelection('\t');
          syncDetails();
          queueSave();
          return;
        }

        const value = textarea.value || '';
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        const lineEndIndex = value.indexOf('\n', end);
        const lineEnd = lineEndIndex === -1 ? value.length : lineEndIndex;

        const selectedLines = value.slice(lineStart, lineEnd).split('\n');

        if (e.shiftKey) {
          let totalRemoved = 0;
          let firstLineRemoved = 0;
          const outdented = selectedLines.map(function(line, index) {
            const match = line.match(/^(\t| {1,4})/);
            if (match) {
              const removed = match[0].length;
              totalRemoved += removed;
              if (index === 0) {
                firstLineRemoved = removed;
              }
              return line.slice(removed);
            }
            return line;
          }).join('\n');

          textarea.value = value.slice(0, lineStart) + outdented + value.slice(lineEnd);
          const nextStart = Math.max(lineStart, start - firstLineRemoved);
          const nextEnd = Math.max(nextStart, end - totalRemoved);
          textarea.selectionStart = nextStart;
          textarea.selectionEnd = nextEnd;
        } else {
          const indented = selectedLines.map(function(line) { return '\t' + line; }).join('\n');
          textarea.value = value.slice(0, lineStart) + indented + value.slice(lineEnd);
          const nextStart = start + 1;
          const nextEnd = end + selectedLines.length;
          textarea.selectionStart = nextStart;
          textarea.selectionEnd = nextEnd;
        }

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

  const api = { initTaskDetailsEditor: initTaskDetailsEditor, normalizeNewlines: normalizeNewlines, buildDateRegexes: buildDateRegexes };
  if (typeof module !== 'undefined' && module.exports) {
    module.exports = api;
  }
  if (global) {
    global.initTaskDetailsEditor = initTaskDetailsEditor;
    global.normalizeDetailsNewlines = normalizeNewlines;
  }
})(typeof window !== 'undefined' ? window : globalThis);
