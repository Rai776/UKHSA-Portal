<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit_cancel'])) {
    header('Location: ../user/my_requests.php');
    exit();
}

$request_id = intval($_POST['request_id'] ?? 0);

if ($request_id <= 0) {
    $_SESSION['request_error'] = 'Invalid request selected.';
    header('Location: ../user/my_requests.php');
    exit();
}

$check_query = '
    SELECT ar.request_id, d.name as dataset_name
    FROM "Access_Request" ar
    JOIN "Dataset" d ON ar.dataset_id = d.dataset_id
    WHERE ar.request_id = $1 
    AND ar.user_id = $2 
    AND ar.request_status = $3
';
$check_result = pg_query_params($conn, $check_query, [
    $request_id,
    $_SESSION['user_id'],
    'Pending'
]);

if (!$check_result) {
    $_SESSION['request_error'] = 'A system error occurred.';
    header('Location: ../user/my_requests.php');
    exit();
}

$request = pg_fetch_assoc($check_result);

if (!$request) {
    $_SESSION['request_error'] = 'Request not found or cannot be cancelled.';
    header('Location: ../user/my_requests.php');
    exit();
}

$delete_query = '
    DELETE FROM "Access_Request" 
    WHERE request_id = $1 AND user_id = $2
';
$delete_result = pg_query_params($conn, $delete_query, [
    $request_id,
    $_SESSION['user_id']
]);

if (!$delete_result) {
    $_SESSION['request_error'] = 'Failed to cancel request. Please try again.';
    header('Location: ../user/my_requests.php');
    exit();
}

$log_query = '
    INSERT INTO "Audit_Log" (user_id, action, target_table, target_id)
    VALUES ($1, $2, $3, $4)
';
@pg_query_params($conn, $log_query, [
    $_SESSION['user_id'],
    'REQUEST_CANCELLED: Cancelled request for "' . $request['dataset_name'] . '"',
    'Access_Request',
    $request_id
]);

$_SESSION['request_success'] = 'Your request for "' . $request['dataset_name'] . '" has been cancelled.';
header('Location: ../user/my_requests.php');
exit();
?>