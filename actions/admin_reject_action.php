<?php
session_start();
require_once '../config/supabase.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Administrator') {
    header('Location: ../user/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin/manage_requests.php');
    exit();
}

$request_id = intval($_POST['request_id'] ?? 0);
$reason     = trim($_POST['rejection_reason'] ?? '');

if ($request_id <= 0) {
    $_SESSION['admin_error'] = 'Invalid request.';
    header('Location: ../admin/manage_requests.php');
    exit();
}

if (empty($reason) || strlen($reason) < 10) {
    $_SESSION['admin_error'] = 'Please provide a reason for rejection (at least 10 characters).';
    header('Location: ../admin/manage_requests.php');
    exit();
}

$check_result = supabaseRequest(
    'Access_Request?select=request_id,user_id,dataset_id,request_status&request_id=eq.' . $request_id
);

if (empty($check_result) || isset($check_result['error'])) {
    $_SESSION['admin_error'] = 'Request not found.';
    header('Location: ../admin/manage_requests.php');
    exit();
}

$access_request = $check_result[0];

$user_result = supabaseRequest(
    'User?select=user_id,full_name,email&user_id=eq.' . $access_request['user_id']
);

if (empty($user_result) || isset($user_result['error'])) {
    $_SESSION['admin_error'] = 'User not found.';
    header('Location: ../admin/manage_requests.php');
    exit();
}

$user = $user_result[0];

$dataset_result = supabaseRequest(
    'Dataset?select=dataset_id,name,sensitivity&dataset_id=eq.' . $access_request['dataset_id']
);

if (empty($dataset_result) || isset($dataset_result['error'])) {
    $_SESSION['admin_error'] = 'Dataset not found.';
    header('Location: ../admin/manage_requests.php');
    exit();
}

$dataset = $dataset_result[0];

if ($dataset['sensitivity'] !== 'Sensitive') {
    $_SESSION['admin_error'] = 'Request not found or not a sensitive dataset request.';
    header('Location: ../admin/manage_requests.php');
    exit();
}

if ($access_request['request_status'] !== 'Pending') {
    $_SESSION['admin_error'] = 'This request has already been ' . strtolower($access_request['request_status']) . '.';
    header('Location: ../admin/manage_requests.php');
    exit();
}

$update_result = supabaseRequest(
    'Access_Request?request_id=eq.' . $request_id,
    'PATCH',
    [
        'request_status'  => 'Rejected',
        'approved_date'   => date('c'),
        'approval_reason' => 'Rejected by ' . $_SESSION['full_name'],
        'approver_id'     => $_SESSION['user_id']
    ]
);

if (isset($update_result['error'])) {
    $_SESSION['admin_error'] = 'Failed to reject request. Please try again.';
    header('Location: ../admin/manage_requests.php');
    exit();
}

supabaseRequest(
    'Audit_Log',
    'POST',
    [
        'user_id'      => $_SESSION['user_id'],
        'action'       => 'REJECTED: Admin rejected sensitive access for "' . $user['full_name'] . '" to dataset "' . $dataset['name'] . '" — Reason: ' . $reason,
        'target_table' => 'Access_Request',
        'target_id'    => $request_id
    ]
);

require_once '../config/email_helper.php';

if (!empty($user['email'])) {
    $email_sent = sendRejectionEmail(
        $user['email'],
        $user['full_name'],
        $dataset['name'],
        $reason
    );

    if ($email_sent) {
        $_SESSION['admin_success'] = 'Request rejected and notification email sent to ' . $user['email'];
    } else {
        $_SESSION['admin_success'] = 'Request rejected but email notification failed.';
    }
} else {
    $_SESSION['admin_success'] = 'Request rejected successfully.';
}

$_SESSION['admin_success'] = 'Sensitive request from "' . $user['full_name'] . '" for "' . $dataset['name'] . '" has been rejected.';
header('Location: ../admin/manage_requests.php');
exit();
?>