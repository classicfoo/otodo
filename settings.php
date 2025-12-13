<?php
require_once 'db.php';
require_once 'line_rules.php';
require_once 'date_formats.php';
require_once 'text_expanders.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
$message = '';
$error = '';
$username = $_SESSION['username'] ?? '';
$location = $_SESSION['location'] ?? '';
$default_priority = (int)($_SESSION['default_priority'] ?? 0);
$details_color = $_SESSION['details_color'] ?? '#212529';
$hashtag_color = normalize_hex_color($_SESSION['hashtag_color'] ?? '#6F42C1', '#6F42C1');
$date_color = normalize_hex_color($_SESSION['date_color'] ?? '#FDA90D', '#FDA90D');
$line_rules = $_SESSION['line_rules'] ?? get_default_line_rules();
$capitalize_sentences = isset($_SESSION['capitalize_sentences']) ? (bool)$_SESSION['capitalize_sentences'] : true;
$date_formats = $_SESSION['date_formats'] ?? get_default_date_formats();
$text_expanders = $_SESSION['text_expanders'] ?? [];
$timezones = DateTimeZone::listIdentifiers();
$accepts_json = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
    || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch';

function respond_json($payload, $code = 200) {
    if ($code !== 200) {
        http_response_code($code);
    }
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? $username);
    $password = $_POST['password'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $default_priority = (int)($_POST['default_priority'] ?? 0);
    if ($default_priority < 0 || $default_priority > 3) {
        $default_priority = 0;
    }

    $line_rules_json = $_POST['line_rules_json'] ?? '[]';
    $decoded_rules = json_decode($line_rules_json, true);
    $line_rules = sanitize_line_rules($decoded_rules);
    if (empty($line_rules)) {
        $line_rules = get_default_line_rules();
    }
    $details_color = normalize_editor_color($_POST['details_color'] ?? $details_color);
    $hashtag_color = normalize_hex_color($_POST['hashtag_color'] ?? $hashtag_color, '#6F42C1');
    $date_color = normalize_hex_color($_POST['date_color'] ?? $date_color, '#FDA90D');
    $capitalize_sentences = isset($_POST['capitalize_sentences']);
    $date_formats_input = $_POST['date_formats'] ?? '';
    $date_formats = sanitize_date_formats_input($date_formats_input);
    $text_expanders_input = $_POST['text_expanders_json'] ?? '[]';
    $decoded_expanders = json_decode($text_expanders_input, true);
    $text_expanders = sanitize_text_expanders($decoded_expanders);

    if ($username === '') {
        $error = 'Username cannot be empty';
    } else {
        try {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE users SET username = :username, password = :password, location = :loc, default_priority = :pri, line_rules = :rules, details_color = :color, hashtag_color = :hashtag_color, date_color = :date_color, capitalize_sentences = :capitalize, date_formats = :date_formats, text_expanders = :text_expanders WHERE id = :id');
                $stmt->execute([
                    ':username' => $username,
                    ':password' => $hash,
                    ':loc' => $location !== '' ? $location : null,
                    ':pri' => $default_priority,
                    ':rules' => encode_line_rules_for_storage($line_rules),
                    ':color' => $details_color,
                    ':hashtag_color' => $hashtag_color,
                    ':date_color' => $date_color,
                    ':capitalize' => $capitalize_sentences ? 1 : 0,
                    ':date_formats' => encode_date_formats_for_storage($date_formats),
                    ':text_expanders' => encode_text_expanders_for_storage($text_expanders),
                    ':id' => $_SESSION['user_id'],
                ]);
            } else {
                $stmt = $db->prepare('UPDATE users SET username = :username, location = :loc, default_priority = :pri, line_rules = :rules, details_color = :color, hashtag_color = :hashtag_color, date_color = :date_color, capitalize_sentences = :capitalize, date_formats = :date_formats, text_expanders = :text_expanders WHERE id = :id');
                $stmt->execute([
                    ':username' => $username,
                    ':loc' => $location !== '' ? $location : null,
                    ':pri' => $default_priority,
                    ':rules' => encode_line_rules_for_storage($line_rules),
                    ':color' => $details_color,
                    ':hashtag_color' => $hashtag_color,
                    ':date_color' => $date_color,
                    ':capitalize' => $capitalize_sentences ? 1 : 0,
                    ':date_formats' => encode_date_formats_for_storage($date_formats),
                    ':text_expanders' => encode_text_expanders_for_storage($text_expanders),
                    ':id' => $_SESSION['user_id'],
                ]);
            }
            $_SESSION['username'] = $username;
            $_SESSION['location'] = $location !== '' ? $location : 'UTC';
            $_SESSION['default_priority'] = $default_priority;
            $_SESSION['line_rules'] = $line_rules;
            $_SESSION['details_color'] = $details_color;
            $_SESSION['hashtag_color'] = $hashtag_color;
            $_SESSION['date_color'] = $date_color;
            $_SESSION['capitalize_sentences'] = $capitalize_sentences ? 1 : 0;
            $_SESSION['date_formats'] = $date_formats;
            $_SESSION['text_expanders'] = $text_expanders;
            $message = 'Settings saved';
        } catch (PDOException $e) {
            $error = 'Username already taken';
        }
    }

    if ($accepts_json) {
        respond_json([
            'status' => $error ? 'error' : 'ok',
            'message' => $error ?: $message,
        ], $error ? 400 : 200);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="/assets/bootstrap/bootstrap.min.css" rel="stylesheet">
    <title>Settings</title>
    <style>
        .navbar-toggler {
            border: 1px solid #e9ecef;
        }
    </style>
</head>
<body class="bg-light">
<nav class="navbar navbar-light bg-white mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php" class="navbar-brand">Otodo</a>
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
            <a href="index.php" class="list-group-item list-group-item-action" data-route>Active Tasks</a>
            <a href="completed.php" class="list-group-item list-group-item-action" data-route>Completed Tasks</a>
            <a href="settings.php" class="list-group-item list-group-item-action" data-route>Settings</a>
            <a href="logout.php" class="list-group-item list-group-item-action">Logout</a>
        </div>
        <div class="mt-3 small text-muted" id="sync-status" aria-live="polite">All changes saved</div>
    </div>
</div>
<div id="view-root" data-view-root data-view="settings">
<div class="container">
    <h5 class="mb-3">Settings</h5>
    <div id="settingsStatus" aria-live="polite">
    <?php if ($message): ?>
        <div class="alert alert-success"><?=$message?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?=$error?></div>
    <?php endif; ?>
    </div>
    <form method="post" class="mb-3" autocomplete="off">
        <div class="mb-3">
            <label class="form-label" for="username">Username</label>
            <input type="text" name="username" id="username" class="form-control" value="<?=htmlspecialchars($username)?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">New Password</label>
            <input type="password" name="password" id="password" class="form-control" placeholder="Leave blank to keep current">
        </div>
        <div class="mb-3">
            <label class="form-label" for="location">Location (timezone)</label>
            <input type="text" name="location" id="location" class="form-control" list="tz-list" value="<?=htmlspecialchars($location)?>" placeholder="Start typing your timezone">
            <datalist id="tz-list">
                <?php foreach ($timezones as $tz): ?>
                    <option value="<?=htmlspecialchars($tz)?>"></option>
                <?php endforeach; ?>
            </datalist>
            <button type="button" class="btn btn-outline-secondary mt-2" id="detect-tz">Use My Timezone</button>
        </div>
        <div class="mb-3">
            <label class="form-label" for="default_priority">Default Task Priority</label>
            <select name="default_priority" id="default_priority" class="form-select">
                <option value="3" <?php if ($default_priority == 3) echo 'selected'; ?>>High</option>
                <option value="2" <?php if ($default_priority == 2) echo 'selected'; ?>>Medium</option>
                <option value="1" <?php if ($default_priority == 1) echo 'selected'; ?>>Low</option>
                <option value="0" <?php if ($default_priority == 0) echo 'selected'; ?>>None</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="details_color">Task description text color</label>
            <input type="color" name="details_color" id="details_color" class="form-control form-control-color" value="<?=htmlspecialchars($details_color ?? '#212529')?>" title="Pick a color for the task description editor">
        </div>
        <div class="mb-3">
            <label class="form-label" for="hashtag_color">Hashtag highlight color</label>
            <input type="color" name="hashtag_color" id="hashtag_color" class="form-control form-control-color" value="<?=htmlspecialchars($hashtag_color ?? '#6F42C1')?>" title="Pick a color for hashtags in the description preview">
            <div class="form-text">Background and border shades will adapt to this color.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="date_color">Date highlight color</label>
            <input type="color" name="date_color" id="date_color" class="form-control form-control-color" value="<?=htmlspecialchars($date_color ?? '#FDA90D')?>" title="Pick a color for highlighted dates">
            <div class="form-text">Background and border shades will adapt to this color.</div>
        </div>
        <div class="mb-3">
            <label class="form-label" for="date_formats">Date formats to highlight</label>
            <textarea class="form-control" id="date_formats" name="date_formats" rows="3" placeholder="DD MMM YYYY&#10;YYYY-MM-DD"><?=htmlspecialchars(implode("\n", $date_formats))?></textarea>
            <div class="form-text">One format per line. Supported tokens: D, DD, M, MM, MMM, MMMM, YY, YYYY. Defaults include DD MMM YYYY and DD/MM/YYYY (for example, 31 Dec 2025).</div>
        </div>
        <div class="form-check form-switch mb-4">
            <input class="form-check-input" type="checkbox" role="switch" id="capitalize_sentences" name="capitalize_sentences" <?=$capitalize_sentences ? 'checked' : ''?>>
            <label class="form-check-label" for="capitalize_sentences">Capitalize matching lines while typing</label>
            <div class="form-text">Uppercases the first letter (and the first word after a prefix) on lines that use your custom prefixes; other lines stay unchanged.</div>
        </div>
        <div class="mb-3">
            <label class="form-label">Custom line rules</label>
            <p class="text-muted small">Define how lines starting with specific prefixes should be highlighted in the task description editor. Capitalization is controlled by the toggle above and does not need per-rule options.</p>
            <div id="lineRulesContainer" class="d-flex flex-column gap-2"></div>
            <div class="mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="addRuleBtn">Add rule</button>
            </div>
            <input type="hidden" name="line_rules_json" id="line_rules_json" value="<?=htmlspecialchars(json_encode($line_rules))?>">
        </div>
        <div class="mb-4">
            <label class="form-label">Text expanders</label>
            <p class="text-muted small">Create shortcuts that expand into longer snippets while editing a task description. Start typing a shortcut to see suggestions.</p>
            <div id="textExpandersContainer" class="d-flex flex-column gap-2"></div>
            <div class="mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="addExpanderBtn">Add shortcut</button>
            </div>
            <input type="hidden" name="text_expanders_json" id="text_expanders_json" value="<?=htmlspecialchars(json_encode($text_expanders))?>">
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </form>
</div>
</div>
<script src="prevent-save-shortcut.js"></script>
<script src="sw-register.js"></script>
<script src="sync-status.js"></script>
<script src="sync-queue-ui.js"></script>
<script src="app-api.js"></script>
<script src="app-router.js"></script>
<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
<script>
window.viewRouter = window.viewRouter || new ViewRouter('#view-root');
const input = document.getElementById('location');
const detectBtn = document.getElementById('detect-tz');
function setBrowserTz() {
    try {
        input.value = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch (e) {}
}
if (detectBtn) {
    detectBtn.addEventListener('click', setBrowserTz);
}
if (input && !input.value) {
    setBrowserTz();
}

const settingsStatus = document.getElementById('settingsStatus');
const settingsForm = document.querySelector('#view-root form');

function renderSettingsStatus(type, message) {
    if (!settingsStatus) return;
    settingsStatus.innerHTML = '';
    if (!message) return;
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.textContent = message;
    settingsStatus.appendChild(alert);
}

if (settingsForm) {
    settingsForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(settingsForm);
        renderSettingsStatus('secondary', 'Saving settings…');
        const response = await ApiClient.saveSettings(formData);
        if (response.ok && response.data && response.data.status === 'ok') {
            renderSettingsStatus('success', response.data.message || 'Settings saved');
        } else {
            const offline = response.offline ? ' You appear to be offline.' : '';
            renderSettingsStatus('danger', (response.error || 'Could not save settings') + offline);
        }
    });
}

