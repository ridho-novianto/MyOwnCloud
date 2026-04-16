<?php
/**
 * Push Notification Helper
 * Sends real Web Push notifications via VAPID to all subscribed endpoints.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Send a push notification to all subscriptions of a user.
 *
 * @param int    $userId  User ID
 * @param string $title   Notification title
 * @param string $body    Notification body text
 * @param string $tag     Notification tag (for grouping/replacing)
 * @param string $url     URL to open when notification is clicked
 * @return int   Number of successful pushes sent
 */
function sendPushNotification(int $userId, string $title, string $body, string $tag = '', string $url = ''): int {
    $db = getDB();
    
    // Check if VAPID keys are configured
    if (!defined('VAPID_PUBLIC_KEY') || !defined('VAPID_PRIVATE_KEY') || 
        !VAPID_PUBLIC_KEY || !VAPID_PRIVATE_KEY) {
        error_log('[Push] VAPID keys not configured');
        return 0;
    }
    
    // Get all push subscriptions for this user
    $stmt = $db->prepare('SELECT * FROM push_subscriptions WHERE user_id = ?');
    $stmt->execute([$userId]);
    $subscriptions = $stmt->fetchAll();
    
    if (empty($subscriptions)) {
        error_log("[Push] No subscriptions found for user $userId");
        return 0;
    }
    
    $auth = [
        'VAPID' => [
            'subject' => VAPID_SUBJECT,
            'publicKey' => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ],
    ];
    
    try {
        $webPush = new WebPush($auth);
    } catch (\Exception $e) {
        error_log('[Push] WebPush init error: ' . $e->getMessage());
        return 0;
    }
    
    $payload = json_encode([
        'title' => $title,
        'body' => $body,
        'icon' => '/assets/icons/icon-192.png',
        'badge' => '/assets/icons/icon-72.png',
        'url' => $url ?: '/?page=tasks',
        'tag' => $tag ?: 'myowncloud-' . time(),
    ]);
    
    $sent = 0;
    $toDelete = [];
    
    foreach ($subscriptions as $sub) {
        try {
            $subscription = Subscription::create([
                'endpoint' => $sub['endpoint'],
                'publicKey' => $sub['p256dh_key'],
                'authToken' => $sub['auth_key'],
            ]);
            
            $webPush->queueNotification($subscription, $payload);
        } catch (\Exception $e) {
            error_log("[Push] Queue error for sub #{$sub['id']}: " . $e->getMessage());
        }
    }
    
    // Flush and check results
    try {
        foreach ($webPush->flush() as $report) {
            $endpoint = $report->getRequest()->getUri()->__toString();
            
            if ($report->isSuccess()) {
                $sent++;
                error_log("[Push] Success: $endpoint");
            } else {
                $reason = $report->getReason();
                error_log("[Push] Failed ($reason): $endpoint");
                
                // If subscription is expired/invalid (410 Gone, 404 Not Found), remove it
                $statusCode = $report->getResponse() ? $report->getResponse()->getStatusCode() : 0;
                if ($statusCode === 410 || $statusCode === 404) {
                    $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")
                       ->execute([$endpoint]);
                    error_log("[Push] Removed invalid subscription: $endpoint");
                }
            }
        }
    } catch (\Exception $e) {
        error_log('[Push] Flush error: ' . $e->getMessage());
    }
    
    return $sent;
}

/**
 * Send push notification for a specific task notification.
 * Creates a notification record in DB AND sends push to all devices.
 *
 * @param int    $userId  User ID
 * @param int    $taskId  Task ID
 * @param string $title   Task title
 * @param string $deadline Task deadline date
 * @param string $type    Type: 'new', 'reminder', 'overdue'
 * @return bool
 */
function sendTaskPushNotification(int $userId, int $taskId, string $title, string $deadline, string $type = 'reminder'): bool {
    $days = daysUntil($deadline);
    
    switch ($type) {
        case 'new':
            $notifTitle = '✅ Tugas Baru Dibuat';
            $message = "Task \"{$title}\" baru saja dibuat. Deadline dalam {$days} hari ({$deadline})";
            $tag = 'new-task-' . $taskId;
            break;
        case 'overdue':
            $daysLate = abs($days);
            $notifTitle = '⚠️ Task Terlambat!';
            $message = "Task \"{$title}\" sudah terlambat {$daysLate} hari! Deadline: {$deadline}";
            $tag = 'overdue-' . $taskId;
            break;
        case 'reminder':
        default:
            $notifTitle = '📋 Deadline Reminder';
            $message = "Task \"{$title}\" deadline dalam {$days} hari ({$deadline})";
            $tag = 'deadline-' . $taskId;
            break;
    }
    
    // Send the actual push notification to all devices
    $sent = sendPushNotification($userId, $notifTitle, $message, $tag);
    
    error_log("[Push] Task notification ({$type}) for task #{$taskId}: sent to {$sent} device(s)");
    
    return $sent > 0;
}
