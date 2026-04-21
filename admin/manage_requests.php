<?php
session_start();
require_once '../config/supabase.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
if ($_SESSION['role'] !== 'Administrator' && $_SESSION['role'] !== 'Approver') {
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

$sensitive_datasets = supabaseRequest(
    'Dataset?select=dataset_id,name,sensitivity&sensitivity=eq.Sensitive'
);

$sensitive_dataset_ids = [];
$dataset_map           = [];
if (!empty($sensitive_datasets) && !isset($sensitive_datasets['error'])) {
    foreach ($sensitive_datasets as $ds) {
        $sensitive_dataset_ids[] = $ds['dataset_id'];
        $dataset_map[$ds['dataset_id']] = $ds;
    }
}

$all_sensitive_requests = [];
if (!empty($sensitive_dataset_ids)) {
    $ids_str = implode(',', $sensitive_dataset_ids);
    $all_sensitive_requests = supabaseRequest(
        'Access_Request?select=request_id,request_status,access_type,purpose,request_date,approved_date,expiry_date,approval_reason,user_id,dataset_id' .
            '&dataset_id=in.(' . $ids_str . ')'
    );
    if (isset($all_sensitive_requests['error'])) {
        $all_sensitive_requests = [];
    }
}

$count_all      = count($all_sensitive_requests);
$count_pending  = count(array_filter($all_sensitive_requests, fn($r) => $r['request_status'] === 'Pending'));
$count_approved = count(array_filter($all_sensitive_requests, fn($r) => $r['request_status'] === 'Approved'));
$count_rejected = count(array_filter($all_sensitive_requests, fn($r) => $r['request_status'] === 'Rejected'));

$all_users  = supabaseRequest('User?select=user_id,full_name,username,team,job_type,training_completed');
$user_map   = [];
if (!empty($all_users) && !isset($all_users['error'])) {
    foreach ($all_users as $u) {
        $user_map[$u['user_id']] = $u;
    }
}

$all_rows = [];
foreach ($all_sensitive_requests as $req) {
    $user    = $user_map[$req['user_id']]       ?? null;
    $dataset = $dataset_map[$req['dataset_id']] ?? null;

    if (!$user || !$dataset) continue;

    $row = array_merge($req, [
        'full_name'        => $user['full_name'],
        'username'         => $user['username'],
        'team'             => $user['team'],
        'job_type'         => $user['job_type'],
        'training_completed' => $user['training_completed'],
        'dataset_name'     => $dataset['name'],
        'sensitivity'      => $dataset['sensitivity'],
    ]);

    $all_rows[] = $row;
}

if ($filter_status !== 'All') {
    $all_rows = array_filter($all_rows, fn($r) => $r['request_status'] === $filter_status);
    $all_rows = array_values($all_rows);
}

if (!empty($search_term)) {
    $search_lower = strtolower($search_term);
    $all_rows = array_filter($all_rows, function ($r) use ($search_lower) {
        return str_contains(strtolower($r['full_name']    ?? ''), $search_lower)
            || str_contains(strtolower($r['dataset_name'] ?? ''), $search_lower)
            || str_contains(strtolower($r['purpose']      ?? ''), $search_lower)
            || str_contains(strtolower($r['team']         ?? ''), $search_lower);
    });
    $all_rows = array_values($all_rows);
}

usort($all_rows, function ($a, $b) {
    $order = ['Pending' => 1, 'Approved' => 2, 'Rejected' => 3];
    $a_order = $order[$a['request_status']] ?? 4;
    $b_order = $order[$b['request_status']] ?? 4;

    if ($a_order !== $b_order) return $a_order - $b_order;

    return strtotime($b['request_date']) - strtotime($a['request_date']);
});

$total_records = count($all_rows);
$total_pages   = max(1, ceil($total_records / $per_page));
$current_page  = min($current_page, $total_pages);
$offset        = ($current_page - 1) * $per_page;
$requests      = array_slice($all_rows, $offset, $per_page);

function buildURL($overrides = [])
{
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
                    <span><?php echo htmlspecialchars($_SESSION['admin_success']);
                            unset($_SESSION['admin_success']); ?></span>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['admin_error'])): ?>
                <div class="alert alert-error">
                    <span class="material-icons">error</span>
                    <span><?php echo htmlspecialchars($_SESSION['admin_error']);
                            unset($_SESSION['admin_error']); ?></span>
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
                                        <?php if ($req['training_completed'] === true || $req['training_completed'] === 't'): ?>
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
                                                <div class="approved-actions">
                                                    <div class="action-info approved-info">
                                                        <span class="material-icons">event</span>
                                                        <div>
                                                            <span class="info-label">Expires</span>
                                                            <span class="info-value">
                                                                <?php echo $req['expiry_date'] ? date('d M Y', strtotime($req['expiry_date'])) : 'N/A'; ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <button
                                                        type="button"
                                                        class="btn-action btn-revoke"
                                                        onclick="openRevokeModal(
                                                            <?php echo $req['request_id']; ?>,
                                                            '<?php echo htmlspecialchars(addslashes($req['full_name'])); ?>',
                                                            '<?php echo htmlspecialchars(addslashes($req['dataset_name'])); ?>'
                                                        )">
                                                        <span class="material-icons">block</span> Revoke
                                                    </button>
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
                            <?php if ($start_page > 2): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="<?php echo buildURL(['page' => $i]); ?>"
                                class="page-num <?php echo $i === $current_page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="page-dots">...</span>
                            <?php endif; ?>
                            <a href="<?php echo buildURL(['page' => $total_pages]); ?>" class="page-num">
                                <?php echo $total_pages; ?>
                            </a>
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

    <div id="revokeModal" class="modal-overlay" style="display:none;">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-header-content">
                    <span class="material-icons modal-header-icon">block</span>
                    <h2>Revoke Dataset Access</h2>
                </div>
                <button type="button" class="modal-close" onclick="closeRevokeModal()">
                    <span class="material-icons">close</span>
                </button>
            </div>
            <form action="../actions/admin_revoke_action.php" method="POST" id="revokeForm">
                <input type="hidden" name="request_id" id="revoke_request_id">
                <div class="modal-body">
                    <div class="modal-warning">
                        <span class="material-icons">warning_amber</span>
                        <span>This will immediately revoke access. The user will be notified by email.</span>
                    </div>
                    <div class="form-group">
                        <label for="revoke_requester">Requester</label>
                        <input type="text" id="revoke_requester" class="form-input" readonly>
                    </div>
                    <div class="form-group">
                        <label for="revoke_dataset">Dataset</label>
                        <input type="text" id="revoke_dataset" class="form-input" readonly>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeRevokeModal()">Cancel</button>
                    <button type="submit" name="revoke" class="btn-confirm-reject" id="confirmRevokeBtn">
                        <span class="material-icons">block</span> Confirm Revoke
                    </button>
                </div>
            </form>
        </div>
    </div>
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
            if (e.target === this) closeRejectModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeRejectModal();
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

        // Revoke Modal
        function openRevokeModal(requestId, requesterName, datasetName) {
            document.getElementById('revoke_request_id').value = requestId;
            document.getElementById('revoke_requester').value = requesterName;
            document.getElementById('revoke_dataset').value = datasetName;
            document.getElementById('revokeModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeRevokeModal() {
            document.getElementById('revokeModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }

        document.getElementById('revokeModal').addEventListener('click', function(e) {
            if (e.target === this) closeRevokeModal();
        });

        var revokeSubmitted = false;
        document.getElementById('revokeForm').addEventListener('submit', function(e) {
            if (revokeSubmitted) {
                e.preventDefault();
                return false;
            }
            revokeSubmitted = true;
            var btn = document.getElementById('confirmRevokeBtn');
            btn.innerHTML = '<span class="material-icons">hourglass_empty</span> Processing...';
            btn.style.backgroundColor = '#b1b4b6';
            btn.style.cursor = 'not-allowed';
        });
    </script>

</body>

</html>