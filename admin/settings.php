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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
        $maintenance_message = trim($_POST['maintenance_message']);
        $require_email_verification = isset($_POST['require_email_verification']) ? 1 : 0;
        $community_creation_points = (int)$_POST['community_creation_points'];

        try {
            // Update maintenance mode
            $stmt = $mysqli->prepare("UPDATE admin_settings SET setting_value = ? WHERE setting_key = 'maintenance_mode'");
            $stmt->bind_param("s", $maintenance_mode);
            $stmt->execute();
            $stmt->close();

            // Update maintenance message
            $stmt = $mysqli->prepare("UPDATE admin_settings SET setting_value = ? WHERE setting_key = 'maintenance_message'");
            $stmt->bind_param("s", $maintenance_message);
            $stmt->execute();
            $stmt->close();

            // Update email verification requirement
            $stmt = $mysqli->prepare("UPDATE admin_settings SET setting_value = ? WHERE setting_key = 'require_email_verification'");
            $stmt->bind_param("s", $require_email_verification);
            $stmt->execute();
            $stmt->close();

            // Update community creation points
            $stmt = $mysqli->prepare("UPDATE admin_settings SET setting_value = ? WHERE setting_key = 'community_creation_points'");
            $stmt->bind_param("s", $community_creation_points);
            $stmt->execute();
            $stmt->close();

            $success = "Settings updated successfully.";
        } catch (Exception $e) {
            $error = "Error updating settings: " . $e->getMessage();
        }
    }
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
                    <h5 class="card-title mb-0">Maintenance Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                       <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="maintenance_mode">Enable Maintenance Mode</label>
                            </div>
                            <small class="text-muted">When enabled, only administrators can access the site.</small>
                        </div>

                        <div class="mb-3">
                            <label for="maintenance_message" class="form-label">Maintenance Message</label>
                            <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3"><?= htmlspecialchars($settings['maintenance_message'] ?? '') ?></textarea>
                            <small class="text-muted">This message will be displayed to users during maintenance.</small>
                        </div>
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