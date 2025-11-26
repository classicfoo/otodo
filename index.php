<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
$stmt = $db->prepare('SELECT id, description, due_date, details, done, priority, starred FROM tasks WHERE user_id = :uid AND done = 0 ORDER BY starred DESC, due_date IS NULL, due_date, priority DESC, id DESC');

$stmt->execute([':uid' => $_SESSION['user_id']]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$priority_labels = [0 => 'None', 1 => 'Low', 2 => 'Medium', 3 => 'High'];
$priority_classes = [0 => 'text-secondary', 1 => 'text-success', 2 => 'text-warning', 3 => 'text-danger'];

$tz = $_SESSION['location'] ?? 'UTC';
try {
    $tzObj = new DateTimeZone($tz);
} catch (Exception $e) {
    $tzObj = new DateTimeZone('UTC');
}
$today = new DateTime('today', $tzObj);
$tomorrow = (clone $today)->modify('+1 day');
$todayFmt = $today->format('Y-m-d');
$tomorrowFmt = $tomorrow->format('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .task-row {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            gap: 0.75rem;
        }
        .task-main {
            flex: 1 1 70%;
            min-width: 0;
            word-break: break-word;
        }
        .task-meta {
            flex: 0 0 30%;
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 0.5rem;
            justify-items: center;
            align-items: center;
        }
        .due-date-badge { width: 100%; max-width: 110px; text-align: center; }
        .priority-text { width: 100%; max-width: 90px; text-align: center; }
        .star-toggle { min-width: 44px; }
        .task-star {
            border: 1px solid #f3c24c;
            border-radius: 4px;
            padding: 0.2rem 0.6rem;
            background: #fff;
            cursor: pointer;
        }
        .star-icon { font-size: 1rem; line-height: 1; }
        .starred .star-icon { color: #ffc107; }
        @media (max-width: 600px) {
            .task-row {
                flex-direction: column;
                align-items: stretch;
            }
            .task-meta {
                width: 100%;
                margin-top: 0.25rem;
                grid-template-columns: auto auto auto;
                justify-content: flex-start;
                justify-items: start;
            }
        }
    </style>
    <title>Todo List</title>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <span class="navbar-brand mb-0 h1">Otodo</span>
        <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu" aria-controls="menu">
            <span class="navbar-toggler-icon"></span>
        </button>
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
    <form action="add_task.php" method="post" class="mb-3">
        <div class="input-group">
            <input type="text" name="description" class="form-control" placeholder="New task" required autocapitalize="none">
            <button class="btn btn-primary" type="submit">Add</button>
        </div>
    </form>
    <div class="list-group">
        <?php foreach ($tasks as $task): ?>
            <?php
                $p = (int)($task['priority'] ?? 0);
                if ($p < 0 || $p > 3) { $p = 0; }
                $due = $task['due_date'] ?? '';
                $dueClass = 'bg-secondary-subtle text-secondary';
                if ($due !== '') {
                    try {
                        $dueDate = new DateTime($due, $tzObj);
                        if ($dueDate < $today) {
                            $due = 'Overdue';
                            $dueClass = 'bg-danger-subtle text-danger';
                        } else {
                            $dueFmt = $dueDate->format('Y-m-d');
                            if ($dueFmt === $todayFmt) {
                                $due = 'Today';
                                $dueClass = 'bg-success-subtle text-success';
                            } elseif ($dueFmt === $tomorrowFmt) {
                                $due = 'Tomorrow';
                                $dueClass = 'bg-primary-subtle text-primary';
                            } else {
                                $due = 'Later';
                                $dueClass = 'bg-primary-subtle text-primary';

                            }
                        }
                    } catch (Exception $e) {
                        // leave $due unchanged if parsing fails
                    }
                }
            ?>
            <a href="task.php?id=<?=$task['id']?>" class="list-group-item list-group-item-action task-row">
                <div class="task-main <?php if ($task['done']) echo 'text-decoration-line-through'; ?>">&ZeroWidthSpace;<?=htmlspecialchars(ucwords(strtolower($task['description'] ?? '')))?></div>
                <div class="task-meta">
                    <?php if ($due !== ''): ?>
                        <span class="badge due-date-badge <?=$dueClass?>"><?=htmlspecialchars($due)?></span>
                    <?php else: ?>
                        <span class="due-date-badge"></span>
                    <?php endif; ?>
                    <span class="small priority-text <?=$priority_classes[$p]?>"><?=$priority_labels[$p]?></span>
                    <button type="button" class="task-star btn btn-sm btn-outline-warning star-toggle <?php if (!empty($task['starred'])) echo 'starred'; ?>" data-id="<?=$task['id']?>" aria-pressed="<?=!empty($task['starred']) ? 'true' : 'false'?>" aria-label="<?=!empty($task['starred']) ? 'Unstar task' : 'Star task'?>">
                        <span class="star-icon" aria-hidden="true"><?=!empty($task['starred']) ? '★' : '☆'?></span>
                        <span class="visually-hidden"><?=!empty($task['starred']) ? 'Starred' : 'Not starred'?></span>
                    </button>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<script src="sw-register.js"></script>
<script src="sync-status.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  window.addEventListener('pageshow', e => {
    if (e.persisted) location.reload();
  });
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) location.reload();
  });

  function setStarAppearance(button, starred) {
    button.classList.toggle('starred', !!starred);
    button.setAttribute('aria-pressed', starred ? 'true' : 'false');
    button.setAttribute('aria-label', starred ? 'Unstar task' : 'Star task');
    const icon = button.querySelector('.star-icon');
    if (icon) icon.textContent = starred ? '★' : '☆';
    const sr = button.querySelector('.visually-hidden');
    if (sr) sr.textContent = starred ? 'Starred' : 'Not starred';
  }

  function bindStarButton(button) {
    button.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      const id = this.dataset.id;
      if (!id) return;
      const next = this.getAttribute('aria-pressed') === 'true' ? 0 : 1;
      const data = new FormData();
      data.append('id', id);
      data.append('starred', next);
      const request = fetch('toggle_star.php', {
        method: 'POST',
        body: data,
        headers: {'Accept': 'application/json', 'X-Requested-With': 'fetch'}
      });
      if (window.trackBackgroundSync) {
        window.trackBackgroundSync(request, {
          syncing: next ? 'Starring task…' : 'Unstarring task…',
          synced: 'Task updated',
          error: 'Could not reach server'
        });
      }
      request.then(resp => resp.ok ? resp.json() : Promise.reject())
      .then(json => {
        if (!json || json.status !== 'ok') throw new Error('Update failed');
        setStarAppearance(button, json.starred);
        if (window.updateSyncStatus) window.updateSyncStatus('synced');
      })
      .catch(() => {
        if (window.updateSyncStatus) window.updateSyncStatus('error', 'Could not reach server');
      });
    });
  }

  const starButtons = document.querySelectorAll('.star-toggle');
  starButtons.forEach(bindStarButton);

  const form = document.querySelector('form[action="add_task.php"]');
  const listGroup = document.querySelector('.container .list-group');
  if (form && listGroup) {
    form.addEventListener('submit', function(e){
      e.preventDefault();
      const data = new FormData(form);
      const description = (data.get('description') || '').toString().trim();
      if (!description) return;

      const tempItem = document.createElement('a');
      tempItem.className = 'list-group-item list-group-item-action task-row opacity-75';
      tempItem.innerHTML = `<div class="task-main">${description}</div><div class="task-meta"><span class="badge due-date-badge bg-primary-subtle text-primary">Today</span><span class="small priority-text text-secondary">Saving…</span><button type="button" class="task-star btn btn-sm btn-outline-warning star-toggle" aria-pressed="false" disabled><span class="star-icon" aria-hidden="true">☆</span><span class="visually-hidden">Not starred</span></button></div>`;
      listGroup.prepend(tempItem);

      data.set('description', description);
      const request = fetch('add_task.php', {
        method: 'POST',
        body: data,
        headers: {'Accept': 'application/json', 'X-Requested-With': 'fetch'}
      });

      if (window.trackBackgroundSync) {
        window.trackBackgroundSync(request, {
          syncing: 'Saving task…',
          synced: 'Task saved',
          error: 'Could not reach server'
        });
      }

      request.then(resp => resp.ok ? resp.json() : Promise.reject())
      .then(json => {
        if (!json || json.status !== 'ok') throw new Error('Save failed');
        tempItem.href = `task.php?id=${json.id}`;
        tempItem.classList.remove('opacity-75');
          const title = tempItem.querySelector('.task-main');
          if (title) title.textContent = json.description;
        const badge = tempItem.querySelector('.badge');
        if (badge) {
          badge.textContent = json.due_label || '';
          badge.className = `badge due-date-badge ${json.due_class || ''}`;
        }
        const priority = tempItem.querySelector('.priority-text');
        if (priority) {
          priority.textContent = json.priority_label || '';
          priority.className = `small priority-text ${json.priority_class || ''}`;
        }
        const star = tempItem.querySelector('.star-toggle');
        if (star) {
          star.dataset.id = json.id;
          star.disabled = false;
          setStarAppearance(star, json.starred || 0);
          bindStarButton(star);
        }
        if (window.updateSyncStatus) window.updateSyncStatus('synced');
      })
      .catch(() => {
        tempItem.classList.add('text-danger');
        const priority = tempItem.querySelector('.priority-text');
        if (priority) priority.textContent = 'Retry needed';
        if (window.updateSyncStatus) window.updateSyncStatus('error', 'Could not reach server');
      })
      .finally(() => {
        form.reset();
      });
    });
  }
</script>
</body>
</html>
