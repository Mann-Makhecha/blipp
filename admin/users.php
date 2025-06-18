<?php
require_once __DIR__ . '/includes/auth.php';
// session_start();

// Debugging: Check if $mysqli is properly initialized
if (!isset($mysqli) || $mysqli->connect_error) {
    die("Database connection failed in users.php: " . ($mysqli->connect_error ?? "Unknown error"));
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = (int)$_POST['user_id'];
        $action = $_POST['action'];

        switch ($action) {
            case 'delete':
                $stmt = $mysqli->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "User deleted successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to delete user.";
                }
                break;

            case 'toggle_role':
                $stmt = $mysqli->prepare("UPDATE users SET role = CASE WHEN role = 'admin' THEN 'user' ELSE 'admin' END WHERE user_id = ?");
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "User role updated successfully.";
                } else {
                    $_SESSION['error_message'] = "Failed to update user role.";
                }
                break;

            case 'verify':
                // Start transaction
                $mysqli->begin_transaction();
                try {
                    // Check if verification badge exists, if not create it
                    $badge_check = $mysqli->prepare("SELECT badge_id FROM badges WHERE name = 'Verified'");
                    $badge_check->execute();
                    $badge_result = $badge_check->get_result();

                    $verification_badge_id = null;
                    if ($badge_result->num_rows === 0) {
                        // Create verification badge
                        $badge_insert = $mysqli->prepare("
                            INSERT INTO badges (name, description, image_path) 
                            VALUES ('Verified', 'Verified account with blue tick', '/assets/badges/verified.png')
                        ");
                        $badge_insert->execute();
                        $verification_badge_id = $mysqli->insert_id;
                    } else {
                        $verification_badge_id = $badge_result->fetch_assoc()['badge_id'];
                    }
                    $badge_check->close();

                    // Check if user already has verification badge
                    $user_badge_check = $mysqli->prepare("
                        SELECT user_badge_id FROM user_badges 
                        WHERE user_id = ? AND badge_id = ?
                    ");
                    $user_badge_check->bind_param("ii", $user_id, $verification_badge_id);
                    $user_badge_check->execute();

                    if ($user_badge_check->get_result()->num_rows === 0) {
                        // Award verification badge to user
                        $user_badge_insert = $mysqli->prepare("
                            INSERT INTO user_badges (user_id, badge_id, awarded_at) 
                            VALUES (?, ?, NOW())
                        ");
                        $user_badge_insert->bind_param("ii", $user_id, $verification_badge_id);
                        $user_badge_insert->execute();

                        // Add 10,000 points
                        $points_update = $mysqli->prepare("UPDATE users SET points = points + 10000 WHERE user_id = ?");
                        $points_update->bind_param("i", $user_id);
                        $points_update->execute();

                        // Record point transaction
                        $transaction = $mysqli->prepare("
                            INSERT INTO point_transactions (user_id, points, description, transaction_date) 
                            VALUES (?, 10000, 'Verification bonus - Blue tick awarded', NOW())
                        ");
                        $transaction->bind_param("i", $user_id);
                        $transaction->execute();

                        $mysqli->commit();
                        $_SESSION['success_message'] = "User verified successfully with blue tick and 10,000 points!";
                    } else {
                        $mysqli->rollback();
                        $_SESSION['error_message'] = "User is already verified.";
                    }
                    $user_badge_check->close();
                } catch (Exception $e) {
                    $mysqli->rollback();
                    $_SESSION['error_message'] = "Failed to verify user: " . $e->getMessage();
                }
                break;

            case 'unverify':
                // Start transaction
                $mysqli->begin_transaction();
                try {
                    // Get verification badge ID
                    $badge_check = $mysqli->prepare("SELECT badge_id FROM badges WHERE name = 'Verified'");
                    $badge_check->execute();
                    $badge_result = $badge_check->get_result();

                    if ($badge_result->num_rows > 0) {
                        $verification_badge_id = $badge_result->fetch_assoc()['badge_id'];

                        // Remove verification badge from user
                        $user_badge_delete = $mysqli->prepare("
                            DELETE FROM user_badges 
                            WHERE user_id = ? AND badge_id = ?
                        ");
                        $user_badge_delete->bind_param("ii", $user_id, $verification_badge_id);
                        $user_badge_delete->execute();

                        // Deduct 10,000 points (if they have enough)
                        $points_update = $mysqli->prepare("UPDATE users SET points = GREATEST(points - 10000, 0) WHERE user_id = ?");
                        $points_update->bind_param("i", $user_id);
                        $points_update->execute();

                        // Record point transaction
                        $transaction = $mysqli->prepare("
                            INSERT INTO point_transactions (user_id, points, description, transaction_date) 
                            VALUES (?, -10000, 'Verification revoked - Blue tick removed', NOW())
                        ");
                        $transaction->bind_param("i", $user_id);
                        $transaction->execute();

                        $mysqli->commit();
                        $_SESSION['success_message'] = "User verification revoked successfully!";
                    } else {
                        $mysqli->rollback();
                        $_SESSION['error_message'] = "Verification badge not found.";
                    }
                    $badge_check->close();
                } catch (Exception $e) {
                    $mysqli->rollback();
                    $_SESSION['error_message'] = "Failed to revoke verification: " . $e->getMessage();
                }
                break;
        }
    }
}

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';

// Build query with verification status
$query = "SELECT u.*, 
    (SELECT COUNT(*) FROM posts WHERE user_id = u.user_id) as post_count,
    (SELECT COUNT(*) FROM community_members WHERE user_id = u.user_id) as community_count,
    (SELECT COUNT(*) FROM user_badges ub 
     JOIN badges b ON ub.badge_id = b.badge_id 
     WHERE ub.user_id = u.user_id AND b.name = 'Verified') as is_verified
    FROM users u WHERE 1=1";
$params = [];
$types = "";

if ($search) {
    $query .= " AND (u.username LIKE ? OR u.email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

if ($role_filter) {
    $query .= " AND u.role = ?";
    $params[] = $role_filter;
    $types .= "s";
}

$query .= " ORDER BY u.created_at DESC";

// Prepare and execute query
$stmt = $mysqli->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
?>
<?php require_once 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Users Management</h1>
        <div>

            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus"></i> Add User
            </button>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <div class="input-group">
                        <input type="text" class="form-control" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-outline-secondary" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="role" onchange="this.form.submit()">
                        <option value="">All Roles</option>
                        <option value="user" <?= $role_filter === 'user' ? 'selected' : '' ?>>User</option>
                        <option value="admin" <?= $role_filter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                </div>
                <?php if ($search || $role_filter): ?>
                    <div class="col-md-3">
                        <a href="users.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear Filters
                        </a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Verification</th>
                            <th>Points</th>
                            <th>Posts</th>
                            <th>Communities</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-circle fa-2x text-gray me-2"></i>
                                        <div>
                                            <h6 class="mb-0">
                                                <?= htmlspecialchars($user['username']) ?>
                                                <?php if ($user['is_verified'] > 0): ?>
                                                    <i class="fas fa-check-circle text-primary ms-1" title="Verified Account"></i>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="text-gray">ID: <?= $user['user_id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $user['role'] === 'admin' ? 'primary' : 'secondary' ?>">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['is_verified'] > 0): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle"></i> Verified
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-times-circle"></i> Not Verified
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= number_format($user['points'] ?? 0) ?></td>
                                <td><?= number_format($user['post_count']) ?></td>
                                <td><?= number_format($user['community_count']) ?></td>
                                <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <div class="btn-group">
                                        <?php if ($user['is_verified'] > 0): ?>
                                            <form method="POST" style="display: inline-block; margin-right: 5px;">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <input type="hidden" name="action" value="unverify">
                                                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Are you sure you want to revoke verification for this user?')" title="Revoke Verification">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display: inline-block; margin-right: 5px;">
                                                <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                <input type="hidden" name="action" value="verify">
                                                <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Are you sure you want to verify this user? They will receive a blue tick and 10,000 points.')" title="Verify User">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display: inline-block; margin-right: 5px;">
                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                            <input type="hidden" name="action" value="toggle_role">
                                            <button type="submit" class="btn btn-sm btn-info" onclick="return confirm('Are you sure you want to change this user\'s role?')" title="Toggle Role">
                                                <i class="fas fa-user-shield"></i>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline-block;">
                                            <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')" title="Delete User">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
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

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="add_user.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" name="username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select" name="role">
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this user? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <input type="hidden" name="action" value="delete">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete User</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function confirmDelete(userId) {
        document.getElementById('deleteUserId').value = userId;
        new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
    }
</script>
</body>

</html>