<?php
session_start();
require_once '../config/supabase.php';
require_once '../config/email_helper.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Administrator' && $_SESSION['role'] !== 'Approver') {
    header('Location: ../user/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['revoke'])) {
    header('Location: ../admin/manage_requests.php');
    exit();
}

$request_id = intval($_POST['request_id'] ?? 0);

if ($request_id <= 0) {
    $_SESSION['admin_error'] = 'Invalid request.';
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

if ($access_request['request_status'] !== 'Approved') {
    $_SESSION['admin_error'] = 'Only approved requests can be revoked.';
    header('Location: ../admin/manage_requests.php?status=Approved');
    exit();
}

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
    'Dataset?select=dataset_id,name&dataset_id=eq.' . $access_request['dataset_id']
);

if (empty($dataset_result) || isset($dataset_result['error'])) {
    $_SESSION['admin_error'] = 'Dataset not found.';
    header('Location: ../admin/manage_requests.php');
    exit();
}

$dataset = $dataset_result[0];

$update_result = supabaseRequest(
    'Access_Request?request_id=eq.' . $request_id,
    'PATCH',
    [
        'request_status'  => 'Rejected',
        'approval_reason' => 'Access revoked by ' . $_SESSION['full_name'] . ' on ' . date('d M Y H:i'),
        'expiry_date'     => null,
    ]
);

if (isset($update_result['error'])) {
    $_SESSION['admin_error'] = 'Failed to revoke access. Please try again.';
    header('Location: ../admin/manage_requests.php?status=Approved');
    exit();
}

supabaseRequest(
    'Audit_Log',
    'POST',
    [
        'user_id'      => $_SESSION['user_id'],
        'action'       => 'REVOKED: ' . $_SESSION['full_name'] . ' revoked access for "' . $user['full_name'] . '" to dataset "' . $dataset['name'] . '"',
        'target_table' => 'Access_Request',
        'target_id'    => $request_id
    ]
);

if (!empty($user['email'])) {
    $email_sent = sendRevokeEmail(
        $user['email'],
        $user['full_name'],
        $dataset['name'],
        $_SESSION['full_name']
    );

    if ($email_sent) {
        $_SESSION['admin_success'] = 'Access revoked and notification email sent to ' . $user['email'];
    } else {
        $_SESSION['admin_success'] = 'Access revoked but email notification failed.';
    }
} else {
    $_SESSION['admin_success'] = 'Access revoked successfully.';
}

$_SESSION['admin_success'] = 'Access for "' . $user['full_name'] . '" to "' . $dataset['name'] . '" has been successfully revoked.';
header('Location: ../admin/manage_requests.php?status=Approved');
exit();
?>