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

$search       = trim($_GET['search'] ?? '');
$current_page = max(1, intval($_GET['page'] ?? 1));
$per_page     = 5;
$offset       = ($current_page - 1) * $per_page;

if (!empty($search)) {
    $count_query = '
        SELECT COUNT(*) as total FROM "Audit_Log"
        WHERE CAST(user_id AS text) LIKE LOWER($1)
        OR LOWER(action) LIKE LOWER($1)
        OR LOWER(target_table) LIKE LOWER($1)
        OR CAST(target_id AS text) LIKE LOWER($1)
    ';
    $count_result = pg_query_params($conn, $count_query, ['%' . $search . '%']);
} else {
    $count_result = pg_query($conn, 'SELECT COUNT(*) as total FROM "Audit_Log"');
}

$total_records = 0;
if ($count_result) {
    $count_row     = pg_fetch_assoc($count_result);
    $total_records = intval($count_row['total']);
}
$total_pages = max(1, ceil($total_records / $per_page));

if (!empty($search)) {
    $ds_query = '
        SELECT user_id, action, target_table, target_id, timestamp
        FROM "Audit_Log"
        WHERE CAST(user_id AS text) LIKE LOWER($1)
        OR LOWER(action) LIKE LOWER($1)
        OR LOWER(target_table) LIKE LOWER($1)
        OR CAST(target_id AS text) LIKE LOWER($1)
        ORDER BY timestamp ASC
        LIMIT $2 OFFSET $3
    ';
    $ds_result = pg_query_params($conn, $ds_query, ['%' . $search . '%', $per_page, $offset]);
} else {
    $ds_query = '
        SELECT user_id, action, target_table, target_id, timestamp
        FROM "Audit_Log"
        ORDER BY timestamp ASC
        LIMIT $1 OFFSET $2
    ';
    $ds_result = pg_query_params($conn, $ds_query, [$per_page, $offset]);
}

$logs = [];
if ($ds_result) {
    while ($row = pg_fetch_assoc($ds_result)) {
        $logs[] = $row;
    }
}
$search_query = !empty($search) ? '&search=' . urlencode($search) : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Log</title>
    <link rel="stylesheet" href="../assets/css/audit_trail.css" />
</head>

<body>
    <?php
    include("navbar.php");
    ?>
    <div class="page-container">
            <div class="page-header">
                <h1>Audit Log</h1>
                <p>View all user actions</p>
            </div>

        <div class="search-section">
            <form method="GET" action="audit_trail.php" class="search-form">
                <div class="search-wrapper">
                    <label for="search" class="search-label">Search logs</label>
                    <div class="search-input-group">
                        <input 
                            type="text" 
                            id="search" 
                            name="search" 
                            class="search-input"
                            placeholder="Search by User ID, action, target table or target ID..."
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
                <a href="audit_trail.php" class="clear-search">Clear search</a>
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
                <strong><?php echo $total_records; ?></strong> logs
            </span>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Action</th>
                        <th>Target Table</th>
                        <th>Target ID</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="empty-row">
                            <?php if (!empty($search)): ?>
                                No logs found matching "<?php echo htmlspecialchars($search); ?>".
                            <?php else: ?>
                                No logs available.
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="log-user-id">
                                <?php echo htmlspecialchars($log['user_id'] ?? "N/A"); ?>
                            </td>
                            <td class="log-action">
                                <?php echo htmlspecialchars($log['action']); ?>
                            </td>
                            <td>
                                <span class="log-target-table">
                                    <?php echo htmlspecialchars($log['target_table']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="log-target-id">
                                    <?php echo htmlspecialchars($log['target_id'] ?? "N/A"); ?>
                                </span>
                            </td>
                            <td>
                                <span class="log-timestamp">
                                    <?php echo htmlspecialchars($log['timestamp'] ?? "N/A"); ?>
                                </span>
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