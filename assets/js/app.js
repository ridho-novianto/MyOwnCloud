/**
 * MyOwnCloud - Core Application JS
 */
const APP_URL = document.querySelector('meta[name="theme-color"]') ? 
    window.location.origin + '/myowncloud' : '/myowncloud';

// Sidebar toggle
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('mainContent');

    if (toggle) {
        toggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            main.classList.toggle('shifted');
        });
    }

    // Close sidebar on mobile when clicking outside
    if (main) {
        main.addEventListener('click', () => {
            if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
                sidebar.classList.remove('open');
                main.classList.remove('shifted');
            }
        });
    }

    // Filter tabs
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            const parent = this.closest('.filter-tabs, .file-filters');
            parent.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            const filter = this.dataset.filter;
            filterItems(filter);
        });
    });

    // Search
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        let timeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => searchItems(this.value), 300);
        });
    }
});

// Filter items based on data attributes
function filterItems(filter) {
    const page = new URLSearchParams(window.location.search).get('page');
    
    if (page === 'tasks') {
        document.querySelectorAll('.task-item').forEach(item => {
            if (filter === 'all') {
                item.style.display = '';
            } else if (filter === 'overdue') {
                item.style.display = item.dataset.overdue === '1' ? '' : 'none';
            } else {
                item.style.display = item.dataset.status === filter ? '' : 'none';
            }
        });
    } else if (page === 'links') {
        document.querySelectorAll('.link-card').forEach(item => {
            item.style.display = (filter === 'all' || item.dataset.category === filter) ? '' : 'none';
        });
    } else if (page === 'files') {
        document.querySelectorAll('.file-item').forEach(item => {
            if (filter === 'all') {
                item.style.display = '';
            } else {
                const mime = item.dataset.mime || '';
                const type = item.dataset.type || '';
                let show = false;
                if (filter === 'image') show = mime.startsWith('image/');
                else if (filter === 'document') show = mime.includes('pdf') || mime.includes('word') || mime.includes('text') || mime.includes('sheet');
                else if (filter === 'video') show = mime.startsWith('video/');
                else if (filter === 'audio') show = mime.startsWith('audio/');
                else if (filter === 'archive') show = mime.includes('zip') || mime.includes('rar') || mime.includes('tar') || mime.includes('gz');
                else if (type === 'folder') show = true;
                item.style.display = show || type === 'folder' ? '' : 'none';
            }
        });
    }
}

// Search items
function searchItems(query) {
    query = query.toLowerCase();
    const page = new URLSearchParams(window.location.search).get('page');
    
    if (page === 'tasks') {
        document.querySelectorAll('.task-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? '' : 'none';
        });
    } else if (page === 'links') {
        document.querySelectorAll('.link-card').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? '' : 'none';
        });
    } else if (page === 'files') {
        document.querySelectorAll('.file-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(query) ? '' : 'none';
        });
    }
}

// Modal management
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    const overlay = document.getElementById('modalOverlay');
    if (modal) modal.classList.add('active');
    if (overlay) overlay.classList.add('active');
}

function closeModal() {
    document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
    const overlay = document.getElementById('modalOverlay');
    if (overlay) overlay.classList.remove('active');
}

// Toast notification
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle';
    toast.innerHTML = `<i class="fas fa-${icon}"></i> <span>${message}</span>`;
    container.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 3000);
}

// Logout
async function handleLogout(e) {
    e.preventDefault();
    try {
        await fetch(APP_URL + '/?page=api/auth', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'logout' })
        });
    } catch(err) {}
    window.location.href = APP_URL + '/?page=login';
}

// Test notification
function testNotification() {
    if ('Notification' in window) {
        if (Notification.permission === 'granted') {
            new Notification('MyOwnCloud', {
                body: 'Notifikasi berfungsi dengan baik!',
                icon: APP_URL + '/assets/icons/icon-192.png'
            });
            showToast('Test notifikasi dikirim!');
        } else if (Notification.permission !== 'denied') {
            Notification.requestPermission().then(p => {
                if (p === 'granted') testNotification();
            });
        } else {
            showToast('Notifikasi diblokir oleh browser', 'error');
        }
    }
}

// API helper
async function apiCall(endpoint, data) {
    const res = await fetch(APP_URL + '/?page=api/' + endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return res.json();
}
