<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Administrator') {
    header('Location: ../user/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['approve'])) {
    header('Location: ../admin/manage_requests.php');
    exit();
}

$request_id = intval($_POST['request_id'] ?? 0);

if ($request_id <= 0) {
    $_SESSION['admin_error'] = 'Invalid request.';
    header('Location: ../admin/manage_requests.php');
    exit();
}

$check_query = '
    SELECT ar.request_id, ar.user_id, ar.dataset_id, ar.request_status,
           u.full_name, d.name as dataset_name, d.sensitivity
    FROM "Access_Request" ar
    JOIN "User" u ON ar.user_id = u.user_id
    JOIN "Dataset" d ON ar.dataset_id = d.dataset_id
    WHERE ar.request_id = $1
      AND d.sensitivity = $2
';
$check_result = pg_query_params($conn, $check_query, [$request_id, 'Sensitive']);
$request = pg_fetch_assoc($check_result);

if (!$request) {
    $_SESSION['admin_error'] = 'Request not found or not a sensitive dataset request.';
    header('Location: ../admin/manage_requests.php');
    exit();
}

if ($request['request_status'] !== 'Pending') {
    $_SESSION['admin_error'] = 'This request has already been ' . strtolower($request['request_status']) . '.';
    header('Location: ../admin/manage_requests.php');
    exit();
}

$update_query = '
    UPDATE "Access_Request"
    SET request_status = $1,
        approved_date = CURRENT_TIMESTAMP,
        expiry_date = (CURRENT_DATE + INTERVAL \'6 months\')::DATE,
        approval_reason = $2,
        approver_id = $3
    WHERE request_id = $4
';
$update_result = pg_query_params($conn, $update_query, [
    'Approved',
    'Approved by ' . $_SESSION['full_name'],
    $_SESSION['user_id'],
    $request_id
]);

if (!$update_result) {
    $_SESSION['admin_error'] = 'Failed to approve request. Please try again.';
    header('Location: ../admin/manage_requests.php');
    exit();
}

$log_query = '
    INSERT INTO "Audit_Log" (user_id, action, target_table, target_id)
    VALUES ($1, $2, $3, $4)
';
@pg_query_params($conn, $log_query, [
    $_SESSION['user_id'],
    'APPROVED: Admin approved sensitive access for "' . $request['full_name'] . '" to dataset "' . $request['dataset_name'] . '"',
    'Access_Request',
    $request_id
]);

require_once '../config/email_helper.php';

$user_query  = pg_query_params($conn, '
    SELECT u.email, u.full_name, d.name AS dataset_name
    FROM "User" u
    JOIN "Access_Request" ar ON ar.user_id = u.user_id
    JOIN "Dataset" d ON ar.dataset_id = d.dataset_id
    WHERE ar.request_id = $1
', [$request_id]);

$user_data = pg_fetch_assoc($user_query);

if ($user_data && !empty($user_data['email'])) {
    $expiry_date = date('Y-m-d', strtotime('+6 months'));

    $email_sent = sendApprovalEmail(
        $user_data['email'],
        $user_data['full_name'],
        $user_data['dataset_name'],
        $expiry_date
    );

    if ($email_sent) {
        $_SESSION['admin_success'] = 'Request approved and notification email sent to ' . $user_data['email'];
    } else {
        $_SESSION['admin_success'] = 'Request approved but email notification failed.';
    }
} else {
    $_SESSION['admin_success'] = 'Request approved successfully.';
}

$_SESSION['admin_success'] = 'Sensitive request from "' . $request['full_name'] . '" for "' . $request['dataset_name'] . '" has been approved. Access expires in 6 months.';
header('Location: ../admin/manage_requests.php');
exit();
?>