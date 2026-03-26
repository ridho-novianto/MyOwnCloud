/**
 * Admin Panel JS
 */

function showAdminTab(tab) {
    document.querySelectorAll('.admin-panel').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.admin-tab').forEach(t => t.classList.remove('active'));
    document.getElementById(tab + 'Panel').style.display = 'block';
    event.target.closest('.admin-tab').classList.add('active');
}

async function editUser(id) {
    try {
        const data = await apiCall('admin', { action: 'get_user', id });
        if (data.user) {
            document.getElementById('editUserId').value = data.user.id;
            document.getElementById('editUserRole').value = data.user.role;
            document.getElementById('editUserStatus').value = data.user.status;
            document.getElementById('editUserQuota').value = data.user.storage_quota_gb;
            openModal('editUserModal');
        }
    } catch(err) {
        showToast('Gagal memuat user', 'error');
    }
}

async function updateUser(e) {
    e.preventDefault();
    try {
        const res = await apiCall('admin', {
            action: 'update_user',
            id: document.getElementById('editUserId').value,
            role: document.getElementById('editUserRole').value,
            status: document.getElementById('editUserStatus').value,
            quota: document.getElementById('editUserQuota').value
        });
        if (res.success) {
            showToast('User diperbarui');
            closeModal();
            location.reload();
        } else {
            showToast(res.error || 'Gagal', 'error');
        }
    } catch(err) {
        showToast('Terjadi kesalahan', 'error');
    }
}

async function deleteUser(id) {
    if (!confirm('Hapus user ini? Semua data user akan terhapus.')) return;
    try {
        const res = await apiCall('admin', { action: 'delete_user', id });
        if (res.success) {
            showToast('User dihapus');
            document.querySelector(`tr[data-user-id="${id}"]`)?.remove();
        }
    } catch(err) {
        showToast('Gagal menghapus', 'error');
    }
}

async function addUser(e) {
    e.preventDefault();
    try {
        const res = await apiCall('admin', {
            action: 'add_user',
            username: document.getElementById('newUsername').value,
            email: document.getElementById('newEmail').value,
            password: document.getElementById('newPassword').value,
            role: document.getElementById('newRole').value,
            quota: document.getElementById('newQuota').value
        });
        if (res.success) {
            showToast('User ditambahkan');
            location.reload();
        } else {
            showToast(res.error || 'Gagal', 'error');
        }
    } catch(err) {
        showToast('Terjadi kesalahan', 'error');
    }
}
