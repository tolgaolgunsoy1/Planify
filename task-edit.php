<?php
// task-edit.php - Edit Task, Update Status/Deadline/Attachment, Delete Option
require_once 'config.php';
require_once 'functions.php';
require_login();

$user = current_user();
$taskId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$taskId) {
    set_flash('error', 'Task ID is required.');
    header('Location: tasks.php');
    exit;
}

// Fetch the task and verify ownership
try {
    if (is_admin()) {
        $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = :id");
        $stmt->execute([':id' => $taskId]);
    } else {
        $stmt = $pdo->prepare("SELECT t.* FROM tasks t 
                               JOIN projects p ON t.project_id = p.id 
                               WHERE t.id = :id AND (t.user_id = :uid OR p.user_id = :uid)");
        $stmt->execute([':id' => $taskId, ':uid' => $user['id']]);
    }
    $task = $stmt->fetch();
} catch (PDOException $e) {
    $task = null;
}

if (!$task) {
    set_flash('error', 'Task not found or access denied.');
    header('Location: tasks.php');
    exit;
}

// Fetch available projects
try {
    if (is_admin()) {
        $pStmt = $pdo->query("SELECT id, title FROM projects ORDER BY title ASC");
    } else {
        $pStmt = $pdo->prepare("SELECT id, title FROM projects WHERE user_id = :uid ORDER BY title ASC");
        $pStmt->execute([':uid' => $user['id']]);
    }
    $projects = $pStmt->fetchAll();
} catch (PDOException $e) {
    $projects = [];
}

$errors = [];
$formData = [
    'title' => $task['title'],
    'description' => $task['description'] ?? '',
    'project_id' => $task['project_id'],
    'status' => $task['status'],
    'priority' => $task['priority'],
    'deadline' => !empty($task['deadline']) ? date('Y-m-d\TH:i', strtotime($task['deadline'])) : '',
    'attachment' => $task['attachment'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['title'] = trim($_POST['title'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['project_id'] = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
    $formData['status'] = trim($_POST['status'] ?? 'todo');
    $formData['priority'] = trim($_POST['priority'] ?? 'medium');
    $formData['deadline'] = trim($_POST['deadline'] ?? '');
    $removeAttachment = isset($_POST['remove_attachment']) && $_POST['remove_attachment'] === '1';

    // Server-side validation
    if (empty($formData['title'])) {
        $errors[] = 'Task title is required.';
    } elseif (mb_strlen($formData['title']) < 2 || mb_strlen($formData['title']) > 150) {
        $errors[] = 'Task title must be between 2 and 150 characters.';
    }

    if (!$formData['project_id']) {
        $errors[] = 'Please select a valid project.';
    }

    if (!in_array($formData['status'], ['todo', 'doing', 'done'], true)) {
        $errors[] = 'Invalid task status selected.';
    }

    if (!in_array($formData['priority'], ['low', 'medium', 'high'], true)) {
        $errors[] = 'Invalid task priority selected.';
    }

    $deadlineSql = null;
    if (!empty($formData['deadline'])) {
        $parsedTime = strtotime($formData['deadline']);
        if ($parsedTime === false) {
            $errors[] = 'Invalid deadline date format.';
        } else {
            $deadlineSql = date('Y-m-d H:i:s', $parsedTime);
        }
    }

    $finalAttachment = $task['attachment'];

    // Handle Attachment Removal
    if ($removeAttachment) {
        if (!empty($task['attachment']) && file_exists('uploads/' . $task['attachment'])) {
            unlink('uploads/' . $task['attachment']);
        }
        $finalAttachment = null;
    }

    // Handle New File Upload (Requirement 9: type check and size <= 1MB)
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = upload_image_file($_FILES['attachment'], 'uploads/');
        if ($uploadResult['success']) {
            if (!empty($task['attachment']) && file_exists('uploads/' . $task['attachment'])) {
                unlink('uploads/' . $task['attachment']);
            }
            $finalAttachment = $uploadResult['filename'];
        } else {
            $errors[] = 'Attachment Error: ' . $uploadResult['error'];
        }
    }

    if (empty($errors)) {
        try {
            $updateSql = "UPDATE tasks SET 
                            project_id = :pid, 
                            title = :title, 
                            description = :description, 
                            status = :status, 
                            priority = :priority, 
                            deadline = :deadline, 
                            attachment = :attachment, 
                            updated_at = NOW() 
                          WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([
                ':pid' => $formData['project_id'],
                ':title' => $formData['title'],
                ':description' => !empty($formData['description']) ? $formData['description'] : null,
                ':status' => $formData['status'],
                ':priority' => $formData['priority'],
                ':deadline' => $deadlineSql,
                ':attachment' => $finalAttachment,
                ':id' => $taskId,
            ]);

            set_flash('success', 'Task "' . $formData['title'] . '" updated successfully.');
            header('Location: board.php?project_id=' . $formData['project_id']);
            exit;

        } catch (PDOException $e) {
            $errors[] = 'Database update error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Edit Task';
require_once 'header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Edit Task #<?= $task['id'] ?></h1>
        <p class="page-subtitle">Update task parameters, workflow progression or attachment.</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <a href="board.php" class="btn btn-outline">Back to Board</a>
        <form method="POST" action="task-delete.php" onsubmit="return confirm('Delete this task permanently?');">
            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
            <button type="submit" class="btn btn-danger">Delete Task</button>
        </form>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="flash-banner flash-error" style="border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
        <div class="flash-content">
            <span class="flash-tag">[ERROR]</span>
            <div class="flash-message">
                <strong>Validation errors:</strong>
                <ul style="margin-top: 0.25rem; margin-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="form-card">
    <form method="POST" action="task-edit.php?id=<?= $taskId ?>" enctype="multipart/form-data" id="taskForm" novalidate>
        <div class="form-group">
            <label for="task_title" class="form-label">Task Title <span class="required">*</span></label>
            <input type="text" name="title" id="task_title" class="form-control" 
                   value="<?= e($formData['title']) ?>" maxlength="150" required 
                   data-counter-target="titleCounter">
            <div class="char-counter" id="titleCounter"><?= mb_strlen($formData['title']) ?> / 150 characters</div>
        </div>

        <div class="form-group">
            <label for="task_desc" class="form-label">Task Description</label>
            <textarea name="description" id="task_desc" class="form-control" rows="4" 
                      maxlength="1000" data-counter-target="descCounter"><?= e($formData['description']) ?></textarea>
            <div class="char-counter" id="descCounter"><?= mb_strlen($formData['description']) ?> / 1000 characters</div>
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label for="task_project" class="form-label">Project <span class="required">*</span></label>
                <select name="project_id" id="task_project" class="form-control" required>
                    <?php foreach ($projects as $proj): ?>
                        <option value="<?= $proj['id'] ?>" <?= (int)$formData['project_id'] === (int)$proj['id'] ? 'selected' : '' ?>>
                            <?= e($proj['title']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-col form-group">
                <label for="task_priority" class="form-label">Priority</label>
                <select name="priority" id="task_priority" class="form-control">
                    <option value="low" <?= $formData['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                    <option value="medium" <?= $formData['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                    <option value="high" <?= $formData['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-col form-group">
                <label for="task_status" class="form-label">Status (To Do / In Progress / Done)</label>
                <select name="status" id="task_status" class="form-control">
                    <option value="todo" <?= $formData['status'] === 'todo' ? 'selected' : '' ?>>To Do</option>
                    <option value="doing" <?= $formData['status'] === 'doing' ? 'selected' : '' ?>>In Progress</option>
                    <option value="done" <?= $formData['status'] === 'done' ? 'selected' : '' ?>>Done</option>
                </select>
            </div>

            <div class="form-col form-group">
                <label for="task_deadline" class="form-label">Deadline</label>
                <input type="datetime-local" name="deadline" id="task_deadline" class="form-control" 
                       value="<?= e($formData['deadline']) ?>">
                <?php if (is_task_overdue($formData['deadline'], $formData['status'])): ?>
                    <div style="font-size: 0.75rem; color: var(--danger); font-weight: 700; margin-top: 0.25rem;">
                        [Notice: This task deadline has expired]
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Current Attachment and Upload Section -->
        <div class="form-group">
            <label class="form-label">Attachment Image (Max 1 MB)</label>
            <?php if (!empty($task['attachment']) && file_exists('uploads/' . $task['attachment'])): ?>
                <div style="margin-bottom: 0.75rem; display: flex; align-items: center; gap: 1rem; background: var(--bg-subtle); padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                    <img src="uploads/<?= e($task['attachment']) ?>" alt="Attachment" style="width: 70px; height: 70px; object-fit: cover; border-radius: 2px;">
                    <div>
                        <div style="font-size: 0.8rem; font-weight: 700;">Current Attachment File</div>
                        <label style="font-size: 0.78rem; color: var(--danger); cursor: pointer; display: flex; align-items: center; gap: 0.35rem; margin-top: 0.25rem;">
                            <input type="checkbox" name="remove_attachment" value="1"> Remove current file
                        </label>
                    </div>
                </div>
            <?php endif; ?>

            <div class="file-upload-zone" onclick="document.getElementById('task_attachment').click()">
                <input type="file" name="attachment" id="task_attachment" style="display:none;" 
                       accept=".jpg,.jpeg,.png,.webp"
                       data-preview-img="taskEditPreviewImg"
                       data-preview-container="taskEditPreviewContainer">
                <p style="margin: 0; font-size: 0.85rem;">Upload or replace image (.jpg, .png, .webp) &bull; Maximum <strong>1 MB</strong></p>
            </div>

            <div id="taskEditPreviewContainer" class="file-preview-wrap">
                <div style="font-size: 0.75rem; font-weight: 700; margin-bottom: 0.25rem;">New Image Preview:</div>
                <img id="taskEditPreviewImg" src="" alt="Selected Preview" class="file-preview-img">
            </div>
        </div>

        <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
            <a href="board.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>
</div>

<?php require_once 'footer.php'; ?>
