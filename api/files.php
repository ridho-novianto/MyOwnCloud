<?php
/**
 * Files API - Upload, Download, Delete, Folder operations
 */
requireLogin();

$db = getDB();
$uid = currentUserId();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle GET requests (download)
if ($method === 'GET' && $action === 'download') {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare('SELECT * FROM files WHERE id = ? AND user_id = ?');
    $stmt->execute([$id, $uid]);
    $file = $stmt->fetch();

    if (!$file) {
        http_response_code(404);
        echo 'File not found';
        exit;
    }

    $filepath = UPLOAD_DIR . '/' . $uid . '/' . $file['filename'];
    if (!file_exists($filepath)) {
        http_response_code(404);
        echo 'File not found on disk';
        exit;
    }

    header('Content-Type: ' . $file['mime_type']);
    header('Content-Disposition: attachment; filename="' . $file['original_name'] . '"');
    header('Content-Length: ' . filesize($filepath));
    readfile($filepath);
    exit;
}

// Handle POST requests
if ($method === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    // File upload (multipart form data)
    if (str_contains($contentType, 'multipart/form-data') || !empty($_FILES)) {
        $action = $_POST['action'] ?? 'upload';

        if ($action === 'upload') {
            if (empty($_FILES['files'])) {
                jsonResponse(['error' => 'Tidak ada file'], 400);
            }

            $folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;

            // Verify folder ownership
            if ($folderId) {
                $stmt = $db->prepare('SELECT id FROM folders WHERE id = ? AND user_id = ?');
                $stmt->execute([$folderId, $uid]);
                if (!$stmt->fetch()) jsonResponse(['error' => 'Folder tidak valid'], 400);
            }

            $storage = getUserStorageInfo($uid);
            $paths = $_POST['paths'] ?? [];
            $uploadDir = ensureUploadDir($uid);
            $results = [];

            $files = $_FILES['files'];
            $fileCount = is_array($files['name']) ? count($files['name']) : 1;

            for ($i = 0; $i < $fileCount; $i++) {
                $name = is_array($files['name']) ? $files['name'][$i] : $files['name'];
                $tmpName = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
                $size = is_array($files['size']) ? $files['size'][$i] : $files['size'];
                $error = is_array($files['error']) ? $files['error'][$i] : $files['error'];
                $type = is_array($files['type']) ? $files['type'][$i] : $files['type'];

                if ($error !== UPLOAD_ERR_OK) {
                    $results[] = ['name' => $name, 'error' => 'Upload error: ' . $error];
                    continue;
                }

                if ($size > MAX_UPLOAD_SIZE) {
                    $results[] = ['name' => $name, 'error' => 'File terlalu besar (maks ' . formatBytes(MAX_UPLOAD_SIZE) . ')'];
                    continue;
                }

                // Check storage quota
                if (empty($storage['is_admin']) && $storage['storage_used'] + $size > $storage['storage_quota']) {
                    $results[] = ['name' => $name, 'error' => 'Storage penuh'];
                    continue;
                } elseif (!empty($storage['is_admin']) && disk_free_space('/') < $size) {
                    $results[] = ['name' => $name, 'error' => 'Disk server penuh'];
                    continue;
                }

                // Handle folder path dynamically
                $targetFolderId = $folderId;
                if (!empty($paths[$i])) {
                    $parts = explode('/', $paths[$i]);
                    array_pop($parts); // remove filename
                    foreach ($parts as $part) {
                        if (empty($part)) continue;
                        $stmt = $db->prepare('SELECT id FROM folders WHERE user_id = ? AND name = ? AND parent_id ' . ($targetFolderId ? '= ?' : 'IS NULL'));
                        $params = [$uid, $part];
                        if ($targetFolderId) $params[] = $targetFolderId;
                        $stmt->execute($params);
                        $subFolder = $stmt->fetch();
                        if ($subFolder) {
                            $targetFolderId = $subFolder['id'];
                        } else {
                            $stmt = $db->prepare('INSERT INTO folders (user_id, name, parent_id) VALUES (?, ?, ?)');
                            $stmt->execute([$uid, $part, $targetFolderId]);
                            $targetFolderId = $db->lastInsertId();
                        }
                    }
                }

                $filename = generateUniqueFilename($name);
                $filepath = $uploadDir . '/' . $filename;

                if (move_uploaded_file($tmpName, $filepath)) {
                    $mime = mime_content_type($filepath) ?: $type;
                    $stmt = $db->prepare('INSERT INTO files (user_id, filename, original_name, filepath, filesize, mime_type, folder_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    $stmt->execute([$uid, $filename, $name, $uid . '/' . $filename, $size, $mime, $targetFolderId]);

                    $storage['storage_used'] += $size;
                    updateStorageUsed($uid);
                    logActivity($uid, 'file_upload', "Uploaded: $name");
                    $results[] = ['name' => $name, 'success' => true, 'id' => $db->lastInsertId()];
                } else {
                    $results[] = ['name' => $name, 'error' => 'Gagal menyimpan file'];
                }
            }

            jsonResponse(['success' => true, 'results' => $results]);
        }
        exit;
    }

    // JSON requests
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM files WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $uid]);
        $file = $stmt->fetch();

        if (!$file) jsonResponse(['error' => 'File tidak ditemukan'], 404);

        $filepath = UPLOAD_DIR . '/' . $uid . '/' . $file['filename'];
        if (file_exists($filepath)) unlink($filepath);

        $stmt = $db->prepare('DELETE FROM files WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $uid]);
        updateStorageUsed($uid);
        logActivity($uid, 'file_delete', "Deleted: " . $file['original_name']);
        jsonResponse(['success' => true]);
    }

    if ($action === 'create_folder') {
        $name = trim($input['name'] ?? '');
        if (!$name) jsonResponse(['error' => 'Nama folder harus diisi'], 400);
        $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;

        $stmt = $db->prepare('INSERT INTO folders (user_id, name, parent_id) VALUES (?, ?, ?)');
        $stmt->execute([$uid, $name, $parentId]);
        logActivity($uid, 'folder_create', "Created folder: $name");
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
    }

    if ($action === 'rename_folder') {
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name'] ?? '');
        if (!$id || !$name) jsonResponse(['error' => 'Data tidak valid'], 400);

        $stmt = $db->prepare('UPDATE folders SET name = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$name, $id, $uid]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'delete_folder') {
        $id = (int)($input['id'] ?? 0);

        // Delete files in folder
        $stmt = $db->prepare('SELECT * FROM files WHERE folder_id = ? AND user_id = ?');
        $stmt->execute([$id, $uid]);
        while ($file = $stmt->fetch()) {
            $filepath = UPLOAD_DIR . '/' . $uid . '/' . $file['filename'];
            if (file_exists($filepath)) unlink($filepath);
        }
        $db->prepare('DELETE FROM files WHERE folder_id = ? AND user_id = ?')->execute([$id, $uid]);

        // Delete subfolder files recursively
        $subStmt = $db->prepare('SELECT id FROM folders WHERE parent_id = ? AND user_id = ?');
        $subStmt->execute([$id, $uid]);
        while ($sub = $subStmt->fetch()) {
            // Recursive delete via same logic
            $db->prepare('DELETE FROM files WHERE folder_id = ? AND user_id = ?')->execute([$sub['id'], $uid]);
            $db->prepare('DELETE FROM folders WHERE id = ? AND user_id = ?')->execute([$sub['id'], $uid]);
        }

        $db->prepare('DELETE FROM folders WHERE id = ? AND user_id = ?')->execute([$id, $uid]);
        updateStorageUsed($uid);
        logActivity($uid, 'folder_delete', "Deleted folder #$id");
        jsonResponse(['success' => true]);
    }

    if ($action === 'move') {
        $type = $input['type'] ?? ''; // 'file' or 'folder'
        $itemId = (int)($input['item_id'] ?? 0);
        $targetFolderId = !empty($input['target_id']) ? (int)$input['target_id'] : null;

        if (!$type || !$itemId) jsonResponse(['error' => 'Data tidak valid'], 400);

        // Cannot move a folder into itself
        if ($type === 'folder' && $itemId === $targetFolderId) {
            jsonResponse(['error' => 'Tidak bisa memindahkan folder ke dalam dirinya sendiri'], 400);
        }

        if ($type === 'file') {
            $stmt = $db->prepare('UPDATE files SET folder_id = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$targetFolderId, $itemId, $uid]);
            logActivity($uid, 'file_move', "Moved file #$itemId to folder #" . ($targetFolderId ?: 'root'));
        } elseif ($type === 'folder') {
            // Prevent moving folder into its own descendants (basic check)
            $tmpId = $targetFolderId;
            while ($tmpId) {
                if ($tmpId === $itemId) {
                    jsonResponse(['error' => 'Folder target tidak valid'], 400);
                }
                $chk = $db->prepare('SELECT parent_id FROM folders WHERE id = ?');
                $chk->execute([$tmpId]);
                $tmpId = $chk->fetchColumn();
            }

            $stmt = $db->prepare('UPDATE folders SET parent_id = ? WHERE id = ? AND user_id = ?');
            $stmt->execute([$targetFolderId, $itemId, $uid]);
            logActivity($uid, 'folder_move', "Moved folder #$itemId to folder #" . ($targetFolderId ?: 'root'));
        }

        jsonResponse(['success' => true]);
    }
}

jsonResponse(['error' => 'Invalid request'], 400);
