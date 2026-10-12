<?php
// admin.php - Administrator Control Panel
// Requirement 8: Two kinds of user (member and admin) who see different things
require_once 'config.php';
require_once 'functions.php';
require_admin();

$user = current_user();
$errors = [];

// Handle Admin Actions (Role Toggle, Delete User)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $targetUserId = filter_input(INPUT_POST, 'target_user_id', FILTER_VALIDATE_INT);

    if (!$targetUserId) {
        $errors[] = 'Invalid target user ID.';
    } elseif ($action === 'toggle_role') {
        $newRole = ($_POST['new_role'] === 'admin') ? 'admin' : 'member';
        
        // Prevent demoting self
        if ($targetUserId === (int)$user['id']) {
            $errors[] = 'You cannot change your own administrator role.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET role = :role WHERE id = :id");
                $stmt->execute([':role' => $newRole, ':id' => $targetUserId]);
                set_flash('success', 'User role updated to ' . ucfirst($newRole) . '.');
                header('Location: admin.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Database error updating role: ' . $e->getMessage();
            }
        }
    } elseif ($action === 'delete_user') {
        // Prevent deleting self
        if ($targetUserId === (int)$user['id']) {
            $errors[] = 'You cannot delete your own active administrator account.';
        } else {
            try {
                // Clean up any uploaded avatars or task attachments for this user
                $attStmt = $pdo->prepare("SELECT attachment FROM tasks WHERE user_id = :uid AND attachment IS NOT NULL");
                $attStmt->execute([':uid' => $targetUserId]);
                foreach ($attStmt->fetchAll() as $att) {
                    if (file_exists('uploads/' . $att['attachment'])) {
                        unlink('uploads/' . $att['attachment']);
                    }
                }

                $avatarStmt = $pdo->prepare("SELECT avatar FROM users WHERE id = :uid AND avatar IS NOT NULL");
                $avatarStmt->execute([':uid' => $targetUserId]);
                $avatarRow = $avatarStmt->fetch();
                if ($avatarRow && file_exists('uploads/' . $avatarRow['avatar'])) {
                    unlink('uploads/' . $avatarRow['avatar']);
                }

                // Delete user (cascades to projects & tasks)
                $delStmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $delStmt->execute([':id' => $targetUserId]);

                set_flash('success', 'User account and associated records deleted.');
                header('Location: admin.php');
                exit;
            } catch (PDOException $e) {
                $errors[] = 'Database error deleting user: ' . $e->getMessage();
            }
        }
    }
}

