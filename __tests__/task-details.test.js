const { initTaskDetailsEditor, normalizeNewlines } = require('../task-details');

function setCaretAtEnd(node) {
  node.selectionStart = node.value.length;
  node.selectionEnd = node.value.length;
}

function setCaret(node, offset) {
  node.selectionStart = offset;
  node.selectionEnd = offset;
}

describe('task details editor behaviors', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <textarea id="detailsInput"></textarea>
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

  test('normalizes newlines and syncs hidden field', () => {
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsInput');
    const saveSpy = jest.fn();
    const editor = initTaskDetailsEditor(details, hidden, saveSpy);

    details.value = 'line1\r\nline2\rline3';
    editor.updateDetails();

    expect(hidden.value).toBe('line1\nline2\nline3');
    expect(normalizeNewlines('a\r\nb')).toBe('a\nb');
  });

  test('tab key inserts tab character and schedules save', () => {
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsInput');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    details.value = 'task';
    setCaretAtEnd(details);

    const event = new KeyboardEvent('keydown', { key: 'Tab', bubbles: true });
    details.dispatchEvent(event);

    expect(details.value).toBe('task\t');
    expect(hidden.value).toBe('task\t');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('double space turns into a tab', () => {
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsInput');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    details.value = 'do ';
    setCaret(details, 3);

    const event = new KeyboardEvent('keydown', { key: ' ', bubbles: true });
    details.dispatchEvent(event);

    expect(details.value).toBe('do\t');
    expect(hidden.value).toBe('do\t');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('enter key preserves indentation on new line', () => {
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsInput');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    details.value = '    indented';
    setCaretAtEnd(details);

    const event = new KeyboardEvent('keydown', { key: 'Enter', bubbles: true });
    details.dispatchEvent(event);

    expect(details.value).toBe('    indented\n    ');
    expect(hidden.value).toBe('    indented\n    ');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('paste inserts plain text and triggers save', () => {
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsInput');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    details.value = 'start';
    setCaretAtEnd(details);

    const pasteEvent = new Event('paste', { bubbles: true });
    pasteEvent.clipboardData = {
      getData: jest.fn(() => ' paste')
    };
    details.dispatchEvent(pasteEvent);

    expect(details.value).toBe('start paste');
    expect(hidden.value).toBe('start paste');
    expect(saveSpy).toHaveBeenCalled();
  });
});
