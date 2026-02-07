<?php
require_once 'db.php';
require_once 'hashtags.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
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

$stmt = $db->prepare("
    SELECT id, description, due_date, details, done, priority, starred
    FROM tasks
    WHERE user_id = :uid AND done = 0
    ORDER BY
        CASE
            WHEN due_date IS NULL OR due_date = '' THEN 0
            WHEN due_date < :today THEN 1
            WHEN due_date = :today THEN 2
            WHEN due_date = :tomorrow THEN 3
            ELSE 4
        END,
        priority DESC,
        starred DESC,
        id DESC
");

$stmt->execute([
    ':uid' => $_SESSION['user_id'],
    ':today' => $todayFmt,
    ':tomorrow' => $tomorrowFmt,
]);
$tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
$priority_labels = [0 => 'None', 1 => 'Low', 2 => 'Medium', 3 => 'High'];
$priority_labels_short = [0 => 'Non', 1 => 'Low', 2 => 'Med', 3 => 'Hig'];
$priority_classes = [0 => 'text-secondary', 1 => 'text-success', 2 => 'text-warning', 3 => 'text-danger'];
$task_ids = array_column($tasks, 'id');
$task_hashtags = get_hashtags_for_tasks($db, (int)$_SESSION['user_id'], $task_ids);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .navbar-toggler {
            border: 1px solid #e9ecef;
        }
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
            display: inline-flex;
            align-items: center;
            justify-content: flex-end;
            gap: 0.5rem;
            width: 100%;
        }
        .due-date-badge {
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 80px;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
        }
        .priority-text { text-align: center; min-width: 70px; }
        .star-toggle {
            min-width: 44px;
            display: inline-flex;
            justify-content: center;
            margin: 0;
        }
        .task-star {
            border: none;
            border-radius: 4px;
            padding: 0;
            background: transparent;
            margin: 0;
            cursor: pointer;
        }
        .task-star:focus-visible { outline: 2px solid #0a2a66; outline-offset: 2px; }
        .task-star:active { background: transparent; }
        .star-icon { font-size: 2rem; line-height: 1; color:rgba(108, 117, 125, 0.51); }
        .starred .star-icon { color: #f4ca4c }
        .task-hashtags { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.35rem; }
        .hashtag-badge { background-color: #f3e8ff; color: #6f42c1; border: 1px solid #e5d4ff; }
        .task-hashtags .placeholder { color: #6c757d; font-size: 0.9rem; }
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
        #hashtagManagerModal .modal-content { border-radius: 1.1rem; }
        #hashtagManagerModal .modal-header { border-bottom: none; padding-bottom: 0; }
        .hashtag-manager-hero { background: linear-gradient(135deg, #f4edff 0%, #f7fbff 100%); border: 1px solid #ebe6ff; box-shadow: 0 0.75rem 1.5rem rgba(17, 24, 39, 0.07); }
        .hashtag-manager-stats .badge { padding: 0.65rem 0.8rem; border: 1px solid rgba(99, 102, 241, 0.15); }
        .hashtag-manager-list { margin-top: 0.25rem; }
        .hashtag-manage-card { position: relative; border: 1px solid #edeaf7; border-radius: 1rem; padding: 1rem; box-shadow: 0 0.65rem 1.3rem rgba(31, 41, 55, 0.08); background: #fff; overflow: hidden; }
        .hashtag-manage-card::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 6px; background: linear-gradient(180deg, #7c3aed, #5b21b6); opacity: 0.18; }
        .hashtag-chip { background-color: #f1eaff; color: #5a2ea6; border: 1px solid #e3d7ff; letter-spacing: 0.04em; font-weight: 700; }
        .hashtag-usage-badge { background: #f6f7fb; color: #4b5563; border: 1px solid #e9ecf5; }
        .hashtag-actions .btn { min-width: 96px; }
        .hashtag-manager-empty { color: #6c757d; background: #f9fafb; border: 1px dashed #d9dfe7; border-radius: 0.85rem; }
        .hashtag-edit-card { background: #f8f9ff; border: 1px solid #e3e3ff; border-radius: 0.85rem; padding: 0.75rem; }
        .hashtag-edit-card input { max-width: 260px; }
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
        .search-toggle svg { width: 1.2rem; height: 1.2rem; display: block; transform: translateX(0.1rem); flex-shrink: 0; }
        .search-toggle svg path { fill: none; stroke: currentColor; stroke-width: 2.1; stroke-linecap: round; stroke-linejoin: round; }
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
                grid-template-columns: minmax(0, 1fr) auto;
                column-gap: 0.5rem;
                padding-right: 0.5rem;
            }
            .task-meta {
                justify-content: flex-end;
                gap: 0.15rem;
            }
            .due-date-badge, .priority-text { width: auto; }
            .due-date-badge {
                min-width: 56px;
                padding-left: 0.3rem;
                padding-right: 0.3rem;
            }
            .priority-text { min-width: 44px; }
            .star-toggle { min-width: 40px; }
            .task-star { padding: 0; }
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
                        <path d="M11 4a7 7 0 1 1 0 14 7 7 0 0 1 0-14z" />
                        <path d="m15.75 15.75 4.25 4.25" />
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
            <button type="button" class="list-group-item list-group-item-action text-start" data-bs-toggle="modal" data-bs-target="#hashtagManagerModal" id="openHashtagManager">Manage hashtags</button>
            <a href="settings.php" class="list-group-item list-group-item-action">Settings</a>
            <a href="logout.php" class="list-group-item list-group-item-action">Logout</a>
        </div>
        <div class="mt-3 small text-muted" id="sync-status" aria-live="polite">All changes saved</div>
    </div>
</div>
<div class="modal fade" id="hashtagManagerModal" tabindex="-1" aria-labelledby="hashtagManagerLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="hashtagManagerLabel">Manage hashtags</h5>
                    <p class="mb-0 text-muted small">Curate your tags, keep naming consistent, and tidy up unused ones.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="hashtag-manager-hero d-flex flex-column flex-md-row align-items-md-center gap-3 mb-4 p-3 rounded-4">
                    <div class="flex-grow-1">
                        <div class="fw-semibold mb-1">Organize every hashtag in one place</div>
                        <div class="text-muted small">Rename for consistency, prune unused tags, and create new ones without leaving your tasks.</div>
                    </div>
                    <div class="hashtag-manager-stats d-flex gap-2 flex-wrap">
                        <span class="badge bg-primary-subtle text-primary">Total <span id="hashtagTotal">0</span></span>
                        <span class="badge bg-success-subtle text-success">In use <span id="hashtagInUse">0</span></span>
                    </div>
                </div>
                <form class="hashtag-add-form mb-3" id="newHashtagForm">
                    <label for="newHashtagInput" class="form-label fw-semibold mb-2">Add a new hashtag</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white text-muted">#</span>
                        <input type="text" id="newHashtagInput" class="form-control" placeholder="project-alpha" autocomplete="off">
                        <button type="submit" class="btn btn-primary">Add hashtag</button>
                    </div>
                    <div class="form-text">Names are saved without the # symbol and automatically lowercased.</div>
                </form>
                <div class="hashtag-manager-list d-flex flex-column gap-3" id="hashtagManagerList" aria-live="polite"></div>
                <div class="mt-3 small" id="hashtagManagerStatus"></div>
            </div>
        </div>
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
            <?php $hashtags = $task_hashtags[$task['id']] ?? []; $hashtag_text = implode(' ', array_map(function($tag){ return '#'.$tag; }, $hashtags)); ?>
            <a href="task.php?id=<?=$task['id']?>" class="list-group-item list-group-item-action task-row" data-task-id="<?=$task['id']?>" data-due-date="<?=htmlspecialchars($rawDue ?? '')?>" data-priority="<?=$p?>" data-starred="<?=!empty($task['starred']) ? '1' : '0'?>" data-hashtags="<?=htmlspecialchars($hashtag_text)?>">
                <div class="task-main">
                    <div class="task-title <?php if ($task['done']) echo 'text-decoration-line-through'; ?>">&ZeroWidthSpace;<?=htmlspecialchars(ucwords(strtolower($task['description'] ?? '')))?></div>
                    <div class="task-hashtags">
                        <?php foreach ($hashtags as $tag): ?>
                            <span class="badge hashtag-badge">#<?=htmlspecialchars($tag)?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="task-meta">
                    <?php if ($due !== ''): ?>
                        <span class="badge due-date-badge <?=$dueClass?>" aria-label="<?=htmlspecialchars($due)?>">
                            <?=htmlspecialchars($due)?>
                        </span>
                    <?php else: ?>
                        <span class="due-date-badge"></span>
                    <?php endif; ?>
                    <span class="small priority-text <?=$priority_classes[$p]?>" aria-label="<?=htmlspecialchars($priority_labels[$p])?>">
                        <span class="d-none d-md-inline"><?=$priority_labels[$p]?></span>
                        <span class="d-inline d-md-none"><?=$priority_labels_short[$p]?></span>
                    </span>
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
                const tags = (row.dataset.hashtags || '').toLowerCase();
                const combined = `${text} ${tags}`.trim();
                row.style.display = query === '' || combined.includes(query) ? '' : 'none';
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
  (function() {
    const modalEl = document.getElementById('hashtagManagerModal');
    const listEl = document.getElementById('hashtagManagerList');
    const statusEl = document.getElementById('hashtagManagerStatus');
    const formEl = document.getElementById('newHashtagForm');
    const inputEl = document.getElementById('newHashtagInput');
    const totalEl = document.getElementById('hashtagTotal');
    const inUseEl = document.getElementById('hashtagInUse');
    if (!modalEl || !listEl) return;

    let hashtags = [];
    let isLoading = false;

    const setStatus = (message, tone = 'muted') => {
      if (!statusEl) return;
      statusEl.className = `mt-3 small text-${tone}`;
      statusEl.textContent = message || '';
    };

    const updateStats = () => {
      if (totalEl) totalEl.textContent = hashtags.length;
      if (inUseEl) {
        const used = hashtags.filter(tag => Number(tag.usage) > 0).length;
        inUseEl.textContent = used;
      }
    };

    const renderList = () => {
      listEl.innerHTML = '';
      updateStats();
      if (!hashtags.length) {
        const empty = document.createElement('div');
        empty.className = 'text-center py-4 px-3 hashtag-manager-empty';
        empty.textContent = 'No hashtags yet. Add one to get started.';
        listEl.appendChild(empty);
        return;
      }

      hashtags.forEach(tag => {
        const item = document.createElement('article');
        item.className = 'hashtag-manage-card';

        const viewRow = document.createElement('div');
        viewRow.className = 'd-flex align-items-center gap-3 flex-wrap flex-md-nowrap';

        const label = document.createElement('div');
        label.className = 'd-flex align-items-center gap-3 flex-grow-1 flex-wrap';
        const badge = document.createElement('span');
        badge.className = 'badge rounded-pill hashtag-chip px-3 py-2 text-uppercase';
        badge.textContent = '#' + tag.name;
        const meta = document.createElement('div');
        meta.className = 'd-flex align-items-center gap-2 text-muted small flex-wrap';
        const usage = document.createElement('span');
        usage.className = 'badge rounded-pill hashtag-usage-badge';
        usage.textContent = `${tag.usage} use${tag.usage === 1 ? '' : 's'}`;
        meta.appendChild(usage);
        label.appendChild(badge);
        label.appendChild(meta);

        const actions = document.createElement('div');
        actions.className = 'hashtag-actions d-flex align-items-center gap-2 ms-md-auto';

        const renameBtn = document.createElement('button');
        renameBtn.type = 'button';
        renameBtn.className = 'btn btn-outline-primary btn-sm';
        renameBtn.textContent = 'Rename';

        const deleteBtn = document.createElement('button');
        deleteBtn.type = 'button';
        deleteBtn.className = 'btn btn-outline-danger btn-sm';
        deleteBtn.textContent = 'Delete';

        const editForm = document.createElement('form');
        editForm.className = 'hashtag-edit-card d-flex flex-column flex-md-row align-items-md-center gap-2';
        editForm.hidden = true;

        const editInput = document.createElement('input');
        editInput.type = 'text';
        editInput.required = true;
        editInput.className = 'form-control';
        editInput.value = tag.name;
        editInput.setAttribute('aria-label', 'New hashtag name');

        const saveBtn = document.createElement('button');
        saveBtn.type = 'submit';
        saveBtn.className = 'btn btn-primary';
        saveBtn.textContent = 'Save changes';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'btn btn-light';
        cancelBtn.textContent = 'Cancel';

        editForm.appendChild(editInput);
        editForm.appendChild(saveBtn);
        editForm.appendChild(cancelBtn);

        const toggleEdit = (open) => {
          editForm.hidden = !open;
          viewRow.hidden = open;
          if (open) {
            editInput.value = tag.name;
            setTimeout(() => editInput.focus(), 50);
          }
        };

        renameBtn.addEventListener('click', () => toggleEdit(true));
        cancelBtn.addEventListener('click', () => toggleEdit(false));

        const sendAction = async (params) => {
          setStatus('Saving changes…', 'secondary');
          const resp = await fetch('manage_hashtags.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
            body: new URLSearchParams(params),
          });
          const json = await resp.json().catch(() => null);
          if (!resp.ok || !json || json.status !== 'ok') {
            throw new Error(json && json.message ? json.message : 'Unable to save hashtag');
          }
          hashtags = Array.isArray(json.hashtags) ? json.hashtags : [];
          renderList();
          setStatus('Saved', 'success');
        };

        editForm.addEventListener('submit', async (event) => {
          event.preventDefault();
          const next = editInput.value.trim();
          if (!next) {
            setStatus('Hashtag cannot be empty', 'danger');
            return;
          }
          renameBtn.disabled = true;
          deleteBtn.disabled = true;
          saveBtn.disabled = true;
          try {
            await sendAction({ action: 'rename', id: tag.id, name: next });
            toggleEdit(false);
          } catch (err) {
            setStatus(err.message || 'Rename failed', 'danger');
          } finally {
            renameBtn.disabled = false;
            deleteBtn.disabled = false;
            saveBtn.disabled = false;
          }
        });

        deleteBtn.addEventListener('click', async () => {
          if (!confirm('Delete #' + tag.name + '? Tasks will lose this hashtag.')) return;
          renameBtn.disabled = true;
          deleteBtn.disabled = true;
          try {
            await sendAction({ action: 'delete', id: tag.id });
          } catch (err) {
            setStatus(err.message || 'Delete failed', 'danger');
          } finally {
            renameBtn.disabled = false;
            deleteBtn.disabled = false;
          }
        });

        actions.appendChild(renameBtn);
        actions.appendChild(deleteBtn);

        viewRow.appendChild(label);
        viewRow.appendChild(actions);
        item.appendChild(viewRow);
        item.appendChild(editForm);
        listEl.appendChild(item);
      });
    };

    const loadHashtags = async () => {
      if (isLoading) return;
      isLoading = true;
      setStatus('Loading hashtags…', 'secondary');
      try {
        const resp = await fetch('manage_hashtags.php', {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
          credentials: 'same-origin',
          cache: 'no-store',
        });
        const fallback = resp.clone();
        const json = await resp.json().catch(() => null);
        if (!resp.ok || !json || json.status !== 'ok') {
          const text = !json ? await fallback.text().catch(() => '') : '';
          const detail = (json && json.message) || (text ? text.slice(0, 140) : '');
          throw new Error(detail || 'Unable to load hashtags');
        }
        hashtags = Array.isArray(json.hashtags) ? json.hashtags : [];
        renderList();
        setStatus(hashtags.length ? '' : 'Start by adding your first hashtag.', hashtags.length ? 'muted' : 'info');
      } catch (err) {
        setStatus(err.message || 'Unable to load hashtags', 'danger');
      } finally {
        isLoading = false;
      }
    };

    if (formEl) {
      formEl.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!inputEl) return;
        const value = inputEl.value.trim();
        if (!value) {
          setStatus('Please enter a hashtag name', 'danger');
          return;
        }
        inputEl.disabled = true;
        try {
          await (async () => {
            const resp = await fetch('manage_hashtags.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-Requested-With': 'fetch' },
              body: new URLSearchParams({ action: 'create', name: value }),
            });
            const json = await resp.json().catch(() => null);
            if (!resp.ok || !json || json.status !== 'ok') {
              throw new Error(json && json.message ? json.message : 'Unable to add hashtag');
            }
            hashtags = Array.isArray(json.hashtags) ? json.hashtags : [];
            renderList();
            setStatus('Added #' + value.replace(/^#+/, '').toLowerCase(), 'success');
            inputEl.value = '';
          })();
        } catch (err) {
          setStatus(err.message || 'Could not add hashtag', 'danger');
        } finally {
          inputEl.disabled = false;
          inputEl.focus();
        }
      });
    }

    modalEl.addEventListener('show.bs.modal', () => {
      setStatus('', 'muted');
      loadHashtags();
    });

    modalEl.addEventListener('shown.bs.modal', () => {
      if (inputEl) inputEl.focus();
    });
  })();
</script>
<script>
  const optimisticDefaultPriority = <?= (int)($_SESSION['default_priority'] ?? 0) ?>;
  const optimisticTodayIso = '<?= htmlspecialchars($todayFmt, ENT_QUOTES) ?>';
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

  function rowSortValueDate(row) {
    const raw = (row.dataset.dueDate || '').slice(0, 10);
    return raw && /^\d{4}-\d{2}-\d{2}$/.test(raw) ? raw : null;
  }

  function dueStatusBucket(row) {
    const due = rowSortValueDate(row);
    if (!due) return 0;
    const today = toIsoDate(0);
    const tomorrow = toIsoDate(1);
    if (due < today) return 1;
    if (due === today) return 2;
    if (due === tomorrow) return 3;
    return 4;
  }

  function rowSortValueInt(row, key) {
    const value = Number(row.dataset[key] || 0);
    return Number.isFinite(value) ? value : 0;
  }

  function compareTaskRows(a, b) {
    const aBucket = dueStatusBucket(a);
    const bBucket = dueStatusBucket(b);
    if (aBucket !== bBucket) return aBucket - bBucket;

    const aPriority = rowSortValueInt(a, 'priority');
    const bPriority = rowSortValueInt(b, 'priority');
    if (aPriority !== bPriority) return bPriority - aPriority;

    const aStarred = rowSortValueInt(a, 'starred');
    const bStarred = rowSortValueInt(b, 'starred');
    if (aStarred !== bStarred) return bStarred - aStarred;

    const aId = rowSortValueInt(a, 'taskId');
    const bId = rowSortValueInt(b, 'taskId');
    return bId - aId;
  }

  function reorderTaskRows() {
    const list = document.querySelector('.container .list-group');
    if (!list) return;
    const rows = Array.from(list.querySelectorAll('.task-row'));
    if (!rows.length) return;
    rows.sort(compareTaskRows);
    rows.forEach(row => list.appendChild(row));
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
        if (btn) {
          setStarAppearance(btn, json.starred);
          const row = btn.closest('.task-row');
          if (row) {
            row.dataset.starred = json.starred ? '1' : '0';
            reorderTaskRows();
          }
        }
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
      const row = this.closest('.task-row');
      if (row) {
        row.dataset.starred = next ? '1' : '0';
        reorderTaskRows();
      }
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
      if (chosen) {
        setStarAppearance(btn, chosen.starred);
        const row = btn.closest('.task-row');
        if (row) row.dataset.starred = chosen.starred ? '1' : '0';
      }
    });
    reorderTaskRows();
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
    if (payload.starred !== undefined && payload.starred !== null) {
      taskEl.dataset.starred = payload.starred ? '1' : '0';
    }
    if (payload.due_date !== undefined && payload.due_date !== null) {
      taskEl.dataset.dueDate = payload.due_date;
    }
    if (Array.isArray(payload.hashtags)) {
      taskEl.dataset.hashtags = payload.hashtags.map(tag => '#' + tag).join(' ');
      const tagContainer = taskEl.querySelector('.task-hashtags');
      renderHashtagRow(tagContainer, payload.hashtags);
    }

    const badge = taskEl.querySelector('.due-date-badge');
    if (badge) {
      renderDueBadge(badge, payload.due_label, payload.due_class);
    }

    const priorityEl = taskEl.querySelector('.priority-text');
    if (priorityEl) {
      const priorityVal = typeof payload.priority === 'number'
        ? payload.priority
        : Number(taskEl.dataset.priority || 0);
      renderPriorityText(priorityEl, priorityVal, payload.priority_label, payload.priority_class);
    }
    reorderTaskRows();
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

  function toIsoDate(offsetDays = 0) {
    const d = new Date();
    d.setHours(0, 0, 0, 0);
    d.setDate(d.getDate() + offsetDays);
    return d.toISOString().slice(0, 10);
  }

  function formatDue(dateStr) {
    if (!dateStr) return { label: '', className: '' };
    const today = toIsoDate(0);
    const tomorrow = toIsoDate(1);
    try {
      const dt = new Date(`${dateStr}T00:00:00`);
      const iso = dt.toISOString().slice(0, 10);
      if (iso === today) return { label: 'Today', className: 'bg-success-subtle text-success' };
      if (iso === tomorrow) return { label: 'Tomorrow', className: 'bg-primary-subtle text-primary' };
      if (dt < new Date(today)) return { label: 'Overdue', className: 'bg-danger-subtle text-danger' };
      return { label: 'Later', className: 'bg-primary-subtle text-primary' };
    } catch (err) {
      return { label: '', className: '' };
    }
  }

  const priorityLabels = { 0: 'None', 1: 'Low', 2: 'Medium', 3: 'High' };
  const priorityClasses = { 0: 'text-secondary', 1: 'text-success', 2: 'text-warning', 3: 'text-danger' };
  const priorityLabelsShort = { 0: 'Non', 1: 'Low', 2: 'Med', 3: 'Hig' };

  function renderDueBadge(badge, label, className = '') {
    if (!badge) return;
    if (!label) {
      badge.innerHTML = '';
      badge.className = 'due-date-badge';
      badge.removeAttribute('aria-label');
      return;
    }
    badge.textContent = label;
    badge.className = `badge due-date-badge ${className || ''}`.trim();
    badge.setAttribute('aria-label', label);
  }

  function renderPriorityText(el, priorityValue, label, className = '') {
    if (!el) return;
    const numericPriority = typeof priorityValue === 'number' ? priorityValue : Number(priorityValue || 0);
    const fullLabel = label || priorityLabels[numericPriority] || priorityLabels[0];
    const shortLabel = priorityLabelsShort[numericPriority] || fullLabel;
    el.innerHTML = `<span class="d-none d-md-inline">${fullLabel}</span><span class="d-inline d-md-none">${shortLabel}</span>`;
    el.className = `small priority-text ${className || priorityClasses[numericPriority] || priorityClasses[0]}`.trim();
    el.setAttribute('aria-label', fullLabel);
  }

  function renderHashtagRow(container, hashtags) {
    if (!container) return;
    container.innerHTML = '';
    if (!Array.isArray(hashtags) || !hashtags.length) return;
    hashtags.forEach(tag => {
      const badge = document.createElement('span');
      badge.className = 'badge hashtag-badge';
      badge.textContent = '#' + tag;
      container.appendChild(badge);
    });
  }

  function applyPendingTaskUpdates() {
    const updates = readPendingUpdates();
    const remaining = { ...updates };
    let changed = false;

    Object.values(updates).forEach(update => {
      const taskId = update.id || update.task_id;
      if (!taskId) return;
      const row = document.querySelector(`.task-row[data-task-id="${taskId}"]`);
      if (!row) return;
      const titleEl = row.querySelector('.task-title');
      if (titleEl && typeof update.description === 'string') {
        titleEl.textContent = update.description || '\u200B';
      }
      if (titleEl && typeof update.done === 'boolean') {
        titleEl.classList.toggle('text-decoration-line-through', update.done);
      }
      if (typeof update.priority === 'number') {
        row.dataset.priority = String(update.priority);
        const priorityEl = row.querySelector('.priority-text');
        if (priorityEl) {
          renderPriorityText(priorityEl, update.priority);
        }
      }
      if (typeof update.starred === 'boolean') {
        row.dataset.starred = update.starred ? '1' : '0';
        const starBtn = row.querySelector('.task-star');
        if (starBtn) {
          starBtn.classList.toggle('starred', update.starred);
          starBtn.setAttribute('aria-pressed', update.starred ? 'true' : 'false');
          starBtn.setAttribute('aria-label', update.starred ? 'Unstar task' : 'Star task');
          const starIcon = starBtn.querySelector('.star-icon');
          if (starIcon) starIcon.textContent = update.starred ? '★' : '☆';
          const srText = starBtn.querySelector('.visually-hidden');
          if (srText) srText.textContent = update.starred ? 'Starred' : 'Not starred';
        }
      }
      if (typeof update.due_date === 'string') {
        row.dataset.dueDate = update.due_date;
        const badge = row.querySelector('.due-date-badge');
        if (badge) {
          const formatted = formatDue(update.due_date);
          renderDueBadge(badge, formatted.label, formatted.className);
        }
      }
      if (Array.isArray(update.hashtags)) {
        row.dataset.hashtags = update.hashtags.map(tag => '#' + tag).join(' ');
        const tags = row.querySelector('.task-hashtags');
        renderHashtagRow(tags, update.hashtags);
      }
      delete remaining[taskId];
      changed = true;
    });

    if (changed) {
      reorderTaskRows();
      writePendingUpdates(remaining);
      if (window.applyTaskSearchFilter) {
        const currentQuery = window.getTaskSearchValue ? window.getTaskSearchValue() : '';
        window.applyTaskSearchFilter(currentQuery);
      }
    }

    return changed;
  }

  function consumeTaskReloadFlag() {
    const needsReload = sessionStorage.getItem(taskReloadKey);
    if (!needsReload) return false;
    sessionStorage.removeItem(taskReloadKey);
    return true;
  }

  function reloadIfPendingUpdates() {
    const updated = applyPendingTaskUpdates();
    if (updated) return true;
    if (consumeTaskReloadFlag()) {
      applyPendingTaskUpdates();
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

      const taskEl = contextTask;
      const taskId = taskEl.dataset.taskId;
      hideContextMenu();

      if (!taskId) {
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
          updateTaskRowUI(taskEl, json);
          if (window.updateSyncStatus) window.updateSyncStatus('synced');
        })
        .catch(() => {
          if (window.updateSyncStatus) window.updateSyncStatus('error', 'Could not update task');
        });
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
  const descriptionInput = form ? form.querySelector('input[name="description"]') : null;
  let isFallbackSubmit = false;
  if (form && listGroup) {
    form.addEventListener('submit', function(e){
      if (isFallbackSubmit) return;
      e.preventDefault();
      const data = new FormData(form);
      const description = (data.get('description') || '').toString().trim();
      if (!description) return;

      if (descriptionInput) descriptionInput.value = '';

      const tempItem = document.createElement('a');
      tempItem.className = 'list-group-item list-group-item-action task-row opacity-75';
      tempItem.dataset.hashtags = '';
      tempItem.dataset.starred = '0';
      tempItem.dataset.taskId = String(Date.now());
      tempItem.dataset.dueDate = optimisticTodayIso;
      tempItem.dataset.priority = String(optimisticDefaultPriority);
      tempItem.innerHTML = `<div class="task-main"><div class="task-title">${description}</div><div class="task-hashtags"></div></div><div class="task-meta"><span class="badge due-date-badge bg-primary-subtle text-primary">Today</span><span class="small priority-text text-secondary">Saving…</span><button type="button" class="task-star star-toggle" aria-pressed="false" disabled><span class="star-icon" aria-hidden="true">☆</span><span class="visually-hidden">Not starred</span></button></div>`;
      listGroup.prepend(tempItem);
      reorderTaskRows();

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
        const title = tempItem.querySelector('.task-title');
        if (title) title.textContent = json.description;
        const badge = tempItem.querySelector('.badge');
        if (badge) {
          renderDueBadge(badge, json.due_label, json.due_class);
        }
        const priority = tempItem.querySelector('.priority-text');
        if (priority) {
          renderPriorityText(priority, json.priority, json.priority_label, json.priority_class);
        }
        const hashtagContainer = tempItem.querySelector('.task-hashtags');
        tempItem.dataset.hashtags = (json.hashtags || []).map(tag => '#' + tag).join(' ');
        renderHashtagRow(hashtagContainer, json.hashtags || []);
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
        tempItem.dataset.starred = json.starred ? '1' : '0';
        reorderTaskRows();
        if (window.updateSyncStatus) window.updateSyncStatus('synced');
      })
      .catch(() => {
        tempItem.remove();
        if (descriptionInput) descriptionInput.value = description;
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
