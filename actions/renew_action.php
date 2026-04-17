<?php
session_start();
require_once '../config/supabase.php';

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

$check_result = supabaseRequest(
    'Access_Request?select=request_id,dataset_id,request_status&request_id=eq.' . $request_id .
    '&user_id=eq.' . $_SESSION['user_id'] .
    '&request_status=eq.Approved'
);

if (empty($check_result) || isset($check_result['error'])) {
    $_SESSION['request_error'] = 'Request not found or cannot be renewed.';
    header('Location: ../user/my_requests.php');
    exit();
}

$access_request = $check_result[0];
$dataset_id     = $access_request['dataset_id'];

$dataset_result = supabaseRequest(
    'Dataset?select=dataset_id,name,sensitivity&dataset_id=eq.' . $dataset_id
);

if (empty($dataset_result) || isset($dataset_result['error'])) {
    $_SESSION['request_error'] = 'Dataset not found.';
    header('Location: ../user/my_requests.php');
    exit();
}

$dataset      = $dataset_result[0];
$dataset_name = $dataset['name'];

$pending_result = supabaseRequest(
    'Access_Request?user_id=eq.' . $_SESSION['user_id'] .
    '&dataset_id=eq.' . $dataset_id .
    '&request_status=eq.Pending' .
    '&purpose=like.RENEWAL:*'
);

if (!empty($pending_result) && !isset($pending_result['error'])) {
    $_SESSION['request_error'] = 'You already have a pending renewal for "' . $dataset_name . '".';
    header('Location: ../user/my_requests.php');
    exit();
}

$rule_result  = supabaseRequest('Rules?select=auto_approve&dataset_id=eq.' . $dataset_id);
$rule         = $rule_result[0] ?? null;

$auto_approve = false;
if ($rule) {
    $auto_approve = ($rule['auto_approve'] === true || $rule['auto_approve'] === 't');
} else {
    $auto_approve = ($dataset['sensitivity'] === 'Non-sensitive');
}

if ($auto_approve) {
    $new_expiry = date('Y-m-d', strtotime('+6 months'));

    $update_result = supabaseRequest(
        'Access_Request?request_id=eq.' . $request_id,
        'PATCH',
        [
            'expiry_date'   => $new_expiry,
            'approved_date' => date('c'),
            'purpose'       => 'RENEWAL: ' . $purpose
        ]
    );

    if (isset($update_result['error'])) {
        $_SESSION['request_error'] = 'Failed to renew. Please try again.';
        header('Location: ../user/my_requests.php');
        exit();
    }

    supabaseRequest(
        'Audit_Log',
        'POST',
        [
            'user_id'      => $_SESSION['user_id'],
            'action'       => 'AUTO_RENEWED: Access to "' . $dataset_name . '" (Non-sensitive) renewed for 6 months',
            'target_table' => 'Access_Request',
            'target_id'    => $request_id
        ]
    );

    $_SESSION['request_success'] = 'Access to "' . $dataset_name . '" has been automatically renewed for 6 months!';
} else {
    $insert_result = supabaseRequest(
        'Access_Request',
        'POST',
        [
            'user_id'        => $_SESSION['user_id'],
            'dataset_id'     => $dataset_id,
            'access_type'    => 'Read',
            'purpose'        => 'RENEWAL: ' . $purpose,
            'request_status' => 'Pending',
            'request_date'   => date('c')
        ],
        ['Prefer: return=representation']
    );

    if (isset($insert_result['error']) || empty($insert_result)) {
        $_SESSION['request_error'] = 'Failed to submit renewal. Please try again.';
        header('Location: ../user/my_requests.php');
        exit();
    }

    $new_request_id = $insert_result[0]['request_id'];

    supabaseRequest(
        'Audit_Log',
        'POST',
        [
            'user_id'      => $_SESSION['user_id'],
            'action'       => 'RENEWAL_REQUEST: Requested renewal for "' . $dataset_name . '" (Sensitive) — awaiting admin approval',
            'target_table' => 'Access_Request',
            'target_id'    => $new_request_id
        ]
    );

    $_SESSION['request_success'] = 'Your renewal request for "' . $dataset_name . '" has been submitted. This dataset is Sensitive and requires administrator approval.';
}

header('Location: ../user/my_requests.php');
exit();
?>