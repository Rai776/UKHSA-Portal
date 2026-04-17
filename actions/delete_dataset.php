<?php
session_start();
require_once '../config/supabase.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header('Location: ../login.php');
    exit();
}

if (!empty($_POST['dataset_id'])) {
    $dataset_id = intval($_POST['dataset_id']);

    $result = supabaseRequest(
        'Dataset?dataset_id=eq.' . $dataset_id,
        'DELETE'
    );

    if (isset($result['error'])) {
        error_log("Failed to delete dataset: " . json_encode($result['error']));
    }

    if (isset($_SESSION['user_id'])) {
        $log_result = supabaseRequest(
            'Audit_Log',
            'POST',
            [
                'user_id'      => $_SESSION['user_id'],
                'action'       => 'DELETE',
                'target_table' => 'Dataset',
                'target_id'    => $dataset_id
            ]
        );

        if (isset($log_result['error'])) {
            die("Audit log failed: " . json_encode($log_result['error']));
        }
    }
}

header('Location: ../admin/rules_management.php');
exit();
?> 