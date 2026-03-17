<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<header>
    <div class="navbar">
        <div class="navbar-brand">
            <a>UKHSA</a>
        </div>
        <div class="navbar-user">
            <a><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></a>
        </div>
        <div>
            <a href="dashboard.php" class="option">
                <div class="material-icons">dashboard</div> Dashboard
            </a>
        </div>
        <div>
            <a href="manage_requests.php" class="option">
                <div class="material-icons">pending_actions</div> Manage Requests
            </a>
        </div>
        <div>
            <a href="dataset_rules.php" class="option">
                <div class="material-icons">rule</div> Dataset & Rules
            </a>
        </div>
        <div>
            <a href="audit_log.php" class="option">
                <div class="material-icons">history</div> Audit Log
            </a>
        </div>
        <div class="logout-item">
            <form action="../actions/logout_action.php" method="POST">
                <button type="submit" name="logout" class="btn-logout">Log out</button>
            </form>
        </div>
    </div>

    <div id="loading-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(255,255,255,0.8); z-index: 9999; align-items: center; justify-content: center;">
        <div style="text-align: center;">
            <div class="spinner"></div>
            <p style="margin-top: 10px; font-family: 'GDS Transport', sans-serif;">Loading...</p>
        </div>
    </div>
    
</header>