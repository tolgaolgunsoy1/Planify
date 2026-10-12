<?php
// profile.php - User Profile, Avatar Upload (<=1MB Requirement 9), Password Change
require_once 'config.php';
require_once 'functions.php';
require_login();

$user = current_user();
$errors = [];

// Re-fetch current user data from database
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $user['id']]);
    $dbUser = $stmt->fetch();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action 1: Update Avatar (Requirement 9: Image upload <= 1 MB)
    if ($action === 'avatar') {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadResult = upload_image_file($_FILES['avatar'], 'uploads/');
            if ($uploadResult['success']) {
                $newAvatar = $uploadResult['filename'];
                if (!empty($dbUser['avatar']) && file_exists('uploads/' . $dbUser['avatar'])) {
                    unlink('uploads/' . $dbUser['avatar']);
                }
                
                $upStmt = $pdo->prepare("UPDATE users SET avatar = :avatar WHERE id = :id");
                $upStmt->execute([':avatar' => $newAvatar, ':id' => $user['id']]);
                $_SESSION['avatar'] = $newAvatar;
                $dbUser['avatar'] = $newAvatar;

                set_flash('success', 'Profile avatar updated.');
                header('Location: profile.php');
                exit;
            } else {
                $errors[] = 'Avatar Upload Error: ' . $uploadResult['error'];
            }
        } else {
            $errors[] = 'Please select an image file to upload.';
        }
    }

    // Action 2: Update Profile Information
    elseif ($action === 'info') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($username) || mb_strlen($username) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (empty($errors)) {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE (username = :u OR email = :e) AND id != :id");
            $checkStmt->execute([':u' => $username, ':e' => $email, ':id' => $user['id']]);
            if ($checkStmt->fetch()) {
                $errors[] = 'Username or email already in use.';
            } else {
                $upStmt = $pdo->prepare("UPDATE users SET username = :u, email = :e WHERE id = :id");
                $upStmt->execute([':u' => $username, ':e' => $email, ':id' => $user['id']]);
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $dbUser['username'] = $username;
                $dbUser['email'] = $email;

                set_flash('success', 'Profile information updated.');
                header('Location: profile.php');
                exit;
            }
        }
    }

    // Action 3: Change Password (password_hash and password_verify)
    elseif ($action === 'password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPass, $dbUser['password'])) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPass) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($newPass !== $confirmPass) {
            $errors[] = 'New password confirmation does not match.';
        } else {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            $upStmt = $pdo->prepare("UPDATE users SET password = :p WHERE id = :id");
            $upStmt->execute([':p' => $newHash, ':id' => $user['id']]);

            set_flash('success', 'Password successfully changed.');
            header('Location: profile.php');
            exit;
        }
    }
}

// Fetch user task stats
try {
    $statStmt = $pdo->prepare("SELECT 
        COUNT(id) AS total_tasks,
        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) AS completed_tasks,
        SUM(CASE WHEN status != 'done' AND deadline IS NOT NULL AND deadline < NOW() THEN 1 ELSE 0 END) AS overdue_tasks
        FROM tasks WHERE user_id = :uid");
    $statStmt->execute([':uid' => $user['id']]);
    $stats = $statStmt->fetch();
} catch (PDOException $e) {
    $stats = ['total_tasks' => 0, 'completed_tasks' => 0, 'overdue_tasks' => 0];
}

$pageTitle = 'My Profile';
require_once 'header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">User Account</h1>
        <p class="page-subtitle">Security settings, account credentials and profile avatar.</p>
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

