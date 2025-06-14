<?php
require_once 'includes/header.php';

// Get post ID from URL
$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$post_id) {
    $_SESSION['error_message'] = "Invalid post ID.";
    header("Location: posts.php");
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
    header("Location: posts.php");
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
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">View Post</h1>
        <div>
            <a href="posts.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Posts
            </a>
            <?php if ($post['report_count'] > 0): ?>
                <a href="reports.php?post_id=<?= $post_id ?>" class="btn btn-warning">
                    <i class="fas fa-flag"></i> View Reports
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-danger" onclick="confirmDelete(<?= $post_id ?>)">
                <i class="fas fa-trash"></i> Delete Post
            </button>
        </div>
    </div>

    <!-- Post Details -->
    <div class="row">
        <div class="col-lg-8">
            <!-- Post Content -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Post Content</h6>
                        <span class="badge bg-<?= $post['report_count'] > 0 ? 'danger' : 'success' ?>">
                            <?= $post['report_count'] ?> Reports
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-3">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user-circle fa-2x text-gray-300"></i>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h6 class="mb-0">@<?= htmlspecialchars($post['username']) ?></h6>
                                <small class="text-muted"><?= htmlspecialchars($post['email']) ?></small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">
                                    <?= date('M d, Y H:i', strtotime($post['created_at'])) ?>
                                </small>
                            </div>
                        </div>
                        <div class="post-content">
                            <?= nl2br(htmlspecialchars($post['content'])) ?>
                        </div>
                        <?php if ($files->num_rows > 0): ?>
                            <div class="mt-3">
                                <h6 class="mb-2">Attached Files:</h6>
                                <div class="row g-2">
                                    <?php while ($file = $files->fetch_assoc()): ?>
                                        <div class="col-md-4">
                                            <div class="card">
                                                <?php if (strpos($file['file_type'], 'image/') === 0): ?>
                                                    <img src="<?= htmlspecialchars($file['file_path']) ?>" 
                                                         class="card-img-top" 
                                                         alt="Post Image"
                                                         style="height: 200px; object-fit: cover;">
                                                <?php else: ?>
                                                    <div class="card-body text-center">
                                                        <i class="fas fa-file fa-3x text-gray-300"></i>
                                                        <p class="mt-2 mb-0"><?= htmlspecialchars($file['file_name']) ?></p>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Comments -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">
                        Comments (<?= $post['comment_count'] ?>)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if ($comments->num_rows > 0): ?>
                        <div class="list-group">
                            <?php while ($comment = $comments->fetch_assoc()): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">@<?= htmlspecialchars($comment['username']) ?></h6>
                                        <small class="text-muted">
                                            <?= date('M d, Y H:i', strtotime($comment['created_at'])) ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center mb-0">No comments yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Post Info -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Post Information</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Post ID</dt>
                        <dd class="col-sm-8"><?= $post['post_id'] ?></dd>

                        <dt class="col-sm-4">Author</dt>
                        <dd class="col-sm-8">@<?= htmlspecialchars($post['username']) ?></dd>

                        <dt class="col-sm-4">Community</dt>
                        <dd class="col-sm-8">
                            <?php if ($post['community_name']): ?>
                                <a href="communities.php?id=<?= $post['community_id'] ?>">
                                    <?= htmlspecialchars($post['community_name']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">None</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Created</dt>
                        <dd class="col-sm-8"><?= date('M d, Y H:i', strtotime($post['created_at'])) ?></dd>

                        <dt class="col-sm-4">Updated</dt>
                        <dd class="col-sm-8"><?= date('M d, Y H:i', strtotime($post['updated_at'])) ?></dd>

                        <dt class="col-sm-4">Comments</dt>
                        <dd class="col-sm-8"><?= $post['comment_count'] ?></dd>

                        <dt class="col-sm-4">Reports</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?= $post['report_count'] > 0 ? 'danger' : 'success' ?>">
                                <?= $post['report_count'] ?>
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>

            <!-- Reports Summary -->
            <?php if ($post['report_count'] > 0): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Reports</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php 
                            $reports->data_seek(0);
                            $count = 0;
                            while ($report = $reports->fetch_assoc() && $count < 5): 
                                $count++;
                            ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">@<?= htmlspecialchars($report['reporter_username']) ?></h6>
                                        <small class="text-muted">
                                            <?= date('M d, Y H:i', strtotime($report['created_at'])) ?>
                                        </small>
                                    </div>
                                    <p class="mb-1"><?= htmlspecialchars($report['reason']) ?></p>
                                </div>
                            <?php endwhile; ?>
                        </div>
                        <?php if ($post['report_count'] > 5): ?>
                            <div class="text-center mt-3">
                                <a href="reports.php?post_id=<?= $post_id ?>" class="btn btn-sm btn-primary">
                                    View All Reports
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
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
                <form method="POST" action="posts.php">
                    <input type="hidden" name="post_id" id="deletePostId">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_post" class="btn btn-danger">Delete</button>
                </form>
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