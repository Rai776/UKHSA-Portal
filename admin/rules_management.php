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

$search       = trim($_GET['search'] ?? '');
$current_page = max(1, intval($_GET['page'] ?? 1));
$per_page     = 5;
$offset       = ($current_page - 1) * $per_page;

$all_datasets = supabaseRequest(
    'Dataset?select=dataset_id,name,description,category,sensitivity,active&order=name.asc'
);

if (isset($all_datasets['error']) || !is_array($all_datasets)) {
    $all_datasets = [];
}

if (!empty($search)) {
    $search_lower = strtolower($search);
    $all_datasets = array_filter($all_datasets, function ($ds) use ($search_lower) {
        return str_contains(strtolower($ds['name']        ?? ''), $search_lower)
            || str_contains(strtolower($ds['description'] ?? ''), $search_lower)
            || str_contains(strtolower($ds['category']    ?? ''), $search_lower)
            || str_contains(strtolower($ds['sensitivity'] ?? ''), $search_lower);
    });
    $all_datasets = array_values($all_datasets);
}

$total_records = count($all_datasets);
$total_pages   = max(1, ceil($total_records / $per_page));
$current_page  = min($current_page, $total_pages);
$offset        = ($current_page - 1) * $per_page;
$datasets      = array_slice($all_datasets, $offset, $per_page);

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
    <?php include("navbar.php"); ?>

    <div class="page-container">
        <div class="header-content">
            <div class="page-header">
                <h1>Dataset Controller</h1>
                <p>Browse available datasets and create, update or delete datasets</p>
            </div>
            <button class="btn-create" onclick="openCreateModal()">
                Create Dataset
            </button>
        </div>

        <div class="search-section">
            <form method="GET" action="rules_management.php" class="search-form">
                <div class="search-wrapper">
                    <label for="search" class="search-label">Search datasets</label>
                    <div class="search-input-group">
                        <input
                            type="text"
                            id="search"
                            name="search"
                            class="search-input"
                            placeholder="Search by name, description, category or sensitivity..."
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
                    <a href="rules_management.php" class="clear-search">Clear search</a>
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
                        <th class="data-table-left-heading">Dataset Name</th>
                        <th class="data-table-left-heading">Description</th>
                        <th class="data-table-left-heading">Category</th>
                        <th class="data-table-left-heading">Sensitivity</th>
                        <th class="data-table-left-heading">Active?</th>
                        <th colspan="2" class="data-table-center-heading">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($datasets)): ?>
                        <tr>
                            <td colspan="7" class="empty-row">
                                <?php if (!empty($search)): ?>
                                    No datasets found matching "<?php echo htmlspecialchars($search); ?>".
                                <?php else: ?>
                                    No datasets available.
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($datasets as $dataset): ?>
                            <?php $is_active = ($dataset['active'] === true || $dataset['active'] === 't'); ?>
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
                                    <?php if ($is_active): ?>
                                        <span class="activity-badge active">True</span>
                                    <?php else: ?>
                                        <span class="activity-badge inactive">False</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn-update"
                                        onclick="openUpdateModal(
                                    <?php echo $dataset['dataset_id']; ?>,
                                    '<?php echo htmlspecialchars(addslashes($dataset['name'])); ?>',
                                    '<?php echo htmlspecialchars($dataset['sensitivity']); ?>',
                                    '<?php echo htmlspecialchars(addslashes($dataset['description'])); ?>',
                                    '<?php echo htmlspecialchars($dataset['category']); ?>',
                                    '<?php echo $is_active ? 'true' : 'false'; ?>'
                                )">Update</button>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="btn-delete"
                                        onclick="openDeleteModal(
                                    <?php echo $dataset['dataset_id']; ?>,
                                    '<?php echo htmlspecialchars(addslashes($dataset['name'])); ?>',
                                    '<?php echo htmlspecialchars($dataset['sensitivity']); ?>',
                                    '<?php echo htmlspecialchars(addslashes($dataset['description'])); ?>',
                                    '<?php echo htmlspecialchars($dataset['category']); ?>',
                                    '<?php echo $is_active ? 'true' : 'false'; ?>'
                                )">Delete</button>
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

    <div class="modal-overlay" id="updateModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Update Details</h2>
                <button type="button" class="modal-close" onclick="closeUpdateModal()">&times;</button>
            </div>
            <form method="POST" action="../actions/update_dataset.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="modal_dataset_name">Dataset Name <span class="required">*</span></label>
                        <input type="text" id="modal_dataset_name" name="name" required>
                        <input type="hidden" name="dataset_id" id="modal_dataset_id">
                    </div>
                    <div class="form-group">
                        <label for="modal_description">Description <span class="required">*</span></label>
                        <textarea id="modal_description" name="description" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="modal_category">Category <span class="required">*</span></label>
                        <input type="text" id="modal_category" name="category" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_sensitivity">Sensitivity Level <span class="required">*</span></label>
                        <select id="modal_sensitivity" name="sensitivity" required>
                            <option value="Sensitive">Sensitive</option>
                            <option value="Non-sensitive">Non-sensitive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="modal_active">Active? <span class="required">*</span></label>
                        <select id="modal_active" name="active" required>
                            <option value="true">True</option>
                            <option value="false">False</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeUpdateModal()">Cancel</button>
                    <button type="submit" name="submit_request" class="btn-submit">Update</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="deleteModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Delete Dataset</h2>
                <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <form method="POST" action="../actions/delete_dataset.php">
                <div class="modal-body">
                    <p>Are you sure you want to delete this dataset?</p>
                    <input type="hidden" name="dataset_id" id="delete_dataset_id">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn-delete">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal-overlay" id="createModal">
        <div class="modal">
            <div class="modal-header">
                <h2>Create Dataset</h2>
                <button type="button" class="modal-close" onclick="closeCreateModal()">&times;</button>
            </div>
            <form method="POST" action="../actions/create_dataset.php">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="create_dataset_name">Dataset Name <span class="required">*</span></label>
                        <input type="text" id="create_dataset_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="create_description">Description <span class="required">*</span></label>
                        <textarea id="create_description" name="description" rows="4" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="create_category">Category <span class="required">*</span></label>
                        <input type="text" id="create_category" name="category" required>
                    </div>
                    <div class="form-group">
                        <label for="create_sensitivity">Sensitivity Level <span class="required">*</span></label>
                        <select id="create_sensitivity" name="sensitivity" required>
                            <option value="Sensitive">Sensitive</option>
                            <option value="Non-sensitive">Non-sensitive</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="create_active">Active? <span class="required">*</span></label>
                        <select id="create_active" name="active" required>
                            <option value="true">True</option>
                            <option value="false">False</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeCreateModal()">Cancel</button>
                    <button type="submit" name="submit_request" class="btn-submit">Create</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openUpdateModal(datasetId, datasetName, sensitivity, description, category, active) {
            document.getElementById('modal_dataset_id').value = datasetId;
            document.getElementById('modal_dataset_name').value = datasetName;
            document.getElementById('modal_sensitivity').value = sensitivity;
            document.getElementById('modal_description').value = description;
            document.getElementById('modal_category').value = category;
            document.getElementById('modal_active').value = active;
            document.getElementById('updateModal').classList.add('active');
        }

        function openDeleteModal(datasetId) {
            document.getElementById('delete_dataset_id').value = datasetId;
            document.getElementById('deleteModal').classList.add('active');
        }

        function openCreateModal() {
            document.getElementById('create_dataset_name').value = '';
            document.getElementById('create_description').value = '';
            document.getElementById('create_category').value = '';
            document.getElementById('create_sensitivity').value = 'Sensitive';
            document.getElementById('create_active').value = 'true';
            document.getElementById('createModal').classList.add('active');
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').classList.remove('active');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        function closeCreateModal() {
            document.getElementById('createModal').classList.remove('active');
        }

        document.getElementById('updateModal').addEventListener('click', function(e) {
            if (e.target === this) closeUpdateModal();
        });

        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });

        document.getElementById('createModal').addEventListener('click', function(e) {
            if (e.target === this) closeCreateModal();
        });
    </script>

</body>

</html>