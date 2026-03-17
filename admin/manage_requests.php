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

$per_page     = 10;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset       = ($current_page - 1) * $per_page;

$filter_status = $_GET['status'] ?? 'Pending';
$search_term   = trim($_GET['search'] ?? '');

$valid_statuses = ['All', 'Pending', 'Approved', 'Rejected'];
if (!in_array($filter_status, $valid_statuses)) {
    $filter_status = 'Pending';
}

$count_all_q = pg_query($conn, '
    SELECT COUNT(*) as total 
    FROM "Access_Request" ar 
    JOIN "Dataset" d ON ar.dataset_id = d.dataset_id 
    WHERE d.sensitivity = \'Sensitive\'
');
$count_all = intval(pg_fetch_assoc($count_all_q)['total']);

$count_pending_q = pg_query_params($conn, '
    SELECT COUNT(*) as total 
    FROM "Access_Request" ar 
    JOIN "Dataset" d ON ar.dataset_id = d.dataset_id 
    WHERE d.sensitivity = \'Sensitive\' AND ar.request_status = $1
', ['Pending']);
$count_pending = intval(pg_fetch_assoc($count_pending_q)['total']);

$count_approved_q = pg_query_params($conn, '
    SELECT COUNT(*) as total 
    FROM "Access_Request" ar 
    JOIN "Dataset" d ON ar.dataset_id = d.dataset_id 
    WHERE d.sensitivity = \'Sensitive\' AND ar.request_status = $1
', ['Approved']);
$count_approved = intval(pg_fetch_assoc($count_approved_q)['total']);

$count_rejected_q = pg_query_params($conn, '
    SELECT COUNT(*) as total 
    FROM "Access_Request" ar 
    JOIN "Dataset" d ON ar.dataset_id = d.dataset_id 
    WHERE d.sensitivity = \'Sensitive\' AND ar.request_status = $1
', ['Rejected']);
$count_rejected = intval(pg_fetch_assoc($count_rejected_q)['total']);

$where_clauses = ["d.sensitivity = 'Sensitive'"];
$params        = [];
$param_index   = 1;

if ($filter_status !== 'All') {
    $where_clauses[] = "ar.request_status = \${$param_index}";
    $params[]        = $filter_status;
    $param_index++;
}

if (!empty($search_term)) {
    $where_clauses[] = "(
        LOWER(u.full_name) LIKE LOWER(\${$param_index})
        OR LOWER(d.name) LIKE LOWER(\${$param_index})
        OR LOWER(ar.purpose) LIKE LOWER(\${$param_index})
        OR LOWER(u.team) LIKE LOWER(\${$param_index})
    )";
    $params[] = '%' . $search_term . '%';
    $param_index++;
}

$where_sql = 'WHERE ' . implode(' AND ', $where_clauses);

$count_query  = "
    SELECT COUNT(*) as total 
    FROM \"Access_Request\" ar 
    JOIN \"User\" u ON ar.user_id = u.user_id 
    JOIN \"Dataset\" d ON ar.dataset_id = d.dataset_id 
    {$where_sql}
";
$count_result  = pg_query_params($conn, $count_query, $params);
$total_records = intval(pg_fetch_assoc($count_result)['total']);
$total_pages   = max(1, ceil($total_records / $per_page));

if ($current_page > $total_pages) {
    $current_page = $total_pages;
    $offset = ($current_page - 1) * $per_page;
}

$limit_idx  = $param_index;
$offset_idx = $param_index + 1;

$data_query = "
    SELECT 
        ar.request_id, ar.request_status, ar.access_type, ar.purpose,
        ar.request_date, ar.approved_date, ar.expiry_date, ar.approval_reason,
        u.user_id, u.full_name, u.username, u.team, u.job_type, u.training_completed,
        d.dataset_id, d.name as dataset_name, d.sensitivity
    FROM \"Access_Request\" ar
    JOIN \"User\" u ON ar.user_id = u.user_id
    JOIN \"Dataset\" d ON ar.dataset_id = d.dataset_id
    {$where_sql}
    ORDER BY 
        CASE ar.request_status 
            WHEN 'Pending' THEN 1 
            WHEN 'Approved' THEN 2 
            WHEN 'Rejected' THEN 3 
        END,
        ar.request_date DESC
    LIMIT \${$limit_idx} OFFSET \${$offset_idx}
";

$params_with_pagination   = $params;
$params_with_pagination[] = $per_page;
$params_with_pagination[] = $offset;

$data_result = pg_query_params($conn, $data_query, $params_with_pagination);
$requests    = [];
if ($data_result) {
    while ($row = pg_fetch_assoc($data_result)) {
        $requests[] = $row;
    }
}

function buildURL($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        $params[$k] = $v;
    }
    return '?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/admin_manage_requests.css" />
    <link rel="stylesheet" href="../assets/css/navbar.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <title>Manage Requests — UKHSA Admin</title>
</head>
<body>

    <?php include("navbar.php"); ?>

    <main class="page-main">
        <div class="page-container">

            <div class="page-header">
                <h1>Manage Sensitive Access Requests</h1>
                <p>Review, approve, or reject access requests for sensitive datasets. Non-sensitive requests are auto-approved.</p>
            </div>

            <div class="alert alert-info">
                <span class="material-icons">info</span>
                <span>Only <strong>sensitive dataset</strong> requests require manual review. Non-sensitive dataset requests are automatically approved upon submission.</span>
            </div>

            <?php if (isset($_SESSION['admin_success'])): ?>
            <div class="alert alert-success">
                <span class="material-icons">check_circle</span>
                <span><?php echo htmlspecialchars($_SESSION['admin_success']); unset($_SESSION['admin_success']); ?></span>
            </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['admin_error'])): ?>
            <div class="alert alert-error">
                <span class="material-icons">error</span>
                <span><?php echo htmlspecialchars($_SESSION['admin_error']); unset($_SESSION['admin_error']); ?></span>
            </div>
            <?php endif; ?>

            <div class="filter-tabs">
                <a href="<?php echo buildURL(['status' => 'Pending', 'page' => 1]); ?>" 
                   class="filter-tab <?php echo $filter_status === 'Pending' ? 'active' : ''; ?>">
                    <span class="material-icons">hourglass_empty</span>
                    Pending <span class="tab-count"><?php echo $count_pending; ?></span>
                </a>
                <a href="<?php echo buildURL(['status' => 'Approved', 'page' => 1]); ?>" 
                   class="filter-tab <?php echo $filter_status === 'Approved' ? 'active' : ''; ?>">
                    <span class="material-icons">check_circle</span>
                    Approved <span class="tab-count"><?php echo $count_approved; ?></span>
                </a>
                <a href="<?php echo buildURL(['status' => 'Rejected', 'page' => 1]); ?>" 
                   class="filter-tab <?php echo $filter_status === 'Rejected' ? 'active' : ''; ?>">
                    <span class="material-icons">cancel</span>
                    Rejected <span class="tab-count"><?php echo $count_rejected; ?></span>
                </a>
                <a href="<?php echo buildURL(['status' => 'All', 'page' => 1]); ?>" 
                   class="filter-tab <?php echo $filter_status === 'All' ? 'active' : ''; ?>">
                    <span class="material-icons">list</span>
                    All <span class="tab-count"><?php echo $count_all; ?></span>
                </a>
            </div>

            <div class="search-section">
                <form method="GET" class="search-form">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                    <div class="search-input-group">
                        <input type="text" 
                               name="search" 
                               class="search-input" 
                               placeholder="Search by name, dataset, team, or purpose..." 
                               value="<?php echo htmlspecialchars($search_term); ?>">
                        <button type="submit" class="search-btn">
                            <span class="material-icons">search</span> Search
                        </button>
                    </div>
                </form>
                <?php if (!empty($search_term)): ?>
                <div class="search-info">
                    <span>Showing results for "<strong><?php echo htmlspecialchars($search_term); ?></strong>"</span>
                    <a href="<?php echo buildURL(['search' => '', 'page' => 1]); ?>" class="clear-search">Clear search</a>
                </div>
                <?php endif; ?>
                <div class="records-info">
                    Showing <?php echo $total_records > 0 ? min($offset + 1, $total_records) : 0; ?>–<?php echo min($offset + $per_page, $total_records); ?> of <?php echo $total_records; ?> sensitive requests
                </div>
            </div>

            <div class="table-wrapper">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Requester</th>
                            <th>Dataset</th>
                            <th>Purpose</th>
                            <th>Training</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($requests)): ?>
                        <tr>
                            <td colspan="7" class="empty-row">
                                <span class="material-icons">inbox</span>
                                <?php if ($filter_status === 'Pending'): ?>
                                <p>No pending sensitive requests. All caught up!</p>
                                <?php else: ?>
                                <p>No <?php echo strtolower($filter_status); ?> sensitive requests found.</p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td data-label="Requester">
                                <div class="requester-info">
                                    <strong><?php echo htmlspecialchars($req['full_name']); ?></strong>
                                    <span class="requester-meta">
                                        <?php echo htmlspecialchars($req['team'] ?? 'N/A'); ?> · <?php echo htmlspecialchars($req['job_type']); ?>
                                    </span>
                                </div>
                            </td>

                            <td data-label="Dataset">
                                <div class="dataset-info">
                                    <span class="dataset-name"><?php echo htmlspecialchars($req['dataset_name']); ?></span>
                                    <span class="sensitivity-badge sensitive">
                                        <span class="material-icons">lock</span> Sensitive
                                    </span>
                                </div>
                            </td>

                            <td data-label="Purpose">
                                <div class="purpose-text" title="<?php echo htmlspecialchars($req['purpose']); ?>">
                                    <?php echo htmlspecialchars(substr($req['purpose'], 0, 80)); ?>
                                    <?php if (strlen($req['purpose']) > 80) echo '...'; ?>
                                </div>
                            </td>

                            <td data-label="Training">
                                <?php if ($req['training_completed'] === 't'): ?>
                                <span class="training-badge completed">
                                    <span class="material-icons">check_circle</span> Completed
                                </span>
                                <?php else: ?>
                                <span class="training-badge incomplete">
                                    <span class="material-icons">cancel</span> Incomplete
                                </span>
                                <?php endif; ?>
                            </td>

                            <td data-label="Date" class="date-cell">
                                <?php echo date('d M Y', strtotime($req['request_date'])); ?>
                                <span class="time-sub"><?php echo date('H:i', strtotime($req['request_date'])); ?></span>
                            </td>

                            <td data-label="Status">
                                <span class="status-badge <?php echo strtolower($req['request_status']); ?>">
                                    <?php echo $req['request_status']; ?>
                                </span>
                            </td>

                            <td data-label="Actions">
                                <div class="action-buttons">
                                    <?php if ($req['request_status'] === 'Pending'): ?>

                                    <form action="../actions/admin_approve_action.php" method="POST" class="inline-form">
                                        <input type="hidden" name="request_id" value="<?php echo $req['request_id']; ?>">
                                        <button type="submit" name="approve" class="btn-action btn-approve"
                                                onclick="return confirm('Approve sensitive access for <?php echo htmlspecialchars(addslashes($req['full_name'])); ?> to <?php echo htmlspecialchars(addslashes($req['dataset_name'])); ?>?')">
                                            <span class="material-icons">check</span> Approve
                                        </button>
                                    </form>

                                    <button type="button" class="btn-action btn-reject"
                                            onclick="openRejectModal(<?php echo $req['request_id']; ?>, '<?php echo htmlspecialchars(addslashes($req['full_name'])); ?>', '<?php echo htmlspecialchars(addslashes($req['dataset_name'])); ?>')">
                                        <span class="material-icons">close</span> Reject
                                    </button>

                                    <?php elseif ($req['request_status'] === 'Approved'): ?>
                                    <div class="action-info approved-info">
                                        <span class="material-icons">event</span>
                                        <div>
                                            <span class="info-label">Expires</span>
                                            <span class="info-value"><?php echo $req['expiry_date'] ? date('d M Y', strtotime($req['expiry_date'])) : 'N/A'; ?></span>
                                        </div>
                                    </div>

                                    <?php elseif ($req['request_status'] === 'Rejected'): ?>
                                    <div class="action-info rejected-info" title="<?php echo htmlspecialchars($req['approval_reason'] ?? ''); ?>">
                                        <span class="material-icons">info</span>
                                        <div>
                                            <span class="info-label">Reason</span>
                                            <span class="info-value">
                                                <?php echo htmlspecialchars(substr($req['approval_reason'] ?? 'No reason', 0, 40)); ?>
                                                <?php if (strlen($req['approval_reason'] ?? '') > 40) echo '...'; ?>
                                            </span>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($current_page > 1): ?>
                <a href="<?php echo buildURL(['page' => $current_page - 1]); ?>" class="page-btn">
                    <span class="material-icons">chevron_left</span> Previous
                </a>
                <?php endif; ?>

                <div class="page-numbers">
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page   = min($total_pages, $current_page + 2);

                    if ($start_page > 1): ?>
                        <a href="<?php echo buildURL(['page' => 1]); ?>" class="page-num">1</a>
                        <?php if ($start_page > 2): ?><span class="page-dots">...</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="<?php echo buildURL(['page' => $i]); ?>" 
                       class="page-num <?php echo $i === $current_page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?><span class="page-dots">...</span><?php endif; ?>
                        <a href="<?php echo buildURL(['page' => $total_pages]); ?>" class="page-num"><?php echo $total_pages; ?></a>
                    <?php endif; ?>
                </div>

                <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo buildURL(['page' => $current_page + 1]); ?>" class="page-btn">
                    Next <span class="material-icons">chevron_right</span>
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <div id="rejectModal" class="modal-overlay" style="display:none;">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-header-content">
                    <span class="material-icons modal-header-icon">block</span>
                    <h2>Reject Sensitive Access Request</h2>
                </div>
                <button type="button" class="modal-close" onclick="closeRejectModal()">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form action="../actions/admin_reject_action.php" method="POST" id="rejectForm">
                <input type="hidden" name="request_id" id="modal_request_id">
                <div class="modal-body">

                    <div class="modal-warning">
                        <span class="material-icons">warning_amber</span>
                        <span>This action cannot be undone. The requester will be notified of the rejection.</span>
                    </div>

                    <div class="form-group">
                        <label for="modal_requester">Requester</label>
                        <input type="text" id="modal_requester" class="form-input" readonly>
                    </div>

                    <div class="form-group">
                        <label for="modal_dataset">Dataset (Sensitive)</label>
                        <input type="text" id="modal_dataset" class="form-input" readonly>
                    </div>

                    <div class="form-group">
                        <label for="modal_reason">Reason for Rejection <span class="required">*</span></label>
                        <textarea id="modal_reason" 
                                  name="rejection_reason" 
                                  class="form-textarea" 
                                  rows="4" 
                                  placeholder="Please provide a clear reason for rejecting this sensitive data request..."
                                  required></textarea>
                        <span class="form-hint">This reason will be visible to the requester. Minimum 10 characters.</span>
                        <span class="char-count" id="charCount">0 characters</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeRejectModal()">Cancel</button>
                    <button type="submit" name="reject" class="btn-confirm-reject" id="confirmRejectBtn">
                        <span class="material-icons">block</span> Confirm Rejection
                    </button>
                </div>
            </form>
        </div>
    </div>

        <script>
        function openRejectModal(requestId, requesterName, datasetName) {
            document.getElementById('modal_request_id').value = requestId;
            document.getElementById('modal_requester').value = requesterName;
            document.getElementById('modal_dataset').value = datasetName;
            document.getElementById('modal_reason').value = '';
            document.getElementById('charCount').textContent = '0 characters';
            document.getElementById('rejectModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';

            setTimeout(function() {
                document.getElementById('modal_reason').focus();
            }, 100);
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        document.getElementById('rejectModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeRejectModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeRejectModal();
            }
        });

        document.getElementById('modal_reason').addEventListener('input', function() {
            var len = this.value.trim().length;
            document.getElementById('charCount').textContent = len + ' characters';
            document.getElementById('charCount').style.color = len < 10 ? '#d4351c' : '#00703c';
        });

        var rejectSubmitted = false;
        document.getElementById('rejectForm').addEventListener('submit', function(e) {
            var reason = document.getElementById('modal_reason').value.trim();

            if (reason.length < 10) {
                e.preventDefault();
                alert('Please provide a more detailed reason (at least 10 characters).');
                document.getElementById('modal_reason').focus();
                return false;
            }

            if (rejectSubmitted) {
                e.preventDefault();
                return false;
            }
            rejectSubmitted = true;

            var btn = document.getElementById('confirmRejectBtn');
            btn.innerHTML = '<span class="material-icons">hourglass_empty</span> Processing...';
            btn.style.backgroundColor = '#b1b4b6';
            btn.style.cursor = 'not-allowed';
        });
    </script>

</body>
</html>