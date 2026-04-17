<?php
session_start();
require_once '../config/db_connect.php';

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
    $active     = ($active_raw === 'true') ? 'true' : 'false';

    if ($dataset_id <= 0 || empty($name) || empty($description) || empty($category) || empty($sensitivity)) {
        header('Location: ../admin/rules_management.php?error=All+fields+are+required');
        exit();
    }

    $result = pg_query_params($conn, '
        UPDATE "Dataset"
        SET name        = $1,
            sensitivity = $2,
            description = $3,
            category    = $4,
            active      = $5::boolean
        WHERE dataset_id = $6
    ', [$name, $sensitivity, $description, $category, $active, $dataset_id]);

    if ($result) {
        pg_query_params($conn, '
            INSERT INTO "Audit_Log" (user_id, action, target_table, target_id)
            VALUES ($1, $2, $3, $4)
        ', [
            $_SESSION['user_id'],
            'UPDATED dataset: ' . $name . ' (Active: ' . $active . ')',
            'Dataset',
            $dataset_id
        ]);

        header('Location: ../admin/rules_management.php?success=Dataset+updated+successfully');
        exit();

    } else {
        echo 'Error updating dataset: ' . pg_last_error($conn);
    }
}
?>