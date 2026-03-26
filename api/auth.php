<?php
/**
 * Auth API - Login, Register, Logout
 */
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (!$email || !$password) {
            jsonResponse(['error' => 'Email dan password harus diisi'], 400);
        }

        $db = getDB();
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? AND status = "active"');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonResponse(['error' => 'Email atau password salah'], 401);
        }

        setLoginSession($user);

        // Update last login
        $stmt = $db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
        $stmt->execute([$user['id']]);

        logActivity($user['id'], 'login', 'User logged in');
        jsonResponse(['success' => true, 'user' => ['id' => $user['id'], 'username' => $user['username'], 'role' => $user['role']]]);
        break;

    case 'register':
        $username = trim($input['username'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (!$username || !$email || !$password) {
            jsonResponse(['error' => 'Semua field harus diisi'], 400);
        }
        if (strlen($password) < 6) {
            jsonResponse(['error' => 'Password minimal 6 karakter'], 400);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Format email tidak valid'], 400);
        }

        $db = getDB();

        // Check existing
        $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$email, $username]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Email atau username sudah terdaftar'], 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, role, storage_quota) VALUES (?, ?, ?, "user", ?)');
        $stmt->execute([$username, $email, $hash, DEFAULT_STORAGE_QUOTA]);
        $userId = (int)$db->lastInsertId();

        // Auto login after register
        $user = ['id' => $userId, 'username' => $username, 'email' => $email, 'role' => 'user', 'avatar' => null];
        setLoginSession($user);

        logActivity($userId, 'register', 'New user registered');
        jsonResponse(['success' => true, 'user' => $user]);
        break;

    case 'logout':
        $userId = currentUserId();
        if ($userId) logActivity($userId, 'logout', 'User logged out');
        logout();
        jsonResponse(['success' => true]);
        break;

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
