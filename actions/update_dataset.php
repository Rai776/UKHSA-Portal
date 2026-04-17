<?php
session_start();
require_once '../config/supabase.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header('Location: ../login.php');
    exit();
}

if (isset($_POST['submit_request'])) {
    $dataset_id  = intval($_POST['dataset_id']  ?? 0);
    $name        = trim($_POST['name']          ?? '');
    $sensitivity = trim($_POST['sensitivity']   ?? '');
    $description = trim($_POST['description']   ?? '');
    $category    = trim($_POST['category']      ?? '');

    $active_raw = trim($_POST['active'] ?? 'true');
    $active     = ($active_raw === 'true') ? true : false;

    if ($dataset_id <= 0 || empty($name) || empty($description) || empty($category) || empty($sensitivity)) {
        header('Location: ../admin/rules_management.php?error=All+fields+are+required');
        exit();
    }

    $result = supabaseRequest(
        'Dataset?dataset_id=eq.' . $dataset_id,
        'PATCH',
        [
            'name'        => $name,
            'sensitivity' => $sensitivity,
            'description' => $description,
            'category'    => $category,
            'active'      => $active
        ]
    );

    if (!isset($result['error'])) {
        supabaseRequest(
            'Audit_Log',
            'POST',
            [
                'user_id'      => $_SESSION['user_id'],
                'action'       => 'UPDATED dataset: ' . $name . ' (Active: ' . ($active ? 'true' : 'false') . ')',
                'target_table' => 'Dataset',
                'target_id'    => $dataset_id
            ]
        );

        header('Location: ../admin/rules_management.php?success=Dataset+updated+successfully');
        exit();
    } else {
        echo 'Error updating dataset: ' . json_encode($result['error']);
    }
}
?>