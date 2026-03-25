<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
if ($_SESSION['role'] !== 'Administrator') {
    header('Location: ../user/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datasets</title>
    <link rel="stylesheet" href="../assets/css/admin_manage_requests.css" />
</head>

<body>
    <?php
    include("navbar.php");
    ?>
    <div class="table-wrapper">
        <?php
        $select_query = 'SELECT * FROM "Dataset"';
        $result = pg_query($select_query);
        echo "<table class='data-table'>";
        echo "
        <thead>
            <tr> 
                <td>Dataset Name</td> 
                <td>Description</td> 
                <td>Sensitivity</td>
                <td>Category</td>
                <td style ='text-align: center' colspan='2'> Action </td> 
            </tr>
        </thead>";
        while ($row = $result->pg_fetch_array(SQLITE3_ASSOC)) {
            $id = $row['dataset_id'];
            $name = $row['name'];
            $description = $row['description'];
            $sensitivity = $row['sensitivity'];
            $category = $row['category'];
            echo "
            <tbody>
                <tr> 
                    <td>$id</td> 
                    <td>$name</td>
                    <td>$description</td>
                    <td>$sensitivity</td>
                    <td>$category</td>
                    <td>
                        <form action='update_dataset.php' method='post'>
                            <input type='hidden' name='' value=$id>
                            <button type='submit'>Update</button>
                        </form>
                    </td> 
                    <td>
                        <form action='delete_dataset.php' method='post'>
                            <input type='hidden' name='' value=$id>
                            <button type='submit'>Delete</button>
                        </form>
                    </td> 
                </tr>
            </tbody>";
        }
        echo "</table>";
        ?>
    </div>
<footer>
    <div class=footer>
        &copy; 2025 Benjamin Hewitt
    </div>
</footer>
</body>
</html>