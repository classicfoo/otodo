<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $db->prepare('SELECT id, description, due_date, details, done, priority FROM tasks WHERE id = :id AND user_id = :uid');
$stmt->execute([':id' => $id, ':uid' => $_SESSION['user_id']]);
$task = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$task) {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $description = trim($_POST['description'] ?? '');
    $due_date = trim($_POST['due_date'] ?? '');
    $details = trim($_POST['details'] ?? '');
    $priority = (int)($_POST['priority'] ?? 0);
    if ($priority < 0 || $priority > 3) {
        $priority = 0;
    }
    $stmt = $db->prepare('UPDATE tasks SET description = :description, due_date = :due_date, details = :details, priority = :priority WHERE id = :id AND user_id = :uid');
    $stmt->execute([
        ':description' => $description,
        ':due_date' => $due_date !== '' ? $due_date : null,
        ':details' => $details !== '' ? $details : null,
        ':priority' => $priority,
        ':id' => $id,
        ':uid' => $_SESSION['user_id'],
    ]);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit();
}

$priority_labels = [0 => 'None', 1 => 'Low', 2 => 'Medium', 3 => 'High'];
$priority_classes = [0 => 'bg-secondary', 1 => 'bg-success', 2 => 'bg-warning', 3 => 'bg-danger'];
$p = (int)($task['priority'] ?? 0);
if ($p < 0 || $p > 3) { $p = 0; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Task Details</title>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php" class="navbar-brand">Otodo</a>
        <div class="d-flex align-items-center gap-2">
            <a href="completed.php" class="btn btn-outline-secondary btn-sm">Completed</a>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm" type="button" id="taskMenu" data-bs-toggle="dropdown" aria-expanded="false">&#x2026;</button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="taskMenu">
                    <li><a class="dropdown-item text-danger" href="delete_task.php?id=<?=$task['id']?>">Delete</a></li>
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
            <a href="logout.php" class="list-group-item list-group-item-action">Logout</a>
        </div>
    </div>
</div>
<div class="container">
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="description" class="form-control" value="<?=htmlspecialchars($task['description'] ?? '')?>" required autocapitalize="none">
        </div>
        <div class="mb-3">
            <label class="form-label">Due Date</label>
            <input type="date" name="due_date" class="form-control" value="<?=htmlspecialchars($task['due_date'] ?? '')?>">
        </div>
        <div class="mb-3">
            <label class="form-label d-flex align-items-center justify-content-between">
                <span>Priority</span>
                <span id="priorityBadge" class="badge <?=$priority_classes[$p]?>"><?=$priority_labels[$p]?></span>
            </label>
            <select name="priority" class="form-select">
                <option value="0" <?php if (($task['priority'] ?? 0) == 0) echo 'selected'; ?>>None</option>
                <option value="3" <?php if (($task['priority'] ?? 2) == 3) echo 'selected'; ?>>High</option>
                <option value="2" <?php if (($task['priority'] ?? 2) == 2) echo 'selected'; ?>>Medium</option>
                <option value="1" <?php if (($task['priority'] ?? 2) == 1) echo 'selected'; ?>>Low</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <div id="detailsEditable" class="form-control" contenteditable="true"><?=nl2br(htmlspecialchars($task['details'] ?? ''))?></div>
            <input type="hidden" name="details" id="detailsInput" value="<?=htmlspecialchars($task['details'] ?? '')?>">
        </div>
        <a href="toggle_task.php?id=<?=$task['id']?>" class="btn btn-success"><?=$task['done'] ? 'Undo' : 'Done'?></a>
    </form>
</div>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const select = document.querySelector('select[name="priority"]');
  const badge = document.getElementById('priorityBadge');
  if (select && badge) {
    const labels = {0: 'None', 1: 'Low', 2: 'Medium', 3: 'High'};
    const classes = {0: 'bg-secondary', 1: 'bg-success', 2: 'bg-warning', 3: 'bg-danger'};
    function updateBadge() {
      const val = parseInt(select.value, 10);
      badge.textContent = labels[val] || 'None';
      badge.className = 'badge ' + (classes[val] || classes[0]);
    }
    select.addEventListener('change', updateBadge);
  }

  const form = document.querySelector('form');
  if (!form) return;
  let timer;

  function scheduleSave() {
    if (timer) clearTimeout(timer);
    timer = setTimeout(sendSave, 500);
  }

  function sendSave(immediate = false) {
    const editable = document.getElementById('detailsEditable');
    const input = document.getElementById('detailsInput');
    if (editable && input) {
      input.value = editable.innerText.trim();
    }
    const data = new FormData(form);
    if (immediate && navigator.sendBeacon) {
      navigator.sendBeacon(window.location.href, data);
    } else {
      fetch(window.location.href, {method: 'POST', body: data});
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
})();
</script>
</body>
</html>
