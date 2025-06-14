<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blipp - report</title>
    <link rel="icon" href="../favicon (2).png" type="image/x-icon">
</head>
<body>
    <?php
session_start();
require_once '../includes/db.php';
require_once 'includes/header.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle report actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['report_id'])) {
        $report_id = (int)$_POST['report_id'];
        $action = $_POST['action'];
        
        if ($action === 'delete_post') {
            // Get post_id from report
            $stmt = $mysqli->prepare("SELECT post_id FROM post_reports WHERE report_id = ?");
            $stmt->bind_param("i", $report_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $report = $result->fetch_assoc();
            
            if ($report) {
                // Delete post and its associated files
                $post_id = $report['post_id'];
                
                // Delete files first
                $mysqli->query("DELETE FROM files WHERE post_id = $post_id");
                
                // Delete post
                $mysqli->query("DELETE FROM posts WHERE post_id = $post_id");
                
                // Delete all reports for this post
                $mysqli->query("DELETE FROM post_reports WHERE post_id = $post_id");
                
                $_SESSION['success_message'] = "Post has been deleted successfully.";
            }
        } elseif ($action === 'dismiss_report') {
            // Delete the report
            $stmt = $mysqli->prepare("DELETE FROM post_reports WHERE report_id = ?");
            $stmt->bind_param("i", $report_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Report has been dismissed.";
            }
        }
        
        header("Location: reports.php");
        exit();
    }
}

// Search and filter functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$post_id_filter = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

// Build query
$query = "SELECT pr.*, p.content as post_content, u.username as reporter_username,
    (SELECT COUNT(*) FROM post_reports WHERE post_id = p.post_id) as total_reports
    FROM post_reports pr
    JOIN posts p ON pr.post_id = p.post_id
    JOIN users u ON pr.reporter_id = u.user_id
    WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $query .= " AND (p.content LIKE ? OR u.username LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($status_filter) {
    $query .= " AND pr.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($post_id_filter) {
    $query .= " AND pr.post_id = ?";
    $params[] = $post_id_filter;
    $types .= "i";
}

$query .= " ORDER BY pr.created_at DESC";

// Prepare and execute query
$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reports = $stmt->get_result();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Reports Management</h1>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search reports..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="resolved" <?= $status_filter === 'resolved' ? 'selected' : '' ?>>Resolved</option>
                    </select>
                </div>
                <?php if ($search || $status_filter || $post_id_filter): ?>
                    <div class="col-md-3">
                        <a href="reports.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Reported Post</th>
                            <th>Reporter</th>
                            <th>Reason</th>
                            <th>Total Reports</th>
                            <th>Status</th>
                            <th>Reported</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($report = $reports->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars(substr($report['post_content'], 0, 100)) ?>...</h6>
                                            <small class="text-muted">Post ID: <?= $report['post_id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($report['reporter_username']) ?></td>
                                <td><?= htmlspecialchars($report['reason']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $report['total_reports'] > 1 ? 'danger' : 'warning' ?>">
                                        <?= number_format($report['total_reports']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $report['status'] === 'pending' ? 'warning' : 'success' ?>">
                                        <?= ucfirst($report['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($report['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a href="../post.php?id=<?= $report['post_id'] ?>" class="dropdown-item" target="_blank">
                                                    <i class="fas fa-eye"></i> View Post
                                                </a>
                                            </li>
                                            <?php if ($report['status'] === 'pending'): ?>
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="report_id" value="<?= $report['report_id'] ?>">
                                                        <input type="hidden" name="action" value="resolve">
                                                        <button type="submit" class="dropdown-item">
                                                            <i class="fas fa-check"></i> Mark as Resolved
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" class="d-inline delete-confirm">
                                                        <input type="hidden" name="report_id" value="<?= $report['report_id'] ?>">
                                                        <input type="hidden" name="action" value="delete_post">
                                                        <button type="submit" class="dropdown-item text-danger">
                                                            <i class="fas fa-trash"></i> Delete Post
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this post? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="report_id" id="deleteReportId">
                    <input type="hidden" name="action" value="delete_post">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Post</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Dismiss Confirmation Modal -->
<div class="modal fade" id="dismissModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Dismiss</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to dismiss this report?
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="report_id" id="dismissReportId">
                    <input type="hidden" name="action" value="dismiss_report">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Dismiss Report</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(reportId) {
    document.getElementById('deleteReportId').value = reportId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function dismissReport(reportId) {
    document.getElementById('dismissReportId').value = reportId;
    new bootstrap.Modal(document.getElementById('dismissModal')).show();
}

function viewPost(postId) {
    window.open('../post.php?id=' + postId, '_blank');
}
</script>

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
    </script>
</body>
</html>