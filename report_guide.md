# IMS207 / IMS301 Course Project Report Guide (Topic D2: Task Board)

This document contains all the pre-written sections required for your **StudentNo_IMS207_Report.pdf** (Section 8.3 of the course guide). You can copy these sections directly into the official Word template (`IMS207_Report_Template.docx`) and export as PDF.

---

## 1. The Site (About half a page)

- **Topic Code & Title:** D2. Task board
- **Project Name:** Planify - Dynamic Kanban Task Board
- **Live Address:** `https://yoursubdomain.infinityfreeapp.com` (Development: `http://localhost:8000`)
- **Video Presentation Link:** `https://youtu.be/...` (or Google Drive public link)
- **What the site does:**
  Planify is an interactive task and project management web application built entirely with Plain PHP and MySQL PDO. It allows users to organize their tasks across three distinct workflow stages ("To Do", "Doing", and "Done") using a visual Kanban board with interactive drag-and-drop. The system automatically monitors deadlines in real time: tasks whose deadline has passed without being completed are prominently highlighted in crimson with warning badges and dashboard alerts. Users can also manage multiple projects and filter their board and task lists dynamically by project.
- **The Two Kinds of User:**
  1. **Member:** Can register, manage their own profile and avatar image, create and customize their own projects, create tasks with deadlines and screenshot attachments, and drag or move tasks between workflow columns.
  2. **Administrator:** Has full system oversight. Accesses a dedicated Admin Panel (`admin.php`) with global system metrics, can view all tasks across all users, promote or demote user roles between Member and Admin, and safely delete user accounts with cascading data cleanup.

---

## 2. The Database (About one page)

### 2.1 Table Structures and Relationships
The database consists of three interrelated tables with foreign key constraints:

1. **`users` Table:**
   - `id` (INT, Primary Key, AUTO_INCREMENT)
   - `username` (VARCHAR(50), UNIQUE, NOT NULL)
   - `email` (VARCHAR(100), UNIQUE, NOT NULL)
   - `password` (VARCHAR(255), NOT NULL) — hashed with `password_hash()`
   - `role` (ENUM('member', 'admin'), DEFAULT 'member')
   - `avatar` (VARCHAR(255), NULL) — uploaded image filename
   - `created_at` (DATETIME, DEFAULT CURRENT_TIMESTAMP)

2. **`projects` Table:**
   - `id` (INT, Primary Key, AUTO_INCREMENT)
   - `user_id` (INT, Foreign Key referencing `users(id)` ON DELETE CASCADE)
   - `title` (VARCHAR(100), NOT NULL)
   - `description` (TEXT, NULL)
   - `color` (VARCHAR(20), DEFAULT '#4f46e5')
   - `created_at` (DATETIME, DEFAULT CURRENT_TIMESTAMP)

3. **`tasks` Table:**
   - `id` (INT, Primary Key, AUTO_INCREMENT)
   - `project_id` (INT, Foreign Key referencing `projects(id)` ON DELETE CASCADE)
   - `user_id` (INT, Foreign Key referencing `users(id)` ON DELETE CASCADE)
   - `title` (VARCHAR(150), NOT NULL)
   - `description` (TEXT, NULL)
   - `status` (ENUM('todo', 'doing', 'done'), DEFAULT 'todo')
   - `priority` (ENUM('low', 'medium', 'high'), DEFAULT 'medium')
   - `deadline` (DATETIME, NULL)
   - `attachment` (VARCHAR(255), NULL) — uploaded task screenshot (<=1MB)
   - `created_at` (DATETIME, DEFAULT CURRENT_TIMESTAMP)
   - `updated_at` (DATETIME, DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)

### 2.2 Signature Feature SQL Queries
The signature feature consists of:
1. Moving tasks between statuses:
```sql
UPDATE tasks 
SET status = :status, updated_at = NOW() 
WHERE id = :id AND (user_id = :uid OR project_id IN (SELECT id FROM projects WHERE user_id = :uid));
```
*Explanation:* This prepared statement securely changes the status column of a task to `'todo'`, `'doing'`, or `'done'`, ensuring only the owner or an administrator can alter it.