<div style="display: grid; grid-template-columns: 300px 1fr; gap: 1.5rem; align-items: start;">
    <!-- Profile Card & Avatar Upload -->
    <div class="card" style="text-align: center;">
        <div style="margin-bottom: 1rem;">
            <?php if (!empty($dbUser['avatar']) && file_exists('uploads/' . $dbUser['avatar'])): ?>
                <img src="uploads/<?= e($dbUser['avatar']) ?>" alt="<?= e($dbUser['username']) ?>" 
                     style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 2px solid var(--border-color); margin: 0 auto;">
            <?php else: ?>
                <div style="width: 100px; height: 100px; border-radius: 50%; background-color: #334155; color: white; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; margin: 0 auto;">
                    <?= strtoupper(substr($dbUser['username'], 0, 1)) ?>
                </div>
            <?php endif; ?>
        </div>

        <h2 style="font-size: 1.2rem; font-weight: 700; margin-bottom: 0.2rem;"><?= e($dbUser['username']) ?></h2>
        <p style="color: var(--text-muted); font-size: 0.825rem; margin-bottom: 0.65rem;"><?= e($dbUser['email']) ?></p>
        <div>
            <span class="user-role-pill role-<?= e($dbUser['role']) ?>" style="font-size: 0.72rem; padding: 0.2rem 0.6rem;">
                <?= strtoupper(e($dbUser['role'])) ?> ACCOUNT
            </span>
        </div>

        <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-color); text-align: left; font-size: 0.8rem;">
            <div style="color: var(--text-muted); margin-bottom: 0.3rem;">Tasks Created: <strong><?= (int)$stats['total_tasks'] ?></strong></div>
            <div style="color: var(--text-muted); margin-bottom: 0.3rem;">Completed: <strong><?= (int)$stats['completed_tasks'] ?></strong></div>
            <div style="color: var(--danger); font-weight: 700;">Overdue: <strong><?= (int)$stats['overdue_tasks'] ?></strong></div>
            <div style="color: var(--text-light); margin-top: 0.5rem;">Registered: <?= date('M j, Y', strtotime($dbUser['created_at'])) ?></div>
        </div>

        <!-- Requirement 9: Avatar Image Upload with <=1MB validation -->
        <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--border-color);">
            <h4 style="font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.5rem; text-align: left;">Upload Avatar (Max 1 MB)</h4>
            <form method="POST" action="profile.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="avatar">
                
                <div class="file-upload-zone" onclick="document.getElementById('avatar_file').click()" style="padding: 0.65rem;">
                    <input type="file" name="avatar" id="avatar_file" style="display:none;" 
                           accept=".jpg,.jpeg,.png,.webp"
                           data-preview-img="avatarPreviewImg"
                           data-preview-container="avatarPreviewContainer">
                    <p style="font-size: 0.78rem; margin: 0;">Select image file (.jpg, .png) &bull; &le; 1 MB</p>
                </div>

                <div id="avatarPreviewContainer" class="file-preview-wrap" style="text-align: center;">
                    <img id="avatarPreviewImg" src="" alt="Preview" class="file-preview-img" style="border-radius: 50%; width: 60px; height: 60px;">
                </div>

                <button type="submit" class="btn btn-outline btn-sm btn-block" style="margin-top: 0.65rem;">Upload Avatar</button>
            </form>
        </div>
    </div>

    <!-- Right Side: Edit Details and Change Password -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Account Info Form -->
        <div class="card">
            <h3 class="card-title">Profile Parameters</h3>
            <form method="POST" action="profile.php">
                <input type="hidden" name="action" value="info">

                <div class="form-row">
                    <div class="form-col form-group">
                        <label for="prof_username" class="form-label">Username</label>
                        <input type="text" name="username" id="prof_username" class="form-control" 
                               value="<?= e($dbUser['username']) ?>" required minlength="3" maxlength="50">
                    </div>

                    <div class="form-col form-group">
                        <label for="prof_email" class="form-label">Email Address</label>
                        <input type="email" name="email" id="prof_email" class="form-control" 
                               value="<?= e($dbUser['email']) ?>" required>
                    </div>
                </div>

                <div style="text-align: right;">
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>
        </div>

        <!-- Password Change Form -->
        <div class="card">
            <h3 class="card-title">Security & Password</h3>
            <form method="POST" action="profile.php">
                <input type="hidden" name="action" value="password">

                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" name="current_password" id="current_password" class="form-control" required>
                </div>

                <div class="form-row">
                    <div class="form-col form-group">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" required minlength="6" placeholder="Min 6 characters">
                    </div>

                    <div class="form-col form-group">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" required minlength="6" placeholder="Repeat new password">
                    </div>
                </div>

                <div style="text-align: right;">
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'footer.php'; ?>
