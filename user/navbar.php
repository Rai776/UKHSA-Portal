<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/navbar.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
</head>

<body>
    <header>
        <div class="navbar">
            <div>
                <a class="ukhsa-logo-item">UKHSA</a>
            </div>
            <div class="item" >
                <a><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></a>
            </div>
            <div>
                <a href="dashboard.php" class="option" >
                    <div class="material-icons">home</div>Dashboard
                </a>
            </div>
            <div>
                <a href="dataset_catalogue.php" class="option">
                    <div class="material-icons">library_books</div>Home / Catalogue
                </a>
            </div>
            <div>
                <a href="my_requests.php" class="option">
                    <div class="material-icons">subject</div>My Requests
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
</body>