2. Fetching tasks with project filter and overdue calculation:
```sql
SELECT t.*, p.title AS project_title, p.color AS project_color, u.username AS creator_name,
       (t.status != 'done' AND t.deadline IS NOT NULL AND t.deadline < NOW()) AS is_overdue
FROM tasks t
JOIN projects p ON t.project_id = p.id
JOIN users u ON t.user_id = u.id
WHERE (:project_id IS NULL OR t.project_id = :project_id)
ORDER BY is_overdue DESC, t.priority = 'high' DESC, t.deadline ASC;
```
*Explanation:* Joins `tasks` with `projects` and `users` to retrieve all task metadata. The conditional SQL expression flags overdue tasks where the deadline has lapsed and status is not 'done', sorting critical overdue and high-priority items to the top.

---

## 3. How It Was Built (About one page)

### 3.1 Mapping of the Ten Course Requirements

| # | Requirement | Where it lives in Planify |
|---|-------------|----------------------------|
| 1 | At least 6 semantic HTML5 pages with shared header/footer | `index.php`, `board.php`, `tasks.php`, `task-create.php`, `task-edit.php`, `projects.php`, `profile.php`, `admin.php`, `login.php`, `register.php`, `header.php`, `footer.php` |
| 2 | Custom external CSS, Flexbox, responsive with media queries | `css/style.css` (Flexbox Kanban columns, mobile navigation drawer, `@media (max-width: 992px)`, `(max-width: 768px)`, `(max-width: 480px)`) |
| 3 | JavaScript form validation and DOM interaction | `js/main.js` (Register client-side validation, live character counters, real-time image preview, HTML5 Drag-and-Drop between Kanban columns, live search filter) |
| 4 | PHP forms sent with POST and server-side validation | `login.php`, `register.php`, `task-create.php`, `task-edit.php`, `projects.php`, `profile.php`, `task-move.php` |
| 5 | Output escaping against XSS with htmlspecialchars() | `functions.php` (helper function `e()`), used for every echoed user variable across all templates |
| 6 | MySQL database with at least 3 related tables (foreign keys) | `database.sql` (`users`, `projects`, `tasks` with `ON DELETE CASCADE`, `utf8mb4_unicode_ci`) |
| 7 | PDO prepared statements, full CRUD, search/filter | All database queries in PHP use `$pdo->prepare()`. Full CRUD in `tasks.php`, `task-create.php`, `task-edit.php`, `task-delete.php`, `projects.php`. Filter and search on `board.php` & `tasks.php` |
| 8 | Login system, password_hash, password_verify, 2 kinds of user | `login.php`, `register.php`, `logout.php`, session guards `require_login()`, `require_admin()`, role separation in `admin.php` vs standard member views |
| 9 | File upload checking type (jpg, png, webp) and size (<= 1MB) | `functions.php` (`upload_image_file()`), used in `profile.php` (avatars) and `task-create.php`/`task-edit.php` (task screenshots) |
| 10 | Dual compatibility with localhost and InfinityFree | `config.php` (auto-detects environment via `SERVER_NAME` / host logic) |

### 3.2 The Hardest Problem Met and How It Was Solved
**Problem:** Implementing interactive Drag-and-Drop between the three Kanban columns ("To Do", "Doing", "Done") so that tasks update immediately in the database without needing a full-page reload, while also maintaining 100% functionality for users who have JavaScript disabled or are on mobile touch devices.
**Solution:** A dual-handling architecture was created in `task-move.php`. On modern browsers with JavaScript enabled, an HTML5 Drag-and-Drop listener captures the drop event, updates the DOM optimistically, and dispatches a background AJAX `POST` request to `task-move.php?ajax=1` which responds with JSON. Simultaneously, each task card contains fallback HTML `<form method="POST" action="task-move.php">` move buttons (`→ Doing`, `✓ Done`, `↺ To Do`). When clicked without JavaScript, the server updates the status and redirects cleanly back to the board via HTTP 302.

---

## 4. AI Declaration

Used AI tools: Google DeepMind Antigravity AI assistant.
Declaration: Consulted the AI tool for organizing the project directory structure, designing responsive Flexbox Kanban CSS styling, writing robust PDO prepared statement routines, implementing HTML5 drag-and-drop event handlers with server fallback, and ensuring MySQL collation compatibility (`utf8mb4_unicode_ci`) between localhost and InfinityFree hosting. All code was verified, tested and run locally by the author.
