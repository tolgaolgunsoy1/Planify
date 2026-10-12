<?php
// project-delete.php - Secure project deletion with cascading cleanup
require_once 'config.php';
require_once 'functions.php';

require_login();
$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: projects.php');
    exit;
}

$projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);

if (!$projectId) {
    set_flash('error', 'Invalid project ID.');
    header('Location: projects.php');
    exit;
}

try {
    // Check permission
    if (is_admin()) {
        $stmt = $pdo->prepare("SELECT id, title FROM projects WHERE id = :id");
        $stmt->execute([':id' => $projectId]);
    } else {
        $stmt = $pdo->prepare("SELECT id, title FROM projects WHERE id = :id AND user_id = :uid");
        $stmt->execute([':id' => $projectId, ':uid' => $user['id']]);
    }

    $project = $stmt->fetch();
    if (!$project) {
        set_flash('error', 'Project not found or you do not have permission to delete it.');
        header('Location: projects.php');
        exit;
    }

    // Clean up task attachments before deleting project
    $attStmt = $pdo->prepare("SELECT attachment FROM tasks WHERE project_id = :pid AND attachment IS NOT NULL");
    $attStmt->execute([':pid' => $projectId]);
    $attachments = $attStmt->fetchAll();
    foreach ($attachments as $att) {
        if (!empty($att['attachment']) && file_exists('uploads/' . $att['attachment'])) {
            unlink('uploads/' . $att['attachment']);
        }
    }

    // Delete project (cascade removes tasks due to foreign key)
    $delStmt = $pdo->prepare("DELETE FROM projects WHERE id = :id");
    $delStmt->execute([':id' => $projectId]);

    set_flash('success', 'Project "' . $project['title'] . '" and its tasks were successfully deleted.');
    header('Location: projects.php');
    exit;

} catch (PDOException $e) {
    set_flash('error', 'Database error deleting project: ' . $e->getMessage());
    header('Location: projects.php');
    exit;
}
