<?php
session_start();
require_once '../config/supabase.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header('Location: ../login.php');
    exit();
}

if (isset($_POST['submit_request'])) {
    $name        = trim($_POST['name']        ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category']    ?? '');
    $sensitivity = $_POST['sensitivity']      ?? '';
    $active_raw  = $_POST['active']           ?? 'true';
    $active      = ($active_raw === 'true' || $active_raw === '1' || $active_raw === true);

    if (
        $name        !== '' &&
        $description !== '' &&
        $category    !== '' &&
        ($sensitivity === 'Sensitive' || $sensitivity === 'Non-sensitive')
    ) {
        $insert_result = supabaseRequest(
            'Dataset',
            'POST',
            [
                'name'        => $name,
                'description' => $description,
                'category'    => $category,
                'sensitivity' => $sensitivity,
                'active'      => $active
            ],
            ['Prefer: return=representation']
        );

        if (isset($insert_result['error']) || empty($insert_result)) {
            error_log("Failed to insert dataset: " . json_encode($insert_result));
            header('Location: ../admin/rules_management.php?error=Failed+to+create+dataset');
            exit();
        }

        $dataset_id = $insert_result[0]['dataset_id'];

        if (isset($_SESSION['user_id'])) {
            supabaseRequest(
                'Audit_Log',
                'POST',
                [
                    'user_id'      => $_SESSION['user_id'],
                    'action'       => 'CREATE',
                    'target_table' => 'Dataset',
                    'target_id'    => $dataset_id
                ]
            );
        }
    }
}

header('Location: ../admin/rules_management.php');
exit();
?> 