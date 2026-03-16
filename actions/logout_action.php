<?php
session_start();
require_once '../config/db_connect.php';

if (isset($_SESSION['user_id'])) {
    $log_query = '
        INSERT INTO "Audit_Log" (user_id, action, target_table, target_id)
        VALUES ($1, $2, $3, $4)
    ';
    @pg_query_params($conn, $log_query, [
        $_SESSION['user_id'],
        'LOGOUT',
        'User',
        $_SESSION['user_id']
    ]);
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