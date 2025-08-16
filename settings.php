<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$db = get_db();
$message = '';
$location = $_SESSION['location'] ?? '';
$dynamic_formatting = (int)($_SESSION['dynamic_formatting'] ?? 1);
$timezones = DateTimeZone::listIdentifiers();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $location = trim($_POST['location'] ?? '');
    $dynamic_formatting = isset($_POST['dynamic_formatting']) ? 1 : 0;
    $stmt = $db->prepare('UPDATE users SET location = :loc, dynamic_formatting = :dyn WHERE id = :id');
    $stmt->execute([
        ':loc' => $location !== '' ? $location : null,
        ':dyn' => $dynamic_formatting,
        ':id' => $_SESSION['user_id'],
    ]);
    $_SESSION['location'] = $location !== '' ? $location : 'UTC';
    $_SESSION['dynamic_formatting'] = $dynamic_formatting;
    $message = 'Settings saved';
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
    </div>
</div>
<div class="container">
    <h5 class="mb-3">Settings</h5>
    <?php if ($message): ?>
        <div class="alert alert-success"><?=$message?></div>
    <?php endif; ?>
    <form method="post" class="mb-3" autocomplete="off">
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
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="dynamic_formatting" id="dynamicFormatting" <?php if ($dynamic_formatting) echo 'checked'; ?>>
            <label class="form-check-label" for="dynamicFormatting">Enable Dynamic Line Formatting</label>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="index.php" class="btn btn-secondary">Back</a>
    </form>
</div>
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
