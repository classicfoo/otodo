document.addEventListener('DOMContentLoaded', () => {
  if (!window.dynamicFormattingEnabled) return;
  const el = document.getElementById('detailsEditable');
  if (!el) return;

  function capitalizeFirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  }

  function getCaret(root) {
    const sel = window.getSelection();
    if (!sel || sel.rangeCount === 0) return 0;
    const range = sel.getRangeAt(0).cloneRange();
    range.selectNodeContents(root);
    range.setEnd(sel.getRangeAt(0).endContainer, sel.getRangeAt(0).endOffset);
    return range.toString().length;
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

  function update() {
    const caret = getCaret(el);
    const lines = el.innerText.split(/\n/);
    const formatted = lines.map(line => {
      if (line.startsWith('T ')) {
        const rest = capitalizeFirst(line.slice(2));
        return `<span style="color:blue;">T ${rest}</span>`;
      }
      return line ? capitalizeFirst(line) : '';
    });
    const html = formatted.join('<br>');
    if (el.innerHTML !== html) {
      el.innerHTML = html;
      setCaret(el, caret);
    }
  }

  el.addEventListener('input', update);
  update();
});
