<?php
// task-delete.php - Secure task deletion handler
require_once 'config.php';
require_once 'functions.php';

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: tasks.php');
    exit;
}

$taskId = filter_input(INPUT_POST, 'task_id', FILTER_VALIDATE_INT);
$returnUrl = $_POST['return_url'] ?? 'tasks.php';

if (!$taskId) {
    set_flash('error', 'Invalid task ID.');
    header('Location: ' . $returnUrl);
    exit;
}

try {
    // Check permission
    if (is_admin()) {
        $stmt = $pdo->prepare("SELECT id, title, attachment FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
    } else {
        $stmt = $pdo->prepare("SELECT id, title, attachment FROM tasks WHERE id = :id AND (user_id = :uid OR project_id IN (SELECT id FROM projects WHERE user_id = :uid))");
        $stmt->execute([':id' => $taskId, ':uid' => $user['id']]);
    }

    $task = $stmt->fetch();
    if (!$task) {
        set_flash('error', 'Task not found or permission denied.');
        header('Location: ' . $returnUrl);
        exit;
    }

    // Delete attachment file if exists
    if (!empty($task['attachment']) && file_exists('uploads/' . $task['attachment'])) {
        unlink('uploads/' . $task['attachment']);
    }

    // Delete task via prepared statement
    $delStmt = $pdo->prepare("DELETE FROM tasks WHERE id = :id");
    $delStmt->execute([':id' => $taskId]);

    set_flash('success', 'Task "' . $task['title'] . '" was successfully deleted.');
    header('Location: ' . $returnUrl);
    exit;

} catch (PDOException $e) {
    set_flash('error', 'Failed to delete task: ' . $e->getMessage());
    header('Location: ' . $returnUrl);
    exit;
}
