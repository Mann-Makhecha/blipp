<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blipp</title>
     <link rel="icon" href="../favicon (2).png" type="image/x-icon">
</head>
<body>
    <?php
session_start();
require_once '../includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get total counts
$total_users = $mysqli->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_posts = $mysqli->query("SELECT COUNT(*) as count FROM posts")->fetch_assoc()['count'];
$total_communities = $mysqli->query("SELECT COUNT(*) as count FROM communities")->fetch_assoc()['count'];
$total_reports = $mysqli->query("SELECT COUNT(*) as count FROM post_reports WHERE status = 'pending'")->fetch_assoc()['count'];

// Get recent users
$recent_users = $mysqli->query("
    SELECT username, email, created_at 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");

// Get recent posts
$recent_posts = $mysqli->query("
    SELECT p.*, u.username 
    FROM posts p 
    JOIN users u ON p.user_id = u.user_id 
    ORDER BY p.created_at DESC 
    LIMIT 5
");

// Handle post view request
if (isset($_GET['view_post'])) {
    $post_id = (int)$_GET['view_post'];
    
    // Get post details
    $post_query = $mysqli->prepare("
        SELECT p.*, u.username, u.email, c.name as community_name, c.community_id,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
        (SELECT COUNT(*) FROM post_reports WHERE post_id = p.post_id) as report_count
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN communities c ON p.community_id = c.community_id
        WHERE p.post_id = ?
    ");
    $post_query->bind_param("i", $post_id);
    $post_query->execute();
    $post = $post_query->get_result()->fetch_assoc();
    
    if ($post) {
        // Get post files
        $files_query = $mysqli->prepare("SELECT * FROM files WHERE post_id = ?");
        $files_query->bind_param("i", $post_id);
        $files_query->execute();
        $files = $files_query->get_result();
        
        // Get comments
        $comments_query = $mysqli->prepare("
            SELECT c.*, u.username 
            FROM comments c
            JOIN users u ON c.user_id = u.user_id
            WHERE c.post_id = ?
            ORDER BY c.created_at DESC
        ");
        $comments_query->bind_param("i", $post_id);
        $comments_query->execute();
        $comments = $comments_query->get_result();
        
        // Get reports
        $reports_query = $mysqli->prepare("
            SELECT r.*, u.username as reporter_username
            FROM post_reports r
            JOIN users u ON r.reporter_id = u.user_id
            WHERE r.post_id = ?
            ORDER BY r.created_at DESC
        ");
        $reports_query->bind_param("i", $post_id);
        $reports_query->execute();
        $reports = $reports_query->get_result();
        
        // Include the view post template
        include 'templates/view_post.php';
        exit;
    }
}

// Get user registration statistics for the last 7 days
$registration_stats = $mysqli->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
    FROM users 
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");

// Prepare data for the chart
$chart_labels = [];
$chart_data = [];

// Initialize the last 7 days with zero counts
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('M d', strtotime($date));
    $chart_data[$date] = 0;
}

// Fill in actual registration counts
while ($row = $registration_stats->fetch_assoc()) {
    $chart_data[$row['date']] = (int)$row['count'];
}

// Convert to array maintaining order
$chart_data = array_values($chart_data);
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Dashboard</h1>
    </div>

    <!-- Statistics Cards -->
    <div class="row">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Users</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_users) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Posts</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_posts) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-comments fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Communities</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_communities) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users-cog fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Reports</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?= number_format($total_reports) ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-flag fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">User Registrations (Last 7 Days)</h6>
                </div>
                <div class="card-body">
                    <div class="chart-area">
                        <canvas id="registrationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Users</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <?php while ($user = $recent_users->fetch_assoc()): ?>
                            <div class="list-group-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?= htmlspecialchars($user['username']) ?></h6>
                                    <small><?= date('M d, Y', strtotime($user['created_at'])) ?></small>
                                </div>
                                <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                            </div>
                        <?php endwhile; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Posts -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Recent Posts</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Content</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($post = $recent_posts->fetch_assoc()): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($post['username']) ?></td>
                                        <td><?= htmlspecialchars(substr($post['content'], 0, 100)) ?>...</td>
                                        <td><?= date('M d, Y H:i', strtotime($post['created_at'])) ?></td>
                                        <td>
                                            <a href="?view_post=<?= $post['post_id'] ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Initialize all tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Initialize all popovers
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl)
        });

        // Confirm delete actions
        document.querySelectorAll('.delete-confirm').forEach(function(element) {
            element.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });

        // Initialize the registration chart
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('registrationChart').getContext('2d');
            var registrationChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?= json_encode($chart_labels) ?>,
                    datasets: [{
                        label: 'New Registrations',
                        data: <?= json_encode($chart_data) ?>,
                        backgroundColor: 'rgba(78, 115, 223, 0.05)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        pointRadius: 3,
                        pointBackgroundColor: 'rgba(78, 115, 223, 1)',
                        pointBorderColor: 'rgba(78, 115, 223, 1)',
                        pointHoverRadius: 5,
                        pointHoverBackgroundColor: 'rgba(78, 115, 223, 1)',
                        pointHoverBorderColor: 'rgba(78, 115, 223, 1)',
                        pointHitRadius: 10,
                        pointBorderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    layout: {
                        padding: {
                            left: 10,
                            right: 25,
                            top: 25,
                            bottom: 0
                        }
                    },
                    scales: {
                        x: {
                            grid: {
                                display: false,
                                drawBorder: false
                            },
                            ticks: {
                                maxTicksLimit: 7
                            }
                        },
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: "rgb(234, 236, 244)",
                                zeroLineColor: "rgb(234, 236, 244)",
                                drawBorder: false,
                                borderDash: [2],
                                zeroLineBorderDash: [2]
                            },
                            ticks: {
                                maxTicksLimit: 5,
                                padding: 10,
                                callback: function(value) {
                                    return value + ' users';
                                }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: "rgb(255,255,255)",
                            bodyColor: "#858796",
                            titleMarginBottom: 10,
                            titleColor: '#6e707e',
                            titleFontSize: 14,
                            borderColor: '#dddfeb',
                            borderWidth: 1,
                            xPadding: 15,
                            yPadding: 15,
                            displayColors: false,
                            intersect: false,
                            mode: 'index',
                            caretPadding: 10,
                            callbacks: {
                                label: function(context) {
                                    var label = context.dataset.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed.y !== null) {
                                        label += context.parsed.y + ' users';
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>