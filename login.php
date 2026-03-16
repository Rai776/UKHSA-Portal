<?php
session_start();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'Administrator') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: user_dashboard.php');
    }
    exit();
}

$error          = $_SESSION['login_error'] ?? '';
$success        = $_SESSION['login_success'] ?? '';
$username_value = $_SESSION['login_username'] ?? '';

unset($_SESSION['login_error']);
unset($_SESSION['login_success']);
unset($_SESSION['login_username']);

if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been successfully logged out.';
}

if (isset($_GET['expired'])) {
    $error = 'Your session has expired. Please log in again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="assets/css/style.css" />
    <title>Login — UKHSA Data Governance Portal</title>
</head>
<body>
    <main class="main-content">
        <div class="login-card">
            <div class="login-card-header">
                <div class="ukhsa-logo">
                    <div class="ukhsa-logo-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="#1D70B8" viewBox="0 0 16 16">
                            <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2m3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2"/>
                        </svg>
                    </div>
                    <div class="ukhsa-logo-text">
                        <span class="ukhsa-logo-name">UKHSA</span>
                        <span class="ukhsa-logo-full">UK Health Security Agency</span>
                    </div>
                </div>
                <h1>Data Governance Portal</h1>
                <p>Sign in to continue</p>
            </div>

            <div class="login-card-body">
                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <span>&#10003;</span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span>&#9888;</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="actions/login_action.php">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input 
                            type="text" 
                            id="username" 
                            name="username" 
                            placeholder="Enter your username"
                            value="<?php echo htmlspecialchars($username_value); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            placeholder="Enter your password"
                            required
                        >
                    </div>

                    <button type="submit" name="login" class="btn-login">
                        Log in
                    </button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>