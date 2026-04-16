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
    if ($action === 'convert') {
        set_time_limit(300); // Allow long conversion time
        $id = (int)($input['id'] ?? 0);
        $format = preg_replace('/[^a-z0-9]/', '', strtolower($input['format'] ?? ''));

        $supportedFormats = ['mp3', 'mp4', 'wav', 'webm', 'ogg', 'aac', 'pdf', 'jpg', 'png', 'webp', 'docx'];
        if (!in_array($format, $supportedFormats)) {
            jsonResponse(['error' => 'Format tidak didukung: ' . $format], 400);
        }

        $stmt = $db->prepare('SELECT * FROM files WHERE id = ? AND user_id = ?');
        $stmt->execute([$id, $uid]);
        $file = $stmt->fetch();

        if (!$file) jsonResponse(['error' => 'File tidak ditemukan'], 404);

        $srcPath = UPLOAD_DIR . '/' . $uid . '/' . $file['filename'];
        if (!file_exists($srcPath)) jsonResponse(['error' => 'File sumber hilang'], 404);

        $pathInfo = pathinfo($file['original_name']);
        $newOriginalName = $pathInfo['filename'] . '.' . $format;
        $newFilename = generateUniqueFilename($newOriginalName);
        $destPath = UPLOAD_DIR . '/' . $uid . '/' . $newFilename;
        $mime = $file['mime_type'];
        $success = false;

        // --- Image Conversion (PHP GD) ---
        if (str_starts_with($mime, 'image/') && in_array($format, ['jpg', 'png', 'webp', 'pdf'])) {
            if ($format === 'pdf') {
                // Image to PDF using PHP GD + custom PDF writer
                $imgInfo = getimagesize($srcPath);
                if (!$imgInfo) jsonResponse(['error' => 'File gambar tidak valid'], 400);

                $imgW = $imgInfo[0];
                $imgH = $imgInfo[1];

                // Create image resource
                $srcImg = null;
                switch ($imgInfo[2]) {
                    case IMAGETYPE_JPEG: $srcImg = imagecreatefromjpeg($srcPath); break;
                    case IMAGETYPE_PNG: $srcImg = imagecreatefrompng($srcPath); break;
                    case IMAGETYPE_WEBP: $srcImg = imagecreatefromwebp($srcPath); break;
                    case IMAGETYPE_GIF: $srcImg = imagecreatefromgif($srcPath); break;
                }
                if (!$srcImg) jsonResponse(['error' => 'Format gambar tidak didukung untuk konversi PDF'], 400);

                // Convert to JPEG first for PDF embedding
                $tmpJpg = tempnam(sys_get_temp_dir(), 'img_');
                imagejpeg($srcImg, $tmpJpg, 90);
                imagedestroy($srcImg);

                // Create minimal PDF with embedded JPEG
                $jpgData = file_get_contents($tmpJpg);
                $jpgLen = strlen($jpgData);
                unlink($tmpJpg);

                // A4 points: 595.28 x 841.89; scale image to fit
                $pageW = 595.28;
                $pageH = 841.89;
                $scale = min($pageW / $imgW, $pageH / $imgH, 1) * 0.95;
                $dispW = round($imgW * $scale);
                $dispH = round($imgH * $scale);
                $offX = round(($pageW - $dispW) / 2);
                $offY = round(($pageH - $dispH) / 2);

                $pdf  = "%PDF-1.4\n";
                $offsets = [];
                // Obj 1: Catalog
                $offsets[1] = strlen($pdf);
                $pdf .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
                // Obj 2: Pages
                $offsets[2] = strlen($pdf);
                $pdf .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
                // Obj 3: Page
                $offsets[3] = strlen($pdf);
                $pdf .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $pageW $pageH] /Contents 4 0 R /Resources << /XObject << /Img 5 0 R >> >> >>\nendobj\n";
                // Obj 4: Content stream
                $content = "q\n$dispW 0 0 $dispH $offX $offY cm\n/Img Do\nQ\n";
                $offsets[4] = strlen($pdf);
                $pdf .= "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}endstream\nendobj\n";
                // Obj 5: Image XObject
                $offsets[5] = strlen($pdf);
                $pdf .= "5 0 obj\n<< /Type /XObject /Subtype /Image /Width $imgW /Height $imgH /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length $jpgLen >>\nstream\n";
                $pdf .= $jpgData;
                $pdf .= "\nendstream\nendobj\n";

                $xrefOffset = strlen($pdf);
                $pdf .= "xref\n0 6\n0000000000 65535 f \n";
                for ($i = 1; $i <= 5; $i++) {
                    $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
                }
                $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xrefOffset\n%%EOF\n";

                file_put_contents($destPath, $pdf);
                $success = file_exists($destPath) && filesize($destPath) > 0;
            } else {
                // Image format conversion (jpg/png/webp)
                $imgInfo = getimagesize($srcPath);
                if (!$imgInfo) jsonResponse(['error' => 'File gambar tidak valid'], 400);

                $srcImg = null;
                switch ($imgInfo[2]) {
                    case IMAGETYPE_JPEG: $srcImg = imagecreatefromjpeg($srcPath); break;
                    case IMAGETYPE_PNG: $srcImg = imagecreatefrompng($srcPath); break;
                    case IMAGETYPE_WEBP: $srcImg = imagecreatefromwebp($srcPath); break;
                    case IMAGETYPE_GIF: $srcImg = imagecreatefromgif($srcPath); break;
                }
                if (!$srcImg) jsonResponse(['error' => 'Format gambar tidak didukung'], 400);

                switch ($format) {
                    case 'jpg': $success = imagejpeg($srcImg, $destPath, 90); break;
                    case 'png': $success = imagepng($srcImg, $destPath, 6); break;
                    case 'webp': $success = imagewebp($srcImg, $destPath, 85); break;
                }
                imagedestroy($srcImg);
            }
        }
        // --- Document Converter (LibreOffice) ---
        elseif (in_array($mime, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain', 'text/csv',
            'application/pdf'
        ]) && in_array($format, ['pdf', 'docx'])) {
            $loPath = trim(shell_exec('which libreoffice 2>/dev/null') ?? '');
            if (!$loPath) {
                jsonResponse(['error' => 'LibreOffice belum terinstall di server. Jalankan: sudo apt install libreoffice-common'], 500);
            }

            $tmpDir = sys_get_temp_dir() . '/lo_convert_' . uniqid();
            mkdir($tmpDir, 0755, true);

            // Copy source to temp with original extension
            $tmpSrc = $tmpDir . '/' . basename($file['original_name']);
            copy($srcPath, $tmpSrc);

            $cmd = escapeshellarg($loPath) . " --headless";
            if ($mime === 'application/pdf' && $format === 'docx') {
                $cmd .= " --infilter=\"writer_pdf_import\"";
            }
            $cmd .= " --convert-to " . escapeshellarg($format) . " --outdir " . escapeshellarg($tmpDir) . " " . escapeshellarg($tmpSrc) . " 2>&1";
            exec($cmd, $output, $returnVar);

            // Find the generated document
            $genFile = $tmpDir . '/' . pathinfo($file['original_name'], PATHINFO_FILENAME) . '.' . $format;
            if (file_exists($genFile)) {
                rename($genFile, $destPath);
                $success = true;
            }

            // Cleanup temp files
            array_map('unlink', glob($tmpDir . '/*'));
            @rmdir($tmpDir);

            if (!$success) {
                jsonResponse(['error' => 'Konversi dokumen gagal', 'details' => implode("\n", $output)], 500);
            }
        }
        // --- Audio/Video Conversion (FFmpeg) ---
        elseif ((str_starts_with($mime, 'audio/') || str_starts_with($mime, 'video/')) && in_array($format, ['mp3', 'mp4', 'wav', 'webm', 'ogg', 'aac'])) {
            $ffmpegPath = trim(shell_exec('which ffmpeg 2>/dev/null') ?? '');
            if (!$ffmpegPath) {
                jsonResponse(['error' => 'FFmpeg belum terinstall di server'], 500);
            }
            $cmd = escapeshellarg($ffmpegPath) . " -y -i " . escapeshellarg($srcPath) . " " . escapeshellarg($destPath) . " 2>&1";
            exec($cmd, $output, $returnVar);
            $success = ($returnVar === 0 && file_exists($destPath));

            if (!$success) {
                jsonResponse(['error' => 'Konversi media gagal', 'details' => implode("\n", $output)], 500);
            }
        }
        else {
            jsonResponse(['error' => 'Kombinasi format tidak didukung'], 400);
        }

        if (!$success || !file_exists($destPath)) {
            jsonResponse(['error' => 'Konversi gagal'], 500);
        }

        $newSize = filesize($destPath);
        $newMime = mime_content_type($destPath) ?: 'application/octet-stream';

        $stmt = $db->prepare('INSERT INTO files (user_id, filename, original_name, filepath, filesize, mime_type, folder_id) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$uid, $newFilename, $newOriginalName, $uid . '/' . $newFilename, $newSize, $newMime, $file['folder_id']]);

        updateStorageUsed($uid);
        logActivity($uid, 'file_convert', "Converted {$file['original_name']} to $format");

        jsonResponse(['success' => true, 'filename' => $newOriginalName]);
    }
}

jsonResponse(['error' => 'Invalid request'], 400);
