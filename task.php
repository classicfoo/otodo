<?php
require_once 'db.php';

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
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
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
        #prioritySelect:hover,
        #prioritySelect:focus {
            background-color: inherit !important;
            color: inherit !important;
        }
        #detailsInput {
            white-space: pre-wrap;
        }
        @media (min-width: 992px) {
            #detailsInput {
                min-height: 30rem;
            }
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
            <div id="detailsInput" class="form-control" contenteditable="true"><?=htmlspecialchars($task['details'] ?? '')?></div>
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
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const select = document.querySelector('select[name="priority"]');
  const badge = document.getElementById('priorityBadge');
  if (select && badge) {
    const labels = {0: 'None', 1: 'Low', 2: 'Medium', 3: 'High'};
    const classes = {0: 'bg-secondary-subtle text-secondary', 1: 'bg-success-subtle text-success', 2: 'bg-warning-subtle text-warning', 3: 'bg-danger-subtle text-danger'};
    function updateBadge() {
      const val = parseInt(select.value, 10);
      badge.textContent = labels[val] || 'None';
      badge.className = 'badge ' + (classes[val] || classes[0]);
    }
    select.addEventListener('change', updateBadge);
  }

  const backLink = document.getElementById('backToList');
  const deleteLink = document.getElementById('taskDeleteLink');
  const currentTaskId = <?=$task['id']?>;

  const form = document.querySelector('form');
  if (!form) return;
  let timer;

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
  if (details && detailsField) {
      updateDetails = function() {
        const text = details.innerText
          .replace(/\r\n/g, "\n")
          .replace(/\r/g, "\n");
        detailsField.value = text;
      };
      details.addEventListener('input', function(){
        updateDetails();
        scheduleSave();
      });
      details.addEventListener('paste', function(e){
        e.preventDefault();
        const text = e.clipboardData.getData('text/plain');
        document.execCommand('insertText', false, text);
        updateDetails();
        scheduleSave();
      });
      details.addEventListener('keydown', function(e) {
        if (e.key === 'Tab') {
          e.preventDefault();
          document.execCommand('insertText', false, "\t");
          updateDetails();
          scheduleSave();
        } else if (e.key === ' ') {
          const sel = window.getSelection();
          if (sel && sel.rangeCount > 0) {
            const range = sel.getRangeAt(0);
            const node = range.startContainer;
            const offset = range.startOffset;
            if (node.nodeType === Node.TEXT_NODE && offset > 0 && node.textContent[offset-1] === ' ') {
              e.preventDefault();
              range.setStart(node, offset-1);
              range.deleteContents();
              document.execCommand('insertText', false, "\t");
              updateDetails();
              scheduleSave();
            }
          }
        } else if (e.key === 'Enter') {
          e.preventDefault();
          const sel = window.getSelection();
          if (sel && sel.rangeCount > 0) {
            const range = sel.getRangeAt(0);
            const preRange = range.cloneRange();
            preRange.setStart(details, 0);
            const textBefore = preRange.toString();
            const lineStart = textBefore.lastIndexOf('\n') + 1;
            const currentLine = textBefore.slice(lineStart);
            const leading = currentLine.match(/^[\t ]*/)[0];
            document.execCommand('insertText', false, "\n" + leading);
            updateDetails();
            scheduleSave();
          }
        }
      });
      updateDetails();
  }

  const taskReloadKey = 'taskListNeedsReload';

  function markListReloadNeeded() {
    try {
      sessionStorage.setItem(taskReloadKey, '1');
    } catch (err) {}
  }

  function scheduleSave() {
    markListReloadNeeded();
    if (timer) clearTimeout(timer);
    timer = setTimeout(sendSave, 500);
  }

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
