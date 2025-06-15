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

// Handle API requests
if (isset($_GET['action']) && $_GET['action'] === 'get_post') {
    header('Content-Type: application/json');
    
    $post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if (!$post_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
        exit();
    }

    // Fetch post data
    $stmt = $mysqli->prepare("
        SELECT 
            p.*,
            u.username,
            c.name as community_name,
            (SELECT COUNT(*) FROM comments WHERE post_id = p.post_id) as comment_count,
            (SELECT COUNT(*) FROM post_reports WHERE post_id = p.post_id) as report_count
        FROM posts p
        JOIN users u ON p.user_id = u.user_id
        LEFT JOIN communities c ON p.community_id = c.community_id
        WHERE p.post_id = ?
    ");

    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve post data.']);
        exit();
    }

    $stmt->bind_param("i", $post_id);
    
    if (!$stmt->execute()) {
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve post data.']);
        exit();
    }

    $result = $stmt->get_result();

    if ($post = $result->fetch_assoc()) {
        // Fetch associated files
        $files_stmt = $mysqli->prepare("
            SELECT file_name, file_path, file_type
            FROM files
            WHERE post_id = ?
        ");

        if (!$files_stmt) {
            echo json_encode(['success' => false, 'message' => 'Failed to retrieve file data.']);
            exit();
        }

        $files_stmt->bind_param("i", $post_id);
        
        if (!$files_stmt->execute()) {
            echo json_encode(['success' => false, 'message' => 'Failed to retrieve file data.']);
            exit();
        }

        $files_result = $files_stmt->get_result();
        
        $files = [];
        while ($file = $files_result->fetch_assoc()) {
            $files[] = $file;
        }
        
        $post['files'] = $files;
        $post['created_at'] = date('F j, Y g:i A', strtotime($post['created_at']));
        
        echo json_encode(['success' => true, 'post' => $post]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Post not found']);
    }
    exit();
}

// Handle view post page
if (isset($_GET['view']) && $_GET['view'] === 'post') {
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

    // Include view post template
    require_once 'includes/header.php';
    include 'templates/view_post.php';
    exit();
}

// Main posts listing page
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3">Posts Management</h1>
    </div>

    <!-- Search and Filter -->
    <div class="card shadow mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" placeholder="Search posts..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="sort">
                        <option value="newest" <?= ($_GET['sort'] ?? '') === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="oldest" <?= ($_GET['sort'] ?? '') === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="reports" <?= ($_GET['sort'] ?? '') === 'reports' ? 'selected' : '' ?>>Most Reported</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Posts Table -->
    <div class="card shadow">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Author</th>
                            <th>Content</th>
                            <th>Community</th>
                            <th>Created</th>
                            <th>Reports</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
                        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
                        
                        $query = "
                            SELECT 
                                p.*,
                                u.username,
                                c.name as community_name,
                                (SELECT COUNT(*) FROM post_reports WHERE post_id = p.post_id) as report_count
                            FROM posts p
                            JOIN users u ON p.user_id = u.user_id
                            LEFT JOIN communities c ON p.community_id = c.community_id
                            WHERE 1=1
                        ";
                        
                        if ($search) {
                            $query .= " AND (p.content LIKE ? OR u.username LIKE ?)";
                        }
                        
                        switch ($sort) {
                            case 'oldest':
                                $query .= " ORDER BY p.created_at ASC";
                                break;
                            case 'reports':
                                $query .= " ORDER BY report_count DESC, p.created_at DESC";
                                break;
                            default:
                                $query .= " ORDER BY p.created_at DESC";
                        }
                        
                        $stmt = $mysqli->prepare($query);
                        
                        if ($search) {
                            $search_param = "%$search%";
                            $stmt->bind_param("ss", $search_param, $search_param);
                        }
                        
                        $stmt->execute();
                        $posts = $stmt->get_result();
                        
                        while ($post = $posts->fetch_assoc()):
                        ?>
                        <tr>
                            <td><?= $post['post_id'] ?></td>
                            <td>@<?= htmlspecialchars($post['username']) ?></td>
                            <td><?= mb_substr(htmlspecialchars($post['content']), 0, 100) ?>...</td>
                            <td>
                                <?php if ($post['community_name']): ?>
                                    <?= htmlspecialchars($post['community_name']) ?>
                                <?php else: ?>
                                    <span class="text-muted">None</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y H:i', strtotime($post['created_at'])) ?></td>
                            <td>
                                <?php if ($post['report_count'] > 0): ?>
                                    <span class="badge bg-danger"><?= $post['report_count'] ?></span>
                                <?php else: ?>
                                    <span class="badge bg-success">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?view=post&id=<?= $post['post_id'] ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $post['post_id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(postId) {
    if (confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
        window.location.href = `?action=delete&id=${postId}`;
    }
}
</script>

