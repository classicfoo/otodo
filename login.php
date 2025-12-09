<?php
require_once 'db.php';
require_once 'line_rules.php';
require_once 'date_formats.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if ($username === '' || $password === '') {
        $error = 'Please fill in all fields';
    } else {
        $stmt = get_db()->prepare('SELECT id, password, location, default_priority, line_rules, details_color, hashtag_color, date_color, capitalize_sentences, date_formats FROM users WHERE username = :username');
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['location'] = $user['location'] ?? 'UTC';
            $_SESSION['default_priority'] = (int)($user['default_priority'] ?? 0);
            $_SESSION['line_rules'] = decode_line_rules_from_storage($user['line_rules'] ?? '');
            $_SESSION['details_color'] = normalize_editor_color($user['details_color'] ?? '#212529');
            $_SESSION['hashtag_color'] = normalize_hex_color($user['hashtag_color'] ?? '#6F42C1', '#6F42C1');
            $_SESSION['date_color'] = normalize_hex_color($user['date_color'] ?? '#FDA90D', '#FDA90D');
            $_SESSION['capitalize_sentences'] = isset($user['capitalize_sentences']) ? (int)$user['capitalize_sentences'] === 1 : true;
            $_SESSION['date_formats'] = decode_date_formats_from_storage($user['date_formats'] ?? '');
            header('Location: index.php');
            exit();
        } else {
            $error = 'Invalid credentials';
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
    <title>Login</title>
</head>
<body class="bg-light">
<div class="container py-5">
    <h2 class="mb-4">Login</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?=$error?></div>
    <?php endif; ?>
    <form method="post" class="mb-3">
        <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
    </form>
    <p>Don't have an account? <a href="register.php">Register</a></p>
</div>
<script src="prevent-save-shortcut.js"></script>
<script src="sw-register.js"></script>
</body>
</html>
