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

$hour = intval(date('H'));
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$total_users_q = pg_query($conn, 'SELECT COUNT(*) as total FROM "User"');
$total_users = intval(pg_fetch_assoc($total_users_q)['total']);

$researchers_q = pg_query_params(
    $conn,
    'SELECT COUNT(*) as total FROM "User" WHERE job_type = $1',
    ['Researcher']
);
$researchers = intval(pg_fetch_assoc($researchers_q)['total']);

$staff_q = pg_query_params(
    $conn,
    'SELECT COUNT(*) as total FROM "User" WHERE job_type = $1',
    ['Staff']
);
$staff_count = intval(pg_fetch_assoc($staff_q)['total']);

$interns_q = pg_query_params(
    $conn,
    'SELECT COUNT(*) as total FROM "User" WHERE job_type = $1',
    ['Intern']
);
$interns = intval(pg_fetch_assoc($interns_q)['total']);

$trained_q = pg_query_params(
    $conn,
    'SELECT COUNT(*) as total FROM "User" WHERE training_completed = $1',
    ['t']
);
$trained = intval(pg_fetch_assoc($trained_q)['total']);
$untrained = $total_users - $trained;

$total_datasets_q = pg_query($conn, 'SELECT COUNT(*) as total FROM "Dataset"');
$total_datasets = intval(pg_fetch_assoc($total_datasets_q)['total']);

$sensitive_q = pg_query_params(
    $conn,
    'SELECT COUNT(*) as total FROM "Dataset" WHERE sensitivity = $1',
    ['Sensitive']
);
$sensitive_datasets = intval(pg_fetch_assoc($sensitive_q)['total']);
$non_sensitive_datasets = $total_datasets - $sensitive_datasets;

$total_requests_q = pg_query($conn, 'SELECT COUNT(*) as total FROM "Access_Request"');
$total_requests = intval(pg_fetch_assoc($total_requests_q)['total']);

$pending_q = pg_query_params(
    $conn,
    'SELECT COUNT(*) as total FROM "Access_Request" WHERE request_status = $1',
    ['Pending']
);
$pending_requests = intval(pg_fetch_assoc($pending_q)['total']);

$approved_q = pg_query_params(
    $conn,
    'SELECT COUNT(*) as total FROM "Access_Request" WHERE request_status = $1',
    ['Approved']
);
$approved_requests = intval(pg_fetch_assoc($approved_q)['total']);

$rejected_q = pg_query_params(
    $conn,
    'SELECT COUNT(*) as total FROM "Access_Request" WHERE request_status = $1',
    ['Rejected']
);
$rejected_requests = intval(pg_fetch_assoc($rejected_q)['total']);

