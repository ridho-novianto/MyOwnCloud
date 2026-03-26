<?php
/**
 * Links API - CRUD
 */
header('Content-Type: application/json');
requireLogin();

$db = getDB();
$uid = currentUserId();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'create';

    if ($action === 'create') {
        $title = trim($input['title'] ?? '');
        $url = trim($input['url'] ?? '');
        if (!$title || !$url) jsonResponse(['error' => 'Judul dan URL harus diisi'], 400);

        $stmt = $db->prepare('INSERT INTO links (user_id, title, url, description, category, icon_color) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$uid, $title, $url, $input['description'] ?? '', $input['category'] ?? 'Uncategorized', $input['icon_color'] ?? '#00e5ff']);
        logActivity($uid, 'link_create', "Created link: $title");
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        if (!$id) jsonResponse(['error' => 'ID tidak valid'], 400);

        $stmt = $db->prepare('UPDATE links SET title=?, url=?, description=?, category=?, icon_color=? WHERE id=? AND user_id=?');
        $stmt->execute([
            trim($input['title'] ?? ''),
            trim($input['url'] ?? ''),
            $input['description'] ?? '',
            $input['category'] ?? 'Uncategorized',
            $input['icon_color'] ?? '#00e5ff',
            $id, $uid
        ]);
        logActivity($uid, 'link_update', "Updated link #$id");
        jsonResponse(['success' => true]);
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM links WHERE id=? AND user_id=?');
        $stmt->execute([$id, $uid]);
        logActivity($uid, 'link_delete', "Deleted link #$id");
        jsonResponse(['success' => true]);
    }

    if ($action === 'toggle_pin') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare('UPDATE links SET is_pinned = NOT is_pinned WHERE id=? AND user_id=?');
        $stmt->execute([$id, $uid]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'track_click') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare('UPDATE links SET click_count = click_count + 1 WHERE id=? AND user_id=?');
        $stmt->execute([$id, $uid]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'get') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM links WHERE id=? AND user_id=?');
        $stmt->execute([$id, $uid]);
        $link = $stmt->fetch();
        if (!$link) jsonResponse(['error' => 'Link tidak ditemukan'], 404);
        jsonResponse(['link' => $link]);
    }
}

jsonResponse(['error' => 'Method not allowed'], 405);
