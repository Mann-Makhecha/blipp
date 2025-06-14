<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blipp - communities</title>
    <link rel="icon" href="../favicon (2).png" type="image/x-icon">
</head>
<body>
    <?php
require_once 'includes/header.php';
require_once '../includes/settings.php';

// Display success message if set
if (!empty($_SESSION['success_message'])) {
    echo "<script>alert('" . htmlspecialchars($_SESSION['success_message']) . "');</script>";
    unset($_SESSION['success_message']);
}

// First, let's check if the communities table exists and its structure
$table_check = $mysqli->query("SHOW TABLES LIKE 'communities'");
if ($table_check->num_rows === 0) {
    die("Communities table does not exist. Please run the database setup script.");
}

// Check table structure
$columns = $mysqli->query("SHOW COLUMNS FROM communities");
$column_names = [];
while ($column = $columns->fetch_assoc()) {
    $column_names[] = $column['Field'];
}

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Handle community actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_community'])) {
        $community_id = (int)$_POST['community_id'];
        $mysqli->query("DELETE FROM communities WHERE community_id = $community_id");
        header("Location: communities.php");
        exit();
    }
}

// Get all communities with member counts
$communities = $mysqli->query("
    SELECT c.*, 
           COUNT(DISTINCT cm.user_id) as member_count,
           COUNT(DISTINCT p.post_id) as post_count,
           u.username as creator_username
    FROM communities c
    LEFT JOIN community_members cm ON c.community_id = cm.community_id
    LEFT JOIN posts p ON c.community_id = p.community_id
    LEFT JOIN users u ON c.creator_id = u.user_id
    GROUP BY c.community_id
    ORDER BY c.created_at DESC
");
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h1 class="h3 mb-0">Communities</h1>
                        <a href="../create_community.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create New Community
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Creator</th>
                                    <th>Members</th>
                                    <th>Posts</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($community = $communities->fetch_assoc()): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($community['name']) ?></strong>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;">
                                                <?= htmlspecialchars($community['description']) ?>
                                            </div>
                                        </td>
                                        <td>@<?= htmlspecialchars($community['creator_username']) ?></td>
                                        <td><?= number_format($community['member_count']) ?></td>
                                        <td><?= number_format($community['post_count']) ?></td>
                                        <td>
                                            <?php if ($community['is_private']): ?>
                                                <span class="badge bg-warning">Private</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Public</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('M d, Y', strtotime($community['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" class="btn btn-sm btn-primary" 
                                                        onclick="viewCommunity(<?= $community['community_id'] ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($_SESSION['role'] === 'admin'): ?>
                                                    <button type="button" class="btn btn-sm btn-info" 
                                                            onclick="togglePrivacy(<?= $community['community_id'] ?>, <?= $community['is_private'] ?>)">
                                                        <i class="fas fa-lock"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="confirmDelete(<?= $community['community_id'] ?>)">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
    </div>
</div>

<!-- Create Community Modal -->
<div class="modal fade" id="createCommunityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Community</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="../create_community.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="name" class="form-label">Community Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_private" name="is_private">
                            <label class="form-check-label" for="is_private">
                                Make this community private
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Community</button>
                </div>
            </form>
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
                Are you sure you want to delete this community? This action cannot be undone and will delete all posts and members.
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="community_id" id="deleteCommunityId">
                    <input type="hidden" name="action" value="delete">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Delete Community</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Privacy Toggle Modal -->
<div class="modal fade" id="privacyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Privacy Setting</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to change the privacy setting of this community?
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" name="community_id" id="privacyCommunityId">
                    <input type="hidden" name="action" value="toggle_private">
                    <input type="hidden" name="is_private" id="privacyValue">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Change Privacy</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- View Community Modal -->
<div class="modal fade" id="viewCommunityModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Community Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <h4 id="communityName"></h4>
                        <p id="communityDescription" class="text-muted"></p>
                        <div class="mb-3">
                            <strong>Created by:</strong> <span id="communityCreator"></span>
                        </div>
                        <div class="mb-3">
                            <strong>Created on:</strong> <span id="communityCreated"></span>
                        </div>
                        <div class="mb-3">
                            <strong>Status:</strong> <span id="communityStatus"></span>
                        </div>
                        <div class="mb-3">
                            <strong>Members:</strong> <span id="communityMembers"></span>
                        </div>
                        <div class="mb-3">
                            <strong>Posts:</strong> <span id="communityPosts"></span>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div id="communityBanner" class="mb-3">
                            <!-- Banner image will be displayed here -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(communityId) {
    document.getElementById('deleteCommunityId').value = communityId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

function togglePrivacy(communityId, currentPrivacy) {
    document.getElementById('privacyCommunityId').value = communityId;
    document.getElementById('privacyValue').value = currentPrivacy ? 0 : 1;
    new bootstrap.Modal(document.getElementById('privacyModal')).show();
}

function viewCommunity(communityId) {
    // Fetch community details
    fetch(`get_community.php?id=${communityId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const community = data.community;
                
                // Update modal content
                document.getElementById('communityName').textContent = community.name;
                document.getElementById('communityDescription').textContent = community.description;
                document.getElementById('communityCreator').textContent = '@' + community.creator_username;
                document.getElementById('communityCreated').textContent = new Date(community.created_at).toLocaleDateString();
                document.getElementById('communityStatus').innerHTML = community.is_private ? 
                    '<span class="badge bg-warning">Private</span>' : 
                    '<span class="badge bg-success">Public</span>';
                document.getElementById('communityMembers').textContent = community.member_count;
                document.getElementById('communityPosts').textContent = community.post_count;
                
                // Handle banner image
                const bannerDiv = document.getElementById('communityBanner');
                if (community.banner_image) {
                    bannerDiv.innerHTML = `<img src="${community.banner_image}" class="img-fluid rounded" alt="Community Banner">`;
                } else {
                    bannerDiv.innerHTML = '<div class="alert alert-info">No banner image</div>';
                }
                
                // Show modal
                new bootstrap.Modal(document.getElementById('viewCommunityModal')).show();
            } else {
                alert('Error loading community details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading community details');
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