<?php
// login.php — Frontend (login-frontend branch)
session_start();

$error = '';
$success = '';
$email_value = '';

// Check for logout message
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been successfully logged out.';
}

// Check for session expired
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

            <!-- Card Header -->
            <div class="login-card-header">
                <div class="lock-icon">
                    <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="#ffffff" viewBox="0 0 16 16">
                        <path d="M8 1a2 2 0 0 1 2 2v4H6V3a2 2 0 0 1 2-2m3 6V3a3 3 0 0 0-6 0v4a2 2 0 0 0-2 2v5a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2"/>
                    </svg>
                </div>
                <h1>Data Governance Portal</h1>
                <p>Sign in to continue</p>
            </div>

            <!-- Card Body -->
            <div class="login-card-body">

                <!-- Success Alert -->
                <?php if (!empty($success)): ?>
                <div class="alert alert-success">
                    <span>&#10003;</span>
                    <span><?php echo htmlspecialchars($success); ?></span>
                </div>
                <?php endif; ?>

                <!-- Error Alert -->
                <?php if (!empty($error)): ?>
                <div class="alert alert-error">
                    <span>&#9888;</span>
                    <span><?php echo htmlspecialchars($error); ?></span>
                </div>
                <?php endif; ?>

                <!-- Login Form -->
                <form method="POST" action="login.php">

                    <!-- Email -->
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            placeholder="Enter your email"
                            value="<?php echo htmlspecialchars($email_value); ?>"
                            required
                        >
                    </div>

                    <!-- Password -->
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

                    <!-- Login Button -->
                    <button type="submit" name="login" class="btn-login">
                        Log in
                    </button>

                </form>

            </div>

        </div>
    </main>

</body>
</html>