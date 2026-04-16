<?php
/**
 * Task/Todo Manager Page
 */
$pageTitle = 'Task Manager';
$pageIcon = 'check-square';
$headerSearch = true;
$searchPlaceholder = 'Cari Task...';
$headerButton = ['text' => 'Tambah Task', 'onclick' => 'openTaskModal()', 'id' => 'addTaskBtn'];
$pageScripts = ['tasks.js'];
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$uid = currentUserId();

// Counts
$counts = ['total' => 0, 'todo' => 0, 'in_progress' => 0, 'done' => 0, 'overdue' => 0];
$stmtC = $db->prepare('SELECT status, COUNT(*) as cnt FROM tasks WHERE user_id = ? GROUP BY status');
$stmtC->execute([$uid]);
while ($r = $stmtC->fetch()) { $counts[$r['status']] = (int)$r['cnt']; $counts['total'] += (int)$r['cnt']; }

$oStmt = $db->prepare('SELECT COUNT(*) FROM tasks WHERE user_id = ? AND deadline < CURDATE() AND status NOT IN ("done","cancelled")');
$oStmt->execute([$uid]);
$counts['overdue'] = (int)$oStmt->fetchColumn();

// All tasks
$tasks = $db->prepare('SELECT * FROM tasks WHERE user_id = ? ORDER BY CASE WHEN deadline IS NULL THEN 1 ELSE 0 END, deadline ASC, FIELD(priority, "urgent","high","medium","low")');
$tasks->execute([$uid]);
$allTasks = $tasks->fetchAll();
?>

<!-- Stats counters -->
<div class="task-counters">
    <div class="counter-pill"><span class="counter-num"><?= $counts['total'] ?></span> Total</div>
    <div class="counter-pill"><span class="counter-num"><?= $counts['todo'] ?></span> To Do</div>
    <div class="counter-pill"><span class="counter-num"><?= $counts['in_progress'] ?></span> Progress</div>
    <div class="counter-pill"><span class="counter-num"><?= $counts['done'] ?></span> Done</div>
    <div class="counter-pill overdue"><span class="counter-num"><?= $counts['overdue'] ?></span> Terlambat</div>
</div>

<!-- Filter tabs -->
<div class="filter-row">
    <div class="filter-tabs">
        <button class="filter-tab active" data-filter="all">Semua</button>
        <button class="filter-tab" data-filter="todo">To Do</button>
        <button class="filter-tab" data-filter="in_progress">In Progress</button>
        <button class="filter-tab" data-filter="done">Done</button>
        <button class="filter-tab filter-overdue" data-filter="overdue"><i class="fas fa-exclamation-circle"></i> Terlambat</button>
    </div>
    <div class="filter-actions">
        <select id="priorityFilter" class="select-styled" onchange="filterTasks()">
            <option value="">Semua Prioritas</option>
            <option value="urgent">Urgent</option>
            <option value="high">High</option>
            <option value="medium">Medium</option>
            <option value="low">Low</option>
        </select>
        <select id="sortFilter" class="select-styled" onchange="filterTasks()">
            <option value="deadline">Sort: Deadline</option>
            <option value="priority">Sort: Prioritas</option>
            <option value="created">Sort: Terbaru</option>
        </select>
    </div>
</div>

<!-- Task List -->
<div class="task-list" id="taskList">
    <?php if (empty($allTasks)): ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-list"></i>
            <h3>Belum ada task</h3>
            <p>Klik "Tambah Task" untuk memulai</p>
        </div>
    <?php else: ?>
        <?php foreach ($allTasks as $task):
            $isOverdue = $task['deadline'] && daysUntil($task['deadline']) < 0 && !in_array($task['status'], ['done', 'cancelled']);
            $deadlineDays = daysUntil($task['deadline']);
        ?>
        <div class="task-item" data-id="<?= $task['id'] ?>" data-status="<?= $task['status'] ?>" data-priority="<?= $task['priority'] ?>" data-overdue="<?= $isOverdue ? '1' : '0' ?>" data-deadline="<?= $task['deadline'] ?>" data-created="<?= $task['created_at'] ?>">
            <div class="task-check">
                <button class="check-btn <?= $task['status'] === 'done' ? 'checked' : '' ?>" onclick="toggleTaskDone(<?= $task['id'] ?>, '<?= $task['status'] ?>')">
                    <i class="fas fa-<?= $task['status'] === 'done' ? 'check-circle' : 'circle' ?>"></i>
                </button>
            </div>
            <div class="task-content">
                <h4 class="<?= $task['status'] === 'done' ? 'task-done' : '' ?>"><?= sanitize($task['title']) ?></h4>
                <?php if ($task['description']): ?>
                    <p class="task-desc"><?= sanitize($task['description']) ?></p>
                <?php endif; ?>
                <div class="task-meta">
                    <span class="badge badge-<?= $task['status'] ?>"><?= strtoupper(str_replace('_', ' ', $task['status'])) ?></span>
                    <span class="badge badge-priority-<?= $task['priority'] ?>"><?= strtoupper($task['priority']) ?></span>
                    <?php if ($task['deadline']): ?>
                        <?php if (in_array($task['status'], ['done', 'cancelled'])): ?>
                            <span class="deadline-tag safe">
                                <i class="fas fa-calendar-check"></i> <?= date('d M Y', strtotime($task['deadline'])) ?>
                            </span>
                        <?php else: ?>
                            <span class="deadline-tag <?= deadlineClass($task['deadline']) ?>">
                                <i class="fas fa-calendar-alt"></i> <?= deadlineLabel($task['deadline']) ?>
                            </span>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($task['tags']): ?>
                        <?php foreach (explode(',', $task['tags']) as $tag): ?>
                            <span class="tag">#<?= sanitize(trim($tag)) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="task-actions">
                <button class="action-btn" onclick="editTask(<?= $task['id'] ?>)" title="Edit">
                    <i class="fas fa-pen"></i>
                </button>
                <button class="action-btn danger" onclick="deleteTask(<?= $task['id'] ?>)" title="Hapus">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Task Modal -->
<div class="modal" id="taskModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="taskModalTitle">Tambah Task</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="taskForm" onsubmit="saveTask(event)">
            <input type="hidden" id="taskId" value="">
            <div class="form-group">
                <label>Judul Task</label>
                <input type="text" id="taskTitle" required placeholder="Judul task..." maxlength="255">
            </div>
            <div class="form-group">
                <label>Deskripsi</label>
                <textarea id="taskDesc" placeholder="Deskripsi (opsional)..." rows="3"></textarea>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Status</label>
                    <select id="taskStatus">
                        <option value="todo">To Do</option>
                        <option value="in_progress">In Progress</option>
                        <option value="done">Done</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Prioritas</label>
                    <select id="taskPriority">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Deadline</label>
                    <input type="date" id="taskDeadline">
                </div>
                <div class="form-group">
                    <label>Tags</label>
                    <input type="text" id="taskTags" placeholder="tag1, tag2, ...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary" id="taskSaveBtn">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
