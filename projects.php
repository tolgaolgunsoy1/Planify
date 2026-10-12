<?php
// projects.php - Projects Management (Full CRUD for Projects Table)
require_once 'config.php';
require_once 'functions.php';
require_login();

$user = current_user();
$errors = [];

// Handle Project Creation or Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $color = trim($_POST['color'] ?? '#1e40af');

    // Server-side validation
    if (empty($title)) {
        $errors[] = 'Project title is required.';
    } elseif (mb_strlen($title) < 2 || mb_strlen($title) > 100) {
        $errors[] = 'Project title must be between 2 and 100 characters.';
    }

    if (!preg_match('/^#[a-f0-9]{6}$/i', $color)) {
        $color = '#1e40af';
    }

    if (empty($errors)) {
        try {
            if ($action === 'create') {
                $stmt = $pdo->prepare("INSERT INTO projects (user_id, title, description, color, created_at) VALUES (:uid, :title, :desc, :color, NOW())");
                $stmt->execute([
                    ':uid' => $user['id'],
                    ':title' => $title,
                    ':desc' => !empty($description) ? $description : null,
                    ':color' => $color,
                ]);
                set_flash('success', 'Project "' . $title . '" created successfully.');
                header('Location: projects.php');
                exit;
            } elseif ($action === 'update') {
                $projectId = filter_input(INPUT_POST, 'project_id', FILTER_VALIDATE_INT);
                if (!$projectId) {
                    $errors[] = 'Invalid project ID.';
                } else {
                    if (is_admin()) {
                        $updateStmt = $pdo->prepare("UPDATE projects SET title = :title, description = :desc, color = :color WHERE id = :id");
                        $updateStmt->execute([':title' => $title, ':desc' => !empty($description) ? $description : null, ':color' => $color, ':id' => $projectId]);
                    } else {
                        $updateStmt = $pdo->prepare("UPDATE projects SET title = :title, description = :desc, color = :color WHERE id = :id AND user_id = :uid");
                        $updateStmt->execute([':title' => $title, ':desc' => !empty($description) ? $description : null, ':color' => $color, ':id' => $projectId, ':uid' => $user['id']]);
                    }
                    set_flash('success', 'Project updated successfully.');
                    header('Location: projects.php');
                    exit;
                }
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Fetch projects with aggregate task metrics
try {
    $sql = "SELECT p.*, u.username AS owner_name,
            COUNT(t.id) AS total_tasks,
            SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_tasks,
            SUM(CASE WHEN t.status != 'done' AND t.deadline IS NOT NULL AND t.deadline < NOW() THEN 1 ELSE 0 END) AS overdue_tasks
            FROM projects p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN tasks t ON p.id = t.project_id
            WHERE 1=1 ";

    $params = [];
    if (!is_admin()) {
        $sql .= "AND p.user_id = :uid ";
        $params[':uid'] = $user['id'];
    }

    $sql .= "GROUP BY p.id ORDER BY p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
} catch (PDOException $e) {
    $projects = [];
}

$pageTitle = 'Projects';
require_once 'header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Projects</h1>
        <p class="page-subtitle">Manage project workspaces, organize related tasks and track completion metrics.</p>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="flash-banner flash-error" style="border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
        <div class="flash-content">
            <span class="flash-tag">[ERROR]</span>
            <div class="flash-message">
                <strong>Error details:</strong>
                <ul style="margin-top: 0.25rem; margin-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 320px; gap: 1.5rem; align-items: start;">
    <!-- Left: Projects List Cards -->
    <div>
        <h2 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 0.85rem;">Active Workspaces (<?= count($projects) ?>)</h2>
        
        <?php if (empty($projects)): ?>
            <div class="card" style="text-align: center; padding: 2.5rem;">
                <p style="color: var(--text-muted);">No projects recorded yet. Create an initial workspace using the panel on the right.</p>
            </div>
        <?php else: ?>
            <div class="projects-grid">
                <?php foreach ($projects as $proj): 
                    $total = (int)$proj['total_tasks'];
                    $done = (int)$proj['done_tasks'];
                    $overdue = (int)$proj['overdue_tasks'];
                    $pct = $total > 0 ? round(($done / $total) * 100) : 0;
                ?>
                    <div class="project-card" style="border-left-color: <?= e($proj['color']) ?>;">
                        <div>
                            <div class="project-header">
                                <h3 class="project-title"><?= e($proj['title']) ?></h3>
                                <span class="task-project-pill" style="background-color: <?= e($proj['color']) ?>;">
                                    <?= $total ?> tasks
                                </span>
                            </div>

                            <?php if (!empty($proj['description'])): ?>
                                <p class="project-desc" style="margin-top: 0.4rem;"><?= e($proj['description']) ?></p>
                            <?php endif; ?>

                            <!-- Completion Progress bar -->
                            <div style="margin-top: 0.75rem;">
                                <div style="display: flex; justify-content: space-between; font-size: 0.72rem; font-weight: 700; margin-bottom: 0.25rem;">
                                    <span>Completion: <?= $pct ?>%</span>
                                    <span><?= $done ?> / <?= $total ?></span>
                                </div>
                                <div style="background-color: #e2e8f0; border-radius: 2px; height: 5px; overflow: hidden;">
                                    <div style="background-color: <?= $pct == 100 ? 'var(--success)' : 'var(--primary)' ?>; width: <?= $pct ?>%; height: 100%;"></div>
                                </div>
                            </div>

                            <?php if ($overdue > 0): ?>
                                <div style="margin-top: 0.45rem; font-size: 0.75rem; color: var(--danger); font-weight: 700;">
                                    [Overdue: <?= $overdue ?> task<?= $overdue > 1 ? 's' : '' ?>]
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="project-meta">
                            <div>
                                <a href="board.php?project_id=<?= $proj['id'] ?>" class="btn btn-outline btn-sm">
                                    Board View
                                </a>
                                <a href="tasks.php?project_id=<?= $proj['id'] ?>" class="btn btn-outline btn-sm">
                                    List View
                                </a>
                            </div>

                            <form method="POST" action="project-delete.php" style="display:inline;" onsubmit="return confirm('Deleting this project will permanently remove its tasks. Proceed?');">
                                <input type="hidden" name="project_id" value="<?= $proj['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Add New Project Form -->
    <div class="card" style="position: sticky; top: 80px;">
        <h3 class="card-title">+ New Project</h3>
        <form method="POST" action="projects.php" novalidate>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label for="proj_title" class="form-label">Title <span class="required">*</span></label>
                <input type="text" name="title" id="proj_title" class="form-control" required maxlength="100" placeholder="Project name">
            </div>

            <div class="form-group">
                <label for="proj_desc" class="form-label">Description</label>
                <textarea name="description" id="proj_desc" class="form-control" rows="3" placeholder="Scope and objective..."></textarea>
            </div>

            <div class="form-group">
                <label for="proj_color" class="form-label">Identifier Color</label>
                <input type="color" name="color" id="proj_color" class="form-control" value="#1e40af" style="height: 38px; padding: 2px;">
            </div>

            <button type="submit" class="btn btn-primary btn-block">Create Workspace</button>
        </form>
    </div>
</div>

<?php require_once 'footer.php'; ?>
