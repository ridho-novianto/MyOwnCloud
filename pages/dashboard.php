<?php
/**
 * Dashboard Page
 */
$pageTitle = 'Dashboard';
$pageIcon = 'th-large';
$headerSearch = true;
$searchPlaceholder = 'Cari Task, Link, File...';
$pageScripts = ['dashboard.js'];
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$uid = currentUserId();

// Get stats
$taskCount = $db->prepare('SELECT COUNT(*) FROM tasks WHERE user_id = ?'); $taskCount->execute([$uid]); $totalTasks = $taskCount->fetchColumn();
$linkCount = $db->prepare('SELECT COUNT(*) FROM links WHERE user_id = ?'); $linkCount->execute([$uid]); $totalLinks = $linkCount->fetchColumn();
$fileCount = $db->prepare('SELECT COUNT(*) FROM files WHERE user_id = ?'); $fileCount->execute([$uid]); $totalFiles = $fileCount->fetchColumn();
$storage = getUserStorageInfo($uid);

// Task status breakdown
$statusStmt = $db->prepare('SELECT status, COUNT(*) as cnt FROM tasks WHERE user_id = ? GROUP BY status');
$statusStmt->execute([$uid]);
$statusData = [];
while ($row = $statusStmt->fetch()) { $statusData[$row['status']] = (int)$row['cnt']; }

// Overdue count
$overdueStmt = $db->prepare('SELECT COUNT(*) FROM tasks WHERE user_id = ? AND deadline < CURDATE() AND status NOT IN ("done","cancelled")');
$overdueStmt->execute([$uid]);
$overdueCount = $overdueStmt->fetchColumn();

// Activity 7 days
$actStmt = $db->prepare('SELECT DATE(created_at) as d, COUNT(*) as cnt FROM activity_log WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d');
$actStmt->execute([$uid]);
$activityData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $activityData[$date] = 0;
}
while ($row = $actStmt->fetch()) { $activityData[$row['d']] = (int)$row['cnt']; }

// Upcoming deadlines
$deadlineStmt = $db->prepare('SELECT id, title, deadline, status FROM tasks WHERE user_id = ? AND deadline IS NOT NULL AND status NOT IN ("done","cancelled") ORDER BY deadline ASC LIMIT 5');
$deadlineStmt->execute([$uid]);
$deadlines = $deadlineStmt->fetchAll();

// Recent tasks
$recentStmt = $db->prepare('SELECT id, title, status, created_at FROM tasks WHERE user_id = ? ORDER BY created_at DESC LIMIT 5');
$recentStmt->execute([$uid]);
$recentTasks = $recentStmt->fetchAll();

$taskPriorityCounts = $db->prepare('SELECT priority, COUNT(*) as cnt FROM tasks WHERE user_id = ? AND status != "cancelled" GROUP BY priority');
$taskPriorityCounts->execute([$uid]);
$priorityInfo = '';
while ($row = $taskPriorityCounts->fetch()) {
    $priorityInfo .= ($priorityInfo ? ', ' : '') . $row['cnt'] . ' ' . ucfirst($row['priority']);
}
?>

<!-- Quick Actions -->
<div class="quick-actions">
    <a href="?page=tasks" class="quick-action-card" onclick="event.preventDefault(); window.location.href='?page=tasks';">
        <i class="fas fa-plus"></i>
        <span>Tambah Task</span>
    </a>
    <a href="?page=links" class="quick-action-card">
        <i class="fas fa-link"></i>
        <span>Tambah Link</span>
    </a>
    <a href="?page=files" class="quick-action-card">
        <i class="fas fa-cloud-upload-alt"></i>
        <span>Upload File</span>
    </a>
    <a href="javascript:void(0)" class="quick-action-card" onclick="testNotification()">
        <i class="fas fa-bell"></i>
        <span>Test Notif</span>
    </a>
</div>

