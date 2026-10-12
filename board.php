<?php
// board.php - Signature Feature: Interactive Kanban Task Board
// 1. Tasks move between "to do", "doing", and "done"
// 2. Tasks whose deadline has passed are highlighted prominently
// 3. Filter by project
$pageTitle = 'Task Board';
require_once 'header.php';
require_login();

$user = current_user();
$projectIdFilter = filter_input(INPUT_GET, 'project_id', FILTER_VALIDATE_INT);

// 1. Fetch available projects for the filter dropdown
try {
    if (is_admin()) {
        $projStmt = $pdo->query("SELECT id, title, color FROM projects ORDER BY title ASC");
        $projects = $projStmt->fetchAll();
    } else {
        $projStmt = $pdo->prepare("SELECT id, title, color FROM projects WHERE user_id = :uid ORDER BY title ASC");
        $projStmt->execute([':uid' => $user['id']]);
        $projects = $projStmt->fetchAll();
    }
} catch (PDOException $e) {
    $projects = [];
}

// 2. Build tasks query with project filter and user permission check
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

if ($projectIdFilter) {
    $sql .= "AND t.project_id = :pid ";
    $params[':pid'] = $projectIdFilter;
}

$sql .= "ORDER BY t.priority = 'high' DESC, t.deadline ASC, t.created_at DESC";

try {
    $taskStmt = $pdo->prepare($sql);
    $taskStmt->execute($params);
    $allTasks = $taskStmt->fetchAll();
} catch (PDOException $e) {
    $allTasks = [];
}

// Separate tasks into Kanban columns and count overdue
$columns = [
    'todo' => [],
    'doing' => [],
    'done' => [],
];

$overdueCount = 0;
foreach ($allTasks as $task) {
    $status = $task['status'];
    if (isset($columns[$status])) {
        $columns[$status][] = $task;
    }
    if (is_task_overdue($task['deadline'], $task['status'])) {
        $overdueCount++;
    }
}
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Task Board</h1>
        <p class="page-subtitle">Kanban workflow management. Move tasks between stages and track project deadlines.</p>
    </div>
    <div>
        <a href="task-create.php<?= $projectIdFilter ? '?project_id=' . $projectIdFilter : '' ?>" class="btn btn-primary">
            + New Task
        </a>
    </div>
</div>

<!-- Signature Feature 2: Overdue Deadline Alert Banner (Clean, Professional) -->
<?php if ($overdueCount > 0): ?>
    <div class="overdue-alert-banner">
        <div class="overdue-alert-content">
            <span class="overdue-alert-badge">Overdue Notice</span>
            <div>
                <div class="overdue-alert-title"><?= $overdueCount ?> task<?= $overdueCount > 1 ? 's have' : ' has' ?> passed deadline</div>
                <div class="overdue-alert-desc">Unfinished tasks past their deadline are highlighted with a high-contrast red border below.</div>
            </div>
        </div>
        <a href="#overdue-section" class="btn btn-danger btn-sm" onclick="document.querySelector('.card-overdue')?.scrollIntoView({behavior: 'smooth'}); return false;">
            View Overdue Tasks
        </a>
    </div>
<?php endif; ?>

