// Get recent posts with user and community info
$recent_posts = $mysqli->query("
    SELECT p.*, u.username, c.name as community_name, c.slug as community_slug,
           (SELECT COUNT(*) FROM post_likes WHERE post_id = p.post_id) as like_count,
           (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count
    FROM posts p
    LEFT JOIN users u ON p.user_id = u.user_id
    LEFT JOIN communities c ON p.community_id = c.community_id
    ORDER BY p.created_at DESC
    LIMIT 5
");

<!-- Recent Posts -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">Recent Posts</h5>
    </div>
    <div class="card-body">
        <?php if ($recent_posts && $recent_posts->num_rows > 0): ?>
            <div class="list-group list-group-flush">
                <?php while ($post = $recent_posts->fetch_assoc()): ?>
                    <div class="list-group-item">
                        <div class="d-flex align-items-center mb-2">
                            <div class="flex-shrink-0">
                                <?php if (!empty($post['image_url'])): ?>
                                    <?php
                                    // Ensure the image URL is properly formatted
                                    $image_url = $post['image_url'];
                                    if (strpos($image_url, 'http') !== 0) {
                                        // If it's a relative path, make it absolute
                                        $image_url = '../' . ltrim($image_url, '/');
                                    }
                                    ?>
                                    <img src="<?= htmlspecialchars($image_url) ?>" 
                                         alt="Post image" 
                                         class="rounded" 
                                         style="width: 100px; height: 100px; object-fit: cover;"
                                         onerror="this.onerror=null; this.src='../assets/images/placeholder.png';">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                         style="width: 100px; height: 100px;">
                                        <i class="fas fa-image text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <h6 class="mb-1">
                                        <?php if ($post['community_id']): ?>
                                            <a href="../community.php?slug=<?= htmlspecialchars($post['community_slug']) ?>" 
                                               class="text-decoration-none">
                                                r/<?= htmlspecialchars($post['community_name']) ?>
                                            </a>
                                        <?php endif; ?>
                                    </h6>
                                    <small class="text-muted">
                                        <?= date('M d, Y', strtotime($post['created_at'])) ?>
                                    </small>
                                </div>
                                <p class="mb-1 text-truncate">
                                    <?= htmlspecialchars($post['content']) ?>
                                </p>
                                <div class="d-flex align-items-center text-muted small">
                                    <span class="me-3">
                                        <i class="fas fa-user"></i> 
                                        <?= htmlspecialchars($post['username']) ?>
                                    </span>
                                    <span class="me-3">
                                        <i class="fas fa-heart"></i> 
                                        <?= number_format($post['like_count']) ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-comment"></i> 
                                        <?= number_format($post['comment_count']) ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">No recent posts found.</p>
        <?php endif; ?>
    </div>
</div> 