<?php
// No session_start() since it's already called in index.php

// Mock data for trending topics (replace with actual data from database if needed)
$trending_topics = [
    ['hashtag' => '#SpaceXLaunch', 'posts' => '12K'],
    ['hashtag' => '#AIRevolution', 'posts' => '8.5K'],
    ['hashtag' => '#TechTrends', 'posts' => '5.2K'],
];

// Mock data for suggested users (replace with actual data from database if needed)
$suggested_users = [
    ['username' => 'Elon Musk', 'handle' => '@elonmusk', 'verified' => true],
    ['username' => 'Jane Doe', 'handle' => '@janedoe', 'verified' => false],
    ['username' => 'TechBit', 'handle' => '@techbit', 'verified' => true],
];
?>

<div class="right-sidebar">
    <!-- Search Bar -->
    <div class="search-bar mb-4">
        <div class="input-group">
            <span class="input-group-text bg-dark border-dark text-white">
                <i class="fas fa-search"></i>
            </span>
            <input type="text" class="form-control bg-dark border-dark text-white" placeholder="Search Blipp" aria-label="Search">
        </div>
    </div>

    <!-- Trending Topics -->
    <div class="trending-section mb-4">
        <h5 class="fw-bold mb-3">Trends for You</h5>
        <?php foreach ($trending_topics as $trend): ?>
            <div class="trend-item p-2 mb-2 rounded hover-bg">
                <div class="d-flex justify-content-between">
                    <div>
                        <a href="#" class="text-white text-decoration-none"><?= htmlspecialchars($trend['hashtag']) ?></a>
                        <div class="text-white small"><?= htmlspecialchars($trend['posts']) ?> Posts</div>
                    </div>
                    <div>
                        <a href="#" class="text-white"><i class="fas fa-ellipsis-h"></i></a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Suggested Users -->
    <div class="suggested-users mb-4">
        <h5 class="fw-bold mb-3">Who to Follow</h5>
        <?php foreach ($suggested_users as $user): ?>
            <div class="user-item d-flex align-items-center p-2 mb-2 rounded hover-bg">
                <i class="fas fa-user-circle fa-2x me-2" style="color: #666;"></i>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center">
                        <a href="#" class="text-white text-decoration-none fw-bold">
                            <?= htmlspecialchars($user['username']) ?>
                        </a>
                        <?php if ($user['verified']): ?>
                            <i class="fas fa-check-circle text-primary ms-1" style="font-size: 0.9rem;"></i>
                        <?php endif; ?>
                    </div>
                    <div class="text-white small"><?= htmlspecialchars($user['handle']) ?></div>
                </div>
                <button class="btn btn-outline-primary btn-sm rounded-pill">Follow</button>
            </div>
        <?php endforeach; ?>
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
.right-sidebar .suggested-users .user-item {
    transition: background-color 0.2s;
}

.right-sidebar .hover-bg:hover {
    background-color: #1a1a1a;
}

.right-sidebar .suggested-users .btn-outline-primary {
    border-color: #1d9bf0;
    color: #1d9bf0;
    font-size: 0.8rem;
    padding: 2px 10px;
}

.right-sidebar .suggested-users .btn-outline-primary:hover {
    background-color: #1d9bf0;
    color: #fff;
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
</style>