<?php
require_once 'db.php';
require_once 'line_rules.php';

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
$line_rules = $_SESSION['line_rules'] ?? get_default_line_rules();
$timezones = DateTimeZone::listIdentifiers();

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

    if ($username === '') {
        $error = 'Username cannot be empty';
    } else {
        try {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE users SET username = :username, password = :password, location = :loc, default_priority = :pri, line_rules = :rules, details_color = :color WHERE id = :id');
                $stmt->execute([
                    ':username' => $username,
                    ':password' => $hash,
                    ':loc' => $location !== '' ? $location : null,
                    ':pri' => $default_priority,
                    ':rules' => encode_line_rules_for_storage($line_rules),
                    ':color' => $details_color,
                    ':id' => $_SESSION['user_id'],
                ]);
            } else {
                $stmt = $db->prepare('UPDATE users SET username = :username, location = :loc, default_priority = :pri, line_rules = :rules, details_color = :color WHERE id = :id');
                $stmt->execute([
                    ':username' => $username,
                    ':loc' => $location !== '' ? $location : null,
                    ':pri' => $default_priority,
                    ':rules' => encode_line_rules_for_storage($line_rules),
                    ':color' => $details_color,
                    ':id' => $_SESSION['user_id'],
                ]);
            }
            $_SESSION['username'] = $username;
            $_SESSION['location'] = $location !== '' ? $location : 'UTC';
            $_SESSION['default_priority'] = $default_priority;
            $_SESSION['line_rules'] = $line_rules;
            $_SESSION['details_color'] = $details_color;
            $message = 'Settings saved';
        } catch (PDOException $e) {
            $error = 'Username already taken';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
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
            <a href="index.php" class="list-group-item list-group-item-action">Active Tasks</a>
            <a href="completed.php" class="list-group-item list-group-item-action">Completed Tasks</a>
            <a href="settings.php" class="list-group-item list-group-item-action">Settings</a>
            <a href="logout.php" class="list-group-item list-group-item-action">Logout</a>
        </div>
        <div class="mt-3 small text-muted" id="sync-status" aria-live="polite">All changes saved</div>
    </div>
</div>
<div class="container">
    <h5 class="mb-3">Settings</h5>
    <?php if ($message): ?>
        <div class="alert alert-success"><?=$message?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?=$error?></div>
    <?php endif; ?>
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
            <label class="form-label">Custom line rules</label>
            <p class="text-muted small">Define how lines starting with specific prefixes should be highlighted in the task description editor.</p>
            <div id="lineRulesContainer" class="d-flex flex-column gap-2"></div>
            <div class="mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="addRuleBtn">Add rule</button>
            </div>
            <input type="hidden" name="line_rules_json" id="line_rules_json" value="<?=htmlspecialchars(json_encode($line_rules))?>">
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </form>
</div>
<script src="prevent-save-shortcut.js"></script>
<script src="sw-register.js"></script>
<script src="sync-status.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
const input = document.getElementById('location');
const detectBtn = document.getElementById('detect-tz');
function setBrowserTz() {
    try {
        input.value = Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch (e) {}
}
detectBtn.addEventListener('click', setBrowserTz);
if (!input.value) {
    setBrowserTz();
}

const lineRulesContainer = document.getElementById('lineRulesContainer');
const addRuleBtn = document.getElementById('addRuleBtn');
const lineRulesInput = document.getElementById('line_rules_json');

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
    removeBtn.className = 'btn btn-outline-danger btn-sm';
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
    rowTop.className = 'd-flex flex-column flex-md-row gap-2 flex-grow-1';
    rowTop.append(prefix, label, color, capitalizeWrapper);
    row.append(rowTop, removeBtn);
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
</script>
</body>
</html>
