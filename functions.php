<?php
// functions.php - Core helper utilities for Planify Task Board

// Ensure session is started safely
function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// XSS Sanitizer shorthand - Rule 5 requirement
function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// Auth helpers
function is_logged_in(): bool {
    init_session();
    return isset($_SESSION['user_id']);
}

function current_user(): ?array {
    init_session();
    if (!is_logged_in()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? 'member',
        'avatar' => $_SESSION['avatar'] ?? null,
    ];
}

function is_admin(): bool {
    init_session();
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function require_login(): void {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to access this page.');
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        set_flash('error', 'Access denied. You do not have administrator permissions.');
        header('Location: index.php');
        exit;
    }
}

// Flash notifications
function set_flash(string $type, string $message): void {
    init_session();
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'error', 'info', 'warning'
        'message' => $message,
    ];
}

function get_flash(): ?array {
    init_session();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Deadline and overdue calculation - Signature Feature
function is_task_overdue(?string $deadline, string $status): bool {
    if (empty($deadline) || $status === 'done') {
        return false;
    }
    $now = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
    $deadlineDate = new DateTime($deadline, new DateTimeZone('Europe/Istanbul'));
    return $deadlineDate < $now;
}

function format_deadline_badge(?string $deadline, string $status): array {
    if (empty($deadline)) {
        return [
            'text' => 'No deadline',
            'class' => 'badge-none',
            'is_overdue' => false,
        ];
    }

    $now = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
    $deadlineDate = new DateTime($deadline, new DateTimeZone('Europe/Istanbul'));
    $isOverdue = ($status !== 'done' && $deadlineDate < $now);

    $diff = $now->diff($deadlineDate);
    $formattedDate = $deadlineDate->format('M j, Y H:i');

    if ($status === 'done') {
        return [
            'text' => $formattedDate,
            'class' => 'badge-done',
            'is_overdue' => false,
        ];
    }

    if ($isOverdue) {
        $days = $diff->days;
        if ($days == 0) {
            $overdueText = 'Overdue (Today)';
        } elseif ($days == 1) {
            $overdueText = 'Overdue (1 day ago)';
        } else {
            $overdueText = "Overdue ($days days ago)";
        }
        return [
            'text' => $overdueText . ' · ' . $formattedDate,
            'class' => 'badge-overdue',
            'is_overdue' => true,
        ];
    }

    // Due in future
    if ($diff->days == 0) {
        return [
            'text' => 'Due today · ' . $deadlineDate->format('H:i'),
            'class' => 'badge-warning',
            'is_overdue' => false,
        ];
    } elseif ($diff->days == 1) {
        return [
            'text' => 'Due tomorrow · ' . $deadlineDate->format('H:i'),
            'class' => 'badge-info',
            'is_overdue' => false,
        ];
    } else {
        return [
            'text' => 'Due in ' . $diff->days . ' days · ' . $formattedDate,
            'class' => 'badge-normal',
            'is_overdue' => false,
        ];
    }
}

// File upload validator - Requirement 9: type (jpg, png, webp) and size (<= 1MB)
function upload_image_file(array $file, string $targetFolder = 'uploads/'): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['success' => false, 'error' => 'Invalid file parameters.'];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'No file was uploaded.'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'File exceeds size limit of 1 MB.'];
        default:
            return ['success' => false, 'error' => 'Unknown upload error occurred.'];
    }

    // Requirement 9: size at most 1 MB (1,048,576 bytes)
    if ($file['size'] > 1048576) {
        return ['success' => false, 'error' => 'File size is too large. Maximum allowed size is 1 MB.'];
    }

    // Whitelist allowed extensions and MIME types
    $allowedMimes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $fileInfo->file($file['tmp_name']);

    if (!array_key_exists($mime, $allowedMimes)) {
        return ['success' => false, 'error' => 'Invalid file type. Only JPG, PNG, and WebP images are accepted.'];
    }

    // Validate extension matching MIME
    $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $validExts = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($originalExt, $validExts)) {
        return ['success' => false, 'error' => 'File extension does not match allowed image formats (.jpg, .png, .webp).'];
    }

    // Generate collision-resistant lowercase random file name
    $ext = $allowedMimes[$mime];
    $filename = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    
    // Ensure uploads directory exists
    if (!is_dir($targetFolder)) {
        mkdir($targetFolder, 0755, true);
    }

    $destination = rtrim($targetFolder, '/') . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['success' => false, 'error' => 'Failed to save uploaded file to destination.'];
    }

    return ['success' => true, 'filename' => $filename];
}
