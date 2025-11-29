<?php
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

$db = get_db();
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$priority = $_POST['priority'] ?? null;
$due_shortcut = $_POST['due_shortcut'] ?? null;

if ($id <= 0) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Invalid task id']);
    exit();
}

$updates = [];
$params = [
    ':id' => $id,
    ':uid' => $_SESSION['user_id'],
];

if ($priority !== null) {
    $p = (int)$priority;
    if ($p < 0 || $p > 3) {
        $p = 0;
    }
    $updates[] = 'priority = :priority';
    $params[':priority'] = $p;
}

if ($due_shortcut !== null) {
    $tz = $_SESSION['location'] ?? null;
    if ($tz === null) {
        $stmt = $db->prepare('SELECT location FROM users WHERE id = :id');
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $tz = $stmt->fetchColumn() ?: 'UTC';
        $_SESSION['location'] = $tz;
    }
    try {
        $tzObj = new DateTimeZone($tz);
    } catch (Exception $e) {
        $tzObj = new DateTimeZone('UTC');
    }
    $today = new DateTime('today', $tzObj);

    $due_date_value = null;
    $has_due_value = true;
    switch ($due_shortcut) {
        case 'today':
            $due_date_value = $today->format('Y-m-d');
            break;
        case 'tomorrow':
            $due_date_value = $today->modify('+1 day')->format('Y-m-d');
            break;
        case 'next-week':
            $due_date_value = $today->modify('+7 day')->format('Y-m-d');
            break;
        case 'clear':
            $due_date_value = null;
            break;
        default:
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$due_shortcut)) {
                $due_date_value = $due_shortcut;
            } else {
                $has_due_value = false;
            }
            break;
    }

    if ($has_due_value) {
        $updates[] = 'due_date = :due_date';
        $params[':due_date'] = $due_date_value !== null ? $due_date_value : null;
    }
}

if (!$updates) {
    header('HTTP/1.1 400 Bad Request');
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No changes provided']);
    exit();
}

$sql = 'UPDATE tasks SET ' . implode(', ', $updates) . ' WHERE id = :id AND user_id = :uid';
$stmt = $db->prepare($sql);
$stmt->execute($params);

$select = $db->prepare('SELECT due_date, priority FROM tasks WHERE id = :id AND user_id = :uid');
$select->execute([':id' => $id, ':uid' => $_SESSION['user_id']]);
$updated = $select->fetch(PDO::FETCH_ASSOC);

$priority_labels = [0 => 'None', 1 => 'Low', 2 => 'Medium', 3 => 'High'];
$priority_classes = [0 => 'text-secondary', 1 => 'text-success', 2 => 'text-warning', 3 => 'text-danger'];

$p = (int)($updated['priority'] ?? 0);
if ($p < 0 || $p > 3) { $p = 0; }

$due = $updated['due_date'] ?? '';
$due_label = '';
$due_class = '';

if ($due !== '' && $due !== null) {
    try {
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

        $dueDate = new DateTime($due, $tzObj);
        if ($dueDate < $today) {
            $due_label = 'Overdue';
            $due_class = 'bg-danger-subtle text-danger';
        } else {
            $dueFmt = $dueDate->format('Y-m-d');
            if ($dueFmt === $todayFmt) {
                $due_label = 'Today';
                $due_class = 'bg-success-subtle text-success';
            } elseif ($dueFmt === $tomorrowFmt) {
                $due_label = 'Tomorrow';
                $due_class = 'bg-primary-subtle text-primary';
            } else {
                $due_label = 'Later';
                $due_class = 'bg-primary-subtle text-primary';
            }
        }
    } catch (Exception $e) {
        $due_label = '';
        $due_class = '';
    }
}

header('Content-Type: application/json');
echo json_encode([
    'status' => 'ok',
    'due_date' => $due !== null ? (string)$due : '',
    'due_label' => $due_label,
    'due_class' => $due_class,
    'priority' => $p,
    'priority_label' => $priority_labels[$p] ?? 'None',
    'priority_class' => $priority_classes[$p] ?? 'text-secondary',
]);
exit();
