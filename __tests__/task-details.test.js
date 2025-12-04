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
  let details;
  let hidden;
  let saveSpy;
  let editor;

  beforeEach(() => {
    document.body.innerHTML = `
    <div id="detailsInput" contenteditable="true"></div>
    <input id="detailsField" type="hidden" />
    `;

    // jsdom stub for insertText used by the editor
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

    details = document.getElementById('detailsInput');
    hidden = document.getElementById('detailsField');
    saveSpy = jest.fn();

    // initialise listeners once per test
    editor = initTaskDetailsEditor(details, hidden, saveSpy);
  });

  test('normalizes newlines and syncs hidden field', () => {
    details.innerText = 'line1\r\nline2\rline3';

    editor.updateDetails();

    expect(hidden.value).toBe('line1\nline2\nline3');
    expect(normalizeNewlines('a\r\nb')).toBe('a\nb');
  });

  test('tab key inserts tab character and schedules save', () => {
    details.textContent = 'task';
    setCaretAtEnd(details.firstChild);

    const event = new KeyboardEvent('keydown', {
      key: 'Tab',
      bubbles: true,
      cancelable: true,
    });
    details.dispatchEvent(event);

    // ensure hidden field is synced after key handling
    editor.updateDetails();

    expect(details.textContent).toBe('task\t');
    expect(hidden.value).toBe('task\t');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('double space turns into a tab', () => {
    details.textContent = 'do ';
    setCaret(details.firstChild, 3);

    const event = new KeyboardEvent('keydown', {
      key: ' ',
      bubbles: true,
      cancelable: true,
    });
    details.dispatchEvent(event);

    editor.updateDetails();

    expect(details.textContent).toBe('do\t');
    expect(hidden.value).toBe('do\t');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('enter key preserves indentation on new line', () => {
    details.textContent = '    indented';
    setCaretAtEnd(details.firstChild);

    const event = new KeyboardEvent('keydown', {
      key: 'Enter',
      bubbles: true,
      cancelable: true,
    });
    details.dispatchEvent(event);

    editor.updateDetails();

    expect(details.textContent).toBe('    indented\n    ');
    expect(hidden.value).toBe('    indented\n    ');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('paste inserts plain text and triggers save', () => {
    details.textContent = 'start';
    setCaretAtEnd(details.firstChild);

    const pasteEvent = new Event('paste', { bubbles: true, cancelable: true });
    pasteEvent.clipboardData = {
      getData: jest.fn(() => ' paste'),
    };
    details.dispatchEvent(pasteEvent);

    editor.updateDetails();

    expect(details.textContent).toBe('start paste');
    expect(hidden.value).toBe('start paste');
    expect(saveSpy).toHaveBeenCalled();
  });
});
