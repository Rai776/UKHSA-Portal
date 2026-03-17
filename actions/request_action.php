<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit_request'])) {
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

$dataset_id         = intval($_POST['dataset_id'] ?? 0);
$purpose            = trim($_POST['purpose'] ?? '');
$training_confirmed = isset($_POST['training_confirmed']) ? true : false;

if ($dataset_id <= 0) {
    $_SESSION['request_error'] = 'Invalid dataset selected.';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

if (empty($purpose)) {
    $_SESSION['request_error'] = 'Please enter the purpose of your request.';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

if (!$training_confirmed) {
    $_SESSION['request_error'] = 'You must confirm you have completed the mandatory training.';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

$ds_query = '
    SELECT dataset_id, name, sensitivity 
    FROM "Dataset" 
    WHERE dataset_id = $1
';
$ds_result = pg_query_params($conn, $ds_query, [$dataset_id]);

if (!$ds_result) {
    $_SESSION['request_error'] = 'A system error occurred.';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

$dataset = pg_fetch_assoc($ds_result);

if (!$dataset) {
    $_SESSION['request_error'] = 'Dataset not found.';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

$pending_query = '
    SELECT request_id FROM "Access_Request"
    WHERE user_id = $1 AND dataset_id = $2 AND request_status = $3
';
$pending_result = pg_query_params($conn, $pending_query, [
    $_SESSION['user_id'],
    $dataset_id,
    'Pending'
]);

if (pg_fetch_assoc($pending_result)) {
    $_SESSION['request_error'] = 'You already have a pending request for "' . $dataset['name'] . '".';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

$approved_query = '
    SELECT request_id FROM "Access_Request"
    WHERE user_id = $1 AND dataset_id = $2 AND request_status = $3
    AND (expiry_date IS NULL OR expiry_date > CURRENT_DATE)
';
$approved_result = pg_query_params($conn, $approved_query, [
    $_SESSION['user_id'],
    $dataset_id,
    'Approved'
]);

if (pg_fetch_assoc($approved_result)) {
    $_SESSION['request_error'] = 'You already have active access to "' . $dataset['name'] . '".';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

$rule_query = '
    SELECT auto_approve, required_approver_role
    FROM "Rules"
    WHERE dataset_id = $1
';
$rule_result = pg_query_params($conn, $rule_query, [$dataset_id]);
$rule = pg_fetch_assoc($rule_result);

$auto_approve = false;
if ($rule) {
    $auto_approve = ($rule['auto_approve'] === 't');
} else {
    // Fallback: auto-approve non-sensitive datasets
    $auto_approve = ($dataset['sensitivity'] === 'Non-sensitive');
}

if ($auto_approve) {
    $insert_query = '
        INSERT INTO "Access_Request" (
            user_id, dataset_id, access_type, purpose,
            request_status, request_date, approved_date, expiry_date
        ) VALUES (
            $1, $2, $3, $4, $5, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP,
            (CURRENT_DATE + INTERVAL \'6 months\')::DATE
        )
        RETURNING request_id
    ';
    $insert_result = pg_query_params($conn, $insert_query, [
        $_SESSION['user_id'],
        $dataset_id,
        'Read',
        $purpose,
        'Approved'
    ]);
} else {
    $insert_query = '
        INSERT INTO "Access_Request" (
            user_id, dataset_id, access_type, purpose,
            request_status, request_date
        ) VALUES (
            $1, $2, $3, $4, $5, CURRENT_TIMESTAMP
        )
        RETURNING request_id
    ';
    $insert_result = pg_query_params($conn, $insert_query, [
        $_SESSION['user_id'],
        $dataset_id,
        'Read',
        $purpose,
        'Pending'
    ]);
}

if (!$insert_result) {
    error_log("Insert error: " . pg_last_error($conn));
    $_SESSION['request_error'] = 'A system error occurred. Please try again.';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

$new_request = pg_fetch_assoc($insert_result);

if ($auto_approve) {
    $action = 'AUTO_APPROVED: Access to "' . $dataset['name'] . '" (Non-sensitive) auto-approved for 6 months';
} else {
    $action = 'ACCESS_REQUEST: Requested access to "' . $dataset['name'] . '" (Sensitive) — awaiting admin approval';
}

$log_query = '
    INSERT INTO "Audit_Log" (user_id, action, target_table, target_id)
    VALUES ($1, $2, $3, $4)
';
@pg_query_params($conn, $log_query, [
    $_SESSION['user_id'],
    $action,
    'Access_Request',
    $new_request['request_id']
]);

if ($auto_approve) {
    $_SESSION['request_success'] = 'Access to "' . $dataset['name'] . '" has been automatically approved! Your access expires in 6 months.';
} else {
    $_SESSION['request_success'] = 'Your request for "' . $dataset['name'] . '" has been submitted. This dataset is Sensitive and requires administrator approval.';
}

header('Location: ../user/dataset_catalogue.php');
exit();
?>