<?php
/**
 * Header & Sidebar Layout Template
 */
$user = currentUser();
$currentPage = $_GET['page'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle ?? 'Dashboard') ?> - <?= APP_NAME ?></title>
    <meta name="description" content="MyOwnCloud - Personal Cloud Workspace">
    <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
    <meta name="theme-color" content="#0a0e1a">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="page-<?= htmlspecialchars($currentPage) ?>">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="app-logo">
                <div class="logo-icon">
                    <i class="fas fa-cloud"></i>
                </div>
                <div class="logo-text">
                    <h1><?= APP_NAME ?></h1>
                    <span>// workspace</span>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <span class="nav-label">MENU UTAMA</span>
                <a href="?page=dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                    <i class="fas fa-th-large"></i>
                    <span>Dashboard</span>
                </a>
                <a href="?page=tasks" class="nav-item <?= $currentPage === 'tasks' ? 'active' : '' ?>">
                    <i class="fas fa-check-square"></i>
                    <span>Task & Todo</span>
                </a>
                <a href="?page=links" class="nav-item <?= $currentPage === 'links' ? 'active' : '' ?>">
                    <i class="fas fa-link"></i>
                    <span>Link Manager</span>
                </a>
                <a href="?page=files" class="nav-item <?= $currentPage === 'files' ? 'active' : '' ?>">
                    <i class="fas fa-folder-open"></i>
                    <span>File Manager</span>
                </a>
                <a href="?page=notes" class="nav-item <?= $currentPage === 'notes' ? 'active' : '' ?>">
                    <i class="fas fa-sticky-note"></i>
                    <span>Notes</span>
                </a>
            </div>

            <?php if (isAdmin()): ?>
            <div class="nav-section">
                <span class="nav-label">ADMIN</span>
                <a href="?page=admin" class="nav-item <?= $currentPage === 'admin' ? 'active' : '' ?>">
                    <i class="fas fa-cog"></i>
                    <span>Admin Panel</span>
                </a>
            </div>
            <?php endif; ?>

            <div class="nav-section">
                <span class="nav-label">PENGATURAN</span>
                <a href="?page=profile" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
                    <i class="fas fa-user"></i>
                    <span>Profil</span>
                </a>
                <a href="?page=notifications" class="nav-item <?= $currentPage === 'notifications' ? 'active' : '' ?>">
                    <i class="fas fa-bell"></i>
                    <span>Aktifkan Notifikasi</span>
                </a>
                <a href="#" class="nav-item logout-btn" onclick="handleLogout(event)">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?php if ($user['avatar']): ?>
                        <img src="<?= APP_URL ?>/uploads/avatars/<?= sanitize($user['avatar']) ?>" alt="Avatar">
                    <?php else: ?>
                        <span><?= strtoupper(substr($user['username'], 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="user-details">
                    <strong><?= sanitize($user['username']) ?></strong>
                    <small><?= strtoupper($user['role']) ?></small>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="top-header">
            <!-- Mobile sidebar toggle (inside header flow) -->
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="page-title">
                <i class="fas fa-<?= $pageIcon ?? 'th-large' ?>"></i>
                <h2><?= sanitize($pageTitle ?? 'Dashboard') ?></h2>
            </div>
            <div class="header-actions">
                <?php if (isset($headerSearch) && $headerSearch): ?>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="<?= $searchPlaceholder ?? 'Cari...' ?>">
                </div>
                <?php endif; ?>
                <?php if (isset($headerButton)): ?>
                <button class="btn btn-primary" onclick="<?= $headerButton['onclick'] ?? '' ?>" id="<?= $headerButton['id'] ?? '' ?>">
                    <i class="fas fa-plus"></i> <?= $headerButton['text'] ?? 'Tambah' ?>
                </button>
                <?php endif; ?>
                <?php if ($currentPage === 'dashboard'): ?>
                <div class="header-greeting" style="display: flex; align-items: center; gap: 15px;">
                    <div id="dashboardClock" style="font-family: 'Consolas', monospace; font-size: 1.1rem; color: var(--cyan); font-weight: 600; padding-right: 15px; border-right: 1px solid var(--border-color);">
                        --:--:--
                    </div>
                    <div>
                        Halo, <?= sanitize($user['username']) ?> <i class="fas fa-user-circle"></i>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="content-body">
