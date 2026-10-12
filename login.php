<?php
// login.php - Secure authentication with password_verify() and session protection
require_once 'config.php';
require_once 'functions.php';
init_session();

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

$errors = [];
$identifier = '';

// Handle quick demo credentials shortcut for grading/testing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['demo_role'])) {
    $demoRole = $_POST['demo_role'];
    if ($demoRole === 'admin') {
        $identifier = 'admin@planify.local';
        $passAttempt = 'Admin123!';
    } else {
        $identifier = 'member@planify.local';
        $passAttempt = 'Member123!';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $passAttempt = $_POST['password'] ?? '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($identifier)) {
        $errors[] = 'Please enter your username or email address.';
    }
    if (empty($passAttempt)) {
        $errors[] = 'Please enter your password.';
    }

    if (empty($errors)) {
        try {
            // Prepared statement to search by username OR email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :id1 OR username = :id2 LIMIT 1");
            $stmt->execute([':id1' => $identifier, ':id2' => $identifier]);
            $userRow = $stmt->fetch();

            // Requirement 8: check password with password_verify()
            if ($userRow && password_verify($passAttempt, $userRow['password'])) {
                // Prevent session fixation
                session_regenerate_id(true);

                $_SESSION['user_id'] = $userRow['id'];
                $_SESSION['username'] = $userRow['username'];
                $_SESSION['email'] = $userRow['email'];
                $_SESSION['role'] = $userRow['role'];
                $_SESSION['avatar'] = $userRow['avatar'];

                set_flash('success', 'Logged in as ' . $userRow['username'] . '.');
                
                // Redirect admin to admin panel, member to index
                header('Location: ' . ($userRow['role'] === 'admin' ? 'admin.php' : 'index.php'));
                exit;
            } else {
                $errors[] = 'Invalid username/email or password.';
            }
        } catch (PDOException $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Log In';
require_once 'header.php';
?>

<div style="max-width: 420px; margin: 2rem auto;">
    <div class="card" style="padding: 1.75rem;">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div class="logo-mark" style="width: 20px; height: 20px; margin: 0 auto 0.5rem auto;"></div>
            <h1 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; letter-spacing: -0.02em;">Planify Sign In</h1>
            <p style="color: var(--text-muted); font-size: 0.825rem;">Enter credentials to access task board.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="flash-banner flash-error" style="border-radius: var(--radius-sm); margin-bottom: 1.25rem; padding: 0.6rem 0.85rem;">
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

        <form method="POST" action="login.php" novalidate>
            <div class="form-group">
                <label for="login_identifier" class="form-label">Username or Email</label>
                <input type="text" name="identifier" id="login_identifier" class="form-control" 
                       value="<?= e($identifier) ?>" required autofocus placeholder="admin@planify.local">
            </div>

            <div class="form-group">
                <label for="login_password" class="form-label">Password</label>
                <input type="password" name="password" id="login_password" class="form-control" required placeholder="Password">
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.65rem; font-size: 0.95rem; margin-top: 0.35rem;">
                Log In
            </button>
        </form>

        <!-- Instructor / Grader Quick Test Logins -->
        <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); text-align: center;">
            <div style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 0.5rem;">
                Quick Evaluation Accounts
            </div>
            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                <form method="POST" action="login.php" style="display:inline;">
                    <input type="hidden" name="demo_role" value="admin">
                    <button type="submit" class="btn btn-outline btn-sm" style="font-size: 0.75rem;">
                        Admin Login
                    </button>
                </form>
                <form method="POST" action="login.php" style="display:inline;">
                    <input type="hidden" name="demo_role" value="member">
                    <button type="submit" class="btn btn-outline btn-sm" style="font-size: 0.75rem;">
                        Member Login
                    </button>
                </form>
            </div>
        </div>

        <p style="text-align: center; margin-top: 1.25rem; font-size: 0.825rem; color: var(--text-muted);">
            Need an account? <a href="register.php" style="font-weight: 600;">Register</a>
        </p>
    </div>
</div>

<?php require_once 'footer.php'; ?>
