<?php
require_once 'db.php';
require_once 'line_rules.php';
require_once 'hashtags.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare('SELECT id, description, due_date, details, done, priority, starred FROM tasks WHERE id = :id AND user_id = :uid');
$stmt->execute([':id' => $id, ':uid' => $_SESSION['user_id']]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task) {
    header('Location: index.php');
    exit();
}

$task_hashtags = get_task_hashtags($db, (int)$task['id'], (int)$_SESSION['user_id']);
$user_hashtags = get_user_hashtags($db, (int)$_SESSION['user_id']);

$ordered_stmt = $db->prepare('SELECT id FROM tasks WHERE user_id = :uid AND done = 0 ORDER BY starred DESC, due_date IS NULL, due_date, priority DESC, id DESC');
$ordered_stmt->execute([':uid' => $_SESSION['user_id']]);
$ordered_ids = $ordered_stmt->fetchAll(PDO::FETCH_COLUMN);
$next_task_id = null;
$current_task_id = (int)$task['id'];
$found_current = false;
foreach ($ordered_ids as $ordered_id) {
    $ordered_id = (int)$ordered_id;
    if ($found_current) {
        $next_task_id = $ordered_id;
        break;
    }
    if ($ordered_id === $current_task_id) {
        $found_current = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = ucwords(strtolower(trim($_POST['description'] ?? '')));
    $due_date = trim($_POST['due_date'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $priority = (int)($_POST['priority'] ?? 0);
    if ($priority < 0 || $priority > 3) {
        $priority = 0;
    }
    $done = isset($_POST['done']) ? 1 : 0;
    $starred = isset($_POST['starred']) ? 1 : 0;
    $stmt = $db->prepare('UPDATE tasks SET description = :description, due_date = :due_date, details = :details, priority = :priority, done = :done, starred = :starred WHERE id = :id AND user_id = :uid');
    $stmt->execute([
        ':description' => $description,
        ':due_date' => $due_date !== '' ? $due_date : null,
        ':details' => $details !== '' ? $details : null,
        ':priority' => $priority,
        ':done' => $done,
        ':starred' => $starred,
        ':id' => $id,
        ':uid' => $_SESSION['user_id'],
    ]);
    $hashtags = collect_hashtags_from_texts($description, $details);
    sync_task_hashtags($db, $id, (int)$_SESSION['user_id'], $hashtags);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok', 'hashtags' => $hashtags]);
    exit();
}

$priority_classes = [
    0 => 'bg-secondary-subtle text-secondary',
    1 => 'bg-success-subtle text-success',
    2 => 'bg-warning-subtle text-warning',
    3 => 'bg-danger-subtle text-danger'
];
$p = (int)($task['priority'] ?? 0);
if ($p < 0 || $p > 3) { $p = 0; }
$line_rules = $_SESSION['line_rules'] ?? get_default_line_rules();
$details_color = normalize_editor_color($_SESSION['details_color'] ?? '#212529');
$capitalize_sentences = isset($_SESSION['capitalize_sentences']) ? (bool)$_SESSION['capitalize_sentences'] : true;
$line_rules_json = htmlspecialchars(json_encode($line_rules));
$details_color_attr = htmlspecialchars($details_color);
$capitalize_sentences_attr = $capitalize_sentences ? 'true' : 'false';
$task_hashtags_json = json_encode($task_hashtags);
$user_hashtags_json = json_encode($user_hashtags);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        #prioritySelect option.bg-secondary-subtle:hover,
        #prioritySelect option.bg-secondary-subtle:focus,
        #prioritySelect option.bg-secondary-subtle:active {
            background-color: var(--bs-secondary-bg-subtle) !important;
            color: var(--bs-secondary-text-emphasis) !important;
        }
        #prioritySelect option.bg-success-subtle:hover,
        #prioritySelect option.bg-success-subtle:focus,
        #prioritySelect option.bg-success-subtle:active {
            background-color: var(--bs-success-bg-subtle) !important;
            color: var(--bs-success-text-emphasis) !important;
        }
        #prioritySelect option.bg-warning-subtle:hover,
        #prioritySelect option.bg-warning-subtle:focus,
        #prioritySelect option.bg-warning-subtle:active {
            background-color: var(--bs-warning-bg-subtle) !important;
            color: var(--bs-warning-text-emphasis) !important;
        }
        #prioritySelect option.bg-danger-subtle:hover,
        #prioritySelect option.bg-danger-subtle:focus,
        #prioritySelect option.bg-danger-subtle:active {
            background-color: var(--bs-danger-bg-subtle) !important;
            color: var(--bs-danger-text-emphasis) !important;

        }
        #prioritySelect.bg-secondary-subtle:focus {
            background-color: var(--bs-secondary-bg-subtle) !important;
            color: var(--bs-secondary-text-emphasis) !important;
        }
        #prioritySelect.bg-success-subtle:focus {
            background-color: var(--bs-success-bg-subtle) !important;
            color: var(--bs-success-text-emphasis) !important;
        }
        #prioritySelect.bg-warning-subtle:focus {
            background-color: var(--bs-warning-bg-subtle) !important;
            color: var(--bs-warning-text-emphasis) !important;
        }
        #prioritySelect.bg-danger-subtle:focus {
            background-color: var(--bs-danger-bg-subtle) !important;
            color: var(--bs-danger-text-emphasis) !important;
        }
        .hashtag-badge {
            background-color: #f3e8ff;
            color: #6f42c1;
            border: 1px solid #e5d4ff;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .hashtag-badge .btn-close {
            --bs-btn-close-opacity: 1;
            --bs-btn-close-focus-shadow: 0 0 0 0.15rem rgba(111,66,193,.25);
            filter: invert(31%) sepia(66%) saturate(655%) hue-rotate(240deg) brightness(93%) contrast(90%);
        }
        .hashtag-row-empty {
            color: #6c757d;
        }
        .hashtag-suggestions {
            margin-top: 0.5rem;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            background: #fff;
            box-shadow: 0 0.75rem 1.5rem rgba(0,0,0,0.08);
            padding: 0.35rem;
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
            position: absolute;
            z-index: 1080;
            min-width: 12rem;
            max-width: 22rem;
            max-height: 14rem;
            overflow-y: auto;
        }
        .hashtag-suggestions button {
            border: none;
            background: transparent;
            color: #6f42c1;
            border-radius: 0.35rem;
            padding: 0.35rem 0.45rem;
            font-size: 0.95rem;
            text-align: left;
        }
        .hashtag-suggestions button:hover,
        .hashtag-suggestions button:focus {
            background: #f1e4ff;
        }
        .hashtag-suggestions button.active {
            background: #e9ddff;
            font-weight: 600;
        }
        .prism-editor {
            position: relative;
            display: grid;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            background-color: #ffffff;
            font-family: monospace;
            cursor: text;
        }
        .prism-editor__textarea,
        .prism-editor__preview {
            grid-area: 1 / 1 / 2 / 2;
            font-family: inherit;
            font-size: 0.9rem;
            line-height: 1.5;
            tab-size: 4;
        }
        .prism-editor__textarea {
            width: 100%;
            min-height: calc(1lh + 1.5rem);
            height: auto;
            field-sizing: content;
            resize: none;
            border: none;
            padding: 0.75rem;
            background: transparent;
            color: transparent;
            caret-color: var(--details-text-color, var(--bs-body-color));
            overflow: hidden;
            white-space: pre-wrap;
            overflow-wrap: break-word;
            outline: none;
            z-index: 1;
            cursor: text;
        }
        .prism-editor__preview {
            position: relative;
            margin: 0;
            pointer-events: none;
            white-space: pre-wrap;
            overflow-wrap: break-word;
            overflow: hidden;
            padding: 0.75rem;
            z-index: 0;
        }
        .prism-editor__preview code {
            display: block;
            color: var(--details-text-color, #212529);
            line-height: inherit;
        }
        .prism-editor .code-line {
            display: block;
            line-height: inherit;
        }
        .prism-editor .code-line-task {
            color: #1d4ed8;
        }
        .prism-editor .code-line-note {
            color: #1e7a3e;
        }
        .prism-editor .code-line-milestone {
            color: #800000;
        }
        .prism-editor .code-line-heading {
            font-weight: 700;
        }
        .prism-editor .code-line-done {
            color: #6c757d;
        }
        .prism-editor .token.tag,
        .prism-editor .token.tag-name {
            color: #d63384;
        }
        .prism-editor .token.attr-name {
            color: #6f42c1;
        }
        .prism-editor .token.attr-value {
            color: #0d6efd;
        }
        .inline-hashtag {
            background: #f3e8ff;
            color: #6f42c1;
            border: 1px solid #e5d4ff;
            border-radius: 999px;
            padding: 0.05rem 0.4rem;
            font-weight: 600;
            white-space: nowrap;
        }
    </style>
    <title>Task Details</title>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white mb-4">

    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php" class="navbar-brand">Otodo</a>
        <div class="d-flex align-items-center gap-2">
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="taskMenu" data-bs-toggle="dropdown" aria-expanded="false">&#x2026;</button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="taskMenu">
                    <li><a class="dropdown-item text-danger" id="taskDeleteLink" href="delete_task.php?id=<?=$task['id']?>">Delete</a></li>
                </ul>
            </div>
        </div>

    </div>
</nav>

<div class="offcanvas offcanvas-start" tabindex="-1" id="menu" aria-labelledby="menuLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="menuLabel">Menu</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
        <p class="mb-4">Hello, <?=htmlspecialchars($_SESSION['username'] ?? '')?></p>
        <div class="list-group">
            <a href="index.php" class="list-group-item list-group-item-action">Active Tasks</a>
            <a href="completed.php" class="list-group-item list-group-item-action">Completed Tasks</a>
            <a href="settings.php" class="list-group-item list-group-item-action">Settings</a>
            <a href="logout.php" class="list-group-item list-group-item-action">Logout</a>
        </div>
        <div class="mt-3 small text-muted" id="sync-status" aria-live="polite">All changes saved</div>
    </div>
</div>
<div class="container">
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="description" class="form-control" value="<?=htmlspecialchars(ucwords(strtolower($task['description'] ?? '')))?>" required autocapitalize="none">
        </div>
        <div class="mb-3">
            <label class="form-label">Hashtags</label>
            <div class="d-flex flex-wrap gap-2 align-items-center position-relative" id="hashtagBadges" aria-live="polite">
                <span class="small hashtag-row-empty">No hashtags yet</span>
            </div>
            <div id="hashtagSuggestions" class="hashtag-suggestions d-none" aria-live="polite"></div>
            <div class="form-text">Type # in the title or description to add hashtags.</div>
        </div>
        <div class="mb-3 d-flex align-items-end gap-3">
            <div>
                <label class="form-label">Due Date</label>
                <input type="date" name="due_date" class="form-control w-auto" value="<?=htmlspecialchars($task['due_date'] ?? '')?>">
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="done" id="doneCheckbox" <?php if ($task['done']) echo 'checked'; ?>>
                <label class="form-check-label" for="doneCheckbox">Completed</label>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label">Priority</label>
            <select name="priority" id="prioritySelect" class="form-select <?=$priority_classes[$p]?>">
                <option value="0" class="bg-secondary-subtle text-secondary" <?php if (($task['priority'] ?? 0) == 0) echo 'selected'; ?>>None</option>
                <option value="3" class="bg-danger-subtle text-danger" <?php if (($task['priority'] ?? 2) == 3) echo 'selected'; ?>>High</option>
                <option value="2" class="bg-warning-subtle text-warning" <?php if (($task['priority'] ?? 2) == 2) echo 'selected'; ?>>Medium</option>
                <option value="1" class="bg-success-subtle text-success" <?php if (($task['priority'] ?? 2) == 1) echo 'selected'; ?>>Low</option>
            </select>
        </div>
        <div class="form-check form-switch mb-3">
            <input class="form-check-input" type="checkbox" name="starred" id="starredCheckbox" <?php if (!empty($task['starred'])) echo 'checked'; ?>>
            <label class="form-check-label" for="starredCheckbox">Star this task</label>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <div id="detailsInput" class="prism-editor" data-language="html" data-line-rules="<?=$line_rules_json?>" data-text-color="<?=$details_color_attr?>" data-capitalize-sentences="<?=$capitalize_sentences_attr?>" style="--details-text-color: <?=$details_color_attr?>;">
                <textarea class="prism-editor__textarea" spellcheck="false"><?=htmlspecialchars($task['details'] ?? '')?></textarea>
                <pre class="prism-editor__preview"><code class="language-markup"></code></pre>
            </div>
            <input type="hidden" name="details" id="detailsField" value="<?=htmlspecialchars($task['details'] ?? '')?>">
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="index.php" class="btn btn-secondary" id="backToList">Back</a>
            <button type="button" class="btn btn-primary" id="nextTaskBtn">Next</button>
        </div>
        <p class="text-muted mt-2 d-none" id="nextTaskMessage"></p>
    </form>
</div>
<script src="prevent-save-shortcut.js"></script>
<script src="sync-status.js"></script>
<script src="task-details.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const select = document.querySelector('select[name="priority"]');
  const badge = document.getElementById('priorityBadge');
  if (select) {
    const labels = {0: 'None', 1: 'Low', 2: 'Medium', 3: 'High'};
    const classes = {0: 'bg-secondary-subtle text-secondary', 1: 'bg-success-subtle text-success', 2: 'bg-warning-subtle text-warning', 3: 'bg-danger-subtle text-danger'};
    const focusColors = {
      0: 'var(--bs-secondary-text-emphasis)',
      1: 'var(--bs-success-text-emphasis)',
      2: 'var(--bs-warning-text-emphasis)',
      3: 'var(--bs-danger-text-emphasis)'
    };
    function applyPriorityStyles() {
      const val = parseInt(select.value, 10);
      select.className = 'form-select ' + (classes[val] || classes[0]);
      const focusColor = focusColors[val] || focusColors[0];
      select.style.setProperty('color', focusColor);
      if (badge) {
        badge.textContent = labels[val] || 'None';
        badge.className = 'badge ' + (classes[val] || classes[0]);
      }
    }
    applyPriorityStyles();
    select.addEventListener('change', applyPriorityStyles);
  }

  const backLink = document.getElementById('backToList');
  const deleteLink = document.getElementById('taskDeleteLink');
  const currentTaskId = <?=$task['id']?>;

  const form = document.querySelector('form');
  if (!form) return;
  let timer;

  const hashtagBadges = document.getElementById('hashtagBadges');
  const hashtagSuggestions = document.getElementById('hashtagSuggestions');
  const titleInputEl = form.querySelector('input[name="description"]');
  const detailsFieldHidden = document.getElementById('detailsField');
  const detailsTextarea = document.querySelector('#detailsInput textarea');
  const taskHashtags = <?= $task_hashtags_json ?: '[]' ?>;
  const userHashtags = <?= $user_hashtags_json ?: '[]' ?>;
  const allHashtags = new Set([...taskHashtags, ...userHashtags]);
  let activeHashtagTarget = null;
  let pendingSaveBlocked = false;
  let activeSuggestionIndex = -1;

  function normalizeHashtag(tag) {
    return (tag || '').replace(/^#+/, '').trim().toLowerCase();
  }

  function extractHashtags(text) {
    if (!text) return [];
    const matches = text.match(/#([\p{L}\p{N}_-]+)/gu) || [];
    const set = new Set();
    matches.forEach(match => {
      const normalized = normalizeHashtag(match);
      if (normalized) set.add(normalized);
    });
    return Array.from(set);
  }

  function currentHashtags() {
    const tags = new Set();
    if (titleInputEl) {
      extractHashtags(titleInputEl.value).forEach(tag => tags.add(tag));
    }
    const detailsValue = detailsFieldHidden ? detailsFieldHidden.value : (detailsTextarea ? detailsTextarea.value : '');
    extractHashtags(detailsValue).forEach(tag => tags.add(tag));
    return Array.from(tags);
  }

  function renderHashtagBadges() {
    if (!hashtagBadges) return;
    const tags = currentHashtags();
    hashtagBadges.innerHTML = '';
    if (!tags.length) {
      const empty = document.createElement('span');
      empty.className = 'small hashtag-row-empty';
      empty.textContent = 'No hashtags yet';
      hashtagBadges.appendChild(empty);
    } else {
      tags.forEach(tag => {
        allHashtags.add(tag);
        const badge = document.createElement('span');
        badge.className = 'badge hashtag-badge';
        const label = document.createElement('span');
        label.textContent = '#' + tag;
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'btn-close btn-close-white btn-sm ms-1';
        close.setAttribute('aria-label', 'Remove hashtag #' + tag);
        close.addEventListener('click', () => removeHashtag(tag));
        badge.appendChild(label);
        badge.appendChild(close);
        hashtagBadges.appendChild(badge);
      });
    }
  }

  function removeHashtag(tag) {
    const normalized = normalizeHashtag(tag);
    if (!normalized) return;
    const pattern = new RegExp(`#${normalized}(?=$|[^\\p{L}\\p{N}_-])`, 'giu');
    if (titleInputEl) {
      titleInputEl.value = (titleInputEl.value || '').replace(pattern, '');
    }
    if (detailsTextarea) {
      detailsTextarea.value = (detailsTextarea.value || '').replace(pattern, '');
    }
    if (detailsFieldHidden && detailsTextarea) {
      detailsFieldHidden.value = detailsTextarea.value || '';
    }
    renderHashtagBadges();
    scheduleSave();
  }

  function hideHashtagSuggestions() {
    if (!hashtagSuggestions) return;
    hashtagSuggestions.classList.add('d-none');
    hashtagSuggestions.innerHTML = '';
    activeHashtagTarget = null;
  }

  function detectActiveHashtag(target) {
    if (!target || typeof target.selectionStart !== 'number') return null;
    const value = target.value || '';
    const caret = target.selectionStart;
    const hashIndex = value.lastIndexOf('#', caret - 1);
    if (hashIndex === -1) return null;
    const beforeChar = hashIndex === 0 ? ' ' : value[hashIndex - 1];
    if (!/\s|\n|\r/.test(beforeChar)) return null;
    const partial = value.slice(hashIndex, caret);
    const match = partial.match(/^#([\p{L}\p{N}_-]*)$/u);
    if (!match) return null;
    return { start: hashIndex, end: caret, query: match[1] || '' };
  }

  function hasUnfinishedHashtag() {
    const inputs = [titleInputEl, detailsTextarea];
    return inputs.some(el => {
      const active = detectActiveHashtag(el);
      return active && active.query !== undefined && active.query.length > 0;
    });
  }

  function positionHashtagSuggestions(target) {
    if (!hashtagSuggestions || !target) return;
    const rect = target.getBoundingClientRect();
    hashtagSuggestions.style.left = `${rect.left + window.scrollX}px`;
    hashtagSuggestions.style.top = `${rect.bottom + window.scrollY + 4}px`;
    hashtagSuggestions.style.width = `${rect.width}px`;
  }

  function showHashtagSuggestions(target) {
    if (!hashtagSuggestions || !target) return;
    const active = detectActiveHashtag(target);
    if (!active || active.query === undefined) {
      hideHashtagSuggestions();
      return;
    }
    const query = normalizeHashtag(active.query);
    if (query === '') {
      hideHashtagSuggestions();
      return;
    }
    const matches = Array.from(allHashtags).filter(tag => tag.startsWith(query) && tag !== query);
    if (!matches.length) {
      hideHashtagSuggestions();
      return;
    }
    hashtagSuggestions.innerHTML = '';
    activeSuggestionIndex = 0;
    matches.slice(0, 8).forEach((tag, index) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = '#' + tag;
      if (index === activeSuggestionIndex) {
        btn.classList.add('active');
      }
      btn.addEventListener('mouseenter', () => setActiveSuggestion(index));
      btn.addEventListener('click', () => insertHashtagSuggestion(tag));
      hashtagSuggestions.appendChild(btn);
    });
    hashtagSuggestions.classList.remove('d-none');
    activeHashtagTarget = { target, start: active.start, end: active.end };
    positionHashtagSuggestions(target);
  }

  function insertHashtagSuggestion(tag) {
    if (!activeHashtagTarget || !tag) return;
    const { target, start, end } = activeHashtagTarget;
    const value = target.value || '';
    const insertion = '#' + tag + ' ';
    const nextValue = value.slice(0, start) + insertion + value.slice(end);
    target.value = nextValue;
    const nextPos = start + insertion.length;
    if (typeof target.setSelectionRange === 'function') {
      target.setSelectionRange(nextPos, nextPos);
    }
    target.focus({ preventScroll: true });
    const evt = new Event('input', { bubbles: true });
    target.dispatchEvent(evt);
    hideHashtagSuggestions();
    trySendPendingSave();
  }

  function setActiveSuggestion(index) {
    const buttons = Array.from(hashtagSuggestions.querySelectorAll('button'));
    if (!buttons.length) return;
    activeSuggestionIndex = Math.max(0, Math.min(index, buttons.length - 1));
    buttons.forEach((btn, idx) => {
      btn.classList.toggle('active', idx === activeSuggestionIndex);
    });
  }

  function acceptActiveSuggestion() {
    const buttons = Array.from(hashtagSuggestions.querySelectorAll('button'));
    if (!buttons.length) return false;
    const btn = buttons[Math.max(0, Math.min(activeSuggestionIndex, buttons.length - 1))];
    btn.click();
    return true;
  }

  renderHashtagBadges();

  function bindHashtagListeners(el) {
    if (!el) return;
    const refresh = () => {
      renderHashtagBadges();
      showHashtagSuggestions(el);
      trySendPendingSave();
    };
    el.addEventListener('input', refresh);
    el.addEventListener('click', () => showHashtagSuggestions(el));
    el.addEventListener('keyup', () => showHashtagSuggestions(el));
    el.addEventListener('keydown', (event) => {
      if (hashtagSuggestions && !hashtagSuggestions.classList.contains('d-none')) {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault();
          const delta = event.key === 'ArrowDown' ? 1 : -1;
          setActiveSuggestion(activeSuggestionIndex + delta);
        } else if (event.key === 'Enter') {
          const accepted = acceptActiveSuggestion();
          if (accepted) {
            event.preventDefault();
          }
        } else if (event.key === 'Escape') {
          hideHashtagSuggestions();
        }
      }
    });
  }

  bindHashtagListeners(titleInputEl);
  bindHashtagListeners(detailsTextarea);

  document.addEventListener('click', (event) => {
    if (!hashtagSuggestions || hashtagSuggestions.classList.contains('d-none')) return;
    if (hashtagSuggestions.contains(event.target)) return;
    if (titleInputEl && titleInputEl === event.target) return;
    if (detailsTextarea && detailsTextarea === event.target) return;
    hideHashtagSuggestions();
  });

  const nextTaskId = <?= $next_task_id !== null ? (int)$next_task_id : 'null' ?>;
  const nextButton = document.getElementById('nextTaskBtn');
  const nextMessage = document.getElementById('nextTaskMessage');
  if (nextButton) {
    if (nextTaskId === null) {
      nextButton.disabled = true;
      if (nextMessage) {
        nextMessage.textContent = 'End of list. No further tasks.';
        nextMessage.classList.remove('d-none');
      }
    }
    nextButton.addEventListener('click', function(){
      if (nextTaskId !== null) {
        window.location.href = 'task.php?id=' + nextTaskId;
      } else if (nextMessage) {
        nextMessage.textContent = 'End of list. No further tasks.';
        nextMessage.classList.remove('d-none');
      }
    });
  }

  let updateDetails;
  const details = document.getElementById('detailsInput');
  const detailsField = document.getElementById('detailsField');
  if (details && detailsField && window.initTaskDetailsEditor) {
    let rules = [];
    const capitalizeSentences = details.dataset.capitalizeSentences === 'true';
    try {
      rules = JSON.parse(details.dataset.lineRules || '[]');
      if (!Array.isArray(rules)) {
        rules = [];
      }
    } catch (err) {
      rules = [];
    }
    const editor = initTaskDetailsEditor(details, detailsField, scheduleSave, {
      lineRules: rules,
      textColor: details.dataset.textColor,
      capitalizeSentences: capitalizeSentences
    });
    if (editor && typeof editor.updateDetails === 'function') {
      const baseUpdate = editor.updateDetails;
      updateDetails = function() {
        const val = baseUpdate();
  renderHashtagBadges();
        return val;
      };
    }
  }

  const taskReloadKey = 'taskListNeedsReload';

  function markListReloadNeeded() {
    try {
      sessionStorage.setItem(taskReloadKey, '1');
    } catch (err) {}
  }

  const updateKey = 'pendingTaskUpdates';

  function readPendingUpdates() {
    try {
      const raw = sessionStorage.getItem(updateKey);
      if (!raw) return {};
      const parsed = JSON.parse(raw);
      return parsed && typeof parsed === 'object' ? parsed : {};
    } catch (err) {
      return {};
    }
  }

  function writePendingUpdates(updates) {
    try {
      sessionStorage.setItem(updateKey, JSON.stringify(updates));
    } catch (err) {}
  }

  function recordPendingUpdate(partial) {
    if (!currentTaskId) return;
    const updates = readPendingUpdates();
    const existing = updates[currentTaskId] || { id: currentTaskId };
    updates[currentTaskId] = { ...existing, ...partial };
    writePendingUpdates(updates);
  }

  function captureFormState() {
    const titleInput = form.querySelector('input[name="description"]');
    const dueInput = form.querySelector('input[name="due_date"]');
    const doneCheckbox = form.querySelector('input[name="done"]');
    const prioritySelect = form.querySelector('select[name="priority"]');
    const starredCheckbox = form.querySelector('input[name="starred"]');
    const detailsWrapper = document.getElementById('detailsInput');
    const detailsField = form.querySelector('input[name="details"]');

    recordPendingUpdate({
      description: titleInput ? titleInput.value.trim() : undefined,
      due_date: dueInput ? dueInput.value : undefined,
      done: doneCheckbox ? doneCheckbox.checked : undefined,
      priority: prioritySelect ? Number(prioritySelect.value) : undefined,
      starred: starredCheckbox ? starredCheckbox.checked : undefined,
      details: detailsField ? detailsField.value : undefined,
      hashtags: currentHashtags()
    });
  }

  function applyPendingUpdatesToForm() {
    const updates = readPendingUpdates();
    const pending = updates[currentTaskId];
    if (!pending) return;

    const titleInput = form.querySelector('input[name="description"]');
    const dueInput = form.querySelector('input[name="due_date"]');
    const doneCheckbox = form.querySelector('input[name="done"]');
    const prioritySelect = form.querySelector('select[name="priority"]');
    const starredCheckbox = form.querySelector('input[name="starred"]');
    const detailsField = form.querySelector('input[name="details"]');

    if (titleInput && typeof pending.description === 'string') {
      titleInput.value = pending.description;
    }
    if (dueInput && typeof pending.due_date === 'string') {
      dueInput.value = pending.due_date;
    }
    if (doneCheckbox && typeof pending.done === 'boolean') {
      doneCheckbox.checked = pending.done;
    }
    if (prioritySelect && typeof pending.priority === 'number') {
      const nextVal = String(pending.priority);
      if (prioritySelect.value !== nextVal) {
        prioritySelect.value = nextVal;
        prioritySelect.dispatchEvent(new Event('change', { bubbles: true }));
      }
    }
    if (starredCheckbox && typeof pending.starred === 'boolean') {
      starredCheckbox.checked = pending.starred;
    }
    if (detailsField && typeof pending.details === 'string') {
      detailsField.value = pending.details;
      if (detailsWrapper) {
        const detailsTextarea = detailsWrapper.querySelector('textarea');
        if (detailsTextarea) {
          detailsTextarea.value = pending.details;
        }
        if (typeof updateDetails === 'function') {
          updateDetails();
        }
      }
    }

    renderHashtagBadges();
  }

  function trySendPendingSave() {
    if (pendingSaveBlocked && !hasUnfinishedHashtag()) {
      pendingSaveBlocked = false;
      scheduleSave();
    }
  }

  function scheduleSave() {
    captureFormState();
    markListReloadNeeded();
    if (timer) clearTimeout(timer);
    if (hasUnfinishedHashtag()) {
      pendingSaveBlocked = true;
      return;
    }
    pendingSaveBlocked = false;
    timer = setTimeout(sendSave, 500);
  }

  applyPendingUpdatesToForm();

  function instantNavigateToIndex() {
    if (window.updateSyncStatus) window.updateSyncStatus('syncing', 'Returning to tasks…');
    window.location.replace('index.php');
  }

  if (backLink) {
    backLink.addEventListener('click', function(e){
      e.preventDefault();
      if (timer) {
        sendSave(true);
      }
      instantNavigateToIndex();
    });
  }

  if (deleteLink) {
    deleteLink.addEventListener('click', function(e){
      e.preventDefault();
      const url = deleteLink.getAttribute('href');
      if (currentTaskId) {
        try {
          const raw = sessionStorage.getItem('deletedTaskIds');
          const parsed = raw ? JSON.parse(raw) : [];
          const list = Array.isArray(parsed) ? parsed : [parsed];
          if (!list.includes(currentTaskId)) list.push(currentTaskId);
          sessionStorage.setItem('deletedTaskIds', JSON.stringify(list));
        } catch (err) {
          sessionStorage.setItem('deletedTaskIds', JSON.stringify([currentTaskId]));
        }
      }
      if (window.updateSharedSyncStatus) window.updateSharedSyncStatus('syncing', 'Deleting task…', {followUpUrl: url});
      if (url) {
        fetch(url, {
          method: 'GET',
          headers: {'Accept': 'application/json', 'X-Requested-With': 'fetch'},
          keepalive: true,
          credentials: 'same-origin'
        }).then(resp => {
          if (resp && resp.ok && window.updateSharedSyncStatus) {
            window.updateSharedSyncStatus('synced', 'Task deleted');
          }
        }).catch(() => {
          if (window.updateSharedSyncStatus) window.updateSharedSyncStatus('error', 'Delete failed. Check connection.', {followUpUrl: url});
        });
      }
      setTimeout(instantNavigateToIndex, 0);
    });
  }

  function sendSave(immediate = false) {
    if (updateDetails) updateDetails();
    const data = new FormData(form);
    if (immediate && navigator.sendBeacon) {
      navigator.sendBeacon(window.location.href, data);
      if (window.updateSyncStatus) window.updateSyncStatus('syncing', 'Saving changes…');
    } else {
      const request = fetch(window.location.href, {method: 'POST', body: data});
      if (window.trackBackgroundSync) {
        window.trackBackgroundSync(request, {syncing: 'Saving changes…'});
      }
    }
  }

  form.addEventListener('input', scheduleSave);
  form.addEventListener('change', scheduleSave);
  form.addEventListener('submit', function(e){ e.preventDefault(); });
  window.addEventListener('beforeunload', function(){
    if (timer) {
      sendSave(true);
    }
  });
  if (window.updateSyncStatus) window.updateSyncStatus('synced');
})();
</script>
<script src="sw-register.js"></script>
</body>
</html>
