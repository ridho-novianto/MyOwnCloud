<?php
/**
 * MyOwnCloud - Main Router
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

initSession();

$page = $_GET['page'] ?? 'dashboard';

// Public pages (no auth required)
$publicPages = ['login', 'register'];

// Handle API requests
if (str_starts_with($page, 'api/')) {
    $apiFile = __DIR__ . '/api/' . basename(str_replace('api/', '', $page)) . '.php';
    if (file_exists($apiFile)) {
        require_once $apiFile;
    } else {
        jsonResponse(['error' => 'API endpoint not found'], 404);
    }
    exit;
}

// Redirect to login if not authenticated
if (!in_array($page, $publicPages) && !isLoggedIn()) {
    header('Location: ' . APP_URL . '/?page=login');
    exit;
}

// Redirect to dashboard if already logged in
if (in_array($page, $publicPages) && isLoggedIn()) {
    header('Location: ' . APP_URL . '/?page=dashboard');
    exit;
}

// Allowed pages
$allowedPages = ['login', 'register', 'dashboard', 'tasks', 'links', 'files', 'admin', 'profile', 'notifications'];
if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

// Admin-only pages
if ($page === 'admin' && !isAdmin()) {
    header('Location: ' . APP_URL . '/?page=dashboard');
    exit;
}

// Load page
$pageFile = __DIR__ . '/pages/' . $page . '.php';
if (file_exists($pageFile)) {
    if (in_array($page, $publicPages)) {
        require_once $pageFile;
    } else {
        require_once $pageFile;
    }
} else {
    echo '404 - Page not found';
}
