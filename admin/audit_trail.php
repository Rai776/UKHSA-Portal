<?php
session_start();
require_once '../config/supabase.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
if ($_SESSION['role'] !== 'Administrator') {
    header('Location: ../user/dashboard.php');
    exit();
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $search_export = trim($_GET['search'] ?? '');

    if (!empty($search_export)) {
        $export_result = supabaseRequest(
            'Audit_Log?select=user_id,action,target_table,target_id,timestamp' .
            '&or=(action.ilike.*' . urlencode($search_export) . '*,target_table.ilike.*' . urlencode($search_export) . '*)' .
            '&order=timestamp.asc'
        );
    } else {
        $export_result = supabaseRequest(
            'Audit_Log?select=user_id,action,target_table,target_id,timestamp&order=timestamp.asc'
        );
    }

    if (isset($export_result['error'])) {
        die('Export failed: ' . json_encode($export_result['error']));
    }

    $filename = 'audit_log_' . date('Y-m-d_H-i-s') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($output, ['User ID', 'Action', 'Target Table', 'Target ID', 'Timestamp']);

    foreach ($export_result as $row) {
        fputcsv($output, [
            $row['user_id']      ?? 'N/A',
            $row['action']       ?? 'N/A',
            $row['target_table'] ?? 'N/A',
            $row['target_id']    ?? 'N/A',
            $row['timestamp']    ?? 'N/A',
        ]);
    }

    fclose($output);
    exit();
}

$search       = trim($_GET['search'] ?? '');
$current_page = max(1, intval($_GET['page'] ?? 1));
$per_page     = 5;
$offset       = ($current_page - 1) * $per_page;

if (!empty($search)) {
    $all_logs = supabaseRequest(
        'Audit_Log?select=user_id,action,target_table,target_id,timestamp' .
        '&or=(action.ilike.*' . urlencode($search) . '*,target_table.ilike.*' . urlencode($search) . '*)' .
        '&order=timestamp.asc'
    );
} else {
    $all_logs = supabaseRequest(
        'Audit_Log?select=user_id,action,target_table,target_id,timestamp&order=timestamp.asc'
    );
}

if (isset($all_logs['error']) || !is_array($all_logs)) {
    $all_logs = [];
}

$total_records = count($all_logs);
$total_pages   = max(1, ceil($total_records / $per_page));
$current_page  = min($current_page, $total_pages);
$offset        = ($current_page - 1) * $per_page;

$logs = array_slice($all_logs, $offset, $per_page);

$search_query = !empty($search) ? '&search=' . urlencode($search) : '';
$export_url   = 'audit_trail.php?export=csv' . $search_query;
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
    <?php include("navbar.php"); ?>
    <div class="page-container">
        <div class="header-content">
            <div class="page-header">
                <h1>Audit Log</h1>
                <p>View all user actions</p>
            </div>
            <a href="<?php echo htmlspecialchars($export_url); ?>" class="btn-export">
                Export as CSV
            </a>
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
                            placeholder="Search by action or target table..."
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
                                <?php echo htmlspecialchars($log['user_id'] ?? 'N/A'); ?>
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
                                    <?php echo htmlspecialchars($log['target_id'] ?? 'N/A'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="log-timestamp">
                                    <?php echo htmlspecialchars($log['timestamp'] ?? 'N/A'); ?>
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
</body>
</html>