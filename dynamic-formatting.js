document.addEventListener('DOMContentLoaded', () => {
  if (!window.dynamicFormattingEnabled) return;
  document.querySelectorAll('input[name="description"]').forEach((el) => {
    const update = () => {
      const val = el.value;
      if (val.startsWith('T ')) {
        el.style.color = 'blue';
        if (val.length > 2) {
          const capital = val.charAt(2).toUpperCase();
          const rest = val.slice(3);
          const newVal = 'T ' + capital + rest;
          if (newVal !== val) {
            const pos = el.selectionStart;
            el.value = newVal;
            el.setSelectionRange(pos, pos);
          }
        }
      } else {
        el.style.color = '';
      }
    };
    el.addEventListener('input', update);
    update();
  });
});

