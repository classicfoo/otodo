(function(global){
  'use strict';

  function normalizeText(text) {
    return (text || '')
      .replace(/\r\n?/g, '\n')
      .replace(/\u00a0/g, ' ');
  }

  function getTextContent(el) {
    return normalizeText(el.textContent || '');
  }

  function setTextContent(el, value) {
    el.textContent = normalizeText(value);
  }

  function getCursorOffset(root) {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return 0;
    const range = sel.getRangeAt(0);
    const pre = range.cloneRange();
    pre.selectNodeContents(root);
    pre.setEnd(range.endContainer, range.endOffset);
    return pre.toString().length;
  }

  function setCursor(root, offset) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    let currentOffset = 0;
    let node;
    while ((node = walker.nextNode())) {
      const nextOffset = currentOffset + node.textContent.length;
      if (nextOffset >= offset) {
        const position = Math.max(0, offset - currentOffset);
        const range = document.createRange();
        range.setStart(node, position);
        range.collapse(true);
        const sel = window.getSelection();
        if (sel) {
          sel.removeAllRanges();
          sel.addRange(range);
        }
        return;
      }
      currentOffset = nextOffset;
    }
    const range = document.createRange();
    range.selectNodeContents(root);
    range.collapse(false);
    const sel = window.getSelection();
    if (sel) {
      sel.removeAllRanges();
      sel.addRange(range);
    }
  }

  function getLineInfo(text, offset) {
    const start = text.lastIndexOf('\n', Math.max(0, offset - 1)) + 1;
    const endIndex = text.indexOf('\n', offset);
    const end = endIndex === -1 ? text.length : endIndex;
    return {
      start,
      end,
      text: text.slice(start, end)
    };
  }

  function replaceRange(text, start, end, replacement) {
    return text.slice(0, start) + replacement + text.slice(end);
  }

  function leadingWhitespace(str) {
    const match = str.match(/^[ \t]*/);
    return match ? match[0] : '';
  }

  class NotethingTextWidget {
    constructor(element, options = {}) {
      this.el = element;
      this.hiddenInput = options.hiddenInput || null;
      this.placeholder = options.placeholder || '';
      this.onChange = typeof options.onChange === 'function' ? options.onChange : null;
      this.maxIndent = options.maxIndent || 12;
      this.indentString = options.indentString || '\t';
      this.autoFocus = options.autoFocus || false;

      this.el.classList.add('notething-text-widget');
      this.el.setAttribute('contenteditable', 'true');
      if (this.placeholder) {
        this.el.dataset.placeholder = this.placeholder;
      }

      const initial = options.value !== undefined
        ? options.value
        : this.hiddenInput
          ? this.hiddenInput.value
          : getTextContent(this.el);
      this.setText(initial);

      this.el.addEventListener('input', () => this.handleInput());
      this.el.addEventListener('keydown', (e) => this.handleKeydown(e));
      this.el.addEventListener('paste', (e) => this.handlePaste(e));
      this.el.addEventListener('click', () => this.sync());
      this.el.addEventListener('blur', () => this.sync());

      if (this.autoFocus) {
        this.el.focus();
      }

      this.sync();
    }

    getText() {
      return getTextContent(this.el);
    }

    setText(value) {
      setTextContent(this.el, value);
      this.sync();
    }

    sync() {
      const text = this.getText();
      if (this.hiddenInput) {
        this.hiddenInput.value = text;
      }
      if (this.onChange) this.onChange(text);
    }

    handleInput() {
      this.sync();
    }

    handlePaste(event) {
      event.preventDefault();
      const text = normalizeText(event.clipboardData.getData('text/plain'));
      document.execCommand('insertText', false, text);
      this.sync();
    }

    handleKeydown(event) {
      if (event.key === 'Tab') {
        event.preventDefault();
        if (event.shiftKey) {
          this.outdentSelection();
        } else {
          this.indentSelection();
        }
        return;
      }

      if (event.key === 'Enter') {
        event.preventDefault();
        this.insertNewline();
        return;
      }

      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'b') {
        event.preventDefault();
        this.toggleBullet();
        return;
      }

      if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'l') {
        event.preventDefault();
        this.toggleTodo();
        return;
      }

      if (event.key === 'Backspace') {
        const text = this.getText();
        const cursor = getCursorOffset(this.el);
        const line = getLineInfo(text, cursor);
        const beforeCursor = cursor - line.start;
        if (beforeCursor <= 1) {
          const match = line.text.match(/^([ \t]*)(- \[[ x]\] |- )/);
          if (match) {
            event.preventDefault();
            const newLine = line.text.replace(match[2], '');
            const updated = replaceRange(text, line.start, line.end, newLine);
            this.setText(updated);
            setCursor(this.el, line.start + match[1].length);
          } else if (line.text.startsWith(this.indentString)) {
            event.preventDefault();
            const updated = replaceRange(text, line.start, line.end, line.text.slice(this.indentString.length));
            this.setText(updated);
            setCursor(this.el, Math.max(line.start, cursor - this.indentString.length));
          }
        }
      }

      if (event.key === ' ' && !event.ctrlKey && !event.metaKey && !event.altKey) {
        const sel = window.getSelection();
        if (sel && sel.rangeCount) {
          const range = sel.getRangeAt(0);
          const node = range.startContainer;
          const offset = range.startOffset;
          if (node.nodeType === Node.TEXT_NODE && offset >= 1) {
            const text = node.textContent;
            if (text[offset - 1] === ' ') {
              event.preventDefault();
              document.execCommand('insertText', false, this.indentString);
              this.sync();
            }
          }
        }
      }
    }

    indentSelection() {
      const text = this.getText();
      const selStart = getCursorOffset(this.el);
      const selEnd = selStart; // we only support collapsed selection reliably
      const startLine = getLineInfo(text, selStart);
      const endLine = getLineInfo(text, selEnd);
      const lines = text.split('\n');
      let cursorOffset = selStart;
      for (let i = this.lineIndexAtOffset(text, startLine.start); i <= this.lineIndexAtOffset(text, endLine.start); i++) {
        if ((lines[i].match(/^[ \t]*/)[0].length / this.indentString.length) >= this.maxIndent) continue;
        lines[i] = this.indentString + lines[i];
        if (i === this.lineIndexAtOffset(text, selStart)) cursorOffset += this.indentString.length;
      }
      this.setFromLines(lines, cursorOffset);
    }

    outdentSelection() {
      const text = this.getText();
      const cursor = getCursorOffset(this.el);
      const startLine = getLineInfo(text, cursor);
      const endLine = startLine;
      const lines = text.split('\n');
      let cursorOffset = cursor;
      for (let i = this.lineIndexAtOffset(text, startLine.start); i <= this.lineIndexAtOffset(text, endLine.start); i++) {
        const line = lines[i];
        if (line.startsWith('\t')) {
          lines[i] = line.slice(1);
          if (i === this.lineIndexAtOffset(text, cursor)) cursorOffset = Math.max(0, cursorOffset - 1);
        } else if (line.startsWith('    ')) {
          lines[i] = line.slice(4);
          if (i === this.lineIndexAtOffset(text, cursor)) cursorOffset = Math.max(0, cursorOffset - 4);
        } else if (line.startsWith(' ')) {
          lines[i] = line.slice(1);
          if (i === this.lineIndexAtOffset(text, cursor)) cursorOffset = Math.max(0, cursorOffset - 1);
        }
      }
      this.setFromLines(lines, cursorOffset);
    }

    insertNewline() {
      const text = this.getText();
      const cursor = getCursorOffset(this.el);
      const line = getLineInfo(text, cursor);
      const lead = leadingWhitespace(line.text);
      const bulletMatch = line.text.slice(lead.length).match(/^(- \[[ x]\] |- )/);
      const isWhitespaceOnly = line.text.trim().length === 0;
      if (bulletMatch) {
        const content = line.text.slice(lead.length + bulletMatch[0].length);
        if (content.trim().length === 0) {
          const newLine = lead + content;
          const updated = replaceRange(text, line.start, line.end, newLine);
          this.setText(updated);
          setCursor(this.el, line.start + lead.length);
          return;
        }
        const nextPrefix = bulletMatch[0].startsWith('- [') ? '- [ ] ' : '- ';
        const addition = '\n' + lead + nextPrefix;
        const updated = replaceRange(text, cursor, cursor, addition);
        this.setText(updated);
        setCursor(this.el, cursor + addition.length);
        return;
      }

      const addition = isWhitespaceOnly ? '\n' : ('\n' + lead);
      const updated = replaceRange(text, cursor, cursor, addition);
      this.setText(updated);
      setCursor(this.el, cursor + addition.length);
    }

    toggleBullet() {
      const text = this.getText();
      const cursor = getCursorOffset(this.el);
      const line = getLineInfo(text, cursor);
      const lead = leadingWhitespace(line.text);
      const hasBullet = /^- /.test(line.text.trimStart());
      let replacement;
      if (hasBullet) {
        replacement = line.text.replace(/^[ \t]*-\s+/, lead);
      } else {
        replacement = lead + '- ' + line.text.trimStart();
      }
      const updated = replaceRange(text, line.start, line.end, replacement);
      this.setText(updated);
      const newCursor = line.start + replacement.length;
      setCursor(this.el, newCursor);
    }

    toggleTodo() {
      const text = this.getText();
      const cursor = getCursorOffset(this.el);
      const line = getLineInfo(text, cursor);
      const lead = leadingWhitespace(line.text);
      const trimmed = line.text.trimStart();
      let replacement = line.text;
      if (/^- \[ \]\s/.test(trimmed)) {
        replacement = lead + '- [x] ' + trimmed.slice(6);
      } else if (/^- \[x\]\s/.test(trimmed)) {
        replacement = lead + '- [ ] ' + trimmed.slice(6);
      } else if (/^-\s/.test(trimmed)) {
        replacement = lead + '- [ ] ' + trimmed.replace(/^-\s*/, '');
      } else {
        replacement = lead + '- [ ] ' + trimmed;
      }
      const updated = replaceRange(text, line.start, line.end, replacement);
      this.setText(updated);
      setCursor(this.el, line.start + replacement.length);
    }

    lineIndexAtOffset(text, offset) {
      return text.slice(0, offset).split('\n').length - 1;
    }

    setFromLines(lines, cursorOffset) {
      setTextContent(this.el, lines.join('\n'));
      this.sync();
      if (typeof cursorOffset === 'number') {
        setCursor(this.el, cursorOffset);
      }
    }
  }

  global.NotethingTextWidget = NotethingTextWidget;
})(window);
