<?php
/**
 * Cron: Check Deadlines and Send Push Notifications
 * 
 * Run every 4 hours (atau hourly): 0 */4 * * * php /var/www/html/myowncloud/cron/notify_deadlines.php
 * 
 * Checks for tasks with deadlines within 7 days and sends push notifications every 4 hours.
 * Uses web-push-php library if available, otherwise stores notifications in DB.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

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

    echo "  Notification stored: {$message} (user: {$task['username']})\n";

    // Try to send push notification if web-push library is available
    $webPushPath = __DIR__ . '/../vendor/autoload.php';
    if (file_exists($webPushPath) && VAPID_PUBLIC_KEY && VAPID_PRIVATE_KEY) {
        require_once $webPushPath;

        $subs = $db->prepare("SELECT * FROM push_subscriptions WHERE user_id = ?");
        $subs->execute([$task['uid']]);

        while ($sub = $subs->fetch()) {
            try {
                $auth = [
                    'VAPID' => [
                        'subject' => VAPID_SUBJECT,
                        'publicKey' => VAPID_PUBLIC_KEY,
                        'privateKey' => VAPID_PRIVATE_KEY,
                    ],
                ];

                $webPush = new \Minishlink\WebPush\WebPush($auth);
                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $sub['endpoint'],
                    'publicKey' => $sub['p256dh_key'],
                    'authToken' => $sub['auth_key'],
                ]);

                $payload = json_encode([
                    'title' => 'MyOwnCloud - Deadline Reminder',
                    'body' => $message,
                    'url' => '/myowncloud/?page=tasks',
                    'tag' => 'deadline-' . $task['id'],
                ]);

                $webPush->sendOneNotification($subscription, $payload);
                echo "  Push sent to subscription #{$sub['id']}\n";
            } catch (\Exception $e) {
                echo "  Push failed: {$e->getMessage()}\n";
                // Remove invalid subscription
                if (str_contains($e->getMessage(), '410') || str_contains($e->getMessage(), '404')) {
                    $db->prepare("DELETE FROM push_subscriptions WHERE id = ?")->execute([$sub['id']]);
                    echo "  Removed invalid subscription #{$sub['id']}\n";
                }
            }
        }
    }
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
    $message = "Task \"{$task['title']}\" sudah terlambat {$days} hari!";

    // Only notify once per week for overdue
    $check = $db->prepare("
        SELECT id FROM notifications 
        WHERE task_id = ? AND user_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND message LIKE '%terlambat%'
    ");
    $check->execute([$task['id'], $task['uid']]);
    if ($check->fetch()) continue;

    $insert = $db->prepare("INSERT INTO notifications (user_id, task_id, message) VALUES (?, ?, ?)");
    $insert->execute([$task['uid'], $task['id'], $message]);
    echo "  Overdue notification: {$message}\n";
}

echo "[" . date('Y-m-d H:i:s') . "] Done.\n";
