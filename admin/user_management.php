<?php
session_start();
require_once '../config/supabase.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
if ($_SESSION['role'] !== 'Administrator') {
    header('Location: ../admin/dashboard.php');
    exit();
}

$success = $_SESSION['um_success'] ?? '';
$error   = $_SESSION['um_error']   ?? '';
unset($_SESSION['um_success'], $_SESSION['um_error']);

$search       = trim($_GET['search'] ?? '');
$current_page = max(1, intval($_GET['page'] ?? 1));
$per_page     = 5;
$offset       = ($current_page - 1) * $per_page;

$all_users = supabaseRequest(
    'User?select=user_id,full_name,username,email,team,job_type,system_role,training_completed,training_expiry&order=full_name.asc'
);

if (isset($all_users['error']) || !is_array($all_users)) {
    $all_users = [];
}

if (!empty($search)) {
    $search_lower = strtolower($search);
    $all_users = array_filter($all_users, function ($u) use ($search_lower) {
        return str_contains(strtolower($u['full_name']   ?? ''), $search_lower)
            || str_contains(strtolower($u['username']    ?? ''), $search_lower)
            || str_contains(strtolower($u['email']       ?? ''), $search_lower)
            || str_contains(strtolower($u['team']        ?? ''), $search_lower)
            || str_contains(strtolower($u['system_role'] ?? ''), $search_lower);
    });
    $all_users = array_values($all_users);
}

$total_records = count($all_users);
$total_pages   = max(1, ceil($total_records / $per_page));
$current_page  = min($current_page, $total_pages);
$offset        = ($current_page - 1) * $per_page;
$users         = array_slice($all_users, $offset, $per_page);

$search_query = !empty($search) ? '&search=' . urlencode($search) : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/user_management.css" />
    <link rel="stylesheet" href="../assets/css/navbar.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <title>User Management — UKHSA Admin</title>
</head>

<body>
    <?php include("navbar.php"); ?>

    <div class="page-container">

        <div class="header-content">
            <div class="page-header">
                <h1>User Management</h1>
                <p>Manage user roles and access levels</p>
            </div>
        </div>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <span class="material-icons">check_circle</span>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">
                <span class="material-icons">error</span>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <div class="search-section">
            <form method="GET" action="user_management.php" class="search-form">
                <div class="search-wrapper">
                    <label for="search" class="search-label">Search users</label>
                    <div class="search-input-group">
                        <input
                            type="text"
                            id="search"
                            name="search"
                            class="search-input"
                            placeholder="Search by name, username, email, team or role..."
                            value="<?php echo htmlspecialchars($search); ?>">
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
                    <a href="user_management.php" class="clear-search">Clear search</a>
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
                <strong><?php echo $total_records; ?></strong> users
            </span>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="data-table-left-heading">Full Name</th>
                        <th class="data-table-left-heading">Username</th>
                        <th class="data-table-left-heading">Email</th>
                        <th class="data-table-left-heading">Team</th>
                        <th class="data-table-left-heading">Job Type</th>
                        <th class="data-table-left-heading">Role</th>
                        <th class="data-table-left-heading">Training</th>
                        <th class="data-table-center-heading">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="8" class="empty-row">
                                <?php if (!empty($search)): ?>
                                    No users found matching "<?php echo htmlspecialchars($search); ?>".
                                <?php else: ?>
                                    No users available.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="dataset-name">
                                    <?php echo htmlspecialchars($user['full_name']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </td>
                                <td class="dataset-desc">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($user['team'] ?? 'N/A'); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($user['job_type'] ?? 'N/A'); ?>
                                </td>
                                <td>
                                    <span class="role-badge <?php echo strtolower($user['system_role']); ?>">
                                        <?php echo htmlspecialchars($user['system_role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['training_completed'] === true || $user['training_completed'] === 't'): ?>
                                        <span class="training-badge completed">
                                            <span class="material-icons">check_circle</span> Completed
                                        </span>
                                    <?php else: ?>
                                        <span class="training-badge incomplete">
                                            <span class="material-icons">cancel</span> Incomplete
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:center;">
                                    <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                        <button
                                            type="button"
                                            class="btn-update"
                                            onclick="openEditModal(
                                                '<?php echo $user['user_id']; ?>',
                                                '<?php echo htmlspecialchars(addslashes($user['full_name'])); ?>',
                                                '<?php echo htmlspecialchars($user['system_role']); ?>',
                                                '<?php echo htmlspecialchars(addslashes($user['team'] ?? '')); ?>',
                                                '<?php echo htmlspecialchars($user['job_type'] ?? ''); ?>',
                                                <?php echo ($user['training_completed'] === true || $user['training_completed'] === 't') ? 'true' : 'false'; ?>,
                                                '<?php echo !empty($user['training_expiry']) ? substr($user['training_expiry'], 0, 10) : ''; ?>'
                                            )">
                                            Edit
                                        </button>
                                    <?php else: ?>
                                        <span class="self-badge">You</span>
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

    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Edit User</h2>
                <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <form method="POST" action="../actions/update_user_role.php">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="edit_user_id">

                    <div class="form-group">
                        <label for="edit_full_name">Full Name</label>
                        <input type="text" id="edit_full_name" class="form-input" readonly>
                    </div>

                    <div class="form-group">
                        <label for="edit_role">Role <span class="required">*</span></label>
                        <select id="edit_role" name="system_role" required>
                            <option value="User">User</option>
                            <option value="Approver">Approver</option>
                            <option value="Administrator">Administrator</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_team">Team <span class="required">*</span></label>
                        <input type="text" id="edit_team" name="team" required>
                    </div>

                    <div class="form-group">
                        <label for="edit_job_type">Job Type <span class="required">*</span></label>
                        <select id="edit_job_type" name="job_type" required>
                            <option value="Researcher">Researcher</option>
                            <option value="Staff">Staff</option>
                            <option value="Intern">Intern</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_training">Training Completed <span class="required">*</span></label>
                        <select id="edit_training" name="training_completed" required>
                            <option value="true">Yes</option>
                            <option value="false">No</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_training_expiry">Training Expiry Date</label>
                        <input type="date" id="edit_training_expiry" name="training_expiry">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                    <button type="submit" name="submit_edit" class="btn-submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditModal(userId, fullName, role, team, jobType, trainingCompleted, trainingExpiry) {
            document.getElementById('edit_user_id').value = userId;
            document.getElementById('edit_full_name').value = fullName;
            document.getElementById('edit_role').value = role;
            document.getElementById('edit_team').value = team;
            document.getElementById('edit_job_type').value = jobType;
            document.getElementById('edit_training').value = trainingCompleted ? 'true' : 'false';
            document.getElementById('edit_training_expiry').value = trainingExpiry;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
        }

        document.getElementById('editModal').addEventListener('click', function(e) {
            if (e.target === this) closeEditModal();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEditModal();
        });
    </script>

</body>

</html>