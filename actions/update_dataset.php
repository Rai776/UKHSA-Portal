<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header('Location: ../login.php');
    exit();
}

if (isset($_POST['submit_request'])) {
    $dataset_id = intval($_POST['dataset_id']);
    $name = trim($_POST['name']);
    $sensitivity = trim($_POST['sensitivity']);
    $description = trim($_POST['description']);
    $category = trim($_POST['category']);
    $active = trim($_POST['active']);

    $update_query = '
        UPDATE "Dataset"
        SET name = $1,
            sensitivity = $2,
            description = $3,
            category = $4,
            active = $5
        WHERE dataset_id = $6
    ';

    $result = pg_query_params($conn, $update_query, [$name, $sensitivity, $description, $category, $active, $dataset_id]);

    if ($result) {
        header('Location: ../admin/rules_management.php?success=Dataset+updated');
        exit();
    } else {
        echo "Error updating dataset: " . pg_last_error($conn);
    }
}
?>