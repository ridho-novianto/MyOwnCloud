<?php
/**
 * MyOwnCloud Configuration
 */

// Database
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'myowncloud');
define('DB_USER', 'root');
define('DB_PASS', 'Vianto06.');

// App
define('APP_NAME', 'MyOwnCloud');
define('APP_URL', 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') );
define('APP_ROOT', __DIR__);
define('UPLOAD_DIR', APP_ROOT . '/uploads');
define('MAX_UPLOAD_SIZE', 100 * 1024 * 1024); // 100MB
define('DEFAULT_STORAGE_QUOTA', 1 * 1024 * 1024 * 1024); // 1GB

// Session
define('SESSION_LIFETIME', 86400 * 7); // 7 days

// VAPID keys for Push Notifications (generate your own at https://vapidkeys.com/)
define('VAPID_PUBLIC_KEY', 'BGefBrMaoqJrXemyE4qPfVffptwENjK0iLYH7C9FOtufgwNpojmPuCmzcUele46ur3r5t9a2BdRNCNpC6JPykuc');
define('VAPID_PRIVATE_KEY', '8xuwE5_XDTtnwaRU3PMjdPpPFI9DRAV9uAQLfgQ0_54');
define('VAPID_SUBJECT', 'mailto:admin@myowncloud.local');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', APP_ROOT . '/error.log');
