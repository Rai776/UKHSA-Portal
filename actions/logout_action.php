<?php
session_start();
require_once '../config/supabase.php';

if (isset($_SESSION['user_id'])) {
    supabaseRequest(
        'Audit_Log',
        'POST',
        [
            'user_id'      => $_SESSION['user_id'],
            'action'       => 'LOGOUT',
            'target_table' => 'User',
            'target_id'    => $_SESSION['user_id']
        ]
    );
}

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

header('Location: ../login.php?logout=success');
exit();
?>