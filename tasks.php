<?php
// tasks.php - Comprehensive Task List Table View
// Features: Search, Filter by Project & Status, Sort, Full CRUD operations, Overdue Highlight
$pageTitle = 'All Tasks';
require_once 'header.php';
require_login();

$user = current_user();

// Filters from GET
$searchQuery = trim($_GET['q'] ?? '');
$projectFilter = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT);
$statusFilter = trim($_GET['status'] ?? '');
$sort = trim($_GET['sort'] ?? 'deadline_asc');

// Fetch user projects for filter dropdown
try {
    if (is_admin()) {
        $pStmt = $pdo->query("SELECT id, title FROM projects ORDER BY title ASC");
        $projects = $pStmt->fetchAll();
    } else {
        $pStmt = $pdo->prepare("SELECT id, title FROM projects WHERE user_id = :uid ORDER BY title ASC");
        $pStmt->execute([':uid' => $user['id']]);
        $projects = $pStmt->fetchAll();
    }
} catch (PDOException $e) {
    $projects = [];
}

// Build SQL query with prepared statement
$params = [];
$sql = "SELECT t.*, p.title AS project_title, p.color AS project_color, u.username AS creator_name 
        FROM tasks t 
        JOIN projects p ON t.project_id = p.id 
        JOIN users u ON t.user_id = u.id 
        WHERE 1=1 ";

if (!is_admin()) {
    $sql .= "AND (t.user_id = :uid OR p.user_id = :uid) ";
    $params[':uid'] = $user['id'];
}

if (!empty($searchQuery)) {
    $sql .= "AND (t.title LIKE :search OR t.description LIKE :search) ";
    $params[':search'] = '%' . $searchQuery . '%';
}

if ($projectFilter) {
    $sql .= "AND t.project_id = :pid ";
    $params[':pid'] = $projectFilter;
}

if (!empty($statusFilter) && in_array($statusFilter, ['todo', 'doing', 'done'])) {
    $sql .= "AND t.status = :status ";
    $params[':status'] = $statusFilter;
}

