const { initTaskDetailsEditor, normalizeNewlines } = require('../task-details');

describe('task details editor behaviors', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div id="detailsInput" class="prism-editor">
        <div contenteditable="true" class="task-details-input"></div>
        <pre class="prism-editor__preview"><code></code></pre>
      </div>
      <input id="detailsField" type="hidden" />
    `;
  });

  test('normalizes newlines and syncs hidden field', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    const editor = initTaskDetailsEditor(details, hidden, saveSpy);

    editable.innerHTML = 'line1\r\nline2\rline3';
    editor.updateDetails();

    expect(hidden.value).toBe('line1\nline2\nline3');
    expect(normalizeNewlines('a\r\nb')).toBe('a\nb');
  });

  test('detects markdown and plain urls and renders anchors', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const hidden = document.getElementById('detailsField');
    initTaskDetailsEditor(details, hidden, jest.fn());

    editable.textContent = 'visit [Docs](https://example.com) or https://open.ai';
    editable.dispatchEvent(new Event('input', { bubbles: true }));

    expect(editable.innerHTML).toContain('<a');
    expect(hidden.value).toBe('visit [Docs](https://example.com) or https://open.ai');
  });

  test('preview hides markdown syntax while keeping link text visible', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const hidden = document.getElementById('detailsField');
    initTaskDetailsEditor(details, hidden, jest.fn());

    editable.textContent = 'find [Docs](https://example.com)';
    editable.dispatchEvent(new Event('input', { bubbles: true }));

    const preview = details.querySelector('code');
    expect(preview.textContent).toContain('find Docs');
    expect(preview.textContent).not.toContain('[');
  });

  test('rendered preview lines stay aligned with editor lines', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const hidden = document.getElementById('detailsField');
    initTaskDetailsEditor(details, hidden, jest.fn());

    editable.textContent = 'This\nThat';
    const preview = details.querySelector('code');

    editable.dispatchEvent(new Event('input', { bubbles: true }));

    expect(preview.innerHTML).toContain('</div><div');
    expect(preview.innerHTML).not.toContain('</div>\n<div');
  });

  test('line coloring ignores leading whitespace and supports milestone/heading/done lines', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const hidden = document.getElementById('detailsField');
    const editor = initTaskDetailsEditor(details, hidden, jest.fn());

    editable.textContent = '  T first task\n\tN note line\n    M milestone\n  # heading\n\tX done\nplain';
    editor.updateDetails();

    const preview = details.querySelector('code');
    const lines = Array.from(preview.querySelectorAll('.code-line'));

    expect(lines[0].classList.contains('code-line-task')).toBe(true);
    expect(lines[1].classList.contains('code-line-note')).toBe(true);
    expect(lines[2].classList.contains('code-line-milestone')).toBe(true);
    expect(lines[3].classList.contains('code-line-heading')).toBe(true);
    expect(lines[4].classList.contains('code-line-done')).toBe(true);
    expect(lines[5].classList.length).toBe(1);
  });

  test('syncing details does not force inline heights', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const preview = details.querySelector('.prism-editor__preview');
    const hidden = document.getElementById('detailsField');

    const editor = initTaskDetailsEditor(details, hidden, jest.fn());

    expect(editable.style.height).toBe('');
    expect(preview.style.height).toBe('');
    expect(details.style.height).toBe('');

    editable.textContent = 'line one\nline two';
    editor.updateDetails();

    expect(editable.style.height).toBe('');
    expect(preview.style.height).toBe('');
    expect(details.style.height).toBe('');
  });

  test('paste inserts plain text and triggers save', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    editable.textContent = 'start';

    const pasteEvent = new Event('paste', { bubbles: true });
    pasteEvent.clipboardData = {
      getData: jest.fn(() => ' paste')
    };
    editable.dispatchEvent(pasteEvent);
    editable.dispatchEvent(new Event('input', { bubbles: true }));

    expect(hidden.value).toBe('start paste');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('caret position is preserved when syncing transformed content', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const hidden = document.getElementById('detailsField');
    initTaskDetailsEditor(details, hidden, jest.fn());

    editable.innerHTML = 'test<div><br></div>';
    editable.focus();
    const range = document.createRange();
    range.setStart(editable.firstChild, 4);
    range.collapse(true);
    const selection = window.getSelection();
    selection.removeAllRanges();
    selection.addRange(range);

    editable.dispatchEvent(new Event('input', { bubbles: true }));

    const updatedSelection = window.getSelection();
    expect(updatedSelection.anchorNode.nodeValue).toContain('test');
    expect(updatedSelection.anchorOffset).toBe(4);
    expect(editable.innerHTML).toContain('<br>');
  });

  test('custom rules and text color settings are applied', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const hidden = document.getElementById('detailsField');
    const rules = [{ prefix: '!', label: 'Important', color: '#FF0000', className: 'custom-important', weight: '700' }];
    const editor = initTaskDetailsEditor(details, hidden, jest.fn(), {
      lineRules: rules,
      textColor: '#123456'
    });

    editable.textContent = '! urgent note';
    editor.updateDetails();

    const previewLine = details.querySelector('.code-line');
    expect(previewLine.classList.contains('custom-important')).toBe(true);
    expect(previewLine.style.getPropertyValue('color')).toBe('rgb(255, 0, 0)');
    expect(previewLine.style.getPropertyValue('font-weight')).toBe('700');
    expect(details.style.getPropertyValue('--details-text-color')).toBe('#123456');
  });

  test('dates matching configured formats render as inline pills', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const hidden = document.getElementById('detailsField');

    const editor = initTaskDetailsEditor(details, hidden, jest.fn(), { dateFormats: ['DD MMM YYYY'] });

    editable.textContent = 'Ship before 31 Dec 2025 and after 1 Jan 2026.';
    editor.updateDetails();

    const preview = details.querySelector('code');
    expect(preview.innerHTML).toContain('<span class="inline-date">31 Dec 2025</span>');
    expect(preview.innerHTML).toContain('<span class="inline-date">1 Jan 2026</span>');
  });

  test('capitalization toggle uppercases lines that match a rule while leaving others untouched', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const hidden = document.getElementById('detailsField');

    const editor = initTaskDetailsEditor(details, hidden, jest.fn(), { capitalizeSentences: true });

    editable.textContent = '  T task\nnote only\nN note';
    editor.updateDetails();

    expect(hidden.value).toBe('  T Task\nnote only\nN Note');
  });

  test('ctrl/cmd click on anchor opens in new window', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const hidden = document.getElementById('detailsField');
    initTaskDetailsEditor(details, hidden, jest.fn());

    editable.textContent = 'Go to https://example.com';
    editable.dispatchEvent(new Event('input', { bubbles: true }));

    const anchor = editable.querySelector('a');
    const openSpy = jest.spyOn(window, 'open').mockImplementation(() => {});
    const clickEvent = new MouseEvent('click', { bubbles: true, metaKey: true });
    anchor.dispatchEvent(clickEvent);

    expect(openSpy).toHaveBeenCalledWith('https://example.com', '_blank', 'noopener');
    openSpy.mockRestore();
  });

  test('context menu on anchor lets user update link text and url', () => {
    const details = document.getElementById('detailsInput');
    const editable = details.querySelector('[contenteditable="true"]');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    editable.textContent = 'See https://example.com';
    editable.dispatchEvent(new Event('input', { bubbles: true }));
    const anchor = editable.querySelector('a');

    const promptSpy = jest.spyOn(window, 'prompt');
    promptSpy.mockImplementationOnce(() => 'Example');
    promptSpy.mockImplementationOnce(() => 'https://example.org');

    const contextEvent = new MouseEvent('contextmenu', { bubbles: true, cancelable: true });
    anchor.dispatchEvent(contextEvent);

    expect(anchor.textContent).toBe('Example');
    expect(anchor.getAttribute('href')).toBe('https://example.org');
    expect(hidden.value).toBe('See [Example](https://example.org)');
    expect(saveSpy).toHaveBeenCalled();
    promptSpy.mockRestore();
  });
});
