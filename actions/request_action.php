<?php
session_start();
require_once '../config/supabase.php';

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

$ds_result = supabaseRequest(
    'Dataset?select=dataset_id,name,sensitivity&dataset_id=eq.' . $dataset_id
);

if (isset($ds_result['error']) || !is_array($ds_result)) {
    $_SESSION['request_error'] = 'A system error occurred. (DS)';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

if (empty($ds_result)) {
    $_SESSION['request_error'] = 'Dataset not found.';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

$dataset = $ds_result[0];

$pending_result = supabaseRequest(
    'Access_Request?select=request_id&user_id=eq.' . $_SESSION['user_id'] .
    '&dataset_id=eq.' . $dataset_id .
    '&request_status=eq.Pending'
);

if (!empty($pending_result) && !isset($pending_result['error'])) {
    $_SESSION['request_error'] = 'You already have a pending request for "' . $dataset['name'] . '".';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

$today           = date('Y-m-d');
$approved_result = supabaseRequest(
    'Access_Request?select=request_id&user_id=eq.' . $_SESSION['user_id'] .
    '&dataset_id=eq.' . $dataset_id .
    '&request_status=eq.Approved' .
    '&expiry_date=gt.' . $today
);

if (!empty($approved_result) && !isset($approved_result['error'])) {
    $_SESSION['request_error'] = 'You already have active access to "' . $dataset['name'] . '".';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

$rule_result = supabaseRequest(
    'Rules?select=auto_approve,required_approver_role&dataset_id=eq.' . $dataset_id
);

$rule         = (!empty($rule_result) && !isset($rule_result['error'])) ? $rule_result[0] : null;
$auto_approve = false;

if ($rule) {
    $auto_approve = ($rule['auto_approve'] === true || $rule['auto_approve'] === 't');
} else {
    $auto_approve = ($dataset['sensitivity'] === 'Non-sensitive');
}

if ($auto_approve) {
    $payload = [
        'user_id'        => $_SESSION['user_id'],
        'dataset_id'     => $dataset_id,
        'access_type'    => 'Read',
        'purpose'        => $purpose,
        'request_status' => 'Approved',
        'request_date'   => date('c'),
        'approved_date'  => date('c'),
        'expiry_date'    => date('Y-m-d', strtotime('+6 months'))
    ];
} else {
    $payload = [
        'user_id'        => $_SESSION['user_id'],
        'dataset_id'     => $dataset_id,
        'access_type'    => 'Read',
        'purpose'        => $purpose,
        'request_status' => 'Pending',
        'request_date'   => date('c')
    ];
}

$insert_result = supabaseRequest('Access_Request', 'POST', $payload);

if (isset($insert_result['error'])) {
    error_log('Insert error: ' . json_encode($insert_result));
    $_SESSION['request_error'] = 'A system error occurred. Please try again.';
    header('Location: ../user/dataset_catalogue.php');
    exit();
}

$new_req = supabaseRequest(
    'Access_Request?select=request_id&user_id=eq.' . $_SESSION['user_id'] .
    '&dataset_id=eq.' . $dataset_id .
    '&order=request_date.desc&limit=1'
);

$new_request_id = $new_req[0]['request_id'] ?? 0;

if ($auto_approve) {
    $action = 'AUTO_APPROVED: Access to "' . $dataset['name'] . '" (Non-sensitive) auto-approved for 6 months';
} else {
    $action = 'ACCESS_REQUEST: Requested access to "' . $dataset['name'] . '" (Sensitive) — awaiting admin approval';
}

supabaseRequest(
    'Audit_Log',
    'POST',
    [
        'user_id'      => $_SESSION['user_id'],
        'action'       => $action,
        'target_table' => 'Access_Request',
        'target_id'    => $new_request_id
    ]
);

if ($auto_approve) {
    $_SESSION['request_success'] = 'Access to "' . $dataset['name'] . '" has been automatically approved! Your access expires in 6 months.';
} else {
    $_SESSION['request_success'] = 'Your request for "' . $dataset['name'] . '" has been submitted. This dataset is Sensitive and requires administrator approval.';
}

header('Location: ../user/dataset_catalogue.php');
exit();
?>