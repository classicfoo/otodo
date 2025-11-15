<?php
require_once 'db.php';

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
$timezones = DateTimeZone::listIdentifiers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? $username);
    $password = $_POST['password'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $default_priority = (int)($_POST['default_priority'] ?? 0);
    if ($default_priority < 0 || $default_priority > 3) {
        $default_priority = 0;
    }

    if ($username === '') {
        $error = 'Username cannot be empty';
    } else {
        try {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('UPDATE users SET username = :username, password = :password, location = :loc, default_priority = :pri WHERE id = :id');
                $stmt->execute([
                    ':username' => $username,
                    ':password' => $hash,
                    ':loc' => $location !== '' ? $location : null,
                    ':pri' => $default_priority,
                    ':id' => $_SESSION['user_id'],
                ]);
            } else {
                $stmt = $db->prepare('UPDATE users SET username = :username, location = :loc, default_priority = :pri WHERE id = :id');
                $stmt->execute([
                    ':username' => $username,
                    ':loc' => $location !== '' ? $location : null,
                    ':pri' => $default_priority,
                    ':id' => $_SESSION['user_id'],
                ]);
            }
            $_SESSION['username'] = $username;
            $_SESSION['location'] = $location !== '' ? $location : 'UTC';
            $_SESSION['default_priority'] = $default_priority;
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
        <div class="mt-4 small text-secondary" data-sync-status>All changes synced</div>
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
    <form method="post" class="mb-3" autocomplete="off" data-sync-form>
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
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </form>
</div>
<script src="sync-status.js"></script>
<script src="sw-register.js"></script>
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
</script>
</body>
</html>
