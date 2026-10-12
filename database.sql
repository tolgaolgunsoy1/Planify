-- Planify Task Board Database Schema and Sample Data
-- Compatible with MySQL 5.7+, MySQL 8+, MariaDB and InfinityFree hosting
-- Collation: utf8mb4_unicode_ci (avoid utf8mb4_0900_ai_ci)
-- Note: Do NOT include CREATE DATABASE or USE statements for InfinityFree phpMyAdmin

DROP TABLE IF EXISTS `tasks`;
DROP TABLE IF EXISTS `projects`;
DROP TABLE IF EXISTS `users`;

-- 1. Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('member', 'admin') NOT NULL DEFAULT 'member',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Projects Table
CREATE TABLE `projects` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(100) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `color` VARCHAR(20) NOT NULL DEFAULT '#4f46e5',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_projects_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tasks Table
CREATE TABLE `tasks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `project_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `status` ENUM('todo', 'doing', 'done') NOT NULL DEFAULT 'todo',
  `priority` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
  `deadline` DATETIME DEFAULT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT `fk_tasks_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample Users
-- Admin: admin@planify.local / Admin123!
-- Member: member@planify.local / Member123!
INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `avatar`, `created_at`) VALUES
(1, 'admin', 'admin@planify.local', '$2y$10$L0urfIp2Rt1CqPWakaouVusNFb7thed20IWHLry8z2IDGXUV50xiu', 'admin', NULL, '2026-10-01 10:00:00'),
(2, 'member', 'member@planify.local', '$2y$10$INJj8t4keUod2rOzSugdeuSnf8dADBrmFVi9ACH6s2xg18enlyL.K', 'member', NULL, '2026-10-02 11:30:00'),
(3, 'selin', 'selin@planify.local', '$2y$10$INJj8t4keUod2rOzSugdeuSnf8dADBrmFVi9ACH6s2xg18enlyL.K', 'member', NULL, '2026-10-03 14:15:00');

-- Sample Projects
INSERT INTO `projects` (`id`, `user_id`, `title`, `description`, `color`, `created_at`) VALUES
(1, 1, 'Web Portal Redesign', 'Revamp UI/UX and improve user workflows across all devices.', '#4f46e5', '2026-10-04 09:00:00'),
(2, 1, 'Mobile App Launch', 'Develop cross-platform Flutter mobile client for tasks.', '#059669', '2026-10-05 10:00:00'),
(3, 2, 'Marketing Campaign Q4', 'Brand awareness, social media calendar and promo banners.', '#d97706', '2026-10-06 12:00:00');

-- Sample Tasks
-- Notice: Some tasks have deadlines in the past (e.g. 2026-10-05, 2026-10-08) and status 'todo'/'doing' to highlight as overdue!
-- Some tasks have passed deadlines but status 'done' (not highlighted).
-- Some tasks have future deadlines.
INSERT INTO `tasks` (`id`, `project_id`, `user_id`, `title`, `description`, `status`, `priority`, `deadline`, `attachment`, `created_at`) VALUES
(1, 1, 1, 'Prepare Wireframes & User Journey', 'Create low-fidelity mockups for main dashboard and mobile task views.', 'done', 'high', '2026-10-08 18:00:00', NULL, '2026-10-04 10:30:00'),
(2, 1, 1, 'Fix Mobile Navigation Drawer bug', 'Menu button is unresponsive on Safari iOS viewport. Needs inspection.', 'todo', 'high', '2026-10-09 17:00:00', NULL, '2026-10-05 11:00:00'),
(3, 1, 2, 'Build Semantic HTML Layout', 'Convert designs into clean HTML5 with semantic tags and custom CSS styling.', 'doing', 'medium', '2026-10-18 20:00:00', NULL, '2026-10-05 14:00:00'),
(4, 1, 1, 'Submit Security Audit Report', 'Check all forms for XSS protection, CSRF validation and SQL injection.', 'todo', 'high', '2026-10-07 12:00:00', NULL, '2026-10-06 09:30:00'),
(5, 2, 1, 'Implement Push Notification sidecar', 'Configure background notification workers for task deadlines.', 'doing', 'low', '2026-10-25 15:00:00', NULL, '2026-10-07 16:00:00'),
(6, 2, 2, 'Setup Firebase Auth Credentials', 'Obtain API keys and initialize auth client for mobile app.', 'done', 'medium', '2026-10-10 14:00:00', NULL, '2026-10-08 10:00:00'),
(7, 3, 2, 'Design Instagram Promo Banners', 'Export 1080x1080 PNG graphics with seasonal color theme.', 'todo', 'medium', '2026-10-10 23:59:00', NULL, '2026-10-08 13:00:00'),
(8, 3, 2, 'Draft Press Release Email Copy', 'Write announcement email for newsletter subscribers.', 'doing', 'low', '2026-10-22 17:00:00', NULL, '2026-10-09 15:30:00'),
(9, 3, 3, 'Review Final Ad Budget with Finance', 'Get sign-off on ad spend for Google and Meta campaigns.', 'done', 'high', '2026-10-11 16:00:00', NULL, '2026-10-10 09:00:00');
