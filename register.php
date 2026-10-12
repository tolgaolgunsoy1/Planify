<?php
// register.php - User registration with password_hash(), client & server validation
require_once 'config.php';
require_once 'functions.php';
init_session();

if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    // Server-side validation (Requirement 4)
    if (empty($username)) {
        $errors[] = 'Username is required.';
    } elseif (mb_strlen($username) < 3 || mb_strlen($username) > 50) {
        $errors[] = 'Username must be between 3 and 50 characters.';
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]+$/', $username)) {
        $errors[] = 'Username may only contain letters, numbers, dots, dashes and underscores.';
    }

    if (empty($email)) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($password)) {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $passwordConfirm) {
        $errors[] = 'Password confirmation does not match.';
    }

    // Check if username or email already exists
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :u OR email = :e LIMIT 1");
            $stmt->execute([':u' => $username, ':e' => $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Username or email is already registered. Please choose another or log in.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }

    // Insert new user with password_hash()
    if (empty($errors)) {
        try {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (:u, :e, :p, 'member', NOW())");
            $insertStmt->execute([
                ':u' => $username,
                ':e' => $email,
                ':p' => $hashedPassword,
            ]);

            $newUserId = (int)$pdo->lastInsertId();

            // Create a default project for the new user
            $projStmt = $pdo->prepare("INSERT INTO projects (user_id, title, description, color, created_at) VALUES (:uid, :title, :desc, '#1e40af', NOW())");
            $projStmt->execute([
                ':uid' => $newUserId,
                ':title' => 'Default Project',
                ':desc' => 'Initial workspace for personal tasks.',
            ]);

            // Auto log in new user
            session_regenerate_id(true);
            $_SESSION['user_id'] = $newUserId;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = 'member';
            $_SESSION['avatar'] = null;

            set_flash('success', 'Registration complete. Welcome to Planify, ' . $username . '.');
            header('Location: board.php');
            exit;

        } catch (PDOException $e) {
            $errors[] = 'Registration failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Register';
require_once 'header.php';
?>

<div style="max-width: 440px; margin: 2rem auto;">
    <div class="card" style="padding: 1.75rem;">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div class="logo-mark" style="width: 20px; height: 20px; margin: 0 auto 0.5rem auto;"></div>
            <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">Create Account</h1>
            <p style="color: var(--text-muted); font-size: 0.825rem;">Register to manage projects and tasks.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="flash-banner flash-error" style="border-radius: var(--radius-sm); margin-bottom: 1.25rem; padding: 0.6rem 0.85rem;">
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

        <!-- Form has id="registerForm" for client-side JS validation (Requirement 3) -->
        <form method="POST" action="register.php" id="registerForm" novalidate>
            <div class="form-group">
                <label for="reg_username" class="form-label">Username <span class="required">*</span></label>
                <input type="text" name="username" id="reg_username" class="form-control" 
                       value="<?= e($username) ?>" required minlength="3" maxlength="50" placeholder="Username">
            </div>

            <div class="form-group">
                <label for="reg_email" class="form-label">Email Address <span class="required">*</span></label>
                <input type="email" name="email" id="reg_email" class="form-control" 
                       value="<?= e($email) ?>" required placeholder="user@example.com">
            </div>

            <div class="form-group">
                <label for="reg_password" class="form-label">Password <span class="required">*</span></label>
                <input type="password" name="password" id="reg_password" class="form-control" 
                       required minlength="6" placeholder="Min 6 characters">
            </div>

            <div class="form-group">
                <label for="reg_password_confirm" class="form-label">Confirm Password <span class="required">*</span></label>
                <input type="password" name="password_confirm" id="reg_password_confirm" class="form-control" 
                       required minlength="6" placeholder="Confirm password">
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.65rem; font-size: 0.95rem; margin-top: 0.5rem;">
                Register
            </button>
        </form>

        <p style="text-align: center; margin-top: 1.25rem; font-size: 0.825rem; color: var(--text-muted);">
            Already have an account? <a href="login.php" style="font-weight: 600;">Sign in</a>
        </p>
    </div>
</div>

<?php require_once 'footer.php'; ?>
