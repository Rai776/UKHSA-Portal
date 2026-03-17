<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit_renew'])) {
    header('Location: ../user/my_requests.php');
    exit();
}

$request_id = intval($_POST['request_id'] ?? 0);
$purpose    = trim($_POST['purpose'] ?? '');

if ($request_id <= 0) {
    $_SESSION['request_error'] = 'Invalid request selected.';
    header('Location: ../user/my_requests.php');
    exit();
}

if (empty($purpose)) {
    $_SESSION['request_error'] = 'Please enter a reason for renewal.';
    header('Location: ../user/my_requests.php');
    exit();
}

$check_query = '
    SELECT ar.request_id, ar.dataset_id, d.name as dataset_name, d.sensitivity
    FROM "Access_Request" ar
    JOIN "Dataset" d ON ar.dataset_id = d.dataset_id
    WHERE ar.request_id = $1 
    AND ar.user_id = $2 
    AND ar.request_status = $3
';
$check_result = pg_query_params($conn, $check_query, [
    $request_id,
    $_SESSION['user_id'],
    'Approved'
]);

$request = pg_fetch_assoc($check_result);

if (!$request) {
    $_SESSION['request_error'] = 'Request not found or cannot be renewed.';
    header('Location: ../user/my_requests.php');
    exit();
}

$pending_query = '
    SELECT request_id FROM "Access_Request"
    WHERE user_id = $1 AND dataset_id = $2 AND request_status = $3
    AND purpose LIKE $4
';
$pending_result = pg_query_params($conn, $pending_query, [
    $_SESSION['user_id'],
    $request['dataset_id'],
    'Pending',
    'RENEWAL:%'
]);

if (pg_fetch_assoc($pending_result)) {
    $_SESSION['request_error'] = 'You already have a pending renewal for "' . $request['dataset_name'] . '".';
    header('Location: ../user/my_requests.php');
    exit();
}

$rule_query = '
    SELECT auto_approve FROM "Rules" WHERE dataset_id = $1
';
$rule_result = pg_query_params($conn, $rule_query, [$request['dataset_id']]);
$rule = pg_fetch_assoc($rule_result);

$auto_approve = false;
if ($rule) {
    $auto_approve = ($rule['auto_approve'] === 't');
} else {
    $auto_approve = ($request['sensitivity'] === 'Non-sensitive');
}

if ($auto_approve) {
    $update_query = '
        UPDATE "Access_Request"
        SET expiry_date = (CURRENT_DATE + INTERVAL \'6 months\')::DATE,
            approved_date = CURRENT_TIMESTAMP,
            purpose = $1
        WHERE request_id = $2
    ';
    $update_result = pg_query_params($conn, $update_query, [
        'RENEWAL: ' . $purpose,
        $request_id
    ]);

    if (!$update_result) {
        $_SESSION['request_error'] = 'Failed to renew. Please try again.';
        header('Location: ../user/my_requests.php');
        exit();
    }

    $log_query = '
        INSERT INTO "Audit_Log" (user_id, action, target_table, target_id)
        VALUES ($1, $2, $3, $4)
    ';
    @pg_query_params($conn, $log_query, [
        $_SESSION['user_id'],
        'AUTO_RENEWED: Access to "' . $request['dataset_name'] . '" (Non-sensitive) renewed for 6 months',
        'Access_Request',
        $request_id
    ]);

    $_SESSION['request_success'] = 'Access to "' . $request['dataset_name'] . '" has been automatically renewed for 6 months!';
} else {
    $insert_query = '
        INSERT INTO "Access_Request" (
            user_id, dataset_id, access_type, purpose,
            request_status, request_date
        ) VALUES ($1, $2, $3, $4, $5, CURRENT_TIMESTAMP)
        RETURNING request_id
    ';
    $insert_result = pg_query_params($conn, $insert_query, [
        $_SESSION['user_id'],
        $request['dataset_id'],
        'Read',
        'RENEWAL: ' . $purpose,
        'Pending'
    ]);

    if (!$insert_result) {
        $_SESSION['request_error'] = 'Failed to submit renewal. Please try again.';
        header('Location: ../user/my_requests.php');
        exit();
    }

    $new_request = pg_fetch_assoc($insert_result);

    $log_query = '
        INSERT INTO "Audit_Log" (user_id, action, target_table, target_id)
        VALUES ($1, $2, $3, $4)
    ';
    @pg_query_params($conn, $log_query, [
        $_SESSION['user_id'],
        'RENEWAL_REQUEST: Requested renewal for "' . $request['dataset_name'] . '" (Sensitive) — awaiting admin approval',
        'Access_Request',
        $new_request['request_id']
    ]);

    $_SESSION['request_success'] = 'Your renewal request for "' . $request['dataset_name'] . '" has been submitted. This dataset is Sensitive and requires administrator approval.';
}

header('Location: ../user/my_requests.php');
exit();
?>