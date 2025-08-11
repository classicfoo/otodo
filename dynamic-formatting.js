(() => {
  function init() {
    if (!window.dynamicFormattingEnabled) return;
    const el = document.getElementById('detailsEditable');
    if (!el) return;
    const hidden = document.getElementById('detailsInput');

    function capitalizeFirst(str) {
      return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function getCaret(root) {
      const sel = window.getSelection();
      if (!sel || sel.rangeCount === 0) return 0;
      const range = sel.getRangeAt(0);
      let caret = 0;
      function traverse(node) {
        if (node === range.endContainer) {
          caret += range.endOffset;
          return true;
        }
        if (node.nodeType === Node.TEXT_NODE) {
          caret += node.textContent.length;
        } else {
          for (const child of node.childNodes) {
            if (traverse(child)) return true;
          }
          if (node.nodeName === 'BR' || (node.nodeName === 'DIV' && node !== root)) {
            caret += 1;
          }
        }
        return false;
      }
      traverse(root);
      return caret;
    }

    function setCaret(root, pos) {
      const selection = window.getSelection();
      const range = document.createRange();
      let current = 0;
      function traverse(node) {
        if (node.nodeType === Node.TEXT_NODE) {
          const next = current + node.textContent.length;
          if (pos <= next) {
            range.setStart(node, pos - current);
            return true;
          }
          current = next;
        } else if (node.nodeName === 'BR') {
          if (pos <= current + 1) {
            range.setStartAfter(node);
            return true;
          }
          current += 1;
        } else {
          for (const child of node.childNodes) {
            if (traverse(child)) return true;
          }
        }
        return false;
      }
      traverse(root);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
    }

      let updating = false;

      function update() {
        if (updating) return;
        updating = true;
        const caret = getCaret(el);
        let text = el.innerText;
        const trailing = text.endsWith('\n');
        text = text.replace(/\n$/, '');
        const lines = text.split(/\n/);

        const formatted = lines.map(line => {
          if (line.startsWith('T ')) {
            const rest = capitalizeFirst(line.slice(2));
            return `<span style="color:blue;">T ${rest}</span>`;
          }
          return line ? capitalizeFirst(line) : '';
        });
        let html = formatted.join('<br>');
        if (trailing) html += '<br>';

        if (el.innerHTML !== html) {
          el.innerHTML = html;
          setTimeout(() => {
            setCaret(el, caret);
            updating = false;
          }, 0);
        } else {
          updating = false;
        }
        if (hidden) hidden.value = text;
      }

    el.addEventListener('input', (e) => {
      if (e.inputType === 'deleteContentBackward' || e.inputType === 'deleteContentForward') {
        if (hidden) {
          const t = el.innerText.replace(/\n$/, '');
          hidden.value = t;
        }
        return;
      }
      update();
    });
    update();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
