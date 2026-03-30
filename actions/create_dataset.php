<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header('Location: ../login.php');
    exit();
}

if (isset($_POST['submit_request'])) {
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category'] ?? '');
    $sensitivity = $_POST['sensitivity'] ?? '';
    $active      = $_POST['active'] ?? '';

    if ($name !== '' && $description !== '' && $category !== '' && ($sensitivity === 'Sensitive' || $sensitivity === 'Non-sensitive') && ($active === 'True' || $active === 'False')) {
        $insert_query = '
            INSERT INTO "Dataset" (name, description, category, sensitivity, active)
            VALUES ($1, $2, $3, $4, $5)
        ';
        $result = pg_query_params($conn, $insert_query, [
            $name,
            $description,
            $category,
            $sensitivity,
            $active
        ]);
    }
}

$select_query = '
    SELECT dataset_id FROM "Dataset"
    ORDER BY dataset_id DESC
    LIMIT 1';

$result = pg_query($conn, $select_query);
$row = pg_fetch_assoc($result);
$dataset_id = $row['dataset_id'];

if (isset($_SESSION['user_id'])) {
    $log_query = '
        INSERT INTO "Audit_Log" (user_id, action, target_table, target_id)
        VALUES ($1, $2, $3, $4)';
        
    @pg_query_params($conn, $log_query, [
        $_SESSION['user_id'],
        'CREATE',
        'DATASET',
        $dataset_id
    ]);
}

header('Location: ../admin/rules_management.php');
exit();