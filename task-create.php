<?php
// task-create.php - Create Task with Server-side validation, POST handling and <=1MB file upload
require_once 'config.php';
require_once 'functions.php';
require_login();

$user = current_user();
$preselectedProjectId = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT);

// Fetch projects
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
    'title' => '',
    'description' => '',
    'project_id' => $preselectedProjectId ?? '',
    'status' => 'todo',
    'priority' => 'medium',
    'deadline' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['title'] = trim($_POST['title'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['project_id'] = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
    $formData['status'] = trim($_POST['status'] ?? 'todo');
    $formData['priority'] = trim($_POST['priority'] ?? 'medium');
    $formData['deadline'] = trim($_POST['deadline'] ?? '');

    // Server-side validation (Requirement 4)
    if (empty($formData['title'])) {
        $errors[] = 'Task title is required.';
    } elseif (mb_strlen($formData['title']) < 2 || mb_strlen($formData['title']) > 150) {
        $errors[] = 'Task title must be between 2 and 150 characters.';
    }

    if (!$formData['project_id']) {
        $errors[] = 'Please select a valid project.';
    } else {
        $checkProject = false;
        foreach ($projects as $proj) {
            if ((int)$proj['id'] === (int)$formData['project_id']) {
                $checkProject = true;
                break;
            }
        }
        if (!$checkProject && !is_admin()) {
            $errors[] = 'The selected project does not exist or you do not have permission.';
        }
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

    // Handle File Upload (Requirement 9)
    $uploadedFilename = null;
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = upload_image_file($_FILES['attachment'], 'uploads/');
        if ($uploadResult['success']) {
            $uploadedFilename = $uploadResult['filename'];
        } else {
            $errors[] = 'Attachment Error: ' . $uploadResult['error'];
        }
    }

    // If validation passed, execute PDO prepared statement
    if (empty($errors)) {
        try {
            $insertSql = "INSERT INTO tasks (project_id, user_id, title, description, status, priority, deadline, attachment, created_at)
                          VALUES (:pid, :uid, :title, :description, :status, :priority, :deadline, :attachment, NOW())";
            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                ':pid' => $formData['project_id'],
                ':uid' => $user['id'],
                ':title' => $formData['title'],
                ':description' => !empty($formData['description']) ? $formData['description'] : null,
                ':status' => $formData['status'],
                ':priority' => $formData['priority'],
                ':deadline' => $deadlineSql,
                ':attachment' => $uploadedFilename,
            ]);

            set_flash('success', 'Task "' . $formData['title'] . '" created successfully.');
            header('Location: board.php?project_id=' . $formData['project_id']);
            exit;

        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Create Task';
require_once 'header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Create Task</h1>
        <p class="page-subtitle">Configure task properties, project assignment and deadline.</p>
    </div>
    <div>
        <a href="board.php" class="btn btn-outline">Back to Board</a>
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

<?php if (empty($projects)): ?>
    <div class="card" style="text-align: center; padding: 2.5rem;">
        <h3>No Projects Available</h3>
        <p style="color: var(--text-muted); margin: 0.5rem 0 1.25rem 0;">A project workspace must be created before assigning tasks.</p>
        <a href="projects.php" class="btn btn-primary">+ Create Project</a>
    </div>
<?php else: ?>
    <div class="form-card">
        <form method="POST" action="task-create.php" enctype="multipart/form-data" id="taskForm" novalidate>
            <div class="form-group">
                <label for="task_title" class="form-label">Task Title <span class="required">*</span></label>
                <input type="text" name="title" id="task_title" class="form-control" 
                       value="<?= e($formData['title']) ?>" maxlength="150" required 
                       placeholder="Task title"
                       data-counter-target="titleCounter">
                <div class="char-counter" id="titleCounter">0 / 150 characters</div>
            </div>

            <div class="form-group">
                <label for="task_desc" class="form-label">Task Description</label>
                <textarea name="description" id="task_desc" class="form-control" rows="4" 
                          maxlength="1000" placeholder="Task requirements and scope..."
                          data-counter-target="descCounter"><?= e($formData['description']) ?></textarea>
                <div class="char-counter" id="descCounter">0 / 1000 characters</div>
            </div>

            <div class="form-row">
                <div class="form-col form-group">
                    <label for="task_project" class="form-label">Project <span class="required">*</span></label>
                    <select name="project_id" id="task_project" class="form-control" required>
                        <option value="">-- Select Project --</option>
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
                    <label for="task_status" class="form-label">Workflow Status</label>
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
                </div>
            </div>

            <!-- Requirement 9: File upload with <=1MB validation and live image preview -->
            <div class="form-group">
                <label for="task_attachment" class="form-label">Attachment Image (Optional, Max 1 MB)</label>
                <div class="file-upload-zone" onclick="document.getElementById('task_attachment').click()">
                    <input type="file" name="attachment" id="task_attachment" style="display:none;" 
                           accept=".jpg,.jpeg,.png,.webp"
                           data-preview-img="taskPreviewImg"
                           data-preview-container="taskPreviewContainer">
                    <p style="margin: 0; font-size: 0.85rem;">Select image (.jpg, .png, .webp) &bull; Maximum size: <strong>1 MB</strong></p>
                </div>
                
                <div id="taskPreviewContainer" class="file-preview-wrap">
                    <div style="font-size: 0.75rem; font-weight: 700; margin-bottom: 0.25rem;">Image Preview:</div>
                    <img id="taskPreviewImg" src="" alt="Selected Preview" class="file-preview-img">
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
                <a href="board.php" class="btn btn-outline">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Task</button>
            </div>
        </form>
    </div>
<?php endif; ?>

<?php require_once 'footer.php'; ?>
