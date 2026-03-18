<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<header>
    <div class="navbar">
        <div>
            <a class="ukhsa-logo-item">UKHSA</a>
        </div>
        <div class="item">
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

    
</header>