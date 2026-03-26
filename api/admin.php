<?php
/**
 * Admin API
 */
header('Content-Type: application/json');
requireAdmin();

$db = getDB();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'add_user') {
    $username = trim($input['username'] ?? '');
    $email = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';
    $role = $input['role'] ?? 'user';
    $quota = (int)($input['quota'] ?? 1) * 1073741824; // GB to bytes

    if (!$username || !$email || !$password) jsonResponse(['error' => 'Semua field harus diisi'], 400);

    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
    $stmt->execute([$email, $username]);
    if ($stmt->fetch()) jsonResponse(['error' => 'Email atau username sudah ada'], 409);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash, role, storage_quota) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$username, $email, $hash, $role, $quota]);
    logActivity(currentUserId(), 'admin_add_user', "Added user: $username");
    jsonResponse(['success' => true]);
}

if ($action === 'update_user') {
    $id = (int)($input['id'] ?? 0);
    $role = $input['role'] ?? 'user';
    $status = $input['status'] ?? 'active';
    $quota = (int)($input['quota'] ?? 1) * 1073741824;

    $stmt = $db->prepare('UPDATE users SET role = ?, status = ?, storage_quota = ? WHERE id = ?');
    $stmt->execute([$role, $status, $quota, $id]);
    logActivity(currentUserId(), 'admin_update_user', "Updated user #$id");
    jsonResponse(['success' => true]);
}

if ($action === 'delete_user') {
    $id = (int)($input['id'] ?? 0);
    if ($id === currentUserId()) jsonResponse(['error' => 'Tidak bisa menghapus akun sendiri'], 400);

    // Delete user files from disk
    $uploadDir = UPLOAD_DIR . '/' . $id;
    if (is_dir($uploadDir)) {
        $it = new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) rmdir($file->getRealPath());
            else unlink($file->getRealPath());
        }
        rmdir($uploadDir);
    }

    $stmt = $db->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$id]);
    logActivity(currentUserId(), 'admin_delete_user', "Deleted user #$id");
    jsonResponse(['success' => true]);
}

if ($action === 'get_user') {
    $id = (int)($input['id'] ?? 0);
    $stmt = $db->prepare('SELECT id, username, email, role, status, storage_quota FROM users WHERE id = ?');
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    if (!$user) jsonResponse(['error' => 'User tidak ditemukan'], 404);
    $user['storage_quota_gb'] = round($user['storage_quota'] / 1073741824, 1);
    jsonResponse(['user' => $user]);
}

jsonResponse(['error' => 'Invalid action'], 400);
