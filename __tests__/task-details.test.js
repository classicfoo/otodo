const { initTaskDetailsEditor, normalizeNewlines } = require('../task-details');

describe('task details editor behaviors', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div id="detailsInput" class="prism-editor">
        <textarea></textarea>
        <pre class="prism-editor__preview"><code></code></pre>
      </div>
      <input id="detailsField" type="hidden" />
    `;
  });

  test('normalizes newlines and syncs hidden field', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    const editor = initTaskDetailsEditor(details, hidden, saveSpy);

    textarea.value = 'line1\r\nline2\rline3';
    editor.updateDetails();

    expect(hidden.value).toBe('line1\nline2\nline3');
    expect(normalizeNewlines('a\r\nb')).toBe('a\nb');
  });

  test('tab key inserts tab character and schedules save', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    textarea.value = 'task';
    textarea.selectionStart = textarea.selectionEnd = textarea.value.length;

    const event = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true });
    textarea.dispatchEvent(event);

    expect(textarea.value).toBe('task\t');
    expect(hidden.value).toBe('task\t');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('double space turns into a tab', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    textarea.value = 'do ';
    textarea.selectionStart = textarea.selectionEnd = 3;

    const event = new KeyboardEvent('keydown', { key: ' ', bubbles: true });
    textarea.dispatchEvent(event);

    expect(textarea.value).toBe('do\t');
    expect(hidden.value).toBe('do\t');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('enter key preserves indentation on new line', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    textarea.value = '    indented';
    textarea.selectionStart = textarea.selectionEnd = textarea.value.length;

    const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true });
    textarea.dispatchEvent(event);

    expect(textarea.value).toBe('    indented\n    ');
    expect(hidden.value).toBe('    indented\n    ');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('rendered preview lines stay aligned with textarea lines', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    initTaskDetailsEditor(details, hidden, jest.fn());

    textarea.value = 'This\nThat';
    const preview = details.querySelector('code');

    // Trigger render
    textarea.dispatchEvent(new Event('input', { bubbles: true }));

    expect(preview.innerHTML).toContain('</div><div');
    expect(preview.innerHTML).not.toContain('</div>\n<div');
  });

  test('line coloring ignores leading whitespace and supports milestone lines', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    const editor = initTaskDetailsEditor(details, hidden, jest.fn());

    textarea.value = '  T first task\n\tN note line\n    M milestone\nplain';
    editor.updateDetails();

    const preview = details.querySelector('code');
    const lines = Array.from(preview.querySelectorAll('.code-line'));

    expect(lines[0].classList.contains('code-line-task')).toBe(true);
    expect(lines[1].classList.contains('code-line-note')).toBe(true);
    expect(lines[2].classList.contains('code-line-milestone')).toBe(true);
    expect(lines[3].classList.length).toBe(1);
  });

  test('syncing details does not force inline heights', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const preview = details.querySelector('.prism-editor__preview');
    const hidden = document.getElementById('detailsField');

    const editor = initTaskDetailsEditor(details, hidden, jest.fn());

    expect(textarea.style.height).toBe('');
    expect(preview.style.height).toBe('');
    expect(details.style.height).toBe('');

    textarea.value = 'line one\nline two';
    editor.updateDetails();

    expect(textarea.style.height).toBe('');
    expect(preview.style.height).toBe('');
    expect(details.style.height).toBe('');
  });

  test('paste inserts plain text and triggers save', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    textarea.value = 'start';
    textarea.selectionStart = textarea.selectionEnd = textarea.value.length;

    const pasteEvent = new Event('paste', { bubbles: true });
    pasteEvent.clipboardData = {
      getData: jest.fn(() => ' paste')
    };
    details.querySelector('textarea').dispatchEvent(pasteEvent);

    expect(textarea.value).toBe('start paste');
    expect(hidden.value).toBe('start paste');
    expect(saveSpy).toHaveBeenCalled();
  });
});
