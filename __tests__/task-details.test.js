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

  test('textarea height follows preview content and respects a single-line minimum', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const preview = details.querySelector('.prism-editor__preview');
    const hidden = document.getElementById('detailsField');

    let mockHeight = 12;
    Object.defineProperty(preview, 'scrollHeight', {
      get: () => mockHeight,
    });

    const editor = initTaskDetailsEditor(details, hidden, jest.fn());

    expect(textarea.style.height).toBe('16px');
    expect(preview.style.height).toBe('16px');
    expect(details.style.height).toBe('16px');

    textarea.value = 'line one\nline two';
    mockHeight = 48;
    editor.updateDetails();

    expect(textarea.style.height).toBe('48px');
    expect(preview.style.height).toBe('48px');
    expect(details.style.height).toBe('48px');
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
