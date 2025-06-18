<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title></title>
    <link rel="stylesheet" href="css/mobilemenu.css">
</head>
<body>
    <!-- Mobile Bottom Navigation -->
    <nav class="mobile-nav">
        <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" aria-label="Home">
            <i class="fas fa-house"></i>
        </a>
        <a href="explore.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'explore.php' ? 'active' : ''; ?>" aria-label="Explore">
            <i class="fa-solid fa-magnifying-glass"></i>
        </a>
        <a href="communities.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'communities.php' ? 'active' : ''; ?>" aria-label="Communities">
            <i class="fas fa-users"></i>
        </a>
       
        <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>" aria-label="Profile">
            <i class="fas fa-user"></i>
        </a>
    </nav>
    <script>
    // Force full page reload on every mobile menu link click
    // (prevents SPA-like behavior or browser caching issues)
    document.querySelectorAll('.mobile-nav .nav-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            window.location.href = this.href;
        });
    });
    </script>
</body>
</html>