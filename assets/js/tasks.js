/**
 * Tasks JS - CRUD Operations
 */

function openTaskModal(id = null) {
    document.getElementById('taskId').value = '';
    document.getElementById('taskTitle').value = '';
    document.getElementById('taskDesc').value = '';
    document.getElementById('taskStatus').value = 'todo';
    document.getElementById('taskPriority').value = 'medium';
    document.getElementById('taskDeadline').value = '';
    document.getElementById('taskTags').value = '';
    document.getElementById('taskModalTitle').textContent = 'Tambah Task';
    openModal('taskModal');
}

async function editTask(id) {
    try {
        const data = await apiCall('tasks', { action: 'get', id });
        if (data.task) {
            const t = data.task;
            document.getElementById('taskId').value = t.id;
            document.getElementById('taskTitle').value = t.title;
            document.getElementById('taskDesc').value = t.description || '';
            document.getElementById('taskStatus').value = t.status;
            document.getElementById('taskPriority').value = t.priority;
            document.getElementById('taskDeadline').value = t.deadline || '';
            document.getElementById('taskTags').value = t.tags || '';
            document.getElementById('taskModalTitle').textContent = 'Edit Task';
            openModal('taskModal');
        }
    } catch(err) {
        showToast('Gagal memuat task', 'error');
    }
}

async function saveTask(e) {
    e.preventDefault();
    const id = document.getElementById('taskId').value;
    const data = {
        action: id ? 'update' : 'create',
        id: id || undefined,
        title: document.getElementById('taskTitle').value,
        description: document.getElementById('taskDesc').value,
        status: document.getElementById('taskStatus').value,
        priority: document.getElementById('taskPriority').value,
        deadline: document.getElementById('taskDeadline').value,
        tags: document.getElementById('taskTags').value
    };

    try {
        const res = await apiCall('tasks', data);
        if (res.success) {
            showToast(id ? 'Task diperbarui' : 'Task ditambahkan');
            closeModal();
            location.reload();
        } else {
            showToast(res.error || 'Gagal menyimpan', 'error');
        }
    } catch(err) {
        showToast('Terjadi kesalahan', 'error');
    }
}

async function deleteTask(id) {
    if (!confirm('Hapus task ini?')) return;
    try {
        const res = await apiCall('tasks', { action: 'delete', id });
        if (res.success) {
            showToast('Task dihapus');
            document.querySelector(`.task-item[data-id="${id}"]`)?.remove();
        }
    } catch(err) {
        showToast('Gagal menghapus', 'error');
    }
}

async function toggleTaskDone(id, currentStatus) {
    const newStatus = currentStatus === 'done' ? 'todo' : 'done';
    try {
        const res = await apiCall('tasks', { action: 'toggle', id, new_status: newStatus });
        if (res.success) location.reload();
    } catch(err) {}
}

function filterTasks() {
    const priority = document.getElementById('priorityFilter')?.value;
    const sort = document.getElementById('sortFilter')?.value;
    const items = Array.from(document.querySelectorAll('.task-item'));

    // Apply priority filter
    items.forEach(item => {
        if (priority && item.dataset.priority !== priority) {
            item.style.display = 'none';
        } else {
            item.style.display = '';
        }
    });

    // Apply sort
    const container = document.getElementById('taskList');
    const visible = items.filter(i => i.style.display !== 'none');
    visible.sort((a, b) => {
        if (sort === 'priority') {
            const order = { urgent: 0, high: 1, medium: 2, low: 3 };
            return (order[a.dataset.priority] || 2) - (order[b.dataset.priority] || 2);
        } else if (sort === 'created') {
            return new Date(b.dataset.created) - new Date(a.dataset.created);
        } else {
            const da = a.dataset.deadline || '9999-12-31';
            const db = b.dataset.deadline || '9999-12-31';
            return da.localeCompare(db);
        }
    });
    visible.forEach(item => container.appendChild(item));
}
