<?php
// header.php - Semantic header and navigation included across all pages
require_once 'config.php';
require_once 'functions.php';
init_session();
$user = current_user();
$currentPage = basename($_SERVER['PHP_SELF']);
$flash = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? e($pageTitle) . ' | Planify' : 'Planify Task Management' ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header class="site-header">
        <div class="header-container">
            <a href="index.php" class="brand-logo">
                <span class="logo-mark"></span>
                <span class="logo-text">Planify</span>
                <span class="badge-topic">D2</span>
            </a>

            <button class="nav-toggle" id="navToggle" aria-label="Toggle navigation menu">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="site-nav" id="siteNav">
                <ul class="nav-links">
                    <li>
                        <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">
                            Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="board.php" class="<?= $currentPage === 'board.php' ? 'active' : '' ?>">
                            Task Board
                        </a>
                    </li>
                    <li>
                        <a href="tasks.php" class="<?= in_array($currentPage, ['tasks.php', 'task-create.php', 'task-edit.php']) ? 'active' : '' ?>">
                            All Tasks
                        </a>
                    </li>
                    <li>
                        <a href="projects.php" class="<?= $currentPage === 'projects.php' ? 'active' : '' ?>">
                            Projects
                        </a>
                    </li>
                    <?php if (is_admin()): ?>
                        <li>
                            <a href="admin.php" class="<?= $currentPage === 'admin.php' ? 'active' : '' ?>">
                                <span class="admin-badge">Admin</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="user-nav-actions">
                    <?php if ($user): ?>
                        <div class="user-profile-menu">
                            <a href="profile.php" class="user-badge-link" title="User Profile">
                                <?php if (!empty($user['avatar']) && file_exists('uploads/' . $user['avatar'])): ?>
                                    <img src="uploads/<?= e($user['avatar']) ?>" alt="<?= e($user['username']) ?>" class="nav-avatar">
                                <?php else: ?>
                                    <span class="nav-avatar-fallback"><?= strtoupper(substr($user['username'], 0, 1)) ?></span>
                                <?php endif; ?>
                                <span class="nav-username"><?= e($user['username']) ?></span>
                                <span class="user-role-pill role-<?= e($user['role']) ?>"><?= strtoupper(e($user['role'])) ?></span>
                            </a>
                            <a href="logout.php" class="btn btn-outline btn-sm">Log Out</a>
                        </div>
                    <?php else: ?>
                        <div class="auth-buttons">
                            <a href="login.php" class="btn btn-outline btn-sm <?= $currentPage === 'login.php' ? 'active' : '' ?>">Log In</a>
                            <a href="register.php" class="btn btn-primary btn-sm <?= $currentPage === 'register.php' ? 'active' : '' ?>">Register</a>
                        </div>
                    <?php endif; ?>
                </div>
            </nav>
        </div>
    </header>

    <?php if ($flash): ?>
        <div class="flash-banner flash-<?= e($flash['type']) ?>">
            <div class="flash-content">
                <span class="flash-tag">[<?= strtoupper(e($flash['type'])) ?>]</span>
                <span class="flash-message"><?= e($flash['message']) ?></span>
                <button class="flash-close" onclick="this.parentElement.parentElement.remove();">&times;</button>
            </div>
        </div>
    <?php endif; ?>

    <main class="main-content">