// Fetch Global System Statistics
try {
    $statUsers = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $statProjects = (int)$pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
    $statTasks = (int)$pdo->query("SELECT COUNT(*) FROM tasks")->fetchColumn();
    $statOverdue = (int)$pdo->query("SELECT COUNT(*) FROM tasks WHERE status != 'done' AND deadline IS NOT NULL AND deadline < NOW()")->fetchColumn();

    // Fetch all users
    $userList = $pdo->query("SELECT u.*, 
        COUNT(DISTINCT p.id) AS project_count, 
        COUNT(DISTINCT t.id) AS task_count 
        FROM users u 
        LEFT JOIN projects p ON u.id = p.user_id 
        LEFT JOIN tasks t ON u.id = t.user_id 
        GROUP BY u.id 
        ORDER BY u.created_at ASC")->fetchAll();

    // Fetch all system tasks
    $allTasks = $pdo->query("SELECT t.*, p.title AS project_title, u.username AS creator_name 
                             FROM tasks t 
                             JOIN projects p ON t.project_id = p.id 
                             JOIN users u ON t.user_id = u.id 
                             ORDER BY t.created_at DESC LIMIT 10")->fetchAll();

} catch (PDOException $e) {
    die("Admin query error: " . $e->getMessage());
}

$pageTitle = 'Admin Panel';
require_once 'header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Administrator Panel</h1>
        <p class="page-subtitle">User privileges, project oversight and system activity monitoring.</p>
    </div>
    <div>
        <span class="user-role-pill role-admin" style="font-size: 0.8rem; padding: 0.25rem 0.65rem;">
            ADMINISTRATOR
        </span>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="flash-banner flash-error" style="border-radius: var(--radius-sm); margin-bottom: 1.5rem;">
        <div class="flash-content">
            <span class="flash-tag">[ERROR]</span>
            <div class="flash-message">
                <?php foreach ($errors as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- System Global Stats (Clean, Serious Top Borders) -->
<div class="stats-grid">
    <div class="stat-card stat-card-primary">
        <div class="stat-number"><?= $statUsers ?></div>
        <div class="stat-label">Total Users</div>
    </div>

    <div class="stat-card">
        <div class="stat-number"><?= $statProjects ?></div>
        <div class="stat-label">Total Projects</div>
    </div>

    <div class="stat-card stat-card-success">
        <div class="stat-number"><?= $statTasks ?></div>
        <div class="stat-label">Total Tasks</div>
    </div>

    <div class="stat-card stat-card-danger">
        <div class="stat-number" style="color: var(--danger);"><?= $statOverdue ?></div>
        <div class="stat-label">System Overdue</div>
    </div>
</div>

<!-- Users Management Table -->
<div class="card">
    <h2 class="card-title">User Accounts (<?= count($userList) ?>)</h2>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Projects</th>
                    <th>Tasks</th>
                    <th>Registered</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($userList as $u): ?>
                    <tr>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <?php if (!empty($u['avatar']) && file_exists('uploads/' . $u['avatar'])): ?>
                                    <img src="uploads/<?= e($u['avatar']) ?>" alt="<?= e($u['username']) ?>" class="nav-avatar">
                                <?php else: ?>
                                    <span class="nav-avatar-fallback" style="width:24px; height:24px; font-size:0.72rem;">
                                        <?= strtoupper(substr($u['username'], 0, 1)) ?>
                                    </span>
                                <?php endif; ?>
                                <strong><?= e($u['username']) ?></strong>
                                <?php if ((int)$u['id'] === (int)$user['id']): ?>
                                    <span style="font-size: 0.72rem; color: var(--text-muted);">(You)</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= e($u['email']) ?></td>
                        <td>
                            <span class="user-role-pill role-<?= e($u['role']) ?>">
                                <?= strtoupper(e($u['role'])) ?>
                            </span>
                        </td>
                        <td><?= (int)$u['project_count'] ?></td>
                        <td><?= (int)$u['task_count'] ?></td>
                        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td style="text-align: right;">
                            <div class="table-actions" style="justify-content: flex-end;">
                                <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                                    <!-- Role Toggle Form -->
                                    <form method="POST" action="admin.php" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle_role">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <input type="hidden" name="new_role" value="<?= $u['role'] === 'admin' ? 'member' : 'admin' ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" title="Change Role">
                                            <?= $u['role'] === 'admin' ? 'Set as Member' : 'Set as Admin' ?>
                                        </button>
                                    </form>

                                    <!-- Delete User Form -->
                                    <form method="POST" action="admin.php" style="display:inline;" onsubmit="return confirm('Delete user \'<?= e($u['username']) ?>\' and associated data?');">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="target_user_id" value="<?= $u['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: var(--text-muted);">Current User</span>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Global Tasks Audit -->
<div class="card">
    <h2 class="card-title">System Activity Log (All Users)</h2>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Task</th>
                    <th>Project</th>
                    <th>Owner</th>
                    <th>Status</th>
                    <th>Deadline</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allTasks as $at): 
                    $isOd = is_task_overdue($at['deadline'], $at['status']);
                    $dbadge = format_deadline_badge($at['deadline'], $at['status']);
                ?>
                    <tr class="<?= $isOd ? 'row-overdue' : '' ?>">
                        <td>
                            <a href="task-edit.php?id=<?= $at['id'] ?>"><strong><?= e($at['title']) ?></strong></a>
                        </td>
                        <td><?= e($at['project_title']) ?></td>
                        <td><?= e($at['creator_name']) ?></td>
                        <td>
                            <span class="table-status-pill status-<?= e($at['status']) ?>">
                                <?= strtoupper(e($at['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?= $dbadge['class'] ?>" style="font-size: 0.78rem;">
                                <?= e($dbadge['text']) ?>
                            </span>
                        </td>
                        <td>
                            <a href="task-edit.php?id=<?= $at['id'] ?>" class="btn btn-outline btn-sm">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once 'footer.php'; ?>
