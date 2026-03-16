<?php
session_start();
require_once '../config/db_connect.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Get greeting based on time
$hour = intval(date('H'));
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css" />
    <link rel="stylesheet" href="../assets/css/user_dashboard.css" />
    <title>Dashboard — UKHSA Data Governance Portal</title>
</head>
<body>

    <?php include("navbar.php"); ?>

    <main class="dashboard-main">
        <div class="dashboard-container">

            <div class="welcome-card">
                <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
                <p>Welcome to the <strong>UKHSA Data Governance Portal</strong>. Use the navigation to browse datasets, manage your access requests, and track permissions.</p>
                <div class="welcome-details">
                    <span class="detail-item">
                        <strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['role']); ?>
                    </span>
                    <span class="detail-item">
                        <strong>Team:</strong> <?php echo htmlspecialchars($_SESSION['team'] ?? 'N/A'); ?>
                    </span>
                    <span class="detail-item">
                        <strong>Job Type:</strong> <?php echo htmlspecialchars($_SESSION['job_type'] ?? 'N/A'); ?>
                    </span>
                </div>
            </div>

        </div>
    </main>

</body>
</html>