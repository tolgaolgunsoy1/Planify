    </main>

    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-col">
                <div class="footer-brand">
                    <span class="logo-mark"></span>
                    <strong>Planify</strong>
                </div>
                <p class="footer-desc">
                    Task and project workflow management system with real-time status transitions and deadline tracking.
                </p>
                <div class="system-environment-tag">
                    <span class="status-dot"></span>
                    <span>Host: <strong><?= (($_SERVER['SERVER_NAME'] ?? '') === 'localhost' || ($_SERVER['SERVER_NAME'] ?? '') === '127.0.0.1') ? 'Localhost' : 'InfinityFree' ?></strong></span>
                </div>
            </div>

            <div class="footer-col">
                <h4>Navigation</h4>
                <ul class="footer-links">
                    <li><a href="index.php">Dashboard</a></li>
                    <li><a href="board.php">Task Board</a></li>
                    <li><a href="tasks.php">Task List</a></li>
                    <li><a href="projects.php">Projects</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Academic Details</h4>
                <p class="course-meta">
                    <strong>Course:</strong> IMS207 / IMS301 Web Design<br>
                    <strong>Topic:</strong> D2 Task board<br>
                    <strong>Architecture:</strong> Plain PHP 8, MySQL PDO, Custom CSS.
                </p>
            </div>
        </div>

        <div class="footer-bottom">
            <div class="footer-bottom-container">
                <p>&copy; <?= date('Y') ?> Planify &bull; Secure output via htmlspecialchars()</p>
                <p class="footer-sign">Student Project for IMS207 / IMS301</p>
            </div>
        </div>
    </footer>

    <script src="js/main.js"></script>
</body>
</html>
