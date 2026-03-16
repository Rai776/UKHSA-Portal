<?php
session_start();
require_once '../config/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['login'])) {
    header('Location: ../login.php');
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    $_SESSION['login_error']    = 'Please enter both username and password.';
    $_SESSION['login_username'] = $username;
    header('Location: ../login.php');
    exit();
}

$query = '
    SELECT 
        user_id, 
        username, 
        password_hash, 
        full_name, 
        email, 
        team,
        system_role, 
        job_type,
        training_completed,
        training_expiry
    FROM "User"
    WHERE LOWER(username) = LOWER($1)
';

$result = pg_query_params($conn, $query, [$username]);

if (!$result) {
    error_log("Login query error: " . pg_last_error($conn));
    $_SESSION['login_error'] = 'A system error occurred. Please try again later.';
    header('Location: ../login.php');
    exit();
}

$user = pg_fetch_assoc($result);

if ($user && password_verify($password, $user['password_hash'])) {

    session_regenerate_id(true);

    $_SESSION['user_id']            = $user['user_id'];
    $_SESSION['username']           = $user['username'];
    $_SESSION['full_name']          = $user['full_name'];
    $_SESSION['email']              = $user['email'];
    $_SESSION['team']               = $user['team'];
    $_SESSION['role']               = $user['system_role'];
    $_SESSION['job_type']           = $user['job_type'];
    $_SESSION['training_completed'] = $user['training_completed'];
    $_SESSION['training_expiry']    = $user['training_expiry'];

    $log_query = '
        INSERT INTO "Audit_Log" (user_id, action, target_table, target_id)
        VALUES ($1, $2, $3, $4)
    ';
    @pg_query_params($conn, $log_query, [
        $user['user_id'],
        'LOGIN: ' . $user['system_role'],
        'User',
        $user['user_id']
    ]);

    if ($user['system_role'] === 'Administrator') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../user/dashboard.php');
    }
    exit();

} else {
    $_SESSION['login_error']    = 'Invalid username or password.';
    $_SESSION['login_username'] = $username;

    $log_query = '
        INSERT INTO "Audit_Log" (user_id, action, target_table, target_id)
        VALUES ($1, $2, $3, $4)
    ';
    @pg_query_params($conn, $log_query, [
        null,
        'LOGIN_FAILED: ' . $username,
        'User',
        null
    ]);

    header('Location: ../login.php');
    exit();
}
?>