<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
    <link rel="stylesheet" href="../assets/css/navbar.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
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
                <div class="material-icons icon-item">dashboard</div> Dashboard
            </a>
        </div>
        <div>
            <a href="manage_requests.php" class="option">
                <div class="material-icons icon-item">pending_actions</div> Manage Requests
            </a>
        </div>
        <div>
            <a href="rules_management.php" class="option">
                <div class="material-icons icon-item">rule</div> Dataset & Rules
            </a>
        </div>
        <div>
            <a href="audit_trail.php" class="option">
                <div class="material-icons icon-item">history</div> Audit Log
            </a>
        </div>
        <div class="logout-item">
            <form action="../actions/logout_action.php" method="POST">
                <button type="submit" name="logout" class="btn-logout">Log out</button>
            </form>
        </div>
    </div>

    
</header>