<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
$stmt = $db->prepare('SELECT id, description, due_date, details, done, priority, starred FROM tasks WHERE user_id = :uid AND done = 0 ORDER BY due_date IS NULL, due_date, priority DESC, id DESC');

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
            display: grid;
            grid-template-columns: minmax(0, 1fr) 300px;
            align-items: center;
            padding: 0.75rem 1rem;
            column-gap: 1rem;
        }
        .task-main {
            min-width: 0;
            word-break: break-word;
        }
        .task-meta {
            display: grid;
            grid-template-columns: auto auto auto;
            column-gap: 0.5rem;
            align-items: center;
            justify-items: center;
        }
        .due-date-badge { width: 100%; text-align: center; }
        .priority-text { width: 100%; text-align: center; }
        .star-toggle { min-width: 44px; }
        .task-star {
            border: none;
            border-radius: 4px;
            padding: 0.2rem 0.6rem;
            background: transparent;
            cursor: pointer;
        }
        .task-star:focus-visible { outline: 2px solid #0a2a66; outline-offset: 2px; }
        .task-star:active { background: transparent; }
        .star-icon { font-size: 2rem; line-height: 1; color:rgba(108, 117, 125, 0.51); }
        .starred .star-icon { color: #f4ca4c }
        .task-context-menu {
            position: fixed;
            background: #fff;
            border: 1px solid rgba(0,0,0,.1);
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
            z-index: 1080;
            min-width: 220px;
            overflow: hidden;
        }
        .task-context-menu .context-header {
            padding: 0.5rem 0.75rem;
            background-color: #f8f9fa;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .task-context-menu .context-group + .context-group { border-top: 1px solid #e9ecef; }
        .task-context-menu .context-label { font-size: 0.75rem; color: #6c757d; padding: 0.35rem 0.75rem 0.25rem; text-transform: uppercase; letter-spacing: 0.04em; }
        .task-context-menu button {
            width: 100%;
            text-align: left;
            padding: 0.5rem 0.75rem;
            border: 0;
            background: transparent;
            font-size: 0.95rem;
        }
        .task-context-menu button:hover,
        .task-context-menu button:focus { background-color: #f1f3f5; }
        .task-context-menu button:focus-visible { outline: 2px solid #0a2a66; outline-offset: -2px; }
        .task-context-menu button .badge { float: right; }
        .task-context-menu button.active { background-color: #e7f1ff; }
        .header-actions { gap: 0.5rem; }
        .task-search {
            display: inline-flex;
            align-items: center;
            width: 2.65rem;
            height: calc(2.5rem + 2px);
            border: 1px solid #e9ecef;
            border-radius: 999px;
            background: #f8f9fa;
            overflow: hidden;
            transition: width 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background-color 0.2s ease;
            box-shadow: none;
            flex-shrink: 1;
            max-width: 360px;
            padding: 0.125rem;
            gap: 0.15rem;
        }
        .task-search.expanded {
            width: min(360px, 70vw);
            border-color: #ced4da;
            background: #fff;
            box-shadow: 0 0.35rem 0.75rem rgba(0, 0, 0, 0.08);
        }
        .search-toggle,
        .search-clear {
            background: transparent;
            border: none;
            padding: 0.35rem 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #212529;
            min-width: 2rem;
            min-height: 2rem;
        }
        .search-toggle:focus-visible,
        .search-clear:focus-visible {
            outline: 2px solid #0a2a66;
            outline-offset: 2px;
        }
        .search-toggle svg { width: 1rem; height: 1rem; }
        .search-input {
            flex: 1 1 auto;
            border: 0;
            outline: none;
            padding: 0.35rem 0;
            min-width: 0;
            opacity: 0;
            pointer-events: none;
            background: transparent;
            font-size: 0.95rem;
            line-height: 1.25;
            border-radius: 999px;
            -webkit-appearance: none;
            appearance: none;
        }
        .search-input::-webkit-search-cancel-button,
        .search-input::-webkit-search-decoration,
        .search-input::-ms-clear,
        .search-input::-ms-reveal {
            display: none;
            width: 0;
            height: 0;
            -webkit-appearance: none;
            appearance: none;
        }
        .task-search.expanded .search-input {
            opacity: 1;
            pointer-events: auto;
            padding-left: 0.35rem;
            padding-right: 0.35rem;
        }
        .search-input:focus {
            outline: none;
            box-shadow: none;
        }
        .search-clear {
            opacity: 0;
            pointer-events: none;
            font-size: 1.15rem;
            line-height: 1;
        }
        .task-search.expanded .search-clear {
            opacity: 1;
            pointer-events: auto;
        }
        @media (max-width: 768px) {
            .task-row {
                grid-template-columns: minmax(0, 1fr) minmax(0, 180px);
                column-gap: 0.5rem;
            }
            .task-meta {
                grid-template-columns: auto auto auto;
                justify-content: end;
                justify-items: end;
            }
            .due-date-badge, .priority-text { width: auto; min-width: 0; }
            .task-search.expanded { width: min(280px, 70vw); }
        }
    </style>
    <title>Todo List</title>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <span class="navbar-brand mb-0 h1">Otodo</span>
        <div class="d-flex align-items-center header-actions ms-auto">
            <div class="task-search" id="task-search" aria-expanded="false">
                <button class="search-toggle" type="button" id="task-search-toggle" aria-label="Search tasks">
                    <svg aria-hidden="true" viewBox="0 0 24 24" focusable="false">
                        <path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z" />
                    </svg>
                </button>
                <input type="text" id="task-search-input" class="search-input" placeholder="Search tasks…" aria-label="Search tasks" tabindex="-1" inputmode="search">
                <button class="search-clear" type="button" id="task-search-clear" aria-label="Clear search">&times;</button>
            </div>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#menu" aria-controls="menu">
                <span class="navbar-toggler-icon"></span>
            </button>
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
                $rawDue = $task['due_date'] ?? '';
                $due = $rawDue;
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
            <a href="task.php?id=<?=$task['id']?>" class="list-group-item list-group-item-action task-row" data-task-id="<?=$task['id']?>" data-due-date="<?=htmlspecialchars($rawDue ?? '')?>" data-priority="<?=$p?>">
                <div class="task-main task-title <?php if ($task['done']) echo 'text-decoration-line-through'; ?>">&ZeroWidthSpace;<?=htmlspecialchars(ucwords(strtolower($task['description'] ?? '')))?></div>
                <div class="task-meta">
                    <?php if ($due !== ''): ?>
                        <span class="badge due-date-badge <?=$dueClass?>"><?=htmlspecialchars($due)?></span>
                    <?php else: ?>
                        <span class="due-date-badge"></span>
                    <?php endif; ?>
                    <span class="small priority-text <?=$priority_classes[$p]?>"><?=$priority_labels[$p]?></span>
                    <button type="button" class="task-star star-toggle <?php if (!empty($task['starred'])) echo 'starred'; ?>" data-id="<?=$task['id']?>" aria-pressed="<?=!empty($task['starred']) ? 'true' : 'false'?>" aria-label="<?=!empty($task['starred']) ? 'Unstar task' : 'Star task'?>">
                        <span class="star-icon" aria-hidden="true"><?=!empty($task['starred']) ? '★' : '☆'?></span>
                        <span class="visually-hidden"><?=!empty($task['starred']) ? 'Starred' : 'Not starred'?></span>
                    </button>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<script src="prevent-save-shortcut.js"></script>
<script src="sw-register.js"></script>
<script src="sync-status.js"></script>
<script>
    (function() {
        const searchContainer = document.getElementById('task-search');
        const searchToggle = document.getElementById('task-search-toggle');
        const searchInput = document.getElementById('task-search-input');
        const clearButton = document.getElementById('task-search-clear');

        if (!searchContainer || !searchToggle || !searchInput || !clearButton) {
            return;
        }

        const isTypingField = (el) => {
            if (!el) return false;
            const tag = el.tagName;
            return tag === 'INPUT' || tag === 'TEXTAREA' || el.isContentEditable;
        };

        const currentTaskRows = () => Array.from(document.querySelectorAll('.task-row'));

        const applyFilter = (value) => {
            const query = (value || '').trim().toLowerCase();
            const rows = currentTaskRows();
            rows.forEach((row) => {
                const title = row.querySelector('.task-title');
                const text = (title ? title.textContent : '').toLowerCase();
                row.style.display = query === '' || text.includes(query) ? '' : 'none';
            });
        };

        window.applyTaskSearchFilter = applyFilter;
        window.getTaskSearchValue = () => searchInput ? searchInput.value : '';

        const expandSearch = () => {
            if (searchContainer.classList.contains('expanded')) return;
            searchContainer.classList.add('expanded');
            searchContainer.setAttribute('aria-expanded', 'true');
            searchInput.removeAttribute('tabindex');
            requestAnimationFrame(() => {
                searchInput.focus({ preventScroll: true });
                searchInput.select();
            });
        };

        const collapseSearch = (clearValue) => {
            searchContainer.classList.remove('expanded');
            searchContainer.setAttribute('aria-expanded', 'false');
            searchInput.setAttribute('tabindex', '-1');
            if (clearValue) {
                if (searchInput.value !== '') {
                    searchInput.value = '';
                    applyFilter('');
                }
            }
        };

        searchToggle.addEventListener('click', () => {
            expandSearch();
        });

        clearButton.addEventListener('click', () => {
            collapseSearch(true);
        });

        searchInput.addEventListener('input', (event) => {
            applyFilter(event.target.value);
        });

        searchInput.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                collapseSearch(true);
            }
        });

        document.addEventListener('keydown', (event) => {
            const activeEl = document.activeElement;
            const typing = isTypingField(activeEl);
            if (event.key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey) {
                if (!typing) {
                    event.preventDefault();
                    expandSearch();
                }
                return;
            }
            if ((event.key === 'f' || event.key === 'F') && (event.ctrlKey || event.metaKey) && !event.shiftKey) {
                if (!typing) {
                    event.preventDefault();
                    expandSearch();
                }
                return;
            }
            if (event.key === 'Escape' && searchContainer.classList.contains('expanded')) {
                event.preventDefault();
                collapseSearch(true);
            }
        });

        document.addEventListener('click', (event) => {
            if (!searchContainer.classList.contains('expanded')) return;
            if (searchContainer.contains(event.target)) return;
            if (searchInput.value.trim() === '') {
                collapseSearch(true);
            }
        });
    })();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
  const pendingStarKey = 'pendingStarToggles';
  const starOverrideKey = 'starStateOverrides';
  function loadPendingStars() {
    try {
      const raw = localStorage.getItem(pendingStarKey);
      const parsed = raw ? JSON.parse(raw) : [];
      if (Array.isArray(parsed)) return parsed;
    } catch (e) {}
    return [];
  }
  let pendingStarQueue = loadPendingStars();

  function loadStarOverrides() {
    try {
      const raw = localStorage.getItem(starOverrideKey);
      const parsed = raw ? JSON.parse(raw) : {};
      if (parsed && typeof parsed === 'object') return parsed;
    } catch (e) {}
    return {};
  }

  let starOverrides = loadStarOverrides();

  function persistPendingStars() {
    try { localStorage.setItem(pendingStarKey, JSON.stringify(pendingStarQueue)); } catch (e) {}
  }

  function persistStarOverrides() {
    try { localStorage.setItem(starOverrideKey, JSON.stringify(starOverrides)); } catch (e) {}
  }

  function recordStarOverride(id, starred) {
    const key = String(id);
    starOverrides[key] = { starred: starred ? 1 : 0, at: Date.now() };
    persistStarOverrides();
  }

  function clearMatchingOverride(id, starred) {
    const key = String(id);
    const entry = starOverrides[key];
    if (entry && entry.starred === (starred ? 1 : 0)) {
      delete starOverrides[key];
      persistStarOverrides();
    }
  }

  function latestStarOverride(id) {
    const entry = starOverrides[String(id)];
    if (entry && typeof entry === 'object' && 'starred' in entry) return entry;
    return null;
  }

  function latestPendingFor(id) {
    let latest = null;
    for (const entry of pendingStarQueue) {
      if (String(entry.id) === String(id)) {
        if (!latest || entry.at > latest.at) latest = entry;
      }
    }
    return latest;
  }

  function setStarAppearance(button, starred) {
    button.classList.toggle('starred', !!starred);
    button.setAttribute('aria-pressed', starred ? 'true' : 'false');
    button.setAttribute('aria-label', starred ? 'Unstar task' : 'Star task');
    const icon = button.querySelector('.star-icon');
    if (icon) icon.textContent = starred ? '★' : '☆';
    const sr = button.querySelector('.visually-hidden');
    if (sr) sr.textContent = starred ? 'Starred' : 'Not starred';
  }

  function enqueuePendingStar(id, starred) {
    const entry = { id: Number(id), starred: starred ? 1 : 0, at: Date.now() };
    pendingStarQueue = pendingStarQueue.filter(item => !(String(item.id) === String(id) && item.starred === entry.starred));
    pendingStarQueue.push(entry);
    persistPendingStars();
    recordStarOverride(id, starred);
    return entry;
  }

  function clearPendingUpTo(entry) {
    pendingStarQueue = pendingStarQueue.filter(item => !(String(item.id) === String(entry.id) && item.at <= entry.at));
    persistPendingStars();
  }

  function findStarButton(id) {
    return document.querySelector('.star-toggle[data-id="' + id + '"]');
  }

  function sendStarUpdate(entry, options = {}) {
    const data = new FormData();
    data.append('id', entry.id);
    data.append('starred', entry.starred);

    const request = fetch('toggle_star.php', {
      method: 'POST',
      body: data,
      headers: {'Accept': 'application/json', 'X-Requested-With': 'fetch'}
    });

    const tracked = window.trackBackgroundSync ? window.trackBackgroundSync(request, {
      syncing: entry.starred ? 'Starring task…' : 'Unstarring task…',
      synced: 'Task updated',
      error: 'Could not reach server'
    }) : request;

    return tracked.then(resp => resp && resp.ok ? resp.json() : Promise.reject())
      .then(json => {
        if (!json || json.status !== 'ok') throw new Error('Update failed');
        const btn = findStarButton(entry.id);
        if (btn) setStarAppearance(btn, json.starred);
        recordStarOverride(entry.id, json.starred);
        clearMatchingOverride(entry.id, json.starred);
        clearPendingUpTo(entry);
        if (!options.silent && window.updateSyncStatus) window.updateSyncStatus('synced');
      })
      .catch(err => {
        if (!options.silent && window.updateSyncStatus) window.updateSyncStatus('error', 'Could not reach server');
        throw err;
      });
  }

  function bindStarButton(button) {
    button.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      const id = this.dataset.id;
      if (!id) return;
      const next = this.getAttribute('aria-pressed') === 'true' ? 0 : 1;

      setStarAppearance(this, next);
      const queued = enqueuePendingStar(id, next);

      sendStarUpdate(queued).catch(() => {});
    });
  }

  function applyPendingStars(buttons) {
    buttons.forEach(btn => {
      const pending = latestPendingFor(btn.dataset.id);
      const override = latestStarOverride(btn.dataset.id);
      const chosen = (() => {
        if (pending && override) return pending.at >= override.at ? pending : override;
        return pending || override || null;
      })();
      if (chosen) setStarAppearance(btn, chosen.starred);
    });
  }

  function flushPendingStars() {
    if (!pendingStarQueue.length) return;
    const latestById = {};
    for (const entry of pendingStarQueue) {
      if (!latestById[entry.id] || entry.at > latestById[entry.id].at) {
        latestById[entry.id] = entry;
      }
    }
    const entries = Object.values(latestById);
    entries.forEach(entry => {
      sendStarUpdate(entry, { silent: true }).catch(() => {});
    });
  }

  const starButtons = document.querySelectorAll('.star-toggle');
  starButtons.forEach(bindStarButton);
  applyPendingStars(starButtons);
  flushPendingStars();
  window.addEventListener('online', flushPendingStars);

  function isoDateFromToday(offset) {
    const now = new Date();
    now.setHours(0, 0, 0, 0);
    now.setDate(now.getDate() + offset);
    return now.toISOString().slice(0, 10);
  }

  function updateTaskRowUI(taskEl, payload) {
    if (!taskEl) return;
    if (payload.priority !== undefined && payload.priority !== null) {
      taskEl.dataset.priority = payload.priority;
    }
    if (payload.due_date !== undefined && payload.due_date !== null) {
      taskEl.dataset.dueDate = payload.due_date;
    }

    const badge = taskEl.querySelector('.due-date-badge');
    if (badge) {
      if (payload.due_label) {
        badge.textContent = payload.due_label;
        badge.className = 'badge due-date-badge ' + (payload.due_class || '');
      } else {
        badge.textContent = '';
        badge.className = 'due-date-badge';
      }
    }

    const priorityEl = taskEl.querySelector('.priority-text');
    if (priorityEl) {
      priorityEl.textContent = payload.priority_label || '';
      priorityEl.className = 'small priority-text ' + (payload.priority_class || '');
    }
  }

  const deletedTaskKey = 'deletedTaskIds';
  function consumeDeletedTasks() {
    const raw = sessionStorage.getItem(deletedTaskKey);
    if (!raw) return;
    let ids = [];
    try {
      const parsed = JSON.parse(raw);
      ids = Array.isArray(parsed) ? parsed : [parsed];
    } catch (err) {
      ids = [raw];
    }
    const remaining = [];
    ids.forEach(id => {
      const normalized = Number(id);
      const target = document.querySelector('.task-row[data-task-id="' + normalized + '"]');
      if (target) {
        target.remove();
      } else {
        remaining.push(normalized);
      }
    });
    if (remaining.length) {
      sessionStorage.setItem(deletedTaskKey, JSON.stringify(remaining));
    } else {
      sessionStorage.removeItem(deletedTaskKey);
    }
    if (window.applyTaskSearchFilter) {
      const currentQuery = window.getTaskSearchValue ? window.getTaskSearchValue() : '';
      window.applyTaskSearchFilter(currentQuery);
    }
  }
  consumeDeletedTasks();

  const taskReloadKey = 'taskListNeedsReload';

  function consumeTaskReloadFlag() {
    const needsReload = sessionStorage.getItem(taskReloadKey);
    if (!needsReload) return false;
    sessionStorage.removeItem(taskReloadKey);
    return true;
  }

  function reloadIfPendingUpdates() {
    if (consumeTaskReloadFlag()) {
      location.reload();
      return true;
    }
    return false;
  }

  // Run an early check in case the page was reloaded normally (not from bfcache)
  // so the refreshed data is fetched immediately when returning from edits.
  reloadIfPendingUpdates();

  window.addEventListener('pageshow', e => {
    consumeDeletedTasks();
    if (reloadIfPendingUpdates()) return;
    if (e.persisted) location.reload();
  });
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden) {
      if (reloadIfPendingUpdates()) return;
      location.reload();
    }
  });

  const contextMenu = document.createElement('div');
  contextMenu.className = 'task-context-menu d-none';
  contextMenu.innerHTML = `
    <div class="context-header">Quick edit</div>
    <div class="context-group" data-group="due">
      <div class="context-label">Due date</div>
      <button type="button" data-action="due" data-value="today">Today <span class="badge bg-success-subtle text-success">Today</span></button>
      <button type="button" data-action="due" data-value="tomorrow">Tomorrow <span class="badge bg-primary-subtle text-primary">Tomorrow</span></button>
      <button type="button" data-action="due" data-value="next-week">Next week <span class="badge bg-primary-subtle text-primary">Later</span></button>
      <button type="button" data-action="due" data-value="clear">No due date</button>
    </div>
    <div class="context-group" data-group="priority">
      <div class="context-label">Priority</div>
      <button type="button" data-action="priority" data-value="3">High</button>
      <button type="button" data-action="priority" data-value="2">Medium</button>
      <button type="button" data-action="priority" data-value="1">Low</button>
      <button type="button" data-action="priority" data-value="0">None</button>
    </div>
  `;
  document.body.appendChild(contextMenu);

  let contextTask = null;

  function setActiveOption(group, value) {
    contextMenu.querySelectorAll(`.context-group[data-group="${group}"] button`).forEach(btn => {
      btn.classList.toggle('active', btn.dataset.value === value);
    });
  }

  function updateActiveOptions(taskEl) {
    const priorityVal = taskEl.dataset.priority || '0';
    setActiveOption('priority', priorityVal);

    const dueDate = (taskEl.dataset.dueDate || '').slice(0, 10);
    let dueChoice = '';
    if (!dueDate) {
      dueChoice = 'clear';
    } else if (dueDate === isoDateFromToday(0)) {
      dueChoice = 'today';
    } else if (dueDate === isoDateFromToday(1)) {
      dueChoice = 'tomorrow';
    } else if (dueDate === isoDateFromToday(7)) {
      dueChoice = 'next-week';
    }
    setActiveOption('due', dueChoice);
  }

    function hideContextMenu() {
      contextMenu.classList.add('d-none');
      contextTask = null;
    }

    function setContextMode(mode) {
      const header = contextMenu.querySelector('.context-header');
      if (header) {
        header.textContent = mode === 'priority' ? 'Set priority' : 'Set due date';
      }
      contextMenu.dataset.mode = mode;
      contextMenu.querySelectorAll('.context-group').forEach(group => {
        group.classList.toggle('d-none', group.dataset.group !== mode);
      });
    }

    function showContextMenu(taskEl, x, y, mode) {
      contextTask = taskEl;
      setContextMode(mode);
      updateActiveOptions(taskEl);
      contextMenu.classList.remove('d-none');
      contextMenu.style.left = '0px';
      contextMenu.style.top = '0px';
      const { width, height } = contextMenu.getBoundingClientRect();
      const padding = 8;
      const maxLeft = window.innerWidth - width - padding;
      const maxTop = window.innerHeight - height - padding;
      const left = Math.min(Math.max(padding, x), Math.max(padding, maxLeft));
      const top = Math.min(Math.max(padding, y), Math.max(padding, maxTop));
      contextMenu.style.left = `${left}px`;
      contextMenu.style.top = `${top}px`;
    }

    contextMenu.addEventListener('click', function(e){
      const btn = e.target.closest('button[data-action]');
      if (!btn || !contextTask) return;
      e.preventDefault();
      const taskId = contextTask.dataset.taskId;
      if (!taskId) {
        hideContextMenu();
        return;
      }
      const data = new FormData();
      data.append('id', taskId);
      if (btn.dataset.action === 'priority') {
        data.append('priority', btn.dataset.value);
      } else if (btn.dataset.action === 'due') {
        data.append('due_shortcut', btn.dataset.value);
      }

      if (window.updateSyncStatus) window.updateSyncStatus('syncing', 'Updating task…');

      const request = fetch('update_task_meta.php', {
        method: 'POST',
        body: data,
        headers: {'Accept': 'application/json', 'X-Requested-With': 'fetch'}
      });

      const tracked = window.trackBackgroundSync ? window.trackBackgroundSync(request, {syncing: 'Updating task…'}) : request;

      tracked.then(resp => resp && resp.ok ? resp.json() : Promise.reject())
        .then(json => {
          if (!json || json.status !== 'ok') throw new Error('Update failed');
          updateTaskRowUI(contextTask, json);
          if (window.updateSyncStatus) window.updateSyncStatus('synced');
        })
        .catch(() => {
          if (window.updateSyncStatus) window.updateSyncStatus('error', 'Could not update task');
        })
        .finally(hideContextMenu);
    });

    document.addEventListener('contextmenu', function(e){
      const targetDue = e.target.closest('.due-date-badge');
      const targetPriority = e.target.closest('.priority-text');
      const targetGroup = targetDue ? 'due' : (targetPriority ? 'priority' : null);
      if (!targetGroup) return;

      const taskEl = e.target.closest('.task-row');
      if (!taskEl) return;
      if (!window.matchMedia('(pointer: fine)').matches) return;
      e.preventDefault();
      showContextMenu(taskEl, e.clientX, e.clientY, targetGroup);
    });

    document.addEventListener('click', function(e){
      if (contextMenu.contains(e.target)) return;
      hideContextMenu();
    });

    window.addEventListener('scroll', hideContextMenu, true);
    window.addEventListener('resize', hideContextMenu);
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') hideContextMenu(); });

  const form = document.querySelector('form[action="add_task.php"]');
  const listGroup = document.querySelector('.container .list-group');
  let isFallbackSubmit = false;
  if (form && listGroup) {
    form.addEventListener('submit', function(e){
      if (isFallbackSubmit) return;
      e.preventDefault();
      const data = new FormData(form);
      const description = (data.get('description') || '').toString().trim();
      if (!description) return;

      const tempItem = document.createElement('a');
      tempItem.className = 'list-group-item list-group-item-action task-row opacity-75';
      tempItem.innerHTML = `<div class="task-main task-title">${description}</div><div class="task-meta"><span class="badge due-date-badge bg-primary-subtle text-primary">Today</span><span class="small priority-text text-secondary">Saving…</span><button type="button" class="task-star star-toggle" aria-pressed="false" disabled><span class="star-icon" aria-hidden="true">☆</span><span class="visually-hidden">Not starred</span></button></div>`;
      listGroup.prepend(tempItem);

      if (window.applyTaskSearchFilter) {
        window.applyTaskSearchFilter(window.getTaskSearchValue ? window.getTaskSearchValue() : '');
      }

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
        tempItem.dataset.taskId = json.id;
        tempItem.dataset.dueDate = json.due_date || '';
        tempItem.dataset.priority = json.priority ?? '0';
        if (window.updateSyncStatus) window.updateSyncStatus('synced');
      })
      .catch(() => {
        tempItem.remove();
        const descInput = form.querySelector('input[name="description"]');
        if (descInput) descInput.value = description;
        isFallbackSubmit = true;
        form.submit();
      })
      .finally(() => {
        if (!isFallbackSubmit) {
          form.reset();
        }
      });
    });
  }
</script>
</body>
</html>
