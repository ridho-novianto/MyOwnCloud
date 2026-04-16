<?php
/**
 * Notifications API
 */
header('Content-Type: application/json');
requireLogin();

$db = getDB();
$uid = currentUserId();
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

if ($action === 'subscribe') {
    $endpoint = $input['endpoint'] ?? '';
    $p256dh = $input['p256dh'] ?? '';
    $auth = $input['auth'] ?? '';

    if (!$endpoint || !$p256dh || !$auth) {
        jsonResponse(['error' => 'Invalid subscription data'], 400);
    }

    // Remove existing subscriptions for this endpoint
    $stmt = $db->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?');
    $stmt->execute([$uid, $endpoint]);

    $stmt = $db->prepare('INSERT INTO push_subscriptions (user_id, endpoint, p256dh_key, auth_key) VALUES (?, ?, ?, ?)');
    $stmt->execute([$uid, $endpoint, $p256dh, $auth]);
    logActivity($uid, 'push_subscribe', 'Enabled push notifications');
    jsonResponse(['success' => true]);
}

if ($action === 'unsubscribe') {
    $endpoint = $input['endpoint'] ?? '';
    $stmt = $db->prepare('DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?');
    $stmt->execute([$uid, $endpoint]);
    logActivity($uid, 'push_unsubscribe', 'Disabled push notifications');
    jsonResponse(['success' => true]);
}

if ($action === 'test') {
    // Send a real test push notification via Web Push
    require_once __DIR__ . '/../includes/push_helper.php';
    $sent = sendPushNotification(
        $uid,
        '🔔 MyOwnCloud Test',
        'Notifikasi berfungsi dengan baik! Push dari server berhasil.',
        'test-push-' . time(),
        '/?page=tasks'
    );
    jsonResponse(['success' => true, 'message' => "Test push sent to $sent device(s)"]);
}

if ($action === 'get_pending') {
    // Get unread notifications for current user (max 20)
    $stmt = $db->prepare("
        SELECT n.id, n.task_id, n.message, n.sent_at,
               t.title as task_title, t.deadline as task_deadline
        FROM notifications n
        LEFT JOIN tasks t ON n.task_id = t.id
        WHERE n.user_id = ? AND n.is_read = 0
        ORDER BY n.sent_at DESC
        LIMIT 20
    ");
    $stmt->execute([$uid]);
    $notifications = $stmt->fetchAll();
    jsonResponse(['success' => true, 'notifications' => $notifications]);
}

if ($action === 'mark_shown') {
    // Mark notifications as read
    $ids = $input['ids'] ?? [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$uid]);
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id IN ($placeholders) AND user_id = ?");
        $stmt->execute($params);
    }
    jsonResponse(['success' => true]);
}

if ($action === 'trigger_check') {
    // Manual trigger: run deadline check logic inline (replaces cron fetch which is blocked by nginx)
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/push_helper.php';
    
    $newNotifs = 0;
    
    // Check upcoming tasks (within 7 days)
    $stmt = $db->prepare("
        SELECT t.id, t.title, t.deadline
        FROM tasks t
        WHERE t.user_id = ? AND t.deadline IS NOT NULL
        AND t.deadline BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        AND t.status NOT IN ('done', 'cancelled')
    ");
    $stmt->execute([$uid]);
    $tasks = $stmt->fetchAll();
    
    foreach ($tasks as $task) {
        // Check if already notified in last 4 hours
        $check = $db->prepare("SELECT id FROM notifications WHERE task_id = ? AND user_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 4 HOUR)");
        $check->execute([$task['id'], $uid]);
        if ($check->fetch()) continue;
        
        $days = daysUntil($task['deadline']);
        $message = "Task \"{$task['title']}\" deadline dalam {$days} hari ({$task['deadline']})";
        $db->prepare("INSERT INTO notifications (user_id, task_id, message) VALUES (?, ?, ?)")->execute([$uid, $task['id'], $message]);
        
        // Send real Web Push notification
        sendTaskPushNotification($uid, (int)$task['id'], $task['title'], $task['deadline'], 'reminder');
        
        $newNotifs++;
    }
    
    // Check overdue tasks
    $stmt = $db->prepare("
        SELECT t.id, t.title, t.deadline
        FROM tasks t
        WHERE t.user_id = ? AND t.deadline < CURDATE()
        AND t.status NOT IN ('done', 'cancelled')
    ");
    $stmt->execute([$uid]);
    $overdueTasks = $stmt->fetchAll();
    
    foreach ($overdueTasks as $task) {
        $check = $db->prepare("SELECT id FROM notifications WHERE task_id = ? AND user_id = ? AND sent_at >= DATE_SUB(NOW(), INTERVAL 4 HOUR) AND message LIKE '%terlambat%'");
        $check->execute([$task['id'], $uid]);
        if ($check->fetch()) continue;
        
        $days = abs(daysUntil($task['deadline']));
        $message = "Task \"{$task['title']}\" sudah terlambat {$days} hari! Deadline: {$task['deadline']}";
        $db->prepare("INSERT INTO notifications (user_id, task_id, message) VALUES (?, ?, ?)")->execute([$uid, $task['id'], $message]);
        
        // Send real Web Push notification
        sendTaskPushNotification($uid, (int)$task['id'], $task['title'], $task['deadline'], 'overdue');
        
        $newNotifs++;
    }
    
    jsonResponse(['success' => true, 'new_notifications' => $newNotifs]);
}

if ($action === 'check') {
    $endpoint = $input['endpoint'] ?? '';
    $stmt = $db->prepare('SELECT id FROM push_subscriptions WHERE user_id = ? AND endpoint = ?');
    $stmt->execute([$uid, $endpoint]);
    jsonResponse(['subscribed' => (bool)$stmt->fetch()]);
}

if ($action === 'vapid_key') {
    jsonResponse(['key' => VAPID_PUBLIC_KEY]);
}

jsonResponse(['error' => 'Invalid action'], 400);
