<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blipp - setting</title>
    <link rel="icon" href="../favicon (2).png" type="image/x-icon">
</head>
<body>
    <?php
require_once '../includes/db.php';
require_once 'includes/auth.php';
require_once '../includes/settings.php';

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Update site name
    $site_name = trim($_POST['site_name']);
    $stmt = $mysqli->prepare("UPDATE admin_settings SET setting_value = ? WHERE setting_key = 'site_name'");
    $stmt->bind_param("s", $site_name);
    $stmt->execute();

    // Update site description
    $site_description = trim($_POST['site_description']);
    $stmt = $mysqli->prepare("UPDATE admin_settings SET setting_value = ? WHERE setting_key = 'site_description'");
    $stmt->bind_param("s", $site_description);
    $stmt->execute();

    // Redirect to prevent form resubmission
    header("Location: settings.php?success=1");
    exit();
}

// Get current settings
$settings = [];
$result = $mysqli->query("SELECT setting_key, setting_value FROM admin_settings");
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Left Column -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">General Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="site_name" class="form-label">Site Name</label>
                            <input type="text" class="form-control" id="site_name" name="site_name" value="<?= htmlspecialchars($settings['site_name'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label for="site_description" class="form-label">Site Description</label>
                            <textarea class="form-control" id="site_description" name="site_description" rows="3"><?= htmlspecialchars($settings['site_description'] ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">General Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="require_email_verification" name="require_email_verification" 
                                       <?= ($settings['require_email_verification'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="require_email_verification">Require Email Verification</label>
                            </div>
                            <small class="text-muted">When enabled, users must verify their email before they can use the site.</small>
                        </div>

                        <div class="mb-3">
                            <label for="community_creation_points" class="form-label">Points Required for Community Creation</label>
                            <input type="number" class="form-control" id="community_creation_points" name="community_creation_points" 
                                   value="<?= htmlspecialchars($settings['community_creation_points'] ?? '1000') ?>" min="0">
                            <small class="text-muted">Minimum points required for users to create a community.</small>
                        </div>

                        <button type="submit" name="update_settings" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if ($success || $error): ?>
    <div class="row mt-3">
        <div class="col-12">
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

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