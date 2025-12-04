const { initTaskDetailsEditor, normalizeNewlines } = require('../task-details');

function setCaretAtEnd(node) {
  const range = document.createRange();
  range.selectNodeContents(node);
  range.collapse(false);
  const selection = window.getSelection();
  selection.removeAllRanges();
  selection.addRange(range);
}

function setCaret(node, offset) {
  const range = document.createRange();
  range.setStart(node, offset);
  range.collapse(true);
  const selection = window.getSelection();
  selection.removeAllRanges();
  selection.addRange(range);
}

describe('task details editor behaviors', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <div id="detailsInput" contenteditable="true"></div>
      <input id="detailsField" type="hidden" />
    `;

    document.execCommand = jest.fn((command, _ui, value) => {
      if (command !== 'insertText') return false;
      const selection = window.getSelection();
      if (!selection || selection.rangeCount === 0) return false;
      const range = selection.getRangeAt(0);
      range.deleteContents();
      const textNode = document.createTextNode(value);
      range.insertNode(textNode);
      range.setStart(textNode, textNode.length);
      range.collapse(true);
      selection.removeAllRanges();
      selection.addRange(range);
      return true;
    });
  });

  afterEach(() => {
    jest.restoreAllMocks();
  });

  test('normalizes newlines and syncs hidden field', () => {
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    const editor = initTaskDetailsEditor(details, hidden, saveSpy);

    details.textContent = 'line1\r\nline2\rline3';
    editor.updateDetails();

    expect(hidden.value).toBe('line1\nline2\nline3');
    expect(normalizeNewlines('a\r\nb')).toBe('a\nb');
  });

  test('tab key inserts tab character and schedules save', () => {
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    details.textContent = 'task';
    setCaretAtEnd(details.firstChild);

    const event = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true });
    details.dispatchEvent(event);

    expect(details.textContent).toBe('task\t');
    expect(hidden.value).toBe('task\t');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('double space turns into a tab', () => {
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    details.textContent = 'do ';
    setCaret(details.firstChild, 3);

    const event = new KeyboardEvent('keydown', { key: ' ', bubbles: true });
    details.dispatchEvent(event);

    expect(details.textContent).toBe('do\t');
    expect(hidden.value).toBe('do\t');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('enter key inserts newline on first press and preserves indentation', () => {
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    details.textContent = '    indented';
    setCaretAtEnd(details.firstChild);

    const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true });
    details.dispatchEvent(event);

    expect(details.textContent).toBe('    indented\n    ');
    expect(hidden.value).toBe('    indented\n    ');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('enter key inserts newline on first press when text exists', () => {
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    details.textContent = 'test';
    setCaretAtEnd(details.firstChild);

    const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true });
    details.dispatchEvent(event);

    expect(details.textContent).toBe('test\n');
    expect(hidden.value).toBe('test\n');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('paste inserts plain text and triggers save', () => {
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    details.textContent = 'start';
    setCaretAtEnd(details.firstChild);

    const pasteEvent = new Event('paste', { bubbles: true });
    pasteEvent.clipboardData = {
      getData: jest.fn(() => ' paste')
    };
    details.dispatchEvent(pasteEvent);

    expect(details.textContent).toBe('start paste');
    expect(hidden.value).toBe('start paste');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('falls back when execCommand reports success but content is unchanged', () => {
    const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsField');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    document.execCommand = jest.fn(() => true);

    details.textContent = 'line';
    setCaretAtEnd(details.firstChild);

    const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true });
    details.dispatchEvent(event);

    expect(details.textContent).toBe('line\n');
    expect(hidden.value).toBe('line\n');
    expect(warnSpy).toHaveBeenCalledWith(expect.stringContaining('execCommand reported success'));
  });
});
