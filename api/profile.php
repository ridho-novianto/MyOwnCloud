<?php
/**
 * Profile API
 */
header('Content-Type: application/json');
requireLogin();

$db = getDB();
$uid = currentUserId();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'update') {
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!$username || !$email) jsonResponse(['error' => 'Username dan email harus diisi'], 400);

    // Check unique
    $stmt = $db->prepare('SELECT id FROM users WHERE (email = ? OR username = ?) AND id != ?');
    $stmt->execute([$email, $username, $uid]);
    if ($stmt->fetch()) jsonResponse(['error' => 'Email atau username sudah digunakan'], 409);

    if ($password && strlen($password) >= 6) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare('UPDATE users SET username = ?, email = ?, password_hash = ? WHERE id = ?');
        $stmt->execute([$username, $email, $hash, $uid]);
    } else {
        $stmt = $db->prepare('UPDATE users SET username = ?, email = ? WHERE id = ?');
        $stmt->execute([$username, $email, $uid]);
    }

    $_SESSION['username'] = $username;
    $_SESSION['email'] = $email;
    logActivity($uid, 'profile_update', 'Updated profile');
    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Invalid action'], 400);
