<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Blipp</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- jQuery (required for some Bootstrap features) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Popper.js -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        :root {
            --sidebar-width: 250px;
            --header-height: 60px;
        }
        
        body {
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .admin-header {
            height: var(--header-height);
            background: #343a40;
            color: white;
            position: fixed;
            top: 0;
            right: 0;
            left: var(--sidebar-width);
            z-index: 1000;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 4px rgba(0,0,0,.1);
        }
        
        .sidebar {
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            background: #343a40;
            padding: 1rem;
            color: white;
            z-index: 1001;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 2rem;
        }
        
        .nav-link {
            color: rgba(255,255,255,.8);
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .nav-link:hover {
            color: white;
            background: rgba(255,255,255,.1);
        }
        
        .nav-link.active {
            color: white;
            background: rgba(255,255,255,.2);
        }
        
        .nav-link i {
            width: 20px;
            text-align: center;
            margin-right: 0.5rem;
        }
        
        .admin-dropdown .dropdown-toggle::after {
            display: none;
        }
        
        .admin-dropdown .dropdown-menu {
            margin-top: 0.5rem;
        }
        
        .admin-dropdown .dropdown-item {
            padding: 0.5rem 1rem;
        }
        
        .admin-dropdown .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 0.5rem;
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,.075);
            margin-bottom: 1.5rem;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid rgba(0,0,0,.125);
            padding: 1rem 1.25rem;
        }
        
        .table th {
            border-top: none;
            background: #f8f9fa;
        }
        
        .btn-icon {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h4 class="mb-4">Admin Panel</h4>
        <nav class="nav flex-column">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : '' ?>" href="index.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : '' ?>" href="users.php">
                <i class="fas fa-users"></i> Users
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'posts.php' ? 'active' : '' ?>" href="posts.php">
                <i class="fas fa-file-alt"></i> Posts
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'reports.php' ? 'active' : '' ?>" href="reports.php">
                <i class="fas fa-flag"></i> Reports
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'communities.php' ? 'active' : '' ?>" href="communities.php">
                <i class="fas fa-users-cog"></i> Communities
            </a>
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : '' ?>" href="settings.php">
                <i class="fas fa-cog"></i> Settings
            </a>
        </nav>
    </div>

    <!-- Header -->
    <header class="admin-header">
        <div>
            <h5 class="mb-0">Welcome, <?= htmlspecialchars($admin['username'] ?? 'Admin') ?></h5>
        </div>
        <div class="admin-dropdown">
            <button class="btn btn-link text-white dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-user-circle"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content"> 