<?php
/**
 * Shared Utility Functions
 */

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function formatBytes(int $bytes, int $precision = 2): string {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    return round($bytes / (1024 ** $pow), $precision) . ' ' . $units[$pow];
}

function timeAgo(string $datetime): string {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) return $diff->y . ' tahun lalu';
    if ($diff->m > 0) return $diff->m . ' bulan lalu';
    if ($diff->d > 0) return $diff->d . ' hari lalu';
    if ($diff->h > 0) return $diff->h . ' jam lalu';
    if ($diff->i > 0) return $diff->i . ' menit lalu';
    return 'Baru saja';
}

function daysUntil(?string $date): ?int {
    if (!$date) return null;
    $now = new DateTime('today');
    $target = new DateTime($date);
    $diff = $now->diff($target);
    return $diff->invert ? -$diff->days : $diff->days;
}

function deadlineLabel(?string $date): string {
    $days = daysUntil($date);
    if ($days === null) return '';
    if ($days < 0) return abs($days) . ' hari terlambat';
    if ($days === 0) return 'Hari ini';
    return $days . ' hari lagi';
}

function deadlineClass(?string $date): string {
    $days = daysUntil($date);
    if ($days === null) return '';
    if ($days < 0) return 'overdue';
    if ($days <= 3) return 'urgent';
    if ($days <= 7) return 'warning';
    return 'safe';
}

function getFileIcon(string $mime): string {
    if (str_starts_with($mime, 'image/')) return 'fa-file-image';
    if (str_starts_with($mime, 'video/')) return 'fa-file-video';
    if (str_starts_with($mime, 'audio/')) return 'fa-file-audio';
    if (str_contains($mime, 'pdf')) return 'fa-file-pdf';
    if (str_contains($mime, 'word') || str_contains($mime, 'document')) return 'fa-file-word';
    if (str_contains($mime, 'sheet') || str_contains($mime, 'excel')) return 'fa-file-excel';
    if (str_contains($mime, 'presentation') || str_contains($mime, 'powerpoint')) return 'fa-file-powerpoint';
    if (str_contains($mime, 'zip') || str_contains($mime, 'rar') || str_contains($mime, 'tar') || str_contains($mime, 'gz')) return 'fa-file-zipper';
    if (str_starts_with($mime, 'text/')) return 'fa-file-lines';
    return 'fa-file';
}

function getFileColorClass(string $mime): string {
    if (str_starts_with($mime, 'image/')) return 'file-image';
    if (str_starts_with($mime, 'video/')) return 'file-video';
    if (str_starts_with($mime, 'audio/')) return 'file-audio';
    if (str_contains($mime, 'pdf')) return 'file-pdf';
    if (str_contains($mime, 'zip') || str_contains($mime, 'rar') || str_contains($mime, 'tar')) return 'file-archive';
    if (str_contains($mime, 'word') || str_contains($mime, 'document')) return 'file-doc';
    return 'file-default';
}

function logActivity(int $userId, string $action, string $details = ''): void {
    try {
        $db = getDB();
        $stmt = $db->prepare('INSERT INTO activity_log (user_id, action, details) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $action, $details]);
    } catch (Exception $e) {
        // Silent fail for logging
    }
}

function updateStorageUsed(int $userId): void {
    $db = getDB();
    $stmt = $db->prepare('SELECT COALESCE(SUM(filesize), 0) as total FROM files WHERE user_id = ?');
    $stmt->execute([$userId]);
    $total = $stmt->fetchColumn();
    $stmt = $db->prepare('UPDATE users SET storage_used = ? WHERE id = ?');
    $stmt->execute([$total, $userId]);
}

function getUserStorageInfo(int $userId): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT role, storage_used, storage_quota FROM users WHERE id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) return ['storage_used' => 0, 'storage_quota' => DEFAULT_STORAGE_QUOTA, 'is_admin' => false];
    
    if ($user['role'] === 'admin') {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        return [
            'storage_used' => $used,
            'storage_quota' => $total,
            'is_admin' => true,
            'actual_used' => $user['storage_used']
        ];
    }
    
    return [
        'storage_used' => $user['storage_used'], 
        'storage_quota' => $user['storage_quota'],
        'is_admin' => false
    ];
}

function ensureUploadDir(int $userId): string {
    $dir = UPLOAD_DIR . '/' . $userId;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function generateUniqueFilename(string $originalName): string {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    return uniqid() . '_' . time() . ($ext ? '.' . $ext : '');
}
