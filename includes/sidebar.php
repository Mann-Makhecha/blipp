<?php
// Removed session_start() since it's already called in index.php

// Check if user is logged in
$user_id = $_SESSION['user_id'] ?? null;


if ($user_id) {
    // Fetch user details
    $stmt = $mysqli->prepare("SELECT username FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $username = $row['username'];
        $handle = "@" . $row['username'];
    }
    $stmt->close();
}


?>

<!DOCTYPE html>
<html lang="en" data-coreui-theme="dark">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>blipp</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/css/coreui.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://kit.fontawesome.com/c508d42d1a.js" crossorigin="anonymous"></script>
  <style>
    body { background-color: #000; }
    .sidebar {
      background-color: #000;
      color: #fff;
      width: 300px;
      height: 100vh;
      position: fixed;
      top: 0;
      left: 0;
      border: none;
      padding-top: 10px;
      z-index: 1000;
    }
    .sidebar-header {
      padding: 10px 20px;
      border-bottom: none;
    }
    .sidebar-brand {
      font-size: 1.5rem;
      font-weight: bold;
      color: #fff;
      display: flex;
      align-items: center;
    }
    .sidebar-brand i {
      margin-right: 10px;
      color: #1d9bf0;
    }
    .sidebar-nav {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    .nav-item {
      margin: 10px 0;
    }
    .nav-link {
      color: #fff;
      font-size: 1.2rem;
      padding: 10px 20px;
      display: flex;
      align-items: center;
      border-radius: 50px;
      transition: background-color 0.2s, color 0.2s;
    }
    .nav-link:hover, .nav-link.active {
      background-color: #1a1a1a;
      color: #1d9bf0;
    }
    .nav-link i {
      margin-right: 15px;
      font-size: 1.4rem;
      width: 24px;
      text-align: center;
    }
    .nav-link strong {
      color: #1d9bf0;
    }
    .profile-section {
      position: absolute;
      bottom: 80px;
      width: 100%;
      padding: 10px 20px;
    }
    .profile-section .profile-link {
      display: flex;
      align-items: center;
      color: #fff;
      padding: 10px;
      border-radius: 50px;
      transition: background-color 0.2s;
    }
    .profile-section .profile-link:hover {
      background-color: #1a1a1a;
    }
    .profile-section .profile-info {
      margin-left: 10px;
    }
    .profile-section .profile-info .username {
      font-weight: bold;
      font-size: 1rem;
    }
    .profile-section .profile-info .handle {
      color: #666;
      font-size: 0.9rem;
    }
    .logout-section {
      position: absolute;
      bottom: 20px;
      width: 100%;
      padding: 10px 20px;
    }
  </style>
</head>
<body class="bg-dark text-white vh-100">
  <div class="sidebar d-none d-md-block">
    <div class="sidebar-header">
      <div class="sidebar-brand">
        <img src="favicon (2).png" alt="">
        <span>Blipp</span>
      </div>
    </div>
    <ul class="sidebar-nav">
      <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">
          <i class="fas fa-house"></i>
          <span>Home</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'explore.php' ? 'active' : '' ?>" href="explore.php">
         <i class="fa-solid fa-magnifying-glass"></i>
          <span>Explore</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'Communities.php' ? 'active' : '' ?>" href="Communities.php">
          <i class="fas fa-users"></i>
          <span>Communities</span>
        </a>
      </li>
     
      <li class="nav-item pro-link">
        <a class="nav-link" href="https://coreui.io/pro/">
          <i class="fas fa-star"></i>
          <span>Try Blipp <strong>PRO</strong></span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : '' ?>" href="profile.php">
          <i class="fas fa-user"></i>
          <span>Profile</span>
        </a>
      </li>
    </ul>

    <!-- Profile Section -->
    <div class="profile-section">
      <a href="profile.php" class="profile-link">
        <i class="fas fa-user-circle fa-2x" style="color: #666;"></i>
        <div class="profile-info">
          <div class="username"><?= htmlspecialchars($username) ?></div>
          <div class="handle"><?= htmlspecialchars($handle) ?></div>
        </div>
      </a>
    </div>

    <!-- Logout Button -->
    <div class="logout-section">
      <a class="nav-link" href="logout.php">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@5.0.0/dist/js/coreui.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>