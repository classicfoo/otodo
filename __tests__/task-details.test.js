const { initTaskDetailsEditor, normalizeNewlines } = require('../task-details');

describe('task details editor behaviors', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <textarea id="detailsInput"></textarea>
    `;
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

  test('input events sync content and schedule saves', () => {
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsInput');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    details.value = 'first line';
    details.dispatchEvent(new Event('input'));

    expect(hidden.value).toBe('first line');
    expect(saveSpy).toHaveBeenCalled();
  });

  test('paste events normalize the content', () => {
    jest.useFakeTimers();
    const details = document.getElementById('detailsInput');
    const hidden = document.getElementById('detailsInput');
    const saveSpy = jest.fn();
    initTaskDetailsEditor(details, hidden, saveSpy);

    details.value = 'line1\r\nline2';
    details.dispatchEvent(new Event('paste'));
    jest.runAllTimers();

    expect(hidden.value).toBe('line1\nline2');
    expect(saveSpy).toHaveBeenCalled();
  });
});