const lineRulesContainer = document.getElementById('lineRulesContainer');
const addRuleBtn = document.getElementById('addRuleBtn');
const lineRulesInput = document.getElementById('line_rules_json');
const textExpandersContainer = document.getElementById('textExpandersContainer');
const addExpanderBtn = document.getElementById('addExpanderBtn');
const textExpandersInput = document.getElementById('text_expanders_json');

function readRules() {
    try {
        const parsed = JSON.parse(lineRulesInput.value || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch (err) {
        return [];
    }
}

function writeRules(rules) {
    lineRulesInput.value = JSON.stringify(rules);
}

function buildRuleRow(rule, idx) {
    const row = document.createElement('div');
    row.className = 'border rounded p-2 d-flex flex-column flex-md-row gap-2 align-items-md-center';

    const persistentClassName = rule.className || '';
    const persistentWeight = rule.weight || '';
    const shouldCapitalize = !!rule.capitalize;

    const prefix = document.createElement('input');
    prefix.type = 'text';
    prefix.className = 'form-control';
    prefix.placeholder = 'Prefix (e.g. T )';
    prefix.value = rule.prefix || '';
    prefix.setAttribute('aria-label', 'Line prefix');

    const label = document.createElement('input');
    label.type = 'text';
    label.className = 'form-control';
    label.placeholder = 'Label (optional)';
    label.value = rule.label || '';
    label.setAttribute('aria-label', 'Rule label');

    const color = document.createElement('input');
    color.type = 'color';
    color.className = 'form-control form-control-color';
    color.value = rule.color || '#1D4ED8';
    color.setAttribute('aria-label', 'Rule color');

    const capitalizeWrapper = document.createElement('div');
    capitalizeWrapper.className = 'form-check mt-2 mt-md-0';
    const capitalizeInput = document.createElement('input');
    capitalizeInput.type = 'checkbox';
    capitalizeInput.className = 'form-check-input';
    capitalizeInput.id = `capitalize-${idx}`;
    capitalizeInput.checked = shouldCapitalize;
    const capitalizeLabel = document.createElement('label');
    capitalizeLabel.className = 'form-check-label';
    capitalizeLabel.setAttribute('for', capitalizeInput.id);
    capitalizeLabel.textContent = 'Capitalize first letter';
    capitalizeWrapper.append(capitalizeInput, capitalizeLabel);

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-outline-danger btn-sm w-100 w-md-auto';
    removeBtn.textContent = 'Remove';

    function updateRule() {
        const rules = readRules();
        const nextRule = {
            prefix: prefix.value,
            label: label.value,
            color: color.value,
            capitalize: capitalizeInput.checked
        };
        if (persistentClassName) {
            nextRule.className = persistentClassName;
        }
        if (persistentWeight) {
            nextRule.weight = persistentWeight;
        }
        rules[idx] = nextRule;
        writeRules(rules);
    }

    prefix.addEventListener('input', updateRule);
    label.addEventListener('input', updateRule);
    color.addEventListener('input', updateRule);
    capitalizeInput.addEventListener('change', updateRule);
    removeBtn.addEventListener('click', function() {
        const rules = readRules();
        rules.splice(idx, 1);
        writeRules(rules);
        renderRules();
    });

    const rowTop = document.createElement('div');
    rowTop.className = 'row g-2 flex-grow-1 align-items-center';

    const prefixCol = document.createElement('div');
    prefixCol.className = 'col-12 col-md-3';
    prefixCol.append(prefix);

    const labelCol = document.createElement('div');
    labelCol.className = 'col-12 col-md-3';
    labelCol.append(label);

    const colorCol = document.createElement('div');
    colorCol.className = 'col-6 col-md-2';
    colorCol.append(color);

    const removeCol = document.createElement('div');
    removeCol.className = 'col-12 col-md-auto';
    removeCol.append(removeBtn);

    rowTop.append(prefixCol, labelCol, colorCol, removeCol);
    row.append(rowTop);
    return row;
}

function renderRules() {
    const rules = readRules();
    if (!rules.length) {
        rules.push({ prefix: 'T ', label: 'Task', color: '#1D4ED8', className: 'code-line-task', capitalize: true });
        writeRules(rules);
    }
    lineRulesContainer.innerHTML = '';
    rules.forEach(function(rule, idx) {
        lineRulesContainer.appendChild(buildRuleRow(rule, idx));
    });
}

addRuleBtn.addEventListener('click', function() {
    const rules = readRules();
    rules.push({ prefix: '', label: '', color: '#1D4ED8' });
    writeRules(rules);
    renderRules();
});

renderRules();

function readExpanders() {
    try {
        const parsed = JSON.parse(textExpandersInput.value || '[]');
        return Array.isArray(parsed) ? parsed : [];
    } catch (err) {
        return [];
    }
}

function writeExpanders(expanders) {
    textExpandersInput.value = JSON.stringify(expanders);
}

function buildExpanderRow(expander, idx) {
    const row = document.createElement('div');
    row.className = 'border rounded p-2 d-flex flex-column flex-md-row gap-2 align-items-md-center';

    const shortcut = document.createElement('input');
    shortcut.type = 'text';
    shortcut.className = 'form-control';
    shortcut.placeholder = 'Shortcut (e.g. prompt:)';
    shortcut.value = expander.shortcut || '';
    shortcut.setAttribute('aria-label', 'Expander shortcut');

    const expansion = document.createElement('input');
    expansion.type = 'text';
    expansion.className = 'form-control';
    expansion.placeholder = 'Expansion (e.g. Follow up)';
    expansion.value = expander.expansion || '';
    expansion.setAttribute('aria-label', 'Expander expansion');

    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn btn-outline-danger btn-sm w-100 w-md-auto';
    removeBtn.textContent = 'Remove';

    function updateExpander() {
        const expanders = readExpanders();
        expanders[idx] = {
            shortcut: shortcut.value,
            expansion: expansion.value
        };
        writeExpanders(expanders);
    }

    shortcut.addEventListener('input', updateExpander);
    expansion.addEventListener('input', updateExpander);
    removeBtn.addEventListener('click', function() {
        const expanders = readExpanders();
        expanders.splice(idx, 1);
        writeExpanders(expanders);
        renderExpanders();
    });

    const rowTop = document.createElement('div');
    rowTop.className = 'row g-2 flex-grow-1 align-items-center';

    const shortcutCol = document.createElement('div');
    shortcutCol.className = 'col-12 col-md-4';
    shortcutCol.append(shortcut);

    const expansionCol = document.createElement('div');
    expansionCol.className = 'col-12 col-md-6';
    expansionCol.append(expansion);

    const removeCol = document.createElement('div');
    removeCol.className = 'col-12 col-md-auto';
    removeCol.append(removeBtn);

    rowTop.append(shortcutCol, expansionCol, removeCol);
    row.append(rowTop);
    return row;
}

function renderExpanders() {
    const expanders = readExpanders();
    textExpandersContainer.innerHTML = '';
    if (!expanders.length) {
        expanders.push({ shortcut: 'prompt:', expansion: 'Follow up' });
        writeExpanders(expanders);
    }
    expanders.forEach(function(expander, idx) {
        textExpandersContainer.appendChild(buildExpanderRow(expander, idx));
    });
}

if (addExpanderBtn) {
    addExpanderBtn.addEventListener('click', function() {
        const expanders = readExpanders();
        expanders.push({ shortcut: '', expansion: '' });
        writeExpanders(expanders);
        renderExpanders();
    });
}

renderExpanders();
</script>
</body>
</html>
