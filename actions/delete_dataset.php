<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header('Location: ../login.php');
    exit();
}

if (!empty($_POST['dataset_id'])) {
    $dataset_id = intval($_POST['dataset_id']);

    $query = 'DELETE FROM "Dataset" WHERE dataset_id = $1';
    $result = pg_query_params($conn, $query, [$dataset_id]);

    if (!$result) {
        error_log("Failed to delete dataset: " . pg_last_error($conn));
    }
}

header('Location: ../admin/rules_management.php');
exit();