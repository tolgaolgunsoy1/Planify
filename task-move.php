<?php
// task-move.php - Move task between "to do", "doing", and "done"
// Signature Feature Action Handler (Handles both AJAX and standard POST)
require_once 'config.php';
require_once 'functions.php';

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: board.php');
    exit;
}

$taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
$newStatus = trim($_POST['new_status'] ?? '');
$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
$returnUrl = $_POST['return_url'] ?? 'board.php';

// Server-side validation
$validStatuses = ['todo', 'doing', 'done'];
if (!$taskId || !in_array($newStatus, $validStatuses, true)) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid task or status.']);
        exit;
    }
    set_flash('error', 'Invalid task movement request.');
    header('Location: ' . $returnUrl);
    exit;
}

try {
    // Check task ownership if not admin
    if (is_admin()) {
        $stmt = $pdo->prepare("SELECT id, title, status FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
    } else {
        $stmt = $pdo->prepare("SELECT id, title, status FROM tasks WHERE id = :id AND (user_id = :uid OR project_id IN (SELECT id FROM projects WHERE user_id = :uid))");
        $stmt->execute([':id' => $taskId, ':uid' => $user['id']]);
    }

    $task = $stmt->fetch();
    if (!$task) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Task not found or access denied.']);
            exit;
        }
        set_flash('error', 'Task not found or you do not have permission to modify it.');
        header('Location: ' . $returnUrl);
        exit;
    }

    // Update status using PDO prepared statement
    $updateStmt = $pdo->prepare("UPDATE tasks SET status = :status, updated_at = NOW() WHERE id = :id");
    $updateStmt->execute([
        ':status' => $newStatus,
        ':id' => $taskId,
    ]);

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'task_id' => $taskId,
            'new_status' => $newStatus,
            'message' => 'Task updated to ' . ucfirst($newStatus),
        ]);
        exit;
    }

    set_flash('success', 'Task "' . $task['title'] . '" moved to ' . ucfirst($newStatus) . '.');
    header('Location: ' . $returnUrl);
    exit;

} catch (PDOException $e) {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Database error.']);
        exit;
    }
    set_flash('error', 'Failed to update task status: ' . $e->getMessage());
    header('Location: ' . $returnUrl);
    exit;
}
