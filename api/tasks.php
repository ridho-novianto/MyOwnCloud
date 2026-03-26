<?php
/**
 * Tasks API - CRUD
 */
header('Content-Type: application/json');
requireLogin();

$db = getDB();
$uid = currentUserId();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $filter = $_GET['filter'] ?? 'all';
    $priority = $_GET['priority'] ?? '';
    $sort = $_GET['sort'] ?? 'deadline';
    $search = $_GET['search'] ?? '';

    $where = ['t.user_id = ?'];
    $params = [$uid];

    if ($filter === 'overdue') {
        $where[] = 't.deadline < CURDATE() AND t.status NOT IN ("done","cancelled")';
    } elseif ($filter !== 'all') {
        $where[] = 't.status = ?';
        $params[] = $filter;
    }

    if ($priority) {
        $where[] = 't.priority = ?';
        $params[] = $priority;
    }

    if ($search) {
        $where[] = '(t.title LIKE ? OR t.description LIKE ?)';
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $orderBy = match($sort) {
        'priority' => 'FIELD(t.priority, "urgent","high","medium","low"), t.deadline ASC',
        'created' => 't.created_at DESC',
        default => 'CASE WHEN t.deadline IS NULL THEN 1 ELSE 0 END, t.deadline ASC'
    };

    $sql = 'SELECT t.* FROM tasks t WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    jsonResponse(['tasks' => $stmt->fetchAll()]);
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'create';

    if ($action === 'create') {
        $title = trim($input['title'] ?? '');
        if (!$title) jsonResponse(['error' => 'Judul harus diisi'], 400);

        $stmt = $db->prepare('INSERT INTO tasks (user_id, title, description, status, priority, deadline, tags) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            $uid, $title,
            $input['description'] ?? '',
            $input['status'] ?? 'todo',
            $input['priority'] ?? 'medium',
            $input['deadline'] ?: null,
            $input['tags'] ?? ''
        ]);
        logActivity($uid, 'task_create', "Created task: $title");
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
    }

    if ($action === 'update') {
        $id = (int)($input['id'] ?? 0);
        $title = trim($input['title'] ?? '');
        if (!$id || !$title) jsonResponse(['error' => 'Data tidak valid'], 400);

        $stmt = $db->prepare('UPDATE tasks SET title=?, description=?, status=?, priority=?, deadline=?, tags=? WHERE id=? AND user_id=?');
        $stmt->execute([
            $title,
            $input['description'] ?? '',
            $input['status'] ?? 'todo',
            $input['priority'] ?? 'medium',
            $input['deadline'] ?: null,
            $input['tags'] ?? '',
            $id, $uid
        ]);
        logActivity($uid, 'task_update', "Updated task: $title");
        jsonResponse(['success' => true]);
    }

    if ($action === 'toggle') {
        $id = (int)($input['id'] ?? 0);
        $newStatus = $input['new_status'] ?? 'done';
        $stmt = $db->prepare('UPDATE tasks SET status=? WHERE id=? AND user_id=?');
        $stmt->execute([$newStatus, $id, $uid]);
        jsonResponse(['success' => true]);
    }

    if ($action === 'delete') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM tasks WHERE id=? AND user_id=?');
        $stmt->execute([$id, $uid]);
        logActivity($uid, 'task_delete', "Deleted task #$id");
        jsonResponse(['success' => true]);
    }

    if ($action === 'get') {
        $id = (int)($input['id'] ?? 0);
        $stmt = $db->prepare('SELECT * FROM tasks WHERE id=? AND user_id=?');
        $stmt->execute([$id, $uid]);
        $task = $stmt->fetch();
        if (!$task) jsonResponse(['error' => 'Task tidak ditemukan'], 404);
        jsonResponse(['task' => $task]);
    }
}

jsonResponse(['error' => 'Method not allowed'], 405);
