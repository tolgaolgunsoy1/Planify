<?php
// logout.php - Secure session destruction and redirection
require_once 'functions.php';
init_session();

// Unset all session variables
$_SESSION = [];

// Delete session cookie if configured
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Restart session just to set flash message
session_start();
set_flash('info', 'You have been logged out successfully.');

header('Location: login.php');
exit;
