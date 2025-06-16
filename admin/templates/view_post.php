<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">View Post</h1>
        <div>
            <a href="<?= strpos($_SERVER['HTTP_REFERER'], 'reports.php') !== false ? 'reports.php' : 'posts.php' ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back
            </a>
            <?php if (is_array($post) && isset($post['report_count']) && $post['report_count'] > 0): ?>
                <a href="reports.php?post_id=<?= $post_id ?>" class="btn btn-warning">
                    <i class="fas fa-flag"></i> View Reports
                </a>
            <?php endif; ?>
            <form method="POST" action="<?= strpos($_SERVER['HTTP_REFERER'], 'reports.php') !== false ? 'reports.php' : 'posts.php' ?>" class="d-inline">
                <input type="hidden" name="post_id" value="<?= $post_id ?>">
                <input type="hidden" name="delete_post" value="1">
                <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this post? This action cannot be undone.')">
                    <i class="fas fa-trash"></i> Delete Post
                </button>
            </form>
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
                        <span class="badge bg-<?= (is_array($post) && isset($post['report_count']) && $post['report_count'] > 0) ? 'danger' : 'success' ?>">
                            <?= is_array($post) && isset($post['report_count']) ? $post['report_count'] : 0 ?> Reports
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
                                <h6 class="mb-0">@<?= is_array($post) && isset($post['username']) ? htmlspecialchars($post['username']) : 'Unknown User' ?></h6>
                                <small class="text-muted"><?= is_array($post) && isset($post['email']) ? htmlspecialchars($post['email']) : '' ?></small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">
                                    <?= is_array($post) && isset($post['created_at']) ? date('M d, Y H:i', strtotime($post['created_at'])) : 'Unknown date' ?>
                                </small>
                            </div>
                        </div>
                        <div class="post-content">
                            <?= is_array($post) && isset($post['content']) ? nl2br(htmlspecialchars($post['content'])) : 'No content available' ?>
                        </div>
                        <?php if ($files->num_rows > 0): ?>
                            <div class="mt-3">
                                <h6 class="mb-2">Attached Files:</h6>
                                <div class="row g-2">
                                    <?php while ($file = $files->fetch_assoc()): ?>
                                        <div class="col-md-4">
                                            <div class="card">
                                                <?php if (strpos($file['file_type'], 'image/') === 0): ?>
                                                    <?php
                                                    // Ensure the image URL is properly formatted
                                                    $image_url = $file['file_path'];
                                                    if (strpos($image_url, 'http') !== 0) {
                                                        // If it's a relative path, make it absolute
                                                        $image_url = '../' . ltrim($image_url, '/');
                                                    }
                                                    ?>
                                                    <img src="<?= htmlspecialchars($image_url) ?>" 
                                                         class="card-img-top" 
                                                         alt="Post Image"
                                                         style="height: 200px; object-fit: cover;"
                                                         onerror="this.onerror=null; this.src='../assets/images/placeholder.png';">
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
                        Comments (<?= is_array($post) && isset($post['comment_count']) ? $post['comment_count'] : 0 ?>)
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
                        <dd class="col-sm-8"><?= is_array($post) && isset($post['post_id']) ? $post['post_id'] : 'N/A' ?></dd>

                        <dt class="col-sm-4">Author</dt>
                        <dd class="col-sm-8">@<?= is_array($post) && isset($post['username']) ? htmlspecialchars($post['username']) : 'Unknown User' ?></dd>

                        <dt class="col-sm-4">Community</dt>
                        <dd class="col-sm-8">
                            <?php if (is_array($post) && isset($post['community_name']) && $post['community_name']): ?>
                                <a href="communities.php?id=<?= is_array($post) && isset($post['community_id']) ? $post['community_id'] : '' ?>">
                                    <?= htmlspecialchars($post['community_name']) ?>
                                </a>
                            <?php else: ?>
                                <span class="text-muted">None</span>
                            <?php endif; ?>
                        </dd>

                        <dt class="col-sm-4">Created</dt>
                        <dd class="col-sm-8"><?= is_array($post) && isset($post['created_at']) ? date('M d, Y H:i', strtotime($post['created_at'])) : 'Unknown date' ?></dd>

                        <dt class="col-sm-4">Updated</dt>
                        <dd class="col-sm-8"><?= is_array($post) && isset($post['updated_at']) ? date('M d, Y H:i', strtotime($post['updated_at'])) : 'Unknown date' ?></dd>

                        <dt class="col-sm-4">Comments</dt>
                        <dd class="col-sm-8"><?= is_array($post) && isset($post['comment_count']) ? $post['comment_count'] : 0 ?></dd>

                        <dt class="col-sm-4">Reports</dt>
                        <dd class="col-sm-8">
                            <span class="badge bg-<?= (is_array($post) && isset($post['report_count']) && $post['report_count'] > 0) ? 'danger' : 'success' ?>">
                                <?= is_array($post) && isset($post['report_count']) ? $post['report_count'] : 0 ?>
                            </span>
                        </dd>
                    </dl>
                </div>
            </div>

            <!-- Reports Summary -->
            <?php if (is_array($post) && isset($post['report_count']) && $post['report_count'] > 0): ?>
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Recent Reports</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php 
                            $reports->data_seek(0);
                            $count = 0;
                            while (($report = $reports->fetch_assoc()) && $count < 5): 
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
                                    <?php if (isset($report['details']) && $report['details']): ?>
                                        <small class="text-muted"><?= htmlspecialchars($report['details']) ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmDelete(postId) {
    if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
        const currentPage = window.location.pathname.includes('reports.php') ? 'reports.php' : 'posts.php';
        window.location.href = `${currentPage}?action=delete&id=${postId}`;
    }
}
</script> 