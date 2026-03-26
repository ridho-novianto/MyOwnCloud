<?php
/**
 * Profile Page
 */
$pageTitle = 'Profil';
$pageIcon = 'user';
$pageScripts = [];
require_once __DIR__ . '/../includes/header.php';

$db = getDB();
$uid = currentUserId();
$stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$uid]);
$user = $stmt->fetch();
$storage = getUserStorageInfo($uid);
?>

<div class="profile-container">
    <div class="profile-card">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php if ($user['avatar']): ?>
                    <img src="<?= APP_URL ?>/uploads/avatars/<?= sanitize($user['avatar']) ?>" alt="Avatar">
                <?php else: ?>
                    <span><?= strtoupper(substr($user['username'], 0, 1)) ?></span>
                <?php endif; ?>
            </div>
            <h2><?= sanitize($user['username']) ?></h2>
            <p><?= sanitize($user['email']) ?></p>
            <span class="badge badge-role-<?= $user['role'] ?>"><?= strtoupper($user['role']) ?></span>
        </div>

        <div class="profile-stats">
            <div class="profile-stat">
                <strong><?= formatBytes($storage['storage_used']) ?></strong>
                <span>Storage Used</span>
            </div>
            <div class="profile-stat">
                <strong><?= formatBytes($storage['storage_quota']) ?></strong>
                <span>Storage Quota</span>
            </div>
            <div class="profile-stat">
                <strong><?= $user['last_login'] ? date('d M Y', strtotime($user['last_login'])) : '-' ?></strong>
                <span>Last Login</span>
            </div>
        </div>
    </div>

    <div class="profile-edit-card">
        <h3><i class="fas fa-edit"></i> Edit Profil</h3>
        <div class="login-error" id="profileMsg" style="display:none"></div>
        <form id="profileForm" onsubmit="updateProfile(event)">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="profUsername" value="<?= sanitize($user['username']) ?>" required minlength="3">
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="profEmail" value="<?= sanitize($user['email']) ?>" required>
            </div>
            <div class="form-group">
                <label>Password Baru (kosongkan jika tidak ingin ubah)</label>
                <input type="password" id="profPassword" placeholder="Password baru..." minlength="6">
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Simpan</button>
        </form>
    </div>
</div>

<script>
async function updateProfile(e) {
    e.preventDefault();
    const msg = document.getElementById('profileMsg');
    try {
        const res = await fetch(APP_URL + '/?page=api/profile', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'update',
                username: document.getElementById('profUsername').value,
                email: document.getElementById('profEmail').value,
                password: document.getElementById('profPassword').value
            })
        });
        const data = await res.json();
        msg.style.display = 'block';
        if (data.success) {
            msg.className = 'login-error success';
            msg.textContent = 'Profil berhasil diperbarui';
            setTimeout(() => location.reload(), 1000);
        } else {
            msg.className = 'login-error';
            msg.textContent = data.error;
        }
    } catch(err) {
        msg.style.display = 'block';
        msg.className = 'login-error';
        msg.textContent = 'Terjadi kesalahan';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
