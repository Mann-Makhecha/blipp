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
        <a href="index.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-house"></i>
        </a>
        <a href="explore.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'explore.php' ? 'active' : ''; ?>">
            <i class="fa-solid fa-magnifying-glass"></i>
        </a>
        <a href="Communities.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'Communities.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
        </a>
        <a href="message.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'message.php' ? 'active' : ''; ?>">
            <i class="fas fa-envelope"></i>
        </a>
        <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
        </a>
    </nav>
</body>
</html>