// Sort order
switch ($sort) {
    case 'deadline_desc':
        $sql .= "ORDER BY t.deadline DESC, t.id DESC";
        break;
    case 'priority_high':
        $sql .= "ORDER BY FIELD(t.priority, 'high', 'medium', 'low'), t.deadline ASC";
        break;
    case 'newest':
        $sql .= "ORDER BY t.created_at DESC";
        break;
    case 'deadline_asc':
    default:
        $sql .= "ORDER BY (t.deadline IS NULL), t.deadline ASC, t.id DESC";
        break;
}

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll();
} catch (PDOException $e) {
    $tasks = [];
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Tasks Management</h1>
        <p class="page-subtitle">View, search, filter and manage all tasks in tabular format.</p>
    </div>
    <div style="display:flex; gap:0.5rem;">
        <a href="board.php<?= $projectFilter ? '?project_id='.$projectFilter : '' ?>" class="btn btn-outline">
            Board View
        </a>
        <a href="task-create.php<?= $projectFilter ? '?project_id='.$projectFilter : '' ?>" class="btn btn-primary">
            + New Task
        </a>
    </div>
</div>

<!-- Search and Filter Bar -->
<div class="filter-bar">
    <form method="GET" action="tasks.php" class="filter-controls" style="width: 100%;">
        <!-- Real-time DOM interaction search input -->
        <div class="filter-group" style="flex: 1; min-width: 220px;">
            <input type="text" name="q" id="liveTaskSearch" value="<?= e($searchQuery) ?>" 
                   placeholder="Search by task title or description..." class="filter-input" style="width: 100%;">
        </div>

        <div class="filter-group">
            <label for="filter_project" class="filter-label">Project:</label>
            <select name="project_id" id="filter_project" class="filter-select">
                <option value="">All Projects</option>
                <?php foreach ($projects as $proj): ?>
                    <option value="<?= $proj['id'] ?>" <?= $projectFilter === (int)$proj['id'] ? 'selected' : '' ?>>
                        <?= e($proj['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="filter_status" class="filter-label">Status:</label>
            <select name="status" id="filter_status" class="filter-select">
                <option value="">All Statuses</option>
                <option value="todo" <?= $statusFilter === 'todo' ? 'selected' : '' ?>>To Do</option>
                <option value="doing" <?= $statusFilter === 'doing' ? 'selected' : '' ?>>In Progress</option>
                <option value="done" <?= $statusFilter === 'done' ? 'selected' : '' ?>>Done</option>
            </select>
        </div>

        <div class="filter-group">
            <label for="filter_sort" class="filter-label">Sort:</label>
            <select name="sort" id="filter_sort" class="filter-select">
                <option value="deadline_asc" <?= $sort === 'deadline_asc' ? 'selected' : '' ?>>Deadline (Earliest)</option>
                <option value="deadline_desc" <?= $sort === 'deadline_desc' ? 'selected' : '' ?>>Deadline (Latest)</option>
                <option value="priority_high" <?= $sort === 'priority_high' ? 'selected' : '' ?>>Priority (High first)</option>
                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Recently Created</option>
            </select>
        </div>

        <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        <?php if (!empty($searchQuery) || $projectFilter || !empty($statusFilter)): ?>
            <a href="tasks.php" class="btn btn-outline btn-sm">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Tasks Table -->
<div class="table-responsive">
    <table class="data-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Project</th>
                <th>Status</th>
                <th>Priority</th>
                <th>Deadline</th>
                <th>Creator</th>
                <th style="text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody id="taskTableBody">
            <?php if (empty($tasks)): ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                        No tasks found matching your filter criteria. <a href="task-create.php">Create a new task</a>.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($tasks as $task): 
                    $isOverdue = is_task_overdue($task['deadline'], $task['status']);
                    $deadlineBadge = format_deadline_badge($task['deadline'], $task['status']);
                ?>
                    <tr class="task-row <?= $isOverdue ? 'row-overdue' : '' ?>"
                        data-title="<?= e($task['title']) ?>"
                        data-desc="<?= e($task['description'] ?? '') ?>"
                        data-project="<?= e($task['project_title']) ?>">
                        <td>
                            <strong>
                                <a href="task-edit.php?id=<?= $task['id'] ?>">
                                    <?= e($task['title']) ?>
                                </a>
                            </strong>
                            <?php if ($isOverdue): ?>
                                <span class="badge-overdue" style="font-size: 0.72rem; margin-left: 0.4rem;">[OVERDUE]</span>
                            <?php endif; ?>
                            <?php if (!empty($task['description'])): ?>
                                <div style="font-size: 0.78rem; color: var(--text-muted); max-width: 320px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <?= e($task['description']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="task-project-pill" style="background-color: <?= e($task['project_color'] ?? '#1e40af') ?>;">
                                <?= e($task['project_title']) ?>
                            </span>
                        </td>
                        <td>
                            <!-- Quick status change form -->
                            <form method="POST" action="task-move.php" style="display: inline-block;">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <input type="hidden" name="return_url" value="tasks.php">
                                <select name="new_status" class="filter-select" style="font-size: 0.75rem; padding: 0.15rem 0.35rem;" onchange="this.form.submit()">
                                    <option value="todo" <?= $task['status'] === 'todo' ? 'selected' : '' ?>>To Do</option>
                                    <option value="doing" <?= $task['status'] === 'doing' ? 'selected' : '' ?>>In Progress</option>
                                    <option value="done" <?= $task['status'] === 'done' ? 'selected' : '' ?>>Done</option>
                                </select>
                            </form>
                        </td>
                        <td>
                            <span class="task-priority-pill priority-<?= e($task['priority']) ?>">
                                <?= e($task['priority']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?= $deadlineBadge['class'] ?>" style="font-size: 0.78rem;">
                                <?= e($deadlineBadge['text']) ?>
                            </span>
                        </td>
                        <td>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">
                                <?= e($task['creator_name']) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <div class="table-actions" style="justify-content: flex-end;">
                                <a href="task-edit.php?id=<?= $task['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                                <form method="POST" action="task-delete.php" style="display:inline;" onsubmit="return confirm('Delete this task?');">
                                    <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                    <input type="hidden" name="return_url" value="tasks.php">
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require_once 'footer.php'; ?>
