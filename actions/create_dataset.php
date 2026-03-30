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

header('Location: ../admin/rules_management.php');
exit();