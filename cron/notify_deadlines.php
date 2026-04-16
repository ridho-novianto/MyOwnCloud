<?php
/**
 * Cron: Check Deadlines and Send Push Notifications
 * 
 * Run every 4 hours: 0 0,4,8,12,16,20 * * * /usr/local/php81/bin/php /var/www/html/myowncloud/cron/notify_deadlines.php
 * 
 * Checks for tasks with deadlines within 7 days and sends push notifications every 4 hours.
 * Uses web-push-php library to send real push notifications.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/push_helper.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting deadline notification check...\n";

$db = getDB();

// Find tasks with deadline within 7 days (not done/cancelled)
$stmt = $db->prepare("
    SELECT t.*, u.username, u.id as uid
    FROM tasks t
    JOIN users u ON t.user_id = u.id
    WHERE t.deadline IS NOT NULL
    AND t.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND t.status NOT IN ('done', 'cancelled')
    ORDER BY t.deadline ASC
");
$stmt->execute();
$tasks = $stmt->fetchAll();

echo "Found " . count($tasks) . " tasks with upcoming deadlines\n";

foreach ($tasks as $task) {
    $days = daysUntil($task['deadline']);
    $message = "Task \"{$task['title']}\" deadline dalam {$days} hari ({$task['deadline']})";

    // Check if notified in the last 4 hours
    $check = $db->prepare("
        SELECT id FROM notifications 
        WHERE task_id = ? AND user_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 4 HOUR)
    ");
    $check->execute([$task['id'], $task['uid']]);
    if ($check->fetch()) {
        echo "  Already notified: {$task['title']} for user {$task['username']}\n";
        continue;
    }

    // Store notification in DB
    $insert = $db->prepare("INSERT INTO notifications (user_id, task_id, message) VALUES (?, ?, ?)");
    $insert->execute([$task['uid'], $task['id'], $message]);

    // Send real Web Push notification
    $sent = sendTaskPushNotification(
        (int)$task['uid'], 
        (int)$task['id'], 
        $task['title'], 
        $task['deadline'], 
        'reminder'
    );

    echo "  Notification stored & pushed to {$sent} device(s): {$message} (user: {$task['username']})\n";
}

// Also notify tasks that are overdue
$overdueStmt = $db->prepare("
    SELECT t.*, u.username, u.id as uid
    FROM tasks t
    JOIN users u ON t.user_id = u.id
    WHERE t.deadline < CURDATE()
    AND t.status NOT IN ('done', 'cancelled')
");
$overdueStmt->execute();
$overdueTasks = $overdueStmt->fetchAll();

echo "Found " . count($overdueTasks) . " overdue tasks\n";

foreach ($overdueTasks as $task) {
    $days = abs(daysUntil($task['deadline']));
    $message = "Task \"{$task['title']}\" sudah terlambat {$days} hari! Deadline: {$task['deadline']}";

    // Only notify every 4 hours for overdue
    $check = $db->prepare("
        SELECT id FROM notifications 
        WHERE task_id = ? AND user_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 4 HOUR)
        AND message LIKE '%terlambat%'
    ");
    $check->execute([$task['id'], $task['uid']]);
    if ($check->fetch()) continue;

    $insert = $db->prepare("INSERT INTO notifications (user_id, task_id, message) VALUES (?, ?, ?)");
    $insert->execute([$task['uid'], $task['id'], $message]);
    
    // Send real Web Push notification
    $sent = sendTaskPushNotification(
        (int)$task['uid'], 
        (int)$task['id'], 
        $task['title'], 
        $task['deadline'], 
        'overdue'
    );
    
    echo "  Overdue notification pushed to {$sent} device(s): {$message}\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
