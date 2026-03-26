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
    // Send a test notification via the service worker
    jsonResponse(['success' => true, 'message' => 'Test notification sent']);
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
