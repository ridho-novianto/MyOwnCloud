<?php
/**
 * Notes API - CRUD
 */
header('Content-Type: application/json');
requireLogin();

$db = getDB();
$uid = currentUserId();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $search = $_GET['search'] ?? '';

    $where = ['user_id = ?'];
    $params = [$uid];

    if ($search) {
        $where[] = '(title LIKE ? OR content LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql = 'SELECT * FROM notes WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['notes' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'create';

    if ($action === 'create') {
        $title = trim($input['title'] ?? 'Untitled Note');
        $content = $input['content'] ?? '';

        $stmt = $db->prepare('INSERT INTO notes (user_id, title, content) VALUES (?, ?, ?)');
        $stmt->execute([$uid, $title, $content]);
        
        $newId = $db->lastInsertId();
        jsonResponse(['success' => true, 'id' => $newId]);
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $title = trim($input['title'] ?? 'Untitled Note');
        $content = $input['content'] ?? '';
        
        if (!$id) jsonResponse(['error' => 'ID tidak valid'], 400);

        $stmt = $db->prepare('UPDATE notes SET title=?, content=? WHERE id=? AND user_id=?');
        $stmt->execute([$title, $content, $id, $uid]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        
        $stmt = $db->prepare('DELETE FROM notes WHERE id=? AND user_id=?');
        $stmt->execute([$id, $uid]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'get') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM notes WHERE id=? AND user_id=?');
        $stmt->execute([$id, $uid]);
        $note = $stmt->fetch();
        if (!$note) jsonResponse(['error' => 'Catatan tidak ditemukan'], 404);
        jsonResponse(['note' => $note]);
    }
}

jsonResponse(['error' => 'Method not allowed'], 405);
