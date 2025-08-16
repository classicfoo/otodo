(() => {
  function init() {
    if (!window.dynamicFormattingEnabled) return;
    const el = document.getElementById('detailsEditable');
    if (!el) return;
    const hidden = document.getElementById('detailsInput');
    if (window.dynamicFormattingDebug) {
      console.log('Dynamic formatting initialized');
    }

    function textToHtml(text) {
      const escaped = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
      return escaped.replace(/\n/g, '<br>');
    }

    function htmlToText(html) {
      const sanitized = html.replace(/(?:<br>\s*)+$/, '');
      return sanitized
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&amp;/g, '&');
    }

    function createRangeAtTextOffset(root, offset) {
      const range = document.createRange();
      let remaining = offset;
      const walker = document.createTreeWalker(
        root,
        NodeFilter.SHOW_ALL,
        {
          acceptNode(node) {
            if (node.nodeType === Node.TEXT_NODE && node.nodeValue.length > 0) return NodeFilter.FILTER_ACCEPT;
            if (node.nodeType === Node.ELEMENT_NODE && node.nodeName === 'BR') return NodeFilter.FILTER_ACCEPT;
            return NodeFilter.FILTER_SKIP;
          }
        }
      );
      let node;
      while ((node = walker.nextNode())) {
        if (node.nodeType === Node.TEXT_NODE) {
          const len = node.nodeValue.length;
          if (remaining <= len) {
            range.setStart(node, remaining);
            range.collapse(true);
            return range;
          }
          remaining -= len;
        } else if (node.nodeName === 'BR') {
          if (remaining === 0) {
            range.setStartBefore(node);
            range.collapse(true);
            return range;
          }
          remaining -= 1;
        }
      }
      range.selectNodeContents(root);
      range.collapse(false);
      return range;
    }

    function setCaretFromTextOffset(root, offset) {
      const sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(createRangeAtTextOffset(root, offset));
    }

    function getTextOffsetFromSelection(root) {
      const sel = window.getSelection();
      if (!sel || sel.rangeCount === 0) return 0;
      const range = sel.getRangeAt(0);
      const walker = document.createTreeWalker(
        root,
        NodeFilter.SHOW_ALL,
        {
          acceptNode(node) {
            if (node.nodeType === Node.TEXT_NODE && node.nodeValue.length > 0) return NodeFilter.FILTER_ACCEPT;
            if (node.nodeType === Node.ELEMENT_NODE && node.nodeName === 'BR') return NodeFilter.FILTER_ACCEPT;
            return NodeFilter.FILTER_SKIP;
          }
        }
      );
      let offset = 0;
      let node;
      while ((node = walker.nextNode())) {
        if (node === range.startContainer && node.nodeType === Node.TEXT_NODE) {
          offset += range.startOffset;
          return offset;
        }
        const nodeRange = document.createRange();
        nodeRange.selectNode(node);
        if (range.compareBoundaryPoints(Range.END_TO_START, nodeRange) <= 0) {
          return offset;
        }
        if (node.nodeType === Node.TEXT_NODE) {
          offset += node.nodeValue.length;
        } else if (node.nodeName === 'BR') {
          offset += 1;
        }
      }
      return offset;
    }

    function capitalizeFirst(str) {
      return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function render(text) {
      const lines = text.split('\n');
      const formatted = lines.map(line => {
        if (line.startsWith('T ')) {
          const rest = capitalizeFirst(line.slice(2));
          return `<span style="color:blue;">${textToHtml(`T ${rest}`)}</span>`;
        }
        return textToHtml(line ? capitalizeFirst(line) : '');
      });
      return formatted.join('<br>');
    }

    let updating = false;

    function update() {
      if (updating) return;
      updating = true;
      const caret = getTextOffsetFromSelection(el);
      const rawHtml = el.innerHTML.replace(/<\/?span[^>]*>/g, '');
      const text = htmlToText(rawHtml);
      const html = render(text);
      if (el.innerHTML !== html) {
        el.innerHTML = html;
      }
      const clamped = Math.max(0, Math.min(text.length, caret));
      setCaretFromTextOffset(el, clamped);
      if (hidden) hidden.value = text;
      if (window.dynamicFormattingDebug) {
        console.log('update', { text, html, caret: clamped });
      }
      updating = false;
    }

    el.addEventListener('input', update);
    update();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();

