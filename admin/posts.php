<?php
require_once 'includes/auth.php';

// Debugging: Check if $mysqli is properly initialized
if (!isset($mysqli) || $mysqli->connect_error) {
    die("Database connection failed: " . ($mysqli->connect_error ?? "Unknown error"));
}

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
<?php require_once 'includes/header.php'; ?>

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

    <!-- Post Details Modal -->
    <div class="modal fade" id="viewPostModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewPostTitle"></h5>
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
                    <div class="mb-3">
                        <strong>Comments:</strong> <span id="viewPostComments"></span>
                    </div>
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
                    Are you sure you want to delete this post? This action cannot be undone and will delete all associated comments, files, and reports.
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="post_id" id="deletePostId">
                        <input type="hidden" name="delete_post" value="1">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Post</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function viewPost(postId) {
        fetch(`get_post.php?id=${postId}`)
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    alert(data.message || 'Error loading post details.');
                    return;
                }
                const post = data.post; // Access the nested post object
                document.getElementById('viewPostTitle').textContent = `Post by ${post.username}`;
                document.getElementById('viewPostContent').textContent = post.content;
                document.getElementById('viewPostAuthor').textContent = `@${post.username}`;
                document.getElementById('viewPostCommunity').textContent = post.community_name || 'None';
                document.getElementById('viewPostDate').textContent = post.created_at; // Use already formatted date
                document.getElementById('viewPostUpvotes').textContent = post.upvotes;
                document.getElementById('viewPostDownvotes').textContent = post.downvotes;
                document.getElementById('viewPostViews').textContent = post.views;

                const filesDiv = document.getElementById('viewPostFiles');
                filesDiv.innerHTML = ''; // Clear previous files
                if (post.files && post.files.length > 0) {
                    post.files.forEach(file => {
                        if (file.file_type.startsWith('image')) {
                            const img = document.createElement('img');
                            let imageUrl = file.file_path;
                            // Prepend '../' if it's a relative path and doesn't start with 'http'
                            if (!imageUrl.startsWith('http') && !imageUrl.startsWith('/')) {
                                imageUrl = '../' + imageUrl;
                            }
                            img.src = imageUrl;
                            img.alt = "Post Image";
                            img.className = "img-fluid mb-2";
                            img.onerror = function() { this.onerror=null; this.src='../assets/images/placeholder.png'; }; // Fallback image
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
                document.getElementById('viewPostComments').textContent = post.comment_count;

                new bootstrap.Modal(document.getElementById('viewPostModal')).show();
            })
            .catch(error => {
                console.error('Error fetching post details:', error);
                alert('Error loading post details.');
            });
    }

    function confirmDelete(postId) {
        document.getElementById('deletePostId').value = postId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
    </script>
</body>
</html>