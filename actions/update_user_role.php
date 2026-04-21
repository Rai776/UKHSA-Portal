<?php
session_start();
require_once '../config/supabase.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

if ($_SESSION['role'] !== 'Administrator') {
    header('Location: ../admin/dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['submit_edit'])) {
    header('Location: ../admin/user_management.php');
    exit();
}

$user_id           = trim($_POST['user_id']          ?? '');
$system_role       = trim($_POST['system_role']       ?? '');
$team              = trim($_POST['team']              ?? '');
$job_type          = trim($_POST['job_type']          ?? '');
$training_raw      = $_POST['training_completed']     ?? 'false';
$training_expiry   = trim($_POST['training_expiry']   ?? '');

if (empty($user_id)) {
    $_SESSION['um_error'] = 'Invalid user selected.';
    header('Location: ../admin/user_management.php');
    exit();
}

$valid_roles = ['Administrator', 'Approver', 'User'];
if (!in_array($system_role, $valid_roles)) {
    $_SESSION['um_error'] = 'Invalid role selected.';
    header('Location: ../admin/user_management.php');
    exit();
}

if ($user_id === $_SESSION['user_id']) {
    $_SESSION['um_error'] = 'You cannot change your own role.';
    header('Location: ../admin/user_management.php');
    exit();
}

$training_completed = ($training_raw === 'true');

$payload = [
    'system_role'        => $system_role,
    'team'               => $team,
    'job_type'           => $job_type,
    'training_completed' => $training_completed,
];

if (!empty($training_expiry)) {
    $payload['training_expiry'] = $training_expiry;
}

$result = supabaseRequest(
    'User?user_id=eq.' . $user_id,
    'PATCH',
    $payload
);

if (isset($result['error'])) {
    $_SESSION['um_error'] = 'Failed to update user. Please try again.';
    header('Location: ../admin/user_management.php');
    exit();
}


$user_result = supabaseRequest('User?select=full_name&user_id=eq.' . $user_id);
$user_name   = $user_result[0]['full_name'] ?? 'Unknown';

supabaseRequest(
    'Audit_Log',
    'POST',
    [
        'user_id'      => $_SESSION['user_id'],
        'action'       => 'UPDATED USER ROLE: ' . $user_name . ' role changed to ' . $system_role . ' by ' . $_SESSION['full_name'],
        'target_table' => 'User',
        'target_id'    => $user_id
    ]
);

$_SESSION['um_success'] = 'User "' . $user_name . '" has been updated successfully. Role set to ' . $system_role . '.';
header('Location: ../admin/user_management.php');
exit();
?>