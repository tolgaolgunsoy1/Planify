<?php
// index.php - Main Dashboard Overview & Landing Portal
$pageTitle = 'Dashboard';
require_once 'header.php';

$user = current_user();

if ($user):
    // Authenticated Dashboard
    try {
        // Task statistics query
        if (is_admin()) {
            $statsSql = "SELECT 
                COUNT(id) AS total,
                SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) AS todo_count,
                SUM(CASE WHEN status = 'doing' THEN 1 ELSE 0 END) AS doing_count,
                SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS done_count,
                SUM(CASE WHEN status != 'done' AND deadline IS NOT NULL AND deadline < NOW() THEN 1 ELSE 0 END) AS overdue_count
                FROM tasks";
            $statStmt = $pdo->query($statsSql);
            $stats = $statStmt->fetch();

            // Recent projects
            $pStmt = $pdo->query("SELECT p.*, u.username AS owner_name, COUNT(t.id) AS task_count 
                                  FROM projects p 
                                  JOIN users u ON p.user_id = u.id 
                                  LEFT JOIN tasks t ON p.id = t.project_id 
                                  GROUP BY p.id ORDER BY p.created_at DESC LIMIT 4");
            $recentProjects = $pStmt->fetchAll();

            // Overdue tasks
            $overdueStmt = $pdo->query("SELECT t.*, p.title AS project_title, p.color AS project_color 
                                        FROM tasks t 
                                        JOIN projects p ON t.project_id = p.id 
                                        WHERE t.status != 'done' AND t.deadline IS NOT NULL AND t.deadline < NOW() 
                                        ORDER BY t.deadline ASC LIMIT 5");
            $overdueTasks = $overdueStmt->fetchAll();

            // Recent tasks
            $recentStmt = $pdo->query("SELECT t.*, p.title AS project_title, p.color AS project_color 
                                       FROM tasks t 
                                       JOIN projects p ON t.project_id = p.id 
                                       ORDER BY t.created_at DESC LIMIT 5");
            $recentTasks = $recentStmt->fetchAll();

        } else {
            $statsSql = "SELECT 
                COUNT(t.id) AS total,
                SUM(CASE WHEN t.status = 'todo' THEN 1 ELSE 0 END) AS todo_count,
                SUM(CASE WHEN t.status = 'doing' THEN 1 ELSE 0 END) AS doing_count,
                SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) AS done_count,
                SUM(CASE WHEN t.status != 'done' AND deadline IS NOT NULL AND deadline < NOW() THEN 1 ELSE 0 END) AS overdue_count
                FROM tasks t
                JOIN projects p ON t.project_id = p.id
                WHERE (t.user_id = :uid OR p.user_id = :uid)";
            $statStmt = $pdo->prepare($statsSql);
            $statStmt->execute([':uid' => $user['id']]);
            $stats = $statStmt->fetch();

            // User projects
            $pStmt = $pdo->prepare("SELECT p.*, COUNT(t.id) AS task_count 
                                    FROM projects p 
                                    LEFT JOIN tasks t ON p.id = t.project_id 
                                    WHERE p.user_id = :uid 
                                    GROUP BY p.id ORDER BY p.created_at DESC LIMIT 4");
            $pStmt->execute([':uid' => $user['id']]);
            $recentProjects = $pStmt->fetchAll();

            // Overdue tasks for user
            $overdueStmt = $pdo->prepare("SELECT t.*, p.title AS project_title, p.color AS project_color 
                                          FROM tasks t 
                                          JOIN projects p ON t.project_id = p.id 
                                          WHERE (t.user_id = :uid OR p.user_id = :uid) 
                                          AND t.status != 'done' AND t.deadline IS NOT NULL AND t.deadline < NOW() 
                                          ORDER BY t.deadline ASC LIMIT 5");
            $overdueStmt->execute([':uid' => $user['id']]);
            $overdueTasks = $overdueStmt->fetchAll();

            // Recent tasks
            $recentStmt = $pdo->prepare("SELECT t.*, p.title AS project_title, p.color AS project_color 
                                         FROM tasks t 
                                         JOIN projects p ON t.project_id = p.id 
                                         WHERE (t.user_id = :uid OR p.user_id = :uid) 
                                         ORDER BY t.created_at DESC LIMIT 5");
            $recentStmt->execute([':uid' => $user['id']]);
            $recentTasks = $recentStmt->fetchAll();
        }
    } catch (PDOException $e) {
        $stats = ['total' => 0, 'todo_count' => 0, 'doing_count' => 0, 'done_count' => 0, 'overdue_count' => 0];
        $recentProjects = [];
        $overdueTasks = [];
        $recentTasks = [];
    }
?>

    <div class="page-header">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Welcome back, <?= e($user['username']) ?>. Overview of active projects and pending task deadlines.</p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="board.php" class="btn btn-primary">Open Task Board</a>
            <a href="task-create.php" class="btn btn-outline">+ New Task</a>
        </div>
    </div>

    <!-- Signature Feature Highlight: Overdue Alert on Dashboard (Clean, Corporate) -->
    <?php if ((int)$stats['overdue_count'] > 0): ?>
        <div class="overdue-alert-banner">
            <div class="overdue-alert-content">
                <span class="overdue-alert-badge">Overdue Notice</span>
                <div>
                    <div class="overdue-alert-title">Attention: <?= (int)$stats['overdue_count'] ?> task<?= (int)$stats['overdue_count'] > 1 ? 's are' : ' is' ?> past deadline</div>
                    <div class="overdue-alert-desc">These items passed their scheduled deadline without completion.</div>
                </div>
            </div>
            <a href="board.php" class="btn btn-danger btn-sm">Inspect on Board</a>
        </div>
    <?php endif; ?>

    <!-- Dashboard Metric Stat Cards -->
    <div class="stats-grid">
        <div class="stat-card stat-card-primary">
            <div class="stat-number"><?= (int)$stats['total'] ?></div>
            <div class="stat-label">Total Tasks</div>
        </div>

        <div class="stat-card">
            <div class="stat-number"><?= (int)$stats['todo_count'] ?></div>
            <div class="stat-label">To Do</div>
        </div>

        <div class="stat-card stat-card-warning">
            <div class="stat-number"><?= (int)$stats['doing_count'] ?></div>
            <div class="stat-label">In Progress</div>
        </div>

        <div class="stat-card stat-card-success">
            <div class="stat-number"><?= (int)$stats['done_count'] ?></div>
            <div class="stat-label">Completed</div>
        </div>

        <div class="stat-card stat-card-danger">
            <div class="stat-number" style="color: var(--danger);"><?= (int)$stats['overdue_count'] ?></div>
            <div class="stat-label">Past Deadline</div>
        </div>
    </div>

    <!-- 2 Column Layout: Overdue & Recent Tasks vs Projects -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
        <div>
            <!-- Overdue Tasks Preview if any -->
            <?php if (!empty($overdueTasks)): ?>
                <div class="card" style="border-left: 4px solid var(--danger);">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                        <h2 class="card-title" style="color: var(--danger); margin: 0;">Overdue Tasks</h2>
                        <a href="board.php" class="btn btn-danger btn-sm">Review on Board</a>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                        <?php foreach ($overdueTasks as $task): 
                            $badge = format_deadline_badge($task['deadline'], $task['status']);
                        ?>
                            <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.65rem 0.85rem; background-color: #fff1f2; border: 1px solid #fecaca; border-radius: var(--radius-sm);">
                                <div>
                                    <strong><a href="task-edit.php?id=<?= $task['id'] ?>" style="color: #7f1d1d;"><?= e($task['title']) ?></a></strong>
                                    <div style="font-size: 0.75rem; color: #991b1b; margin-top: 0.15rem;">
                                        Project: <?= e($task['project_title']) ?> &bull; Status: <?= ucfirst(e($task['status'])) ?>
                                    </div>
                                </div>
                                <span class="badge-overdue" style="font-size: 0.72rem;">
                                    <?= e($badge['text']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Tasks Table -->
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                    <h2 class="card-title" style="margin: 0;">Recent Tasks</h2>
                    <a href="tasks.php" class="btn btn-outline btn-sm">View All Tasks</a>
                </div>

                <?php if (empty($recentTasks)): ?>
                    <p style="color: var(--text-muted); font-size: 0.85rem;">No tasks created yet. <a href="task-create.php">Create task</a>.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Task</th>
                                    <th>Project</th>
                                    <th>Status</th>
                                    <th>Deadline</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentTasks as $rt): 
                                    $isOd = is_task_overdue($rt['deadline'], $rt['status']);
                                    $dbadge = format_deadline_badge($rt['deadline'], $rt['status']);
                                ?>
                                    <tr class="<?= $isOd ? 'row-overdue' : '' ?>">
                                        <td>
                                            <a href="task-edit.php?id=<?= $rt['id'] ?>"><strong><?= e($rt['title']) ?></strong></a>
                                        </td>
                                        <td>
                                            <span class="task-project-pill" style="background-color: <?= e($rt['project_color']) ?>;">
                                                <?= e($rt['project_title']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="table-status-pill status-<?= e($rt['status']) ?>">
                                                <?= ucfirst(e($rt['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="<?= $dbadge['class'] ?>" style="font-size: 0.78rem;">
                                                <?= e($dbadge['text']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Side: Projects Overview -->
        <div>
            <div class="card">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem;">
                    <h2 class="card-title" style="margin: 0;">Projects</h2>
                    <a href="projects.php" class="btn btn-outline btn-sm">+ New</a>
                </div>

                <?php if (empty($recentProjects)): ?>
                    <p style="color: var(--text-muted); font-size: 0.85rem;">No projects found. <a href="projects.php">Create project</a>.</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 0.65rem;">
                        <?php foreach ($recentProjects as $rp): ?>
                            <div style="padding: 0.75rem 0.85rem; border: 1px solid var(--border-color); border-radius: var(--radius-sm); border-left: 4px solid <?= e($rp['color']) ?>;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <strong style="font-size: 0.9rem;"><?= e($rp['title']) ?></strong>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);"><?= (int)$rp['task_count'] ?> tasks</span>
                                </div>
                                <div style="margin-top: 0.45rem;">
                                    <a href="board.php?project_id=<?= $rp['id'] ?>" class="btn btn-outline btn-sm" style="font-size: 0.72rem; padding: 0.15rem 0.45rem;">
                                        View on Board
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php else: ?>
    <!-- Guest / Public Landing Page (Clean, Professional, Serious) -->
    <div style="text-align: center; max-width: 760px; margin: 3rem auto;">
        <h1 style="font-size: 2.25rem; font-weight: 800; color: #0f172a; margin: 0.5rem 0 0.85rem 0; letter-spacing: -0.02em;">
            Planify Task Management
        </h1>
        <p style="font-size: 1.05rem; color: var(--text-muted); line-height: 1.6; margin-bottom: 1.75rem;">
            A responsive, database-driven Kanban task management platform developed with Plain PHP and MySQL PDO.
            Fulfills all requirements for course <strong>IMS207 / IMS301 Web Design and Internet Programming</strong> (Topic D2).
        </p>

        <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
            <a href="login.php" class="btn btn-primary btn-lg">Sign In</a>
            <a href="register.php" class="btn btn-outline btn-lg">Register Account</a>
        </div>
    </div>

    <!-- Signature Features Grid (Serious, Corporate) -->
    <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); margin-bottom: 2.5rem;">
        <div class="card" style="border-top: 3px solid var(--primary);">
            <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.4rem;">Workflow Stages</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5;">
                Tasks transition between "To Do", "In Progress" and "Done" columns via interactive drag-and-drop or single-click status actions.
            </p>
        </div>

        <div class="card" style="border-top: 3px solid var(--danger);">
            <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.4rem;">Deadline Monitoring</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5;">
                Tasks whose deadline has expired without completion are automatically highlighted with high-contrast red borders and overdue labels.
            </p>
        </div>

        <div class="card" style="border-top: 3px solid #64748b;">
            <h3 style="font-size: 1rem; font-weight: 700; margin-bottom: 0.4rem;">Project Segregation & Search</h3>
            <p style="color: var(--text-muted); font-size: 0.85rem; line-height: 1.5;">
                Filter tasks dynamically by project workspace, with live real-time filtering on task list tables.
            </p>
        </div>
    </div>

    <!-- Ready Demo Accounts Panel -->
    <div class="card" style="background-color: #ffffff; text-align: center; padding: 1.5rem;">
        <h3 style="font-size: 0.95rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.4rem;">
            Evaluation Accounts (Requirement 8)
        </h3>
        <p style="color: var(--text-muted); font-size: 0.825rem; margin-bottom: 1rem;">
            Both user roles can be evaluated using the accounts below:
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <div style="background: var(--bg-subtle); padding: 0.65rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 0.85rem;">
                <strong>Administrator:</strong> <code>admin@planify.local</code> / <code>Admin123!</code>
            </div>
            <div style="background: var(--bg-subtle); padding: 0.65rem 1rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color); font-size: 0.85rem;">
                <strong>Standard Member:</strong> <code>member@planify.local</code> / <code>Member123!</code>
            </div>
        </div>
        <div style="margin-top: 1rem;">
            <a href="login.php" class="btn btn-primary btn-sm">Sign In to Test</a>
        </div>
    </div>

<?php endif; ?>

<?php require_once 'footer.php'; ?>