$monthly_query = "
    SELECT 
        TO_CHAR(request_date, 'Mon YYYY') as month_label,
        TO_CHAR(request_date, 'YYYY-MM') as month_sort,
        COUNT(*) as total,
        SUM(CASE WHEN request_status = 'Approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN request_status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN request_status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM \"Access_Request\"
    WHERE request_date >= NOW() - INTERVAL '6 months'
    GROUP BY month_label, month_sort
    ORDER BY month_sort ASC
";
$monthly_result = pg_query($conn, $monthly_query);
$chart_labels = [];
$chart_approved = [];
$chart_pending = [];
$chart_rejected = [];
if ($monthly_result) {
    while ($row = pg_fetch_assoc($monthly_result)) {
        $chart_labels[]   = $row['month_label'];
        $chart_approved[] = intval($row['approved']);
        $chart_pending[]  = intval($row['pending']);
        $chart_rejected[] = intval($row['rejected']);
    }
}

$top_datasets_query = '
    SELECT d.name, d.sensitivity, COUNT(ar.request_id) as request_count
    FROM "Access_Request" ar
    JOIN "Dataset" d ON ar.dataset_id = d.dataset_id
    GROUP BY d.name, d.sensitivity
    ORDER BY request_count DESC
    LIMIT 5
';
$top_datasets_result = pg_query($conn, $top_datasets_query);
$top_ds_labels = [];
$top_ds_counts = [];
$top_ds_colors = [];
if ($top_datasets_result) {
    while ($row = pg_fetch_assoc($top_datasets_result)) {
        $top_ds_labels[] = $row['name'];
        $top_ds_counts[] = intval($row['request_count']);
        $top_ds_colors[] = $row['sensitivity'] === 'Sensitive' ? '#d4351c' : '#00703c';
    }
}

$team_query = '
    SELECT u.team, COUNT(ar.request_id) as request_count
    FROM "Access_Request" ar
    JOIN "User" u ON ar.user_id = u.user_id
    GROUP BY u.team
    ORDER BY request_count DESC
';
$team_result = pg_query($conn, $team_query);
$team_labels = [];
$team_counts = [];
if ($team_result) {
    while ($row = pg_fetch_assoc($team_result)) {
        $team_labels[] = $row['team'] ?? 'Unknown';
        $team_counts[] = intval($row['request_count']);
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/admin_dashboard.css" />
    <link rel="stylesheet" href="../assets/css/navbar.css" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/icon?family=Material+Icons">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <title>Admin Dashboard — UKHSA Data Governance Portal</title>
</head>

<body>

    <?php include("navbar.php"); ?>

    <main class="dashboard-main">
        <div class="dashboard-container">

            <div class="welcome-card">
                <div class="welcome-text">
                    <h1><?php echo $greeting; ?>, <?php echo htmlspecialchars($_SESSION['full_name']); ?></h1>
                    <p>Welcome to the <strong>UKHSA Data Governance Admin Portal</strong>. Monitor access requests, manage users, and review system activity.</p>
                </div>
                <div class="welcome-details">
                    <span class="detail-item"><strong>Role:</strong> <?php echo htmlspecialchars($_SESSION['role']); ?></span>
                    <span class="detail-item"><strong>Team:</strong> <?php echo htmlspecialchars($_SESSION['team'] ?? 'N/A'); ?></span>
                    <span class="detail-item"><strong>Date:</strong> <?php echo date('d M Y, H:i'); ?></span>
                </div>
            </div>

            <?php if ($pending_requests > 0): ?>
                <div class="banner banner-warning">
                    <span class="material-icons">pending_actions</span>
                    <div>
                        <strong><?php echo $pending_requests; ?> Pending Request<?php echo $pending_requests !== 1 ? 's' : ''; ?></strong>
                        <p>Access requests are awaiting your review. <a href="manage_requests.php">Review now →</a></p>
                    </div>
                </div>
            <?php endif; ?>

            <div class="section-header">
                <h2>System Overview</h2>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon blue"><span class="material-icons">people</span></div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $total_users; ?></span>
                        <span class="stat-label">Total Users</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><span class="material-icons">storage</span></div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $total_datasets; ?></span>
                        <span class="stat-label">Datasets</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon yellow"><span class="material-icons">hourglass_empty</span></div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $pending_requests; ?></span>
                        <span class="stat-label">Pending</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green-dark"><span class="material-icons">check_circle</span></div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $approved_requests; ?></span>
                        <span class="stat-label">Approved</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon red"><span class="material-icons">cancel</span></div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $rejected_requests; ?></span>
                        <span class="stat-label">Rejected</span>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon purple"><span class="material-icons">assignment</span></div>
                    <div class="stat-info">
                        <span class="stat-number"><?php echo $total_requests; ?></span>
                        <span class="stat-label">Total Requests</span>
                    </div>
                </div>
            </div>
            <div class="section-header">
                <h2>Analytics & Reporting</h2>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <h3>Requests Over Time</h3>
                    <p class="chart-desc">Monthly breakdown of access requests by status</p>
                    <div class="chart-wrapper">
                        <canvas id="requestsOverTimeChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Request Status</h3>
                    <p class="chart-desc">Overall distribution of request outcomes</p>
                    <div class="chart-wrapper">
                        <canvas id="statusPieChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <h3>Top Requested Datasets</h3>
                    <p class="chart-desc">Most popular datasets by number of requests</p>
                    <div class="chart-wrapper">
                        <canvas id="topDatasetsChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Requests by Team</h3>
                    <p class="chart-desc">Which teams are requesting the most access</p>
                    <div class="chart-wrapper">
                        <canvas id="teamChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="charts-grid">
                <div class="chart-card">
                    <h3>Dataset Sensitivity</h3>
                    <p class="chart-desc">Sensitive vs Non-sensitive datasets</p>
                    <div class="chart-wrapper">
                        <canvas id="sensitivityChart"></canvas>
                    </div>
                </div>

                <div class="chart-card">
                    <h3>Users by Job Type</h3>
                    <p class="chart-desc">Distribution of users across job types</p>
                    <div class="chart-wrapper">
                        <canvas id="userTypeChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="section-header">
                <h2>Quick Actions</h2>
            </div>

            <div class="actions-grid">
                <a href="manage_requests.php" class="action-card">
                    <span class="material-icons">pending_actions</span>
                    <div>
                        <strong>Manage Requests</strong>
                        <p>Review and approve/reject pending access requests</p>
                    </div>
                    <?php if ($pending_requests > 0): ?>
                        <span class="action-badge"><?php echo $pending_requests; ?></span>
                    <?php endif; ?>
                </a>

                <a href="dataset_rules.php" class="action-card">
                    <span class="material-icons">rule</span>
                    <div>
                        <strong>Dataset & Rules</strong>
                        <p>Manage datasets and auto-approval rules</p>
                    </div>
                </a>

                <a href="audit_log.php" class="action-card">
                    <span class="material-icons">history</span>
                    <div>
                        <strong>Audit Trail</strong>
                        <p>View all system activity and user actions</p>
                    </div>
                </a>
            </div>

            <div class="section-header">
                <h2>Training Overview</h2>
            </div>

            <div class="training-grid">
                <div class="training-card trained">
                    <span class="material-icons">school</span>
                    <div>
                        <span class="training-number"><?php echo $trained; ?></span>
                        <span class="training-label">Training Completed</span>
                    </div>
                </div>
                <div class="training-card untrained">
                    <span class="material-icons">warning</span>
                    <div>
                        <span class="training-number"><?php echo $untrained; ?></span>
                        <span class="training-label">Training Incomplete</span>
                    </div>
                </div>
            </div>

        </div>
    </main>
    <script>
        Chart.defaults.font.family = "'GDS Transport', Arial, sans-serif";
        Chart.defaults.font.size = 12;

        new Chart(document.getElementById('requestsOverTimeChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                        label: 'Approved',
                        data: <?php echo json_encode($chart_approved); ?>,
                        backgroundColor: '#00703c',
                        borderRadius: 2
                    },
                    {
                        label: 'Pending',
                        data: <?php echo json_encode($chart_pending); ?>,
                        backgroundColor: '#f47738',
                        borderRadius: 2
                    },
                    {
                        label: 'Rejected',
                        data: <?php echo json_encode($chart_rejected); ?>,
                        backgroundColor: '#d4351c',
                        borderRadius: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    }
                },
                scales: {
                    x: {
                        stacked: true,
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('statusPieChart'), {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Rejected'],
                datasets: [{
                    data: [
                        <?php echo $approved_requests; ?>,
                        <?php echo $pending_requests; ?>,
                        <?php echo $rejected_requests; ?>
                    ],
                    backgroundColor: ['#00703c', '#f47738', '#d4351c'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('topDatasetsChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($top_ds_labels); ?>,
                datasets: [{
                    label: 'Requests',
                    data: <?php echo json_encode($top_ds_counts); ?>,
                    backgroundColor: <?php echo json_encode($top_ds_colors); ?>,
                    borderRadius: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        ticks: {
                            font: {
                                size: 11
                            },
                            callback: function(value) {
                                var label = this.getLabelForValue(value);
                                return label.length > 25 ? label.substr(0, 25) + '...' : label;
                            }
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('teamChart'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($team_labels); ?>,
                datasets: [{
                    label: 'Requests',
                    data: <?php echo json_encode($team_counts); ?>,
                    backgroundColor: '#1D70B8',
                    borderRadius: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('sensitivityChart'), {
            type: 'pie',
            data: {
                labels: ['Sensitive', 'Non-sensitive'],
                datasets: [{
                    data: [
                        <?php echo $sensitive_datasets; ?>,
                        <?php echo $non_sensitive_datasets; ?>
                    ],
                    backgroundColor: ['#d4351c', '#00703c'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    }
                }
            }
        });

        new Chart(document.getElementById('userTypeChart'), {
            type: 'doughnut',
            data: {
                labels: ['Researchers', 'Staff', 'Interns'],
                datasets: [{
                    data: [
                        <?php echo $researchers; ?>,
                        <?php echo $staff_count; ?>,
                        <?php echo $interns; ?>
                    ],
                    backgroundColor: ['#1D70B8', '#00703c', '#f47738'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 15
                        }
                    }
                }
            }
        });
    </script>

</body>

</html>