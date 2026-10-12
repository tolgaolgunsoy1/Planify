================================================================================
IMS207 / IMS301 Web Design and Internet Programming - Course Project
================================================================================

Student Information:
-------------------
Full Name       : [Your Name Surname]
Student Number  : [Your Student Number]
Course Code     : IMS207 / IMS301
Topic Code      : D2. Task board
Topic Title     : Task Board with Status Workflow, Project Filter & Overdue Highlighting
Academic Term   : Fall Semester

Submission Links:
----------------
Live Website URL        : https://yoursubdomain.infinityfreeapp.com (or http://localhost:8000)
Video Presentation Link : [Paste your unlisted YouTube or Google Drive video link here]

Test Accounts (Requirement 8):
------------------------------
1. Administrator Account:
   - Email    : admin@planify.local
   - Username : admin
   - Password : Admin123!
   - Role     : admin (Access to Admin Panel, user management, global audit)

2. Standard Member Account:
   - Email    : member@planify.local
   - Username : member
   - Password : Member123!
   - Role     : member (Manages own projects, tasks and board)

Key Features & Course Requirements Checklist:
---------------------------------------------
[X] Req 1: At least 6 pages, valid semantic HTML5 (header, nav, main, footer)
           included via header.php and footer.php. (index.php, board.php, tasks.php,
           task-create.php, task-edit.php, projects.php, profile.php, admin.php,
           login.php, register.php).
[X] Req 2: Own external CSS (css/style.css), modern Flexbox layout, responsive design
           with media queries for mobile viewports. No frameworks (no Bootstrap/Tailwind).
[X] Req 3: Client-side JavaScript (js/main.js) with client form validation (register & task),
           DOM interactions: live character counter, image preview before upload,
           interactive Kanban drag-and-drop between To Do / Doing / Done, and real-time live search.
[X] Req 4: Secure PHP POST forms with strict server-side validation.
[X] Req 5: All user-supplied output protected against XSS using htmlspecialchars().
[X] Req 6: MySQL database with 3 related tables (users, projects, tasks) and foreign keys.
[X] Req 7: All database queries run via PDO prepared statements. Full CRUD on tasks and projects.
           Search and multi-criteria filters on board and task list.
[X] Req 8: Session-based login system, password_hash() and password_verify(), role separation
           between Member and Admin.
[X] Req 9: File upload supporting image avatars and task attachments with strict MIME validation
           and size limit (at most 1 MB).
[X] Req 10: InfinityFree & Localhost dual compatibility via auto-detecting config.php.

Topic D2 Signature Features:
----------------------------
1. Tasks Move Between "To Do", "Doing", and "Done":
   - Kanban board organizes tasks into 3 distinct status columns.
   - Interactive HTML5 drag-and-drop with AJAX updates + accessible fallback move buttons.
2. Overdue Deadline Highlighter:
   - Tasks whose deadline has passed (and status is not 'done') glow with red borders,
     an "OVERDUE" warning badge, and trigger a prominent alert banner on the board & dashboard.
3. Filter by Project:
   - Dropdown selector on board.php and tasks.php filters tasks to any selected project.

Local Development Run Instructions:
-----------------------------------
1. Start MySQL in XAMPP.
2. In phpMyAdmin or MySQL terminal, import database.sql into database 'planify_db'.
3. In this project folder, run the built-in PHP development server:
   php -S localhost:8000
4. Open your browser and navigate to:
   http://localhost:8000

InfinityFree Deployment Steps:
------------------------------
1. Create account on InfinityFree and create a MySQL database.
2. Open phpMyAdmin on InfinityFree and import database.sql.
3. Update database credentials in config.php (right-hand side values).
4. Upload all files into the 'htdocs' directory via FileZilla FTP or File Manager.
5. In InfinityFree panel, activate Free SSL and open your https:// address.
6. Before zipping for submission, replace the host password in config.php with '***'.

AI Tools Declaration (Section 10):
----------------------------------
Used AI tools: Antigravity AI (Google DeepMind).
Declaration: AI assistant was consulted for architecting the project structure according to the
course specification, designing custom responsive CSS layouts, setting up PDO prepared statements,
implementing drag-and-drop JavaScript and verifying cross-environment compatibility between
localhost and InfinityFree hosting. All code was reviewed, tested, and explained by the author.
================================================================================
