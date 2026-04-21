<?php
session_start();
require_once '../config/supabase.php';

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

$result = supabaseRequest(
    'User?select=user_id,username,password_hash,full_name,email,team,system_role,job_type,training_completed,training_expiry&username=ilike.' . urlencode($username)
);

if (isset($result['error']) || !is_array($result)) {
    error_log("Login query error: " . json_encode($result));
    $_SESSION['login_error'] = 'A system error occurred. Please try again later.';
    header('Location: ../login.php');
    exit();
}

$user = $result[0] ?? null;

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

    supabaseRequest(
        'Audit_Log',
        'POST',
        [
            'user_id'      => $user['user_id'],
            'action'       => 'LOGIN: ' . $user['system_role'],
            'target_table' => 'User',
            'target_id'    => $user['user_id']
        ]
    );

    if ($user['system_role'] === 'Administrator' || $user['system_role'] === 'Approver') {
        header('Location: ../admin/dashboard.php');
    } else {
        header('Location: ../user/dashboard.php');
    }
    exit();
    
} else {

    $_SESSION['login_error']    = 'Invalid username or password.';
    $_SESSION['login_username'] = $username;

    supabaseRequest(
        'Audit_Log',
        'POST',
        [
            'user_id'      => null,
            'action'       => 'LOGIN_FAILED: ' . $username,
            'target_table' => 'User',
            'target_id'    => null
        ]
    );

    header('Location: ../login.php');
    exit();
}
