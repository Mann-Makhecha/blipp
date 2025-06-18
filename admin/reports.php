<?php
// session_start();
require_once '../includes/db.php';
require_once 'includes/auth.php';

// Add this at the top of the file, after session_start()
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received: " . print_r($_POST, true));
    error_log("Raw POST data: " . file_get_contents('php://input'));
    error_log("Request headers: " . print_r(getallheaders(), true));
}

// Handle view post page
if (isset($_GET['view']) && $_GET['view'] === 'post') {
    $post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$post_id) {
        $_SESSION['error_message'] = "Invalid post ID.";
        header("Location: reports.php");
        exit();
    }

    // Get post details with user and community info
    $stmt = $mysqli->prepare("
        SELECT 
            p.*,
            u.username,
            u.email,
            c.name as community_name,
            c.community_id,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
            (SELECT COUNT(*) FROM post_reports WHERE post_id = p.post_id) as report_count
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN communities c ON p.community_id = c.community_id
        WHERE p.post_id = ?
    ");
    $stmt->bind_param("i", $post_id);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();

    if (!$post) {
        $_SESSION['error_message'] = "Post not found.";
        header("Location: reports.php");
        exit();
    }

    // Get post files
    $files = $mysqli->query("
        SELECT * FROM files 
        WHERE post_id = $post_id
    ");

    // Get post comments
    $comments = $mysqli->query("
        SELECT 
            c.*,
            u.username
        FROM comments c
        JOIN users u ON c.user_id = u.user_id
        WHERE c.post_id = $post_id
        ORDER BY c.created_at DESC
    ");

    // Get post reports
    $reports = $mysqli->query("
        SELECT 
            r.*,
            u.username as reporter_username
        FROM post_reports r
        JOIN users u ON r.reporter_id = u.user_id
        WHERE r.post_id = $post_id
        ORDER BY r.created_at DESC
    ");

    // Include view post template
    require_once 'includes/header.php';
    include 'templates/view_post.php';
    exit();
}

// Handle post actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received in reports.php: " . print_r($_POST, true));
    
    if (isset($_POST['delete_post']) && isset($_POST['post_id'])) {
        error_log("Delete post action triggered");
        $post_id = (int)$_POST['post_id'];
        error_log("Post ID to delete: " . $post_id);
        
        // Start transaction
        $mysqli->begin_transaction();
        
        try {
            // Delete files from database
            $delete_files_stmt = $mysqli->prepare("DELETE FROM files WHERE post_id = ?");
            $delete_files_stmt->bind_param("i", $post_id);
            $delete_files_stmt->execute();
            $delete_files_stmt->close();
            error_log("Files deleted from database");
            
            // Delete comments
            $delete_comments_stmt = $mysqli->prepare("DELETE FROM comments WHERE post_id = ?");
            $delete_comments_stmt->bind_param("i", $post_id);
            $delete_comments_stmt->execute();
            $delete_comments_stmt->close();
            error_log("Comments deleted");
            
            // Delete reports
            $delete_reports_stmt = $mysqli->prepare("DELETE FROM post_reports WHERE post_id = ?");
            $delete_reports_stmt->bind_param("i", $post_id);
            $delete_reports_stmt->execute();
            $delete_reports_stmt->close();
            error_log("Reports deleted");
            
            // Delete the post
            $delete_post_stmt = $mysqli->prepare("DELETE FROM posts WHERE post_id = ?");
            $delete_post_stmt->bind_param("i", $post_id);
            $delete_post_stmt->execute();
            $delete_post_stmt->close();
            error_log("Post deleted");
            
            $mysqli->commit();
            error_log("Transaction committed successfully");
            $_SESSION['success_message'] = "Post deleted successfully.";
        } catch (Exception $e) {
            $mysqli->rollback();
            error_log("Error deleting post: " . $e->getMessage());
            $_SESSION['error_message'] = "Error deleting post: " . $e->getMessage();
        }
        
        header("Location: reports.php");
        exit();
    } elseif (isset($_POST['action']) && isset($_POST['report_id'])) {
        $report_id = (int)$_POST['report_id'];
        $action = $_POST['action'];
        
        if ($action === 'resolve') {
            // Delete the report
            $stmt = $mysqli->prepare("DELETE FROM post_reports WHERE report_id = ?");
            $stmt->bind_param("i", $report_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Report has been dismissed.";
            } else {
                $_SESSION['error_message'] = "Error dismissing report.";
            }
        } else {
            $_SESSION['error_message'] = "Invalid action.";
        }
        
        header("Location: reports.php");
        exit();
    }
}

// Handle GET actions (for delete from view_post.php)
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $post_id = (int)$_GET['id'];
    
    // Start transaction
    $mysqli->begin_transaction();
    
    try {
        // Delete files first
        $delete_files_stmt = $mysqli->prepare("DELETE FROM files WHERE post_id = ?");
        $delete_files_stmt->bind_param("i", $post_id);
        $delete_files_stmt->execute();
        $delete_files_stmt->close();
        
        // Delete comments
        $delete_comments_stmt = $mysqli->prepare("DELETE FROM comments WHERE post_id = ?");
        $delete_comments_stmt->bind_param("i", $post_id);
        $delete_comments_stmt->execute();
        $delete_comments_stmt->close();
        
        // Delete reports
        $delete_reports_stmt = $mysqli->prepare("DELETE FROM post_reports WHERE post_id = ?");
        $delete_reports_stmt->bind_param("i", $post_id);
        $delete_reports_stmt->execute();
        $delete_reports_stmt->close();
        
        // Delete the post
        $delete_post_stmt = $mysqli->prepare("DELETE FROM posts WHERE post_id = ?");
        $delete_post_stmt->bind_param("i", $post_id);
        $delete_post_stmt->execute();
        $delete_post_stmt->close();
        
        $mysqli->commit();
        $_SESSION['success_message'] = "Post has been deleted successfully.";
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['error_message'] = "Error deleting post: " . $e->getMessage();
    }
    
    header("Location: reports.php");
    exit();
}

// Search and filter functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$post_id_filter = isset($_GET['post_id']) ? (int)$_GET['post_id'] : 0;

// Build query
$query = "SELECT pr.*, p.content as post_content, u.username as reporter_username,\n    (SELECT COUNT(*) FROM post_reports WHERE post_id = p.post_id) as total_reports\n    FROM post_reports pr\n    JOIN posts p ON pr.post_id = p.post_id\n    JOIN users u ON pr.reporter_id = u.user_id\n    WHERE 1=1";
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
<?php require_once 'includes/header.php'; ?>

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
                                    <div class="d-flex gap-2">
                                        <a href="?view=post&id=<?= $report['post_id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($report['status'] === 'pending'): ?>
                                            <form method="POST" action="reports.php" class="d-inline">
                                                <input type="hidden" name="report_id" value="<?= $report['report_id'] ?>">
                                                <input type="hidden" name="action" value="resolve">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to dismiss this report?')">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </form>
                                            <form method="POST" action="reports.php" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this post and all its reports? This action cannot be undone.');">
                                                <input type="hidden" name="post_id" value="<?= $report['post_id'] ?>">
                                                <input type="hidden" name="delete_post" value="1">
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash-alt"></i> Delete Post (ID: <?= $report['post_id'] ?>)
                                                </button>
                                            </form>
                                        <?php endif; ?>
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

<!-- Post Details Modal (Optional - if you have a separate get_post.php) -->
<div class="modal fade" id="viewPostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewPostTitle">Post Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Content:</strong> <span id="viewPostContent"></span>
                </div>
                <div class="mb-3">
                    <strong>Author:</strong> <span id="viewPostAuthor"></span>
                </div>
                <div class="mb-3">
                    <strong>Community:</strong> <span id="viewPostCommunity"></span>
                </div>
                <div class="mb-3">
                    <strong>Posted On:</strong> <span id="viewPostDate"></span>
                </div>
                <div class="mb-3">
                    <strong>Upvotes:</strong> <span id="viewPostUpvotes"></span>
                </div>
                <div class="mb-3">
                    <strong>Downvotes:</strong> <span id="viewPostDownvotes"></span>
                </div>
                <div class="mb-3">
                    <strong>Views:</strong> <span id="viewPostViews"></span>
                </div>
                <div id="viewPostFiles" class="mb-3"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function viewPost(postId) {
        fetch(`get_post.php?id=${postId}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert(data.error);
                    return;
                }
                document.getElementById('viewPostTitle').textContent = `Post by ${data.username}`;
                document.getElementById('viewPostContent').textContent = data.content;
                document.getElementById('viewPostAuthor').textContent = `@${data.username}`;
                document.getElementById('viewPostCommunity').textContent = data.community_name || 'None';
                document.getElementById('viewPostDate').textContent = new Date(data.created_at).toLocaleString();
                document.getElementById('viewPostUpvotes').textContent = data.upvotes;
                document.getElementById('viewPostDownvotes').textContent = data.downvotes;
                document.getElementById('viewPostViews').textContent = data.views;

                const filesDiv = document.getElementById('viewPostFiles');
                filesDiv.innerHTML = ''; // Clear previous files
                if (data.files && data.files.length > 0) {
                    data.files.forEach(file => {
                        if (file.file_type.startsWith('image')) {
                            const img = document.createElement('img');
                            img.src = file.file_path;
                            img.alt = "Post Image";
                            img.className = "img-fluid mb-2";
                            filesDiv.appendChild(img);
                        } else if (file.file_type.startsWith('video')) {
                            const video = document.createElement('video');
                            video.controls = true;
                            video.className = "img-fluid mb-2";
                            const source = document.createElement('source');
                            source.src = file.file_path;
                            source.type = file.file_type;
                            video.appendChild(source);
                            filesDiv.appendChild(video);
                        }
                    });
                }
                new bootstrap.Modal(document.getElementById('viewPostModal')).show();
            })
            .catch(error => {
                console.error('Error fetching post details:', error);
                alert('Error loading post details.');
            });
    }

    function handleAction(reportId, action) {
        if (action === 'resolve' && !confirm('Are you sure you want to dismiss this report?')) {
            return;
        }
        if (action === 'delete_post' && !confirm('Are you sure you want to delete this post and all its reports? This action cannot be undone.')) {
            return;
        }
        
        // Create and submit form programmatically
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'reports.php';

        const reportIdInput = document.createElement('input');
        reportIdInput.type = 'hidden';
        reportIdInput.name = 'report_id';
        reportIdInput.value = reportId;

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = action;

        form.appendChild(reportIdInput);
        form.appendChild(actionInput);
        document.body.appendChild(form);
        
        form.submit();
    }

    // Add error handling for form submissions
    document.addEventListener('DOMContentLoaded', function() {
        // Add error handling for all forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                // Form submission handling
            });
        });
    });
</script>
</body>
</html>