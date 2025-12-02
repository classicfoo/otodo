(function(global){
  'use strict';

  function normalizeText(text) {
    return (text || '')
      .replace(/\r\n?/g, '\n')
      .replace(/\u00a0/g, ' ');
  }

  function getLineInfo(root) {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return null;
    const range = sel.getRangeAt(0);
    const pre = range.cloneRange();
    pre.selectNodeContents(root);
    pre.setEnd(range.startContainer, range.startOffset);
    const textBefore = pre.toString();
    const start = textBefore.lastIndexOf('\n') + 1;

    const post = range.cloneRange();
    post.selectNodeContents(root);
    post.setStart(range.endContainer, range.endOffset);
    const textAfter = post.toString();
    const endOffset = textBefore.length + (range.endContainer === range.startContainer ? range.endOffset - range.startOffset : range.endOffset);
    const lineEnd = textBefore.length + textAfter.indexOf('\n');
    const end = lineEnd >= textBefore.length ? lineEnd : textBefore.length + post.toString().length;

    return {
      start,
      end,
      textBefore,
      textAfter,
      range
    };
  }

  function setCursor(root, offset) {
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null);
    let currentOffset = 0;
    let node;
    while ((node = walker.nextNode())) {
      const nextOffset = currentOffset + node.textContent.length;
      if (nextOffset >= offset) {
        const position = offset - currentOffset;
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
    // Fallback to end
    const range = document.createRange();
    range.selectNodeContents(root);
    range.collapse(false);
    const sel = window.getSelection();
    if (sel) {
      sel.removeAllRanges();
      sel.addRange(range);
    }
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

      const initial = options.value !== undefined ? options.value : this.hiddenInput ? this.hiddenInput.value : this.el.innerText;
      this.setText(initial);

      this.el.addEventListener('input', () => this.handleInput());
      this.el.addEventListener('keydown', (e) => this.handleKeydown(e));
      this.el.addEventListener('paste', (e) => this.handlePaste(e));
      this.el.addEventListener('blur', () => this.sync());

      if (this.autoFocus) {
        this.el.focus();
      }

      this.sync();
    }

    getText() {
      return normalizeText(this.el.innerText);
    }

    setText(value) {
      const normalized = normalizeText(value);
      this.el.innerText = normalized;
      this.sync();
    }

    sync() {
      if (this.hiddenInput) {
        this.hiddenInput.value = this.getText();
      }
      if (this.onChange) this.onChange(this.getText());
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
        this.insertNewlineWithIndent();
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
      const info = getLineInfo(this.el);
      if (!info) return;
      const lines = this.getText().split('\n');
      const before = lines.slice(0, 0);
      const startLine = this.getText().slice(0, info.start).split('\n').length - 1;
      const endLine = this.getText().slice(0, info.end).split('\n').length - 1;
      let cursorOffset = this.getCursorOffset();
      for (let i = startLine; i <= endLine; i++) {
        const line = lines[i] || '';
        const indentCount = line.match(/^[\t ]*/)[0].length;
        if (indentCount / this.indentString.length >= this.maxIndent) continue;
        lines[i] = this.indentString + line;
        if (i === startLine) cursorOffset += this.indentString.length;
      }
      this.setFromLines(lines, cursorOffset);
    }

    outdentSelection() {
      const info = getLineInfo(this.el);
      if (!info) return;
      const lines = this.getText().split('\n');
      const startLine = this.getText().slice(0, info.start).split('\n').length - 1;
      const endLine = this.getText().slice(0, info.end).split('\n').length - 1;
      let cursorOffset = this.getCursorOffset();
      for (let i = startLine; i <= endLine; i++) {
        const line = lines[i] || '';
        if (line.startsWith('\t')) {
          lines[i] = line.slice(1);
          if (i === startLine) cursorOffset = Math.max(0, cursorOffset - 1);
        } else if (line.startsWith('    ')) {
          lines[i] = line.slice(4);
          if (i === startLine) cursorOffset = Math.max(0, cursorOffset - 4);
        } else if (line.startsWith(' ')) {
          lines[i] = line.slice(1);
          if (i === startLine) cursorOffset = Math.max(0, cursorOffset - 1);
        }
      }
      this.setFromLines(lines, cursorOffset);
    }

    insertNewlineWithIndent() {
      const info = getLineInfo(this.el);
      const text = this.getText();
      const before = text.slice(0, info ? info.start : 0);
      const currentLine = text.slice(info ? info.start : 0, info ? info.end : text.length);
      const leading = currentLine.match(/^[\t ]*/)[0];
      const addition = '\n' + leading;
      document.execCommand('insertText', false, addition);
      this.sync();
    }

    toggleBullet() {
      const info = getLineInfo(this.el);
      if (!info) return;
      const text = this.getText();
      const lines = text.split('\n');
      const idx = text.slice(0, info.start).split('\n').length - 1;
      const line = lines[idx] || '';
      if (line.trim().startsWith('- ')) {
        lines[idx] = line.replace(/^[\t ]*-\s+/, '');
      } else {
        lines[idx] = line.replace(/^[\t ]*/, match => match) + '- ' + line.trimStart();
      }
      const cursorOffset = lines.slice(0, idx).join('\n').length + (idx > 0 ? 1 : 0) + lines[idx].length;
      this.setFromLines(lines, cursorOffset);
    }

    toggleTodo() {
      const info = getLineInfo(this.el);
      if (!info) return;
      const text = this.getText();
      const lines = text.split('\n');
      const idx = text.slice(0, info.start).split('\n').length - 1;
      const line = lines[idx] || '';
      const trimmed = line.trimStart();
      const leading = line.slice(0, line.length - trimmed.length);
      if (/^- \[ \]\s/.test(trimmed)) {
        lines[idx] = leading + '- [x] ' + trimmed.slice(6);
      } else if (/^- \[x\]\s/.test(trimmed)) {
        lines[idx] = leading + '- [ ] ' + trimmed.slice(6);
      } else {
        lines[idx] = leading + '- [ ] ' + trimmed;
      }
      const cursorOffset = lines.slice(0, idx).join('\n').length + (idx > 0 ? 1 : 0) + lines[idx].length;
      this.setFromLines(lines, cursorOffset);
    }

    setFromLines(lines, cursorOffset) {
      this.el.innerText = lines.join('\n');
      this.sync();
      if (typeof cursorOffset === 'number') {
        setCursor(this.el, cursorOffset);
      }
    }

    getCursorOffset() {
      const sel = window.getSelection();
      if (!sel || sel.rangeCount === 0) return 0;
      const range = sel.getRangeAt(0);
      const pre = range.cloneRange();
      pre.selectNodeContents(this.el);
      pre.setEnd(range.endContainer, range.endOffset);
      return pre.toString().length;
    }
  }

  global.NotethingTextWidget = NotethingTextWidget;
})(window);
