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
