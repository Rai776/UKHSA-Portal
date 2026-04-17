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

$hour = intval(date('H'));
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 18) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}

$all_users     = supabaseRequest('User?select=job_type,training_completed');
$total_users   = count($all_users);
$researchers   = count(array_filter($all_users, fn($u) => $u['job_type'] === 'Researcher'));
$staff_count   = count(array_filter($all_users, fn($u) => $u['job_type'] === 'Staff'));
$interns       = count(array_filter($all_users, fn($u) => $u['job_type'] === 'Intern'));
$trained       = count(array_filter($all_users, fn($u) => $u['training_completed'] === true));
$untrained     = $total_users - $trained;

$all_datasets       = supabaseRequest('Dataset?select=sensitivity');
$total_datasets     = count($all_datasets);
$sensitive_datasets = count(array_filter($all_datasets, fn($d) => $d['sensitivity'] === 'Sensitive'));
$non_sensitive_datasets = $total_datasets - $sensitive_datasets;

$all_requests     = supabaseRequest('Access_Request?select=request_id,request_status,request_date');
$total_requests   = count($all_requests);
$pending_requests = count(array_filter($all_requests, fn($r) => $r['request_status'] === 'Pending'));
$approved_requests = count(array_filter($all_requests, fn($r) => $r['request_status'] === 'Approved'));
$rejected_requests = count(array_filter($all_requests, fn($r) => $r['request_status'] === 'Rejected'));

$six_months_ago  = date('Y-m-d', strtotime('-6 months'));
$monthly_data    = [];

foreach ($all_requests as $req) {
    if (empty($req['request_date'])) continue;

    $req_date = substr($req['request_date'], 0, 10);
    if ($req_date < $six_months_ago) continue;

    $month_sort  = substr($req['request_date'], 0, 7);
    $month_label = date('M Y', strtotime($req_date));

    if (!isset($monthly_data[$month_sort])) {
        $monthly_data[$month_sort] = [
            'label'    => $month_label,
            'approved' => 0,
            'pending'  => 0,
            'rejected' => 0
        ];
    }

    $status = $req['request_status'];
    if ($status === 'Approved') $monthly_data[$month_sort]['approved']++;
    if ($status === 'Pending')  $monthly_data[$month_sort]['pending']++;
    if ($status === 'Rejected') $monthly_data[$month_sort]['rejected']++;
}

ksort($monthly_data);

$chart_labels   = [];
$chart_approved = [];
$chart_pending  = [];
$chart_rejected = [];

foreach ($monthly_data as $month) {
    $chart_labels[]   = $month['label'];
    $chart_approved[] = $month['approved'];
    $chart_pending[]  = $month['pending'];
    $chart_rejected[]  = $month['rejected'];
}

$all_requests_ds = supabaseRequest('Access_Request?select=dataset_id');
$dataset_counts  = [];

foreach ($all_requests_ds as $req) {
    $did = $req['dataset_id'];
    $dataset_counts[$did] = ($dataset_counts[$did] ?? 0) + 1;
}

arsort($dataset_counts);
$top_dataset_ids = array_slice(array_keys($dataset_counts), 0, 5, true);

$top_ds_labels = [];
$top_ds_counts = [];
$top_ds_colors = [];

foreach ($top_dataset_ids as $did) {
    $ds = supabaseRequest('Dataset?select=name,sensitivity&dataset_id=eq.' . $did);
    if (!empty($ds) && !isset($ds['error'])) {
        $top_ds_labels[] = $ds[0]['name'];
        $top_ds_counts[] = $dataset_counts[$did];
        $top_ds_colors[] = $ds[0]['sensitivity'] === 'Sensitive' ? '#d4351c' : '#00703c';
    }
}

$all_requests_team = supabaseRequest('Access_Request?select=user_id');
$user_request_map  = [];

foreach ($all_requests_team as $req) {
    $uid = $req['user_id'];
    $user_request_map[$uid] = ($user_request_map[$uid] ?? 0) + 1;
}

$team_counts_map = [];
foreach ($user_request_map as $uid => $count) {
    $u = supabaseRequest('User?select=team&user_id=eq.' . $uid);
    if (!empty($u) && !isset($u['error'])) {
        $team = $u[0]['team'] ?? 'Unknown';
        $team_counts_map[$team] = ($team_counts_map[$team] ?? 0) + $count;
    }
}

arsort($team_counts_map);
$team_labels = array_keys($team_counts_map);
$team_counts = array_values($team_counts_map);
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
                }, {
                    label: 'Pending',
                    data: <?php echo json_encode($chart_pending); ?>,
                    backgroundColor: '#f47738',
                    borderRadius: 2
                }, {
                    label: 'Rejected',
                    data: <?php echo json_encode($chart_rejected); ?>,
                    backgroundColor: '#d4351c',
                    borderRadius: 2
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