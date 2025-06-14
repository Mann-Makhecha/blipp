<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blipp-post</title>
    <link rel="icon" href="../favicon (2).png" type="image/x-icon">
</head>
<body>
    <?php
require_once 'includes/header.php';

// Handle post actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_post'])) {
        $post_id = (int)$_POST['post_id'];
        
        // Start transaction
        $mysqli->begin_transaction();
        
        try {
            // Delete associated files
            $file_stmt = $mysqli->prepare("SELECT file_path FROM files WHERE post_id = ?");
            $file_stmt->bind_param("i", $post_id);
            $file_stmt->execute();
            $file_result = $file_stmt->get_result();
            
            while ($file = $file_result->fetch_assoc()) {
                if (file_exists($file['file_path'])) {
                    unlink($file['file_path']);
                }
            }
            
            // Delete files from database
            $mysqli->query("DELETE FROM files WHERE post_id = $post_id");
            
            // Delete comments
            $mysqli->query("DELETE FROM comments WHERE post_id = $post_id");
            
            // Delete reports
            $mysqli->query("DELETE FROM post_reports WHERE post_id = $post_id");
            
            // Delete the post
            $mysqli->query("DELETE FROM posts WHERE post_id = $post_id");
            
            $mysqli->commit();
            $_SESSION['success_message'] = "Post deleted successfully.";
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['error_message'] = "Error deleting post: " . $e->getMessage();
        }
    }
}

// Build the query based on search and filters
$where_conditions = [];
$params = [];
$types = "";

if (!empty($_GET['search'])) {
    $search = "%" . $mysqli->real_escape_string($_GET['search']) . "%";
    $where_conditions[] = "(p.content LIKE ? OR u.username LIKE ?)";
    $params[] = $search;
    $params[] = $search;
    $types .= "ss";
}

if (!empty($_GET['community'])) {
    $community_id = (int)$_GET['community'];
    $where_conditions[] = "p.community_id = ?";
    $params[] = $community_id;
    $types .= "i";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get all communities for the filter
$communities = $mysqli->query("
    SELECT community_id, name 
    FROM communities 
    ORDER BY name ASC
");

// Get posts with user and community info
$query = "
    SELECT 
        p.*,
        u.username,
        c.name as community_name,
        (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
        (SELECT COUNT(*) FROM post_reports WHERE post_id = p.post_id) as report_count
    FROM posts p
    JOIN users u ON p.user_id = u.user_id
    LEFT JOIN communities c ON p.community_id = c.community_id
    $where_clause
    ORDER BY p.created_at DESC
";

$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$posts = $stmt->get_result();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Posts Management</h1>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search posts or users..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select" name="community">
                        <option value="">All Communities</option>
                        <?php while ($community = $communities->fetch_assoc()): ?>
                            <option value="<?= $community['community_id'] ?>" <?= isset($_GET['community']) && $_GET['community'] == $community['community_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($community['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <a href="posts.php" class="btn btn-secondary w-100">Clear Filters</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Posts Table -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Content</th>
                            <th>Author</th>
                            <th>Community</th>
                            <th>Comments</th>
                            <th>Reports</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($posts->num_rows > 0): ?>
                            <?php while ($post = $posts->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="text-truncate" style="max-width: 300px;">
                                            <?= htmlspecialchars($post['content']) ?>
                                        </div>
                                    </td>
                                    <td>@<?= htmlspecialchars($post['username']) ?></td>
                                    <td><?= $post['community_name'] ? htmlspecialchars($post['community_name']) : '<span class="text-muted">None</span>' ?></td>
                                    <td><?= $post['comment_count'] ?></td>
                                    <td>
                                        <?php if ($post['report_count'] > 0): ?>
                                            <span class="badge bg-danger"><?= $post['report_count'] ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-success">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M d, Y H:i', strtotime($post['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-primary" onclick="viewPost(<?= $post['post_id'] ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($post['report_count'] > 0): ?>
                                                <a href="reports.php?post_id=<?= $post['post_id'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-flag"></i>
                                                </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $post['post_id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                    <p class="text-muted mb-0">No posts found.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
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
                    <input type="hidden" name="post_id" id="deletePostId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_post" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Post Preview Modal -->
<div class="modal fade" id="postPreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Post Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="postPreviewContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(postId) {
    document.getElementById('deletePostId').value = postId;
    var deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}

function viewPost(postId) {
    // Show loading state
    document.getElementById('postPreviewContent').innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div></div>';
    
    // Show modal
    var modal = new bootstrap.Modal(document.getElementById('postPreviewModal'));
    modal.show();
    
    // Fetch post data
    fetch(`get_post.php?id=${postId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const post = data.post;
                let content = `
                    <div class="post-preview">
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user-circle fa-2x text-gray-300"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">@${post.username}</h6>
                                <small class="text-muted">${post.created_at}</small>
                            </div>
                        </div>
                        <div class="post-content mb-3">
                            ${post.content}
                        </div>`;
                
                if (post.files && post.files.length > 0) {
                    content += '<div class="post-files mb-3">';
                    post.files.forEach(file => {
                        if (file.file_type.startsWith('image/')) {
                            content += `
                                <img src="../${file.file_path}" 
                                     class="img-fluid rounded mb-2" 
                                     alt="Post Image"
                                     style="max-height: 300px; object-fit: contain;">`;
                        } else {
                            content += `
                                <div class="card mb-2">
                                    <div class="card-body text-center">
                                        <i class="fas fa-file fa-2x text-gray-300"></i>
                                        <p class="mt-2 mb-0">${file.file_name}</p>
                                    </div>
                                </div>`;
                        }
                    });
                    content += '</div>';
                }
                
                content += `
                    <div class="post-stats">
                        <small class="text-muted">
                            <i class="fas fa-comments"></i> ${post.comment_count} Comments
                            <span class="mx-2">|</span>
                            <i class="fas fa-flag"></i> ${post.report_count} Reports
                        </small>
                    </div>`;
                
                document.getElementById('postPreviewContent').innerHTML = content;
            } else {
                document.getElementById('postPreviewContent').innerHTML = `
                    <div class="alert alert-danger">
                        ${data.message || 'Failed to load post preview.'}
                    </div>`;
            }
        })
        .catch(error => {
            document.getElementById('postPreviewContent').innerHTML = `
                <div class="alert alert-danger">
                    Error loading post preview. Please try again.
                </div>`;
        });
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