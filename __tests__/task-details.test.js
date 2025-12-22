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

  test('tab indents all selected lines and preserves selection range', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    textarea.value = 'alpha\nbeta\ngamma';
    textarea.selectionStart = 0;
    textarea.selectionEnd = 'alpha\nbeta'.length;

    const event = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true });
    textarea.dispatchEvent(event);

    expect(textarea.value).toBe('\talpha\n\tbeta\ngamma');
    expect(hidden.value).toBe('\talpha\n\tbeta\ngamma');
    expect(textarea.selectionStart).toBe(1);
    expect(textarea.selectionEnd).toBe('alpha\nbeta'.length + 2);
    expect(saveSpy).toHaveBeenCalled();
  });

  test('shift+tab outdents selected lines and keeps selection', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    textarea.value = '\talpha\n\tbeta\ngamma';
    textarea.selectionStart = 1;
    textarea.selectionEnd = '\talpha\n\tbeta'.length + 1;

    const event = new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true });
    textarea.dispatchEvent(event);

    expect(textarea.value).toBe('alpha\nbeta\ngamma');
    expect(hidden.value).toBe('alpha\nbeta\ngamma');
    expect(textarea.selectionStart).toBe(0);
    expect(textarea.selectionEnd).toBe('alpha\nbeta'.length + 1);
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

  test('home key jumps to start of visible text first', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    textarea.value = '    spaced line';
    textarea.selectionStart = textarea.selectionEnd = textarea.value.length;

    const event = new KeyboardEvent('keydown', { key: 'Home', bubbles: true });
    textarea.dispatchEvent(event);

    expect(textarea.selectionStart).toBe(4);
    expect(textarea.selectionEnd).toBe(4);
    expect(saveSpy).not.toHaveBeenCalled();
  });

  test('shift+home selects back to the start of visible text first', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    initTaskDetailsEditor(details, hidden, jest.fn());

    textarea.value = '\t  abcd efgh';
    textarea.selectionStart = textarea.selectionEnd = 11;

    const event = new KeyboardEvent('keydown', { key: 'Home', shiftKey: true, bubbles: true });
    textarea.dispatchEvent(event);

    expect(textarea.selectionStart).toBe(3);
    expect(textarea.selectionEnd).toBe(11);
  });

  test('home toggles between visible text start and line start', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    initTaskDetailsEditor(details, hidden, jest.fn());

    textarea.value = '    abcd';
    textarea.selectionStart = textarea.selectionEnd = 4;

    const firstPress = new KeyboardEvent('keydown', { key: 'Home', bubbles: true });
    textarea.dispatchEvent(firstPress);

    expect(textarea.selectionStart).toBe(0);
    expect(textarea.selectionEnd).toBe(0);

    textarea.selectionStart = textarea.selectionEnd = 0;

    const secondPress = new KeyboardEvent('keydown', { key: 'Home', bubbles: true });
    textarea.dispatchEvent(secondPress);

    expect(textarea.selectionStart).toBe(4);
    expect(textarea.selectionEnd).toBe(4);
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

  test('line coloring ignores leading whitespace and supports milestone/heading/done lines', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    const editor = initTaskDetailsEditor(details, hidden, jest.fn());

    textarea.value = '  T first task\n\tN note line\n    M milestone\n  # heading\n\tX done\nplain';
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

  test('custom rules and text color settings are applied', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    const rules = [{ prefix: '!', label: 'Important', color: '#FF0000', className: 'custom-important', weight: '700' }];
    const editor = initTaskDetailsEditor(details, hidden, jest.fn(), {
      lineRules: rules,
      textColor: '#123456'
    });

    textarea.value = '! urgent note';
    editor.updateDetails();

    const previewLine = details.querySelector('.code-line');
    expect(previewLine.classList.contains('custom-important')).toBe(true);
    expect(previewLine.style.getPropertyValue('color')).toBe('rgb(255, 0, 0)');
    expect(previewLine.style.getPropertyValue('font-weight')).toBe('700');
    expect(details.style.getPropertyValue('--details-text-color')).toBe('#123456');
  });

  test('dates matching configured formats render as inline pills', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');

    const editor = initTaskDetailsEditor(details, hidden, jest.fn(), { dateFormats: ['DD MMM YYYY'] });

    textarea.value = 'Ship before 31 Dec 2025 and after 1 Jan 2026.';
    editor.updateDetails();

    const preview = details.querySelector('code');
    expect(preview.innerHTML).toContain('<span class="inline-date">31 Dec 2025</span>');
    expect(preview.innerHTML).toContain('<span class="inline-date">1 Jan 2026</span>');
  });

  test('links render with hyperlink controls and support editing', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();

    const editor = initTaskDetailsEditor(details, hidden, saveSpy);

    textarea.value = 'Visit https://example.com for details';
    editor.updateDetails();

    const link = details.querySelector('.details-link');
    expect(link).not.toBeNull();
    expect(link.textContent).toBe('https://example.com');

    const openSpy = jest.spyOn(window, 'open').mockImplementation(() => {});
    link.dispatchEvent(new MouseEvent('click', { bubbles: true, ctrlKey: true }));
    expect(openSpy).toHaveBeenCalledWith('https://example.com', '_blank', 'noopener,noreferrer');
    openSpy.mockRestore();

    link.dispatchEvent(new MouseEvent('click', { bubbles: true }));
    expect(textarea.selectionStart).toBe(textarea.value.indexOf('https://example.com'));
    expect(textarea.selectionEnd).toBe(textarea.selectionStart + 'https://example.com'.length);

    link.dispatchEvent(new MouseEvent('contextmenu', { bubbles: true }));
    const menuButton = details.querySelector('.link-edit-trigger');
    expect(menuButton).not.toBeNull();
    menuButton.click();

    const textInput = details.querySelector('.link-text-input');
    const urlInput = details.querySelector('.link-url-input');
    expect(textInput).not.toBeNull();
    expect(urlInput).not.toBeNull();

    textInput.value = 'Docs';
    urlInput.value = 'docs.example.com';
    const form = details.querySelector('.link-edit-form');
    form.dispatchEvent(new Event('submit', { bubbles: true }));

    expect(textarea.value).toContain('[Docs](http://docs.example.com)');
    expect(hidden.value).toContain('[Docs](http://docs.example.com)');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('capitalization toggle uppercases lines that match a rule while leaving others untouched', () => {
    const details = document.getElementById('detailsInput');
    const textarea = details.querySelector('textarea');
    const hidden = document.getElementById('detailsField');

    const editor = initTaskDetailsEditor(details, hidden, jest.fn(), { capitalizeSentences: true });

    textarea.value = '  T task\nnote only\nN note';
    editor.updateDetails();

    expect(textarea.value).toBe('  T Task\nnote only\nN Note');
    expect(hidden.value).toBe('  T Task\nnote only\nN Note');
  });
});
