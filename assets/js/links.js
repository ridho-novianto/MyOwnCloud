/**
 * Links JS - CRUD Operations
 */

function openLinkModal() {
    document.getElementById('linkId').value = '';
    document.getElementById('linkTitle').value = '';
    document.getElementById('linkUrl').value = '';
    document.getElementById('linkDesc').value = '';
    document.getElementById('linkCategory').value = 'Uncategorized';
    document.getElementById('linkColor').value = '#00e5ff';
    document.getElementById('linkModalTitle').textContent = 'Tambah Link';
    openModal('linkModal');
}

async function editLink(id) {
    try {
        const data = await apiCall('links', { action: 'get', id });
        if (data.link) {
            const l = data.link;
            document.getElementById('linkId').value = l.id;
            document.getElementById('linkTitle').value = l.title;
            document.getElementById('linkUrl').value = l.url;
            document.getElementById('linkDesc').value = l.description || '';
            document.getElementById('linkCategory').value = l.category;
            document.getElementById('linkColor').value = l.icon_color;
            document.getElementById('linkModalTitle').textContent = 'Edit Link';
            openModal('linkModal');
        }
    } catch(err) {
        showToast('Gagal memuat link', 'error');
    }
}

async function saveLink(e) {
    e.preventDefault();
    const id = document.getElementById('linkId').value;
    const data = {
        action: id ? 'update' : 'create',
        id: id || undefined,
        title: document.getElementById('linkTitle').value,
        url: document.getElementById('linkUrl').value,
        description: document.getElementById('linkDesc').value,
        category: document.getElementById('linkCategory').value,
        icon_color: document.getElementById('linkColor').value
    };

    try {
        const res = await apiCall('links', data);
        if (res.success) {
            showToast(id ? 'Link diperbarui' : 'Link ditambahkan');
            closeModal();
            location.reload();
        } else {
            showToast(res.error || 'Gagal menyimpan', 'error');
        }
    } catch(err) {
        showToast('Terjadi kesalahan', 'error');
    }
}

async function deleteLink(id) {
    if (!confirm('Hapus link ini?')) return;
    try {
        const res = await apiCall('links', { action: 'delete', id });
        if (res.success) {
            showToast('Link dihapus');
            document.querySelector(`.link-card[data-id="${id}"]`)?.remove();
        }
    } catch(err) {
        showToast('Gagal menghapus', 'error');
    }
}

async function togglePin(id, isPinned) {
    try {
        const res = await apiCall('links', { action: 'toggle_pin', id });
        if (res.success) location.reload();
    } catch(err) {}
}

async function trackClick(id) {
    try {
        await apiCall('links', { action: 'track_click', id });
    } catch(err) {}
}
