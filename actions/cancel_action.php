<?php
session_start();
require_once '../config/supabase.php';

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

$check_result = supabaseRequest(
    'Access_Request?select=request_id,dataset_id&request_id=eq.' . $request_id .
    '&user_id=eq.' . $_SESSION['user_id'] .
    '&request_status=eq.Pending'
);

if (isset($check_result['error']) || !is_array($check_result)) {
    $_SESSION['request_error'] = 'A system error occurred.';
    header('Location: ../user/my_requests.php');
    exit();
}

if (empty($check_result)) {
    $_SESSION['request_error'] = 'Request not found or cannot be cancelled.';
    header('Location: ../user/my_requests.php');
    exit();
}

$access_request = $check_result[0];

$dataset_result = supabaseRequest(
    'Dataset?select=name&dataset_id=eq.' . $access_request['dataset_id']
);

$dataset_name = $dataset_result[0]['name'] ?? 'Unknown Dataset';

$delete_result = supabaseRequest(
    'Access_Request?request_id=eq.' . $request_id .
    '&user_id=eq.' . $_SESSION['user_id'],
    'DELETE'
);

if (isset($delete_result['error'])) {
    $_SESSION['request_error'] = 'Failed to cancel request. Please try again.';
    header('Location: ../user/my_requests.php');
    exit();
}

supabaseRequest(
    'Audit_Log',
    'POST',
    [
        'user_id'      => $_SESSION['user_id'],
        'action'       => 'REQUEST_CANCELLED: Cancelled request for "' . $dataset_name . '"',
        'target_table' => 'Access_Request',
        'target_id'    => $request_id
    ]
);

$_SESSION['request_success'] = 'Your request for "' . $dataset_name . '" has been cancelled.';
header('Location: ../user/my_requests.php');
exit();
?>