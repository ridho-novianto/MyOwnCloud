/**
 * MyOwnCloud - Core Application JS
 */
const manifestLink = document.querySelector('link[rel="manifest"]');
const APP_URL = manifestLink ? manifestLink.href.replace(/\/manifest\.json$/, '') : window.location.origin;

// Sidebar toggle
document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const main = document.getElementById('mainContent');

    if (toggle) {
        toggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('open');
            main.classList.toggle('shifted');
        });
    }

    // Close sidebar on mobile when clicking outside (but not on the toggle)
    if (main) {
        main.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && sidebar.classList.contains('open')) {
                // Don't close if clicking the toggle button itself
                if (e.target.closest('.sidebar-toggle')) return;
                sidebar.classList.remove('open');
                main.classList.remove('shifted');
            }
        });
    }

    // Filter tabs
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.addEventListener('click', function () {
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
        searchInput.addEventListener('input', function () {
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
    } catch (err) { }
    window.location.href = APP_URL + '/?page=login';
}

// Test notification
async function testNotification() {
    if ('Notification' in window) {
        if (Notification.permission === 'granted') {
            try {
                // Use Service Worker showNotification (works on mobile)
                let registration = await navigator.serviceWorker.getRegistration();
                if (!registration) {
                    registration = await navigator.serviceWorker.register(APP_URL + '/sw.js?v=5');
                    await navigator.serviceWorker.ready;
                }
                await registration.showNotification('MyOwnCloud', {
                    body: 'Notifikasi berfungsi dengan baik!',
                    icon: APP_URL + '/assets/icons/icon-192.png',
                    badge: APP_URL + '/assets/icons/icon-72.png',
                    vibrate: [200, 100, 200],
                    tag: 'test-dashboard',
                    requireInteraction: true
                });
                showToast('Test notifikasi dikirim!');
            } catch (err) {
                console.error('Notification error:', err);
                showToast('Gagal mengirim notifikasi: ' + err.message, 'error');
            }
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

// Background Notification System
// Replaces cron-based approach (cron URL is blocked by nginx security rules)
document.addEventListener('DOMContentLoaded', () => {
    // Run on page load after short delay
    setTimeout(runNotificationCycle, 3000);
    
    // Run every 4 hours (server-side push handles immediate delivery)
    setInterval(runNotificationCycle, 4 * 60 * 60 * 1000);
});

async function runNotificationCycle() {
    try {
        // Step 1: Trigger server-side deadline check (creates notification records in DB)
        await apiCall('notifications', { action: 'trigger_check' });
        
        // Step 2: Fetch any pending (unread) notifications
        const res = await apiCall('notifications', { action: 'get_pending' });
        if (!res.success || !res.notifications || res.notifications.length === 0) return;
        
        // Step 3: Show them as browser notifications
        const shownIds = [];
        for (const notif of res.notifications) {
            const shown = await showBrowserNotification(
                notif.message.includes('terlambat') ? '⚠️ Task Terlambat!' : '📋 Deadline Reminder',
                notif.message,
                'deadline-' + (notif.task_id || notif.id)
            );
            if (shown) shownIds.push(notif.id);
        }
        
        // Step 4: Mark shown notifications as read so they don't re-appear
        if (shownIds.length > 0) {
            await apiCall('notifications', { action: 'mark_shown', ids: shownIds });
        }
    } catch (e) {
        console.log('[NotifCycle] Error:', e);
    }
}

async function showBrowserNotification(title, body, tag) {
    if (!('Notification' in window)) return false;
    
    if (Notification.permission !== 'granted') {
        // Don't ask for permission automatically — user must enable via Notifications page
        return false;
    }
    
    try {
        let registration = await navigator.serviceWorker.getRegistration();
        if (!registration) {
            registration = await navigator.serviceWorker.register(APP_URL + '/sw.js?v=5');
            await navigator.serviceWorker.ready;
        }
        
        await registration.showNotification(title, {
            body: body,
            icon: APP_URL + '/assets/icons/icon-192.png',
            badge: APP_URL + '/assets/icons/icon-72.png',
            vibrate: [200, 100, 200],
            tag: tag || 'myowncloud-' + Date.now(),
            renotify: true,
            requireInteraction: true,
            data: { url: APP_URL + '/?page=tasks' },
            actions: [
                { action: 'open', title: 'Buka Tasks' },
                { action: 'dismiss', title: 'Tutup' }
            ]
        });
        return true;
    } catch (e) {
        console.log('[Notif] showNotification error:', e);
        return false;
    }
}