<!-- Signature Feature 3: Filter by Project Bar -->
<div class="filter-bar">
    <div class="filter-controls">
        <form method="GET" action="board.php" class="filter-group">
            <label for="projectFilterSelect" class="filter-label">Project Filter:</label>
            <select name="project_id" id="projectFilterSelect" class="filter-select" onchange="this.form.submit()">
                <option value="">All Projects (<?= count($allTasks) ?> tasks)</option>
                <?php foreach ($projects as $proj): ?>
                    <option value="<?= $proj['id'] ?>" <?= $projectIdFilter === (int)$proj['id'] ? 'selected' : '' ?>>
                        <?= e($proj['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <noscript>
                <button type="submit" class="btn btn-outline btn-sm">Filter</button>
            </noscript>
        </form>

        <?php if ($projectIdFilter): ?>
            <a href="board.php" class="btn btn-outline btn-sm">Reset Filter</a>
        <?php endif; ?>
    </div>

    <div class="board-legend">
        <span class="badge-overdue" style="font-size: 0.78rem; margin-right: 0.75rem;">
            [Overdue: Red Border]
        </span>
        <span class="badge-done" style="font-size: 0.78rem;">
            [Completed: Green]
        </span>
    </div>
</div>

<!-- Signature Feature 1: Move Between "To Do", "Doing", and "Done" -->
<div class="board-columns">
    <?php
    $columnConfigs = [
        'todo' => ['title' => 'To Do', 'class' => 'column-todo'],
        'doing' => ['title' => 'In Progress', 'class' => 'column-doing'],
        'done' => ['title' => 'Done', 'class' => 'column-done'],
    ];

    foreach ($columnConfigs as $colKey => $colConfig):
        $tasksInCol = $columns[$colKey];
    ?>
        <section class="board-column <?= $colConfig['class'] ?>">
            <div class="column-header">
                <div class="column-title">
                    <span><?= $colConfig['title'] ?></span>
                </div>
                <span class="column-count"><?= count($tasksInCol) ?></span>
            </div>

            <div class="column-tasks-container" data-status="<?= $colKey ?>">
                <?php if (empty($tasksInCol)): ?>
                    <div class="task-empty-state">No tasks in <?= strtolower($colConfig['title']) ?>.</div>
                <?php else: ?>
                    <?php foreach ($tasksInCol as $task): 
                        $isOverdue = is_task_overdue($task['deadline'], $task['status']);
                        $deadlineBadge = format_deadline_badge($task['deadline'], $task['status']);
                    ?>
                        <article class="task-card <?= $isOverdue ? 'card-overdue' : '' ?>" 
                                 draggable="true" 
                                 data-task-id="<?= $task['id'] ?>"
                                 data-current-status="<?= $task['status'] ?>">
                            
                            <div class="task-card-header">
                                <span class="task-project-pill" style="background-color: <?= e($task['project_color'] ?? '#1e40af') ?>;">
                                    <?= e($task['project_title']) ?>
                                </span>
                                <span class="task-priority-pill priority-<?= e($task['priority']) ?>">
                                    <?= e($task['priority']) ?>
                                </span>
                            </div>

                            <h3 class="task-card-title">
                                <a href="task-edit.php?id=<?= $task['id'] ?>">
                                    <?= e($task['title']) ?>
                                </a>
                            </h3>

                            <?php if (!empty($task['description'])): ?>
                                <p class="task-card-desc"><?= e($task['description']) ?></p>
                            <?php endif; ?>

                            <?php if (!empty($task['attachment']) && file_exists('uploads/' . $task['attachment'])): ?>
                                <div class="task-card-attachment">
                                    <img src="uploads/<?= e($task['attachment']) ?>" alt="Attachment" class="task-card-img-preview">
                                </div>
                            <?php endif; ?>

                            <div class="task-card-footer">
                                <div class="task-deadline <?= $deadlineBadge['class'] ?>" title="Deadline">
                                    <span><?= e($deadlineBadge['text']) ?></span>
                                </div>

                                <!-- Accessible Non-JS Quick Move Action Buttons -->
                                <div class="task-move-actions">
                                    <?php if ($colKey === 'todo'): ?>
                                        <form method="POST" action="task-move.php" style="display:inline;">
                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                            <input type="hidden" name="new_status" value="doing">
                                            <input type="hidden" name="return_url" value="board.php<?= $projectIdFilter ? '?project_id='.$projectIdFilter : '' ?>">
                                            <button type="submit" class="btn-move" title="Move to In Progress">To Doing</button>
                                        </form>
                                        <form method="POST" action="task-move.php" style="display:inline;">
                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                            <input type="hidden" name="new_status" value="done">
                                            <input type="hidden" name="return_url" value="board.php<?= $projectIdFilter ? '?project_id='.$projectIdFilter : '' ?>">
                                            <button type="submit" class="btn-move" title="Move to Done">To Done</button>
                                        </form>
                                    <?php elseif ($colKey === 'doing'): ?>
                                        <form method="POST" action="task-move.php" style="display:inline;">
                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                            <input type="hidden" name="new_status" value="todo">
                                            <input type="hidden" name="return_url" value="board.php<?= $projectIdFilter ? '?project_id='.$projectIdFilter : '' ?>">
                                            <button type="submit" class="btn-move" title="Move to To Do">To To Do</button>
                                        </form>
                                        <form method="POST" action="task-move.php" style="display:inline;">
                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                            <input type="hidden" name="new_status" value="done">
                                            <input type="hidden" name="return_url" value="board.php<?= $projectIdFilter ? '?project_id='.$projectIdFilter : '' ?>">
                                            <button type="submit" class="btn-move" title="Move to Done">To Done</button>
                                        </form>
                                    <?php elseif ($colKey === 'done'): ?>
                                        <form method="POST" action="task-move.php" style="display:inline;">
                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                            <input type="hidden" name="new_status" value="doing">
                                            <input type="hidden" name="return_url" value="board.php<?= $projectIdFilter ? '?project_id='.$projectIdFilter : '' ?>">
                                            <button type="submit" class="btn-move" title="Reopen to In Progress">To Doing</button>
                                        </form>
                                        <form method="POST" action="task-move.php" style="display:inline;">
                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                            <input type="hidden" name="new_status" value="todo">
                                            <input type="hidden" name="return_url" value="board.php<?= $projectIdFilter ? '?project_id='.$projectIdFilter : '' ?>">
                                            <button type="submit" class="btn-move" title="Move to To Do">To To Do</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>

<?php require_once 'footer.php'; ?>
