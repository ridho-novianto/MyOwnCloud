<?php
/**
 * Admin Panel Page
 */
$pageTitle = 'Admin Panel';
$pageIcon = 'cog';
$pageScripts = ['admin.js'];
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

$db = getDB();

// Stats
$totalUsers = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalTasks = $db->query('SELECT COUNT(*) FROM tasks')->fetchColumn();
$totalFiles = $db->query('SELECT COUNT(*) FROM files')->fetchColumn();
$totalStorage = $db->query('SELECT COALESCE(SUM(storage_used),0) FROM users')->fetchColumn();

// Admin/user count
$adminCount = $db->query('SELECT COUNT(*) FROM users WHERE role="admin"')->fetchColumn();
$userCount = $totalUsers - $adminCount;

// Users list
$users = $db->query('SELECT u.*, 
    (SELECT COUNT(*) FROM tasks WHERE user_id = u.id) as task_count,
    (SELECT COUNT(*) FROM files WHERE user_id = u.id) as file_count
    FROM users u ORDER BY u.created_at DESC')->fetchAll();

// Recent activity
$activities = $db->query('SELECT a.*, u.username FROM activity_log a JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 20')->fetchAll();
?>

<!-- Admin Stats -->
<div class="stats-grid">
    <div class="stat-card stat-cyan">
        <div class="stat-icon"><i class="fas fa-users"></i></div>
        <div class="stat-content">
            <div class="stat-number"><?= $totalUsers ?></div>
            <div class="stat-label">Total Users</div>
            <div class="stat-detail"><?= $adminCount ?> Admin · <?= $userCount ?> User</div>
        </div>
        <div class="stat-bar bar-cyan"></div>
    </div>
    <div class="stat-card stat-green">
        <div class="stat-icon"><i class="fas fa-check-square"></i></div>
        <div class="stat-content">
            <div class="stat-number"><?= $totalTasks ?></div>
            <div class="stat-label">Total Tasks</div>
            <div class="stat-detail">Keseluruhan</div>
        </div>
        <div class="stat-bar bar-green"></div>
    </div>
    <div class="stat-card stat-orange">
        <div class="stat-icon"><i class="fas fa-folder-open"></i></div>
        <div class="stat-content">
            <div class="stat-number"><?= $totalFiles ?></div>
            <div class="stat-label">Total Files</div>
            <div class="stat-detail"><?= formatBytes($totalStorage) ?></div>
        </div>
        <div class="stat-bar bar-orange"></div>
    </div>
    <div class="stat-card stat-purple">
        <div class="stat-icon"><i class="fas fa-database"></i></div>
        <div class="stat-content">
            <div class="stat-number"><?= formatBytes($totalStorage) ?></div>
            <div class="stat-label">Total Storage</div>
            <div class="stat-detail">Seluruh user</div>
        </div>
        <div class="stat-bar bar-purple"></div>
    </div>
</div>

<!-- Tabs -->
<div class="admin-tabs">
    <button class="admin-tab active" onclick="showAdminTab('users')"><i class="fas fa-users"></i> Users</button>
    <button class="admin-tab" onclick="showAdminTab('activity')"><i class="fas fa-list"></i> Activity Log</button>
    <button class="admin-tab" onclick="showAdminTab('adduser')"><i class="fas fa-user-plus"></i> Tambah User</button>
</div>

<!-- Users Table -->
<div class="admin-panel" id="usersPanel">
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>USER</th>
                    <th>ROLE</th>
                    <th>STATUS</th>
                    <th>STORAGE</th>
                    <th>TASKS</th>
                    <th>FILES</th>
                    <th>LAST LOGIN</th>
                    <th>AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr data-user-id="<?= $u['id'] ?>">
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar small">
                                <span><?= strtoupper(substr($u['username'], 0, 1)) ?></span>
                            </div>
                            <div>
                                <strong><?= sanitize($u['username']) ?></strong><br>
                                <small><?= sanitize($u['email']) ?></small>
                            </div>
                        </div>
                    </td>
                    <td><span class="badge badge-role-<?= $u['role'] ?>"><?= strtoupper($u['role']) ?></span></td>
                    <td><span class="status-dot <?= $u['status'] ?>"></span> <?= ucfirst($u['status']) ?></td>
                    <td><?= formatBytes($u['storage_used']) ?><br><small><?= formatBytes($u['storage_quota']) ?></small></td>
                    <td><?= $u['task_count'] ?></td>
                    <td><?= $u['file_count'] ?></td>
                    <td><?= $u['last_login'] ? date('d M Y, H:i', strtotime($u['last_login'])) : '-' ?></td>
                    <td>
                        <div class="table-actions">
                            <button class="action-btn" onclick="editUser(<?= $u['id'] ?>)" title="Edit"><i class="fas fa-pen"></i></button>
                            <?php if ($u['id'] !== currentUserId()): ?>
                            <button class="action-btn danger" onclick="deleteUser(<?= $u['id'] ?>)" title="Hapus"><i class="fas fa-trash"></i></button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Activity Log -->
<div class="admin-panel" id="activityPanel" style="display:none">
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>USER</th>
                    <th>ACTION</th>
                    <th>DETAILS</th>
                    <th>WAKTU</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($activities as $act): ?>
                <tr>
                    <td><?= sanitize($act['username']) ?></td>
                    <td><span class="badge badge-action"><?= sanitize($act['action']) ?></span></td>
                    <td><?= sanitize($act['details']) ?></td>
                    <td><?= timeAgo($act['created_at']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add User Form -->
<div class="admin-panel" id="adduserPanel" style="display:none">
    <div class="form-card">
        <form id="addUserForm" onsubmit="addUser(event)">
            <div class="form-row">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="newUsername" required placeholder="Username" minlength="3">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="newEmail" required placeholder="email@example.com">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" id="newPassword" required placeholder="Min 6 karakter" minlength="6">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select id="newRole">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Storage Quota (GB)</label>
                <input type="number" id="newQuota" value="1" min="1" max="100">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-user-plus"></i> Tambah User</button>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal" id="editUserModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Edit User</h3>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form id="editUserForm" onsubmit="updateUser(event)">
            <input type="hidden" id="editUserId">
            <div class="form-group">
                <label>Role</label>
                <select id="editUserRole"><option value="user">User</option><option value="admin">Admin</option></select>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="editUserStatus"><option value="active">Active</option><option value="inactive">Inactive</option><option value="banned">Banned</option></select>
            </div>
            <div class="form-group">
                <label>Storage Quota (GB)</label>
                <input type="number" id="editUserQuota" value="1" min="1" max="100">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
