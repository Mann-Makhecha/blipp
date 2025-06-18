<?php
// No session_start() since it's already called in index.php

// Helper function for time ago (define early to avoid undefined function error)
if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $now = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        $post_time = new DateTime($datetime, new DateTimeZone('Asia/Kolkata'));
        $interval = $now->diff($post_time);
        
        if ($interval->y > 0) {
            return $interval->y . 'y ago';
        } elseif ($interval->m > 0) {
            return $interval->m . 'm ago';
        } elseif ($interval->d > 0) {
            return $interval->d . 'd ago';
        } elseif ($interval->h > 0) {
            return $interval->h . 'h ago';
        } elseif ($interval->i > 0) {
            return $interval->i . 'm ago';
        } else {
            return 'Just now';
        }
    }
}

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;

// Get trending topics from actual posts (hashtags in content)
$trending_topics = [];
if ($user_id) {
    $trending_query = $conn->prepare("
        SELECT 
            SUBSTRING_INDEX(SUBSTRING_INDEX(p.content, '#', -1), ' ', 1) as hashtag,
            COUNT(*) as post_count
        FROM posts p 
        WHERE p.content LIKE '%#%' 
        AND p.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY hashtag 
        HAVING hashtag != '' AND LENGTH(hashtag) > 1
        ORDER BY post_count DESC 
        LIMIT 5
    ");
    $trending_query->execute();
    $trending_result = $trending_query->get_result();
    
    while ($row = $trending_result->fetch_assoc()) {
        $trending_topics[] = [
            'hashtag' => '#' . $row['hashtag'],
            'posts' => $row['post_count'] > 1000 ? number_format($row['post_count'] / 1000, 1) . 'K' : $row['post_count']
        ];
    }
    $trending_query->close();
}

// Get suggested users (users not followed by current user, with most followers)
$suggested_users = [];
if ($user_id) {
    $suggested_query = $conn->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.email,
            COUNT(DISTINCT f1.follower_id) as follower_count,
            COUNT(DISTINCT f2.followed_id) as following_count,
            EXISTS(SELECT 1 FROM user_badges ub JOIN badges b ON ub.badge_id = b.badge_id WHERE ub.user_id = u.user_id AND b.name = 'Verified') as is_verified
        FROM users u
        LEFT JOIN follows f1 ON u.user_id = f1.followed_id
        LEFT JOIN follows f2 ON u.user_id = f2.follower_id
        WHERE u.user_id != ?
        AND u.user_id NOT IN (
            SELECT followed_id FROM follows WHERE follower_id = ?
        )
        GROUP BY u.user_id
        ORDER BY follower_count DESC, u.username ASC
        LIMIT 5
    ");
    $suggested_query->bind_param("ii", $user_id, $user_id);
    $suggested_query->execute();
    $suggested_result = $suggested_query->get_result();
    
    while ($row = $suggested_result->fetch_assoc()) {
        $suggested_users[] = [
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'handle' => '@' . $row['username'],
            'follower_count' => $row['follower_count'],
            'following_count' => $row['following_count'],
            'verified' => $row['is_verified']
        ];
    }
    $suggested_query->close();
}

// Get recent activity (latest posts from followed users)
$recent_activity = [];
if ($user_id) {
    $activity_query = $conn->prepare("
        SELECT 
            p.post_id,
            p.content,
            p.created_at,
            u.username,
            u.user_id,
            EXISTS(SELECT 1 FROM user_badges ub JOIN badges b ON ub.badge_id = b.badge_id WHERE ub.user_id = u.user_id AND b.name = 'Verified') as is_verified
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        JOIN follows f ON p.user_id = f.followed_id
        WHERE f.follower_id = ?
        ORDER BY p.created_at DESC
        LIMIT 3
    ");
    $activity_query->bind_param("i", $user_id);
    $activity_query->execute();
    $activity_result = $activity_query->get_result();
    
    while ($row = $activity_result->fetch_assoc()) {
        $recent_activity[] = [
            'post_id' => $row['post_id'],
            'content' => $row['content'],
            'created_at' => $row['created_at'],
            'username' => $row['username'],
            'user_id' => $row['user_id'],
            'verified' => $row['is_verified']
        ];
    }
    $activity_query->close();
}

// Get user's stats
$user_stats = [];
if ($user_id) {
    $stats_query = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM posts WHERE user_id = ?) as posts_count,
            (SELECT COUNT(*) FROM follows WHERE follower_id = ?) as following_count,
            (SELECT COUNT(*) FROM follows WHERE followed_id = ?) as followers_count
    ");
    $stats_query->bind_param("iii", $user_id, $user_id, $user_id);
    $stats_query->execute();
    $stats_result = $stats_query->get_result();
    $user_stats = $stats_result->fetch_assoc();
    $stats_query->close();
}

// Handle search functionality
$search_results = [];
$search_query = $_GET['search'] ?? '';
if ($search_query && strlen($search_query) > 2) {
    $search_stmt = $conn->prepare("
        SELECT 
            u.user_id,
            u.username,
            u.email,
            EXISTS(SELECT 1 FROM user_badges ub JOIN badges b ON ub.badge_id = b.badge_id WHERE ub.user_id = u.user_id AND b.name = 'Verified') as is_verified
        FROM users u
        WHERE u.username LIKE ? OR u.email LIKE ?
        LIMIT 5
    ");
    $search_term = "%$search_query%";
    $search_stmt->bind_param("ss", $search_term, $search_term);
    $search_stmt->execute();
    $search_result = $search_stmt->get_result();
    
    while ($row = $search_result->fetch_assoc()) {
        $search_results[] = [
            'user_id' => $row['user_id'],
            'username' => $row['username'],
            'handle' => '@' . $row['username'],
            'verified' => $row['is_verified']
        ];
    }
    $search_stmt->close();
}
?>

<div class="right-sidebar">
    <!-- Search Bar -->
    <div class="search-bar mb-4">
        <form method="GET" action="" id="searchForm">
            <div class="input-group">
                <span class="input-group-text bg-dark border-dark text-white">
                    <i class="fas fa-search"></i>
                </span>
                <input type="text" 
                       class="form-control bg-dark border-dark text-white" 
                       placeholder="Search users..." 
                       name="search"
                       value="<?= htmlspecialchars($search_query) ?>"
                       aria-label="Search">
                <?php if ($search_query): ?>
                    <button type="button" class="btn btn-outline-secondary" onclick="clearSearch()">
                        <i class="fas fa-times"></i>
                    </button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Search Results -->
    <?php if ($search_query && !empty($search_results)): ?>
        <div class="search-results mb-4">
            <h6 class="fw-bold mb-3">Search Results</h6>
            <?php foreach ($search_results as $user): ?>
                <div class="user-item d-flex align-items-center p-2 mb-2 rounded hover-bg">
                    <i class="fas fa-user-circle fa-2x me-2" style="color: #666;"></i>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center">
                            <a href="profile.php?user_id=<?= $user['user_id'] ?>" class="text-white text-decoration-none fw-bold">
                                <?= htmlspecialchars($user['username']) ?>
                            </a>
                            <?php if ($user['verified']): ?>
                                <i class="fas fa-check-circle text-primary ms-1" style="font-size: 0.9rem;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="text-white small"><?= htmlspecialchars($user['handle']) ?></div>
                    </div>
                    <button class="btn btn-outline-primary btn-sm rounded-pill follow-btn" 
                            data-user-id="<?= $user['user_id'] ?>"
                            onclick="followUser(<?= $user['user_id'] ?>)">
                        Follow
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- User Stats (if logged in) -->
    <?php if ($user_id && $user_stats): ?>
        <div class="user-stats mb-4">
            <h6 class="fw-bold mb-3">Your Stats</h6>
            <div class="stats-grid">
                <div class="stat-item text-center p-2">
                    <div class="stat-number"><?= $user_stats['posts_count'] ?></div>
                    <div class="stat-label small">Posts</div>
                </div>
                <div class="stat-item text-center p-2">
                    <div class="stat-number"><?= $user_stats['followers_count'] ?></div>
                    <div class="stat-label small">Followers</div>
                </div>
                <div class="stat-item text-center p-2">
                    <div class="stat-number"><?= $user_stats['following_count'] ?></div>
                    <div class="stat-label small">Following</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Activity -->
    <?php if ($user_id && !empty($recent_activity)): ?>
        <div class="recent-activity mb-4">
            <h6 class="fw-bold mb-3">Recent Activity</h6>
            <?php foreach ($recent_activity as $activity): ?>
                <div class="activity-item p-2 mb-2 rounded hover-bg">
                    <div class="d-flex align-items-start">
                        <i class="fas fa-user-circle fa-lg me-2 mt-1" style="color: #666;"></i>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center mb-1">
                                <a href="profile.php?user_id=<?= $activity['user_id'] ?>" class="text-white text-decoration-none fw-bold small">
                                    <?= htmlspecialchars($activity['username']) ?>
                                </a>
                                <?php if ($activity['verified']): ?>
                                    <i class="fas fa-check-circle text-primary ms-1" style="font-size: 0.8rem;"></i>
                                <?php endif; ?>
                                <span class="text-muted small ms-2">
                                    <?= timeAgo($activity['created_at']) ?>
                                </span>
                            </div>
                            <div class="text-white small">
                                <?= htmlspecialchars(substr($activity['content'], 0, 50)) ?><?= strlen($activity['content']) > 50 ? '...' : '' ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Trending Topics -->
    <?php if (!empty($trending_topics)): ?>
        <div class="trending-section mb-4">
            <h6 class="fw-bold mb-3">Trending Topics</h6>
            <?php foreach ($trending_topics as $trend): ?>
                <div class="trend-item p-2 mb-2 rounded hover-bg">
                    <div class="d-flex justify-content-between">
                        <div>
                            <a href="explore.php?q=<?= urlencode($trend['hashtag']) ?>" class="text-white text-decoration-none">
                                <?= htmlspecialchars($trend['hashtag']) ?>
                            </a>
                            <div class="text-muted small"><?= htmlspecialchars($trend['posts']) ?> posts</div>
                        </div>
                        <div>
                            <button class="btn btn-link text-muted p-0" onclick="showTrendOptions('<?= $trend['hashtag'] ?>')">
                                <i class="fas fa-ellipsis-h"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Suggested Users -->
    <?php if ($user_id && !empty($suggested_users)): ?>
        <div class="suggested-users mb-4">
            <h6 class="fw-bold mb-3">Who to Follow</h6>
            <?php foreach ($suggested_users as $user): ?>
                <div class="user-item d-flex align-items-center p-2 mb-2 rounded hover-bg">
                    <i class="fas fa-user-circle fa-2x me-2" style="color: #666;"></i>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center">
                            <a href="profile.php?user_id=<?= $user['user_id'] ?>" class="text-white text-decoration-none fw-bold">
                                <?= htmlspecialchars($user['username']) ?>
                            </a>
                            <?php if ($user['verified']): ?>
                                <i class="fas fa-check-circle text-primary ms-1" style="font-size: 0.9rem;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="text-white small">
                            <?= htmlspecialchars($user['handle']) ?> · <?= $user['follower_count'] ?> followers
                        </div>
                    </div>
                   
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Quick Actions -->
    <div class="quick-actions mb-4">
        <h6 class="fw-bold mb-3">Quick Actions</h6>
        <div class="d-grid gap-2">
            <a href="create_post.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-2"></i>Create Post
            </a>
            
            <a href="blipp_pro.php" class="btn btn-warning btn-sm">
                <i class="fas fa-crown me-2"></i>Upgrade to Pro
            </a>
        </div>
    </div>
</div>

<style>
.right-sidebar {
    background-color: #000;
    color: #fff;
    height: 100vh;
    width: 20vw;
    padding: 1.5rem;
    overflow-y: auto;
    position: fixed;
    top: 0;
}

.right-sidebar .search-bar .form-control {
    background-color: #1a1a1a;
    border: 1px solid #333;
    color: #fff;
}

.right-sidebar .search-bar .form-control::placeholder {
    color: #666;
}

.right-sidebar .search-bar .form-control:focus {
    background-color: #1a1a1a;
    border-color: #1d9bf0;
    box-shadow: 0 0 0 0.25rem rgba(29, 155, 240, 0.25);
    color: #fff;
}

.right-sidebar .trending-section .trend-item,
.right-sidebar .suggested-users .user-item,
.right-sidebar .activity-item,
.right-sidebar .search-results .user-item {
    transition: background-color 0.2s;
}

.right-sidebar .hover-bg:hover {
    background-color: #1a1a1a;
}

.right-sidebar .suggested-users .btn-outline-primary,
.right-sidebar .search-results .btn-outline-primary {
    border-color: #1d9bf0;
    color: #1d9bf0;
    font-size: 0.8rem;
    padding: 2px 10px;
}

.right-sidebar .suggested-users .btn-outline-primary:hover,
.right-sidebar .search-results .btn-outline-primary:hover {
    background-color: #1d9bf0;
    color: #fff;
}

.right-sidebar .stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.5rem;
}

.right-sidebar .stat-item {
    background-color: #1a1a1a;
    border-radius: 0.5rem;
}

.right-sidebar .stat-number {
    font-size: 1.2rem;
    font-weight: bold;
    color: #1d9bf0;
}

.right-sidebar .stat-label {
    color: #666;
}

.right-sidebar .quick-actions .btn {
    font-size: 0.9rem;
}

/* Scrollbar */
.right-sidebar::-webkit-scrollbar {
    width: 8px;
}

.right-sidebar::-webkit-scrollbar-track {
    background: #000;
}

.right-sidebar::-webkit-scrollbar-thumb {
    background: #333;
    border-radius: 4px;
}

.right-sidebar::-webkit-scrollbar-thumb:hover {
    background: #666;
}

/* Loading animation */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #1d9bf0;
    border-top: 2px solid transparent;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

@media (max-width: 991px) {
  .right-sidebar, .right-sidebar-container {
    display: none !important;
    width: 0 !important;
    min-width: 0 !important;
    max-width: 0 !important;
    position: static !important;
    left: auto !important;
    right: auto !important;
    z-index: 0 !important;
  }
}
</style>

<script>
// Auto-submit search form on input
document.querySelector('input[name="search"]').addEventListener('input', function() {
    if (this.value.length > 2 || this.value.length === 0) {
        setTimeout(() => {
            document.getElementById('searchForm').submit();
        }, 500);
    }
});

// Clear search
function clearSearch() {
    window.location.href = window.location.pathname;
}

// Follow user functionality

// Show trend options (placeholder for future functionality)
function showTrendOptions(hashtag) {
    // This could open a modal or dropdown with options like "Not interested", "Follow topic", etc.
    // Placeholder for future functionality
}

// Refresh sidebar content periodically
setInterval(() => {
    // Only refresh if user is not actively interacting
    if (!document.querySelector('.right-sidebar:hover')) {
        // This could be enhanced to only refresh specific sections
        // For now, it's a placeholder for future real-time updates
    }
}, 30000); // Refresh every 30 seconds
</script>