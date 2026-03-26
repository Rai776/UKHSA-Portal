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

$expire_query = '
    UPDATE "Access_Request"
    SET request_status = \'Rejected\',
        approval_reason = \'Access expired automatically on \' || expiry_date::TEXT
    WHERE request_status = \'Approved\'
    AND expiry_date IS NOT NULL
    AND expiry_date < CURRENT_DATE
';
$expire_result = pg_query($conn, $expire_query);

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$request_error   = $_SESSION['request_error'] ?? '';
$request_success = $_SESSION['request_success'] ?? '';
unset($_SESSION['request_error']);
unset($_SESSION['request_success']);

$search       = trim($_GET['search'] ?? '');
$current_page = max(1, intval($_GET['page'] ?? 1));
$per_page     = 5;
$offset       = ($current_page - 1) * $per_page;

if (!empty($search)) {
    $count_query = '
        SELECT COUNT(*) as total FROM "Dataset"
        WHERE LOWER(name) LIKE LOWER($1)
        OR LOWER(description) LIKE LOWER($1)
        OR LOWER(category) LIKE LOWER($1)
        OR LOWER(sensitivity) LIKE LOWER($1)
    ';
    $count_result = pg_query_params($conn, $count_query, ['%' . $search . '%']);
} else {
    $count_result = pg_query($conn, 'SELECT COUNT(*) as total FROM "Dataset"');
}

$total_records = 0;
if ($count_result) {
    $count_row     = pg_fetch_assoc($count_result);
    $total_records = intval($count_row['total']);
}
$total_pages = max(1, ceil($total_records / $per_page));

if (!empty($search)) {
    $ds_query = '
        SELECT dataset_id, name, description, category, sensitivity, active
        FROM "Dataset"
        WHERE LOWER(name) LIKE LOWER($1)
        OR LOWER(description) LIKE LOWER($1)
        OR LOWER(category) LIKE LOWER($1)
        OR LOWER(sensitivity) LIKE LOWER($1)
        ORDER BY name ASC
        LIMIT $2 OFFSET $3
    ';
    $ds_result = pg_query_params($conn, $ds_query, ['%' . $search . '%', $per_page, $offset]);
} else {
    $ds_query = '
        SELECT dataset_id, name, description, category, sensitivity, active
        FROM "Dataset"
        ORDER BY name ASC
        LIMIT $1 OFFSET $2
    ';
    $ds_result = pg_query_params($conn, $ds_query, [$per_page, $offset]);
}

$datasets = [];
if ($ds_result) {
    while ($row = pg_fetch_assoc($ds_result)) {
        $datasets[] = $row;
    }
}

$req_query = '
    SELECT dataset_id, request_status
    FROM "Access_Request"
    WHERE user_id = $1
    AND (request_status = $2 OR request_status = $3)
';
$req_result = pg_query_params($conn, $req_query, [
    $_SESSION['user_id'],
    'Pending',
    'Approved'
]);

$user_requests = [];
if ($req_result) {
    while ($row = pg_fetch_assoc($req_result)) {
        $user_requests[$row['dataset_id']] = $row['request_status'];
    }
}

$search_query = !empty($search) ? '&search=' . urlencode($search) : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Datasets</title>
    <link rel="stylesheet" href="../assets/css/rules.css" />
</head>

<body>
    <?php
    include("navbar.php");
    ?>
    <div class="page-container">
        <div class="page-header">
            <h1>Dataset Controller</h1>
            <p>Browse available datasets and create, update or delete datasets</p>
        </div>

        <?php if (!empty($request_success)): ?>
        <div class="alert alert-success">
            <span>&#10003;</span>
            <span><?php echo htmlspecialchars($request_success); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($request_error)): ?>
        <div class="alert alert-error">
            <span>&#9888;</span>
            <span><?php echo htmlspecialchars($request_error); ?></span>
        </div>
        <?php endif; ?>

        <div class="search-section">
            <form method="GET" action="dataset_catalogue.php" class="search-form">
                <div class="search-wrapper">
                    <label for="search" class="search-label">Search datasets</label>
                    <div class="search-input-group">
                        <input 
                            type="text" 
                            id="search" 
                            name="search" 
                            class="search-input"
                            placeholder="Search by name, description, category or sensitivity..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                        <button type="submit" class="search-btn">Search</button>
                    </div>
                </div>
            </form>

            <?php if (!empty($search)): ?>
            <div class="search-info">
                <span>
                    Showing <strong><?php echo $total_records; ?></strong> 
                    result<?php echo $total_records !== 1 ? 's' : ''; ?> 
                    for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                </span>
                <a href="dataset_catalogue.php" class="clear-search">Clear search</a>
            </div>
            <?php endif; ?>
        </div>

        <div class="records-info">
            <span>
                Showing 
                <strong><?php echo min($offset + 1, $total_records); ?></strong> 
                to 
                <strong><?php echo min($offset + $per_page, $total_records); ?></strong> 
                of 
                <strong><?php echo $total_records; ?></strong> datasets
            </span>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Dataset Name</th>
                        <th>Description</th>
                        <th>Category</th>
                        <th>Sensitivity</th>
                        <th>Active</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($datasets)): ?>
                    <tr>
                        <td colspan="5" class="empty-row">
                            <?php if (!empty($search)): ?>
                                No datasets found matching "<?php echo htmlspecialchars($search); ?>".
                            <?php else: ?>
                                No datasets available.
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($datasets as $dataset): ?>
                        <tr>
                            <td class="dataset-name">
                                <?php echo htmlspecialchars($dataset['name']); ?>
                            </td>
                            <td class="dataset-desc">
                                <?php echo htmlspecialchars($dataset['description']); ?>
                            </td>
                            <td>
                                <span class="category-badge">
                                    <?php echo htmlspecialchars($dataset['category'] ?? 'General'); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($dataset['sensitivity'] === 'Sensitive'): ?>
                                    <span class="sensitivity-badge sensitive">Sensitive</span>
                                <?php else: ?>
                                    <span class="sensitivity-badge non-sensitive">Non-sensitive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($dataset['active'] === 'True'): ?>
                                    <span class="activity-badge active">Active</span>
                                <?php else: ?>
                                    <span class="activity-badge inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button 
                                    type="button" 
                                    class="btn-update"
                                    onclick="">Update</button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <nav class="pagination">
                <div class="pagination-info">
                    Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                </div>
                <div class="pagination-links">

                    <?php if ($current_page > 1): ?>
                    <a href="?page=<?php echo $current_page - 1; ?><?php echo $search_query; ?>" class="pagination-btn">
                        &laquo; Previous
                    </a>
                    <?php else: ?>
                    <span class="pagination-btn disabled">&laquo; Previous</span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page   = min($total_pages, $current_page + 2);

                    if ($start_page > 1): ?>
                        <a href="?page=1<?php echo $search_query; ?>" class="pagination-num">1</a>
                        <?php if ($start_page > 2): ?>
                            <span class="pagination-dots">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i === $current_page): ?>
                            <span class="pagination-num active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?><?php echo $search_query; ?>" class="pagination-num">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="pagination-dots">...</span>
                        <?php endif; ?>
                        <a href="?page=<?php echo $total_pages; ?><?php echo $search_query; ?>" class="pagination-num">
                            <?php echo $total_pages; ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?><?php echo $search_query; ?>" class="pagination-btn">
                        Next &raquo;
                    </a>
                    <?php else: ?>
                    <span class="pagination-btn disabled">Next &raquo;</span>
                    <?php endif; ?>

                </div>
            </nav>
            <?php endif; ?>
        </div>

</body>
</html>