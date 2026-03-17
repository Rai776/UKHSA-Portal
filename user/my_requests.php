<?php
session_start();
require_once '../config/db_connect.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$request_error   = $_SESSION['request_error'] ?? '';
$request_success = $_SESSION['request_success'] ?? '';
unset($_SESSION['request_error']);
unset($_SESSION['request_success']);

$search       = trim($_GET['search'] ?? '');
$filter       = trim($_GET['filter'] ?? 'all');
$current_page = max(1, intval($_GET['page'] ?? 1));
$per_page     = 5;
$offset       = ($current_page - 1) * $per_page;

$conditions = 'WHERE ar.user_id = $1';
$params     = [$_SESSION['user_id']];
$param_num  = 2;

if ($filter === 'pending') {
    $conditions .= " AND ar.request_status = \$" . $param_num;
    $params[] = 'Pending';
    $param_num++;
} elseif ($filter === 'approved') {
    $conditions .= " AND ar.request_status = \$" . $param_num;
    $params[] = 'Approved';
    $param_num++;
} elseif ($filter === 'rejected') {
    $conditions .= " AND ar.request_status = \$" . $param_num;
    $params[] = 'Rejected';
    $param_num++;
}

if (!empty($search)) {
    $conditions .= " AND (LOWER(d.name) LIKE LOWER(\$" . $param_num . ")";
    $conditions .= " OR LOWER(d.sensitivity) LIKE LOWER(\$" . $param_num . "))";
    $params[] = '%' . $search . '%';
    $param_num++;
}

$count_query = '
    SELECT COUNT(*) as total
    FROM "Access_Request" ar
    JOIN "Dataset" d ON ar.dataset_id = d.dataset_id
    ' . $conditions;

$count_result  = pg_query_params($conn, $count_query, $params);
$total_records = 0;
if ($count_result) {
    $row = pg_fetch_assoc($count_result);
    $total_records = intval($row['total']);
}
$total_pages = max(1, ceil($total_records / $per_page));

$fetch_query = '
    SELECT 
        ar.request_id,
        ar.dataset_id,
        d.name as dataset_name,
        d.sensitivity,
        ar.access_type,
        ar.purpose,
        ar.request_status,
        ar.approval_reason,
        ar.request_date,
        ar.approved_date,
        ar.expiry_date
    FROM "Access_Request" ar
    JOIN "Dataset" d ON ar.dataset_id = d.dataset_id
    ' . $conditions . '
    ORDER BY ar.request_date DESC
    LIMIT $' . $param_num . ' OFFSET $' . ($param_num + 1);

$params[] = $per_page;
$params[] = $offset;

$fetch_result = pg_query_params($conn, $fetch_query, $params);
$requests = [];
if ($fetch_result) {
    while ($row = pg_fetch_assoc($fetch_result)) {
        $requests[] = $row;
    }
}

$count_all_q = pg_query_params($conn, '
    SELECT COUNT(*) as total FROM "Access_Request" WHERE user_id = $1
', [$_SESSION['user_id']]);
$count_all = intval(pg_fetch_assoc($count_all_q)['total']);

$count_pending_q = pg_query_params($conn, '
    SELECT COUNT(*) as total FROM "Access_Request" WHERE user_id = $1 AND request_status = $2
', [$_SESSION['user_id'], 'Pending']);
$count_pending = intval(pg_fetch_assoc($count_pending_q)['total']);

$count_approved_q = pg_query_params($conn, '
    SELECT COUNT(*) as total FROM "Access_Request" WHERE user_id = $1 AND request_status = $2
', [$_SESSION['user_id'], 'Approved']);
$count_approved = intval(pg_fetch_assoc($count_approved_q)['total']);

$count_rejected_q = pg_query_params($conn, '
    SELECT COUNT(*) as total FROM "Access_Request" WHERE user_id = $1 AND request_status = $2
', [$_SESSION['user_id'], 'Rejected']);
$count_rejected = intval(pg_fetch_assoc($count_rejected_q)['total']);

$search_query = !empty($search) ? '&search=' . urlencode($search) : '';
$filter_query = ($filter !== 'all') ? '&filter=' . urlencode($filter) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/my_requests.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <title>My Requests — UKHSA Data Governance Portal</title>
</head>
<body>

    <?php include("navbar.php"); ?>

    <main class="page-main">
        <div class="page-container">
            <div class="page-header">
                <h1>My Requests</h1>
                <p>Track and manage your dataset access requests</p>
            </div>

            <?php if (!empty($request_success)): ?>
            <div class="alert alert-success">
                <span class="material-icons">check_circle</span>
                <span><?php echo htmlspecialchars($request_success); ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($request_error)): ?>
            <div class="alert alert-error">
                <span class="material-icons">error</span>
                <span><?php echo htmlspecialchars($request_error); ?></span>
            </div>
            <?php endif; ?>

            <div class="filter-tabs">
                <a href="?filter=all<?php echo $search_query; ?>" 
                   class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All <span class="tab-count"><?php echo $count_all; ?></span>
                </a>
                <a href="?filter=pending<?php echo $search_query; ?>" 
                   class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                    Pending <span class="tab-count"><?php echo $count_pending; ?></span>
                </a>
                <a href="?filter=approved<?php echo $search_query; ?>" 
                   class="filter-tab <?php echo $filter === 'approved' ? 'active' : ''; ?>">
                    Approved <span class="tab-count"><?php echo $count_approved; ?></span>
                </a>
                <a href="?filter=rejected<?php echo $search_query; ?>" 
                   class="filter-tab <?php echo $filter === 'rejected' ? 'active' : ''; ?>">
                    Rejected <span class="tab-count"><?php echo $count_rejected; ?></span>
                </a>
            </div>

            <div class="search-section">
                <form method="GET" action="my_requests.php" class="search-form">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <div class="search-input-group">
                        <input 
                            type="text" 
                            name="search" 
                            class="search-input"
                            placeholder="Search by dataset name or sensitivity..."
                            value="<?php echo htmlspecialchars($search); ?>"
                        >
                        <button type="submit" class="search-btn">Search</button>
                    </div>
                </form>

                <?php if (!empty($search)): ?>
                <div class="search-info">
                    <span>
                        Showing <strong><?php echo $total_records; ?></strong> 
                        result<?php echo $total_records !== 1 ? 's' : ''; ?> 
                        for "<strong><?php echo htmlspecialchars($search); ?></strong>"
                    </span>
                    <a href="?filter=<?php echo $filter; ?>" class="clear-search">Clear search</a>
                </div>
                <?php endif; ?>
            </div>

            <div class="records-info">
                Showing 
                <strong><?php echo $total_records > 0 ? min($offset + 1, $total_records) : 0; ?></strong> 
                to 
                <strong><?php echo min($offset + $per_page, $total_records); ?></strong> 
                of 
                <strong><?php echo $total_records; ?></strong> requests
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Dataset Name</th>
                            <th>Sensitivity</th>
                            <th>Status</th>
                            <th>Request Date</th>
                            <th>Expiry Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="6" class="empty-row">
                                <?php if (!empty($search)): ?>
                                    No requests found matching "<?php echo htmlspecialchars($search); ?>".
                                <?php elseif ($filter !== 'all'): ?>
                                    No <?php echo htmlspecialchars($filter); ?> requests found.
                                <?php else: ?>
                                    You have no requests yet. 
                                    <a href="dataset_catalogue.php">Browse datasets</a> to get started.
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): ?>
                            <tr>
                                <td class="dataset-name">
                                    <?php echo htmlspecialchars($req['dataset_name']); ?>
                                </td>
                                <td>
                                    <?php if ($req['sensitivity'] === 'Sensitive'): ?>
                                        <span class="sensitivity-badge sensitive">Sensitive</span>
                                    <?php else: ?>
                                        <span class="sensitivity-badge non-sensitive">Non-sensitive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    switch ($req['request_status']) {
                                        case 'Approved': $status_class = 'approved'; break;
                                        case 'Pending':  $status_class = 'pending';  break;
                                        case 'Rejected': $status_class = 'rejected'; break;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <?php echo htmlspecialchars($req['request_status']); ?>
                                    </span>
                                </td>
                                <td class="date-cell">
                                    <?php echo date('d M Y', strtotime($req['request_date'])); ?>
                                </td>
                                <td class="date-cell">
                                    <?php if ($req['expiry_date']): ?>
                                        <?php 
                                        $expiry = strtotime($req['expiry_date']);
                                        $is_expired = $expiry < time();
                                        $is_expiring = $expiry < strtotime('+30 days');
                                        ?>
                                        <span class="<?php echo $is_expired ? 'expired-text' : ($is_expiring ? 'expiring-text' : ''); ?>">
                                            <?php echo date('d M Y', $expiry); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="no-date">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($req['request_status'] === 'Approved'): ?>
                                        <button 
                                            type="button" 
                                            class="btn-action btn-renew"
                                            onclick="openRenewModal(
                                                <?php echo $req['request_id']; ?>,
                                                '<?php echo htmlspecialchars(addslashes($req['dataset_name'])); ?>',
                                                '<?php echo $req['expiry_date'] ? date('d M Y', strtotime($req['expiry_date'])) : 'No expiry'; ?>'
                                            )"
                                        >
                                            <span class="material-icons">autorenew</span> Renew
                                        </button>

                                    <?php elseif ($req['request_status'] === 'Pending'): ?>
                                        <button 
                                            type="button" 
                                            class="btn-action btn-cancel"
                                            onclick="openCancelModal(
                                                <?php echo $req['request_id']; ?>,
                                                '<?php echo htmlspecialchars(addslashes($req['dataset_name'])); ?>'
                                            )"
                                        >
                                            <span class="material-icons">close</span> Cancel
                                        </button>

                                    <?php elseif ($req['request_status'] === 'Rejected'): ?>
                                        <button 
                                            type="button" 
                                            class="btn-action btn-reason"
                                            onclick="openReasonModal(
                                                '<?php echo htmlspecialchars(addslashes($req['dataset_name'])); ?>',
                                                '<?php echo htmlspecialchars(addslashes($req['approval_reason'] ?? 'No reason provided.')); ?>'
                                            )"
                                        >
                                            <span class="material-icons">visibility</span> View Reason
                                        </button>
                                    <?php endif; ?>
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
                    <a href="?page=<?php echo $current_page - 1; ?>&filter=<?php echo $filter; ?><?php echo $search_query; ?>" class="pagination-btn">
                        &laquo; Previous
                    </a>
                    <?php else: ?>
                    <span class="pagination-btn disabled">&laquo; Previous</span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page   = min($total_pages, $current_page + 2);

                    if ($start_page > 1): ?>
                        <a href="?page=1&filter=<?php echo $filter; ?><?php echo $search_query; ?>" class="pagination-num">1</a>
                        <?php if ($start_page > 2): ?>
                            <span class="pagination-dots">...</span>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i === $current_page): ?>
                            <span class="pagination-num active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?><?php echo $search_query; ?>" class="pagination-num">
                                <?php echo $i; ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="pagination-dots">...</span>
                        <?php endif; ?>
                        <a href="?page=<?php echo $total_pages; ?>&filter=<?php echo $filter; ?><?php echo $search_query; ?>" class="pagination-num">
                            <?php echo $total_pages; ?>
                        </a>
                    <?php endif; ?>

                    <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?php echo $current_page + 1; ?>&filter=<?php echo $filter; ?><?php echo $search_query; ?>" class="pagination-btn">
                        Next &raquo;
                    </a>
                    <?php else: ?>
                    <span class="pagination-btn disabled">Next &raquo;</span>
                    <?php endif; ?>

                </div>
            </nav>
            <?php endif; ?>

        </div>
    </main>

    <div class="modal-overlay" id="renewModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Renew Access</h2>
                <button type="button" class="modal-close" onclick="closeAllModals()">&times;</button>
            </div>
            <form method="POST" action="../actions/renew_action.php">
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="renew_request_id">
                    <p class="modal-text">
                        You are requesting to renew access for:
                    </p>
                    <p class="modal-dataset" id="renew_dataset_name"></p>
                    <p class="modal-expiry">
                        Current expiry: <strong id="renew_expiry_date"></strong>
                    </p>
                    <div class="form-group">
                        <label for="renew_purpose">
                            Reason for Renewal <span class="required">*</span>
                        </label>
                        <textarea 
                            id="renew_purpose" 
                            name="purpose" 
                            rows="3" 
                            placeholder="Explain why you need continued access..."
                            required
                        ></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeAllModals()">Cancel</button>
                    <button type="submit" name="submit_renew" class="btn-modal-submit btn-green">Renew Access</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="cancelModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Cancel Request</h2>
                <button type="button" class="modal-close" onclick="closeAllModals()">&times;</button>
            </div>
            <form method="POST" action="../actions/cancel_action.php">
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="cancel_request_id">
                    <p class="modal-text">
                        Are you sure you want to cancel your request for:
                    </p>
                    <p class="modal-dataset" id="cancel_dataset_name"></p>
                    <p class="modal-warning">
                        <span class="material-icons">warning</span>
                        This action cannot be undone. You will need to submit a new request.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" onclick="closeAllModals()">Go Back</button>
                    <button type="submit" name="submit_cancel" class="btn-modal-submit btn-red">Cancel Request</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="reasonModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Rejection Reason</h2>
                <button type="button" class="modal-close" onclick="closeAllModals()">&times;</button>
            </div>
            <div class="modal-body">
                <p class="modal-text">
                    Your request for the following dataset was rejected:
                </p>
                <p class="modal-dataset" id="reason_dataset_name"></p>
                <div class="reason-box">
                    <label>Reason provided by administrator:</label>
                    <p id="reason_text" class="reason-text"></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeAllModals()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function openRenewModal(requestId, datasetName, expiryDate) {
            document.getElementById('renew_request_id').value = requestId;
            document.getElementById('renew_dataset_name').textContent = datasetName;
            document.getElementById('renew_expiry_date').textContent = expiryDate;
            document.getElementById('renew_purpose').value = '';
            document.getElementById('renewModal').classList.add('active');
        }

        function openCancelModal(requestId, datasetName) {
            document.getElementById('cancel_request_id').value = requestId;
            document.getElementById('cancel_dataset_name').textContent = datasetName;
            document.getElementById('cancelModal').classList.add('active');
        }

        function openReasonModal(datasetName, reason) {
            document.getElementById('reason_dataset_name').textContent = datasetName;
            document.getElementById('reason_text').textContent = reason;
            document.getElementById('reasonModal').classList.add('active');
        }

        function closeAllModals() {
            document.getElementById('renewModal').classList.remove('active');
            document.getElementById('cancelModal').classList.remove('active');
            document.getElementById('reasonModal').classList.remove('active');
        }

        document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeAllModals();
                }
            });
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllModals();
            }
        });
    </script>

</body>
</html>