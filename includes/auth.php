<?php
/**
 * Authentication & Session Helpers
 */

function initSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        if (isApiRequest()) {
            jsonResponse(['error' => 'Unauthorized'], 401);
        }
        header('Location: ' . APP_URL . '/?page=login');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (($_SESSION['user_role'] ?? '') !== 'admin') {
        if (isApiRequest()) {
            jsonResponse(['error' => 'Forbidden'], 403);
        }
        header('Location: ' . APP_URL . '/?page=dashboard');
        exit;
    }
}

function currentUser(): ?array {
    if (!isLoggedIn()) return null;
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'],
        'email' => $_SESSION['email'],
        'role' => $_SESSION['user_role'],
        'avatar' => $_SESSION['avatar'] ?? null,
    ];
}

function currentUserId(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function isAdmin(): bool {
    return ($_SESSION['user_role'] ?? '') === 'admin';
}

function generateCSRF(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function isApiRequest(): bool {
    return str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/myowncloud/api/') ||
           (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') ||
           str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

function setLoginSession(array $user): void {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['avatar'] = $user['avatar'];
    session_regenerate_id(true);
}

function logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