<!-- Stats Cards -->
<div class="stats-grid">
    <div class="stat-card stat-cyan">
        <div class="stat-icon"><i class="fas fa-check-square"></i></div>
        <div class="stat-content">
            <div class="stat-number"><?= $totalTasks ?></div>
            <div class="stat-label">Total Tasks</div>
            <div class="stat-detail"><?= $priorityInfo ?: 'Belum ada task' ?></div>
        </div>
        <div class="stat-bar bar-cyan"></div>
    </div>
    <div class="stat-card stat-green">
        <div class="stat-icon"><i class="fas fa-link"></i></div>
        <div class="stat-content">
            <div class="stat-number"><?= $totalLinks ?></div>
            <div class="stat-label">Saved Links</div>
            <div class="stat-detail"><?= $totalLinks ?> tersimpan</div>
        </div>
        <div class="stat-bar bar-green"></div>
    </div>
    <div class="stat-card stat-orange">
        <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
        <div class="stat-content">
            <div class="stat-number"><?= $totalFiles ?></div>
            <div class="stat-label">Total Files</div>
            <div class="stat-detail"><?= formatBytes(!empty($storage['is_admin']) ? $storage['actual_used'] : $storage['storage_used']) ?> digunakan</div>
        </div>
        <div class="stat-bar bar-orange"></div>
    </div>
    <div class="stat-card stat-purple">
        <div class="stat-icon"><i class="fas <?= !empty($storage['is_admin']) ? 'fa-hdd' : 'fa-database' ?>"></i></div>
        <div class="stat-content">
            <div class="stat-number"><?= formatBytes($storage['storage_used']) ?></div>
            <div class="stat-label"><?= !empty($storage['is_admin']) ? 'System Storage' : 'Storage Used' ?></div>
            <div class="stat-detail">Total: <?= formatBytes($storage['storage_quota']) ?></div>
        </div>
        <div class="stat-bar bar-purple"></div>
    </div>
</div>

<!-- Charts Row -->
<div class="charts-grid">
    <div class="chart-card">
        <h3><i class="fas fa-chart-pie"></i> Status Task</h3>
        <div class="chart-container">
            <canvas id="taskStatusChart"></canvas>
        </div>
        <div class="chart-legend" id="taskLegend"></div>
    </div>
    <div class="chart-card">
        <h3><i class="fas fa-chart-bar"></i> Aktivitas 7 Hari</h3>
        <div class="chart-container">
            <canvas id="activityChart"></canvas>
        </div>
    </div>
</div>

<!-- Bottom Row -->
<div class="bottom-grid">
    <div class="info-card">
        <div class="info-card-header">
            <h3><i class="fas fa-clock"></i> Deadline Terdekat</h3>
            <a href="?page=tasks" class="btn btn-sm">Lihat Semua</a>
        </div>
        <div class="info-list">
            <?php if (empty($deadlines)): ?>
                <div class="empty-state small">Tidak ada deadline</div>
            <?php else: ?>
                <?php foreach ($deadlines as $task): ?>
                <div class="info-list-item">
                    <div class="info-dot <?= deadlineClass($task['deadline']) ?>"></div>
                    <span class="info-title"><?= sanitize($task['title']) ?></span>
                    <span class="info-meta deadline-<?= deadlineClass($task['deadline']) ?>">
                        <?php
                        $days = daysUntil($task['deadline']);
                        if ($days < 0): ?>
                            <i class="fas fa-exclamation-circle"></i> <?= abs($days) ?> hari terlambat
                        <?php elseif ($days === 0): ?>
                            <i class="fas fa-fire"></i> Hari ini
                        <?php else: ?>
                            <i class="fas fa-calendar"></i> <?= date('d M Y', strtotime($task['deadline'])) ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="info-card">
        <div class="info-card-header">
            <h3><i class="fas fa-tasks"></i> Task Terbaru</h3>
            <a href="?page=tasks" class="btn btn-sm">Lihat Semua</a>
        </div>
        <div class="info-list">
            <?php if (empty($recentTasks)): ?>
                <div class="empty-state small">Belum ada task</div>
            <?php else: ?>
                <?php foreach ($recentTasks as $task): ?>
                <div class="info-list-item">
                    <span class="badge badge-<?= $task['status'] ?>"><?= strtoupper(str_replace('_', ' ', $task['status'])) ?></span>
                    <span class="info-title"><?= sanitize($task['title']) ?></span>
                    <span class="info-date"><?= date('d M Y', strtotime($task['created_at'])) ?></span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Chart Data -->
<script>
window.chartData = {
    status: {
        todo: <?= $statusData['todo'] ?? 0 ?>,
        in_progress: <?= $statusData['in_progress'] ?? 0 ?>,
        done: <?= $statusData['done'] ?? 0 ?>,
        cancelled: <?= $statusData['cancelled'] ?? 0 ?>,
        overdue: <?= $overdueCount ?>
    },
    activity: {
        labels: <?= json_encode(array_map(fn($d) => date('D', strtotime($d)), array_keys($activityData))) ?>,
        data: <?= json_encode(array_values($activityData)) ?>
    }
};
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
