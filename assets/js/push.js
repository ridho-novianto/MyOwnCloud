/**
 * Push Notification JS
 */

document.addEventListener('DOMContentLoaded', () => {
    checkNotificationStatus();
});

async function checkNotificationStatus() {
    const dot = document.getElementById('notifStatusDot');
    const text = document.getElementById('notifStatusText');
    const enableBtn = document.getElementById('enableNotifBtn');
    const disableBtn = document.getElementById('disableNotifBtn');
    const testBtn = document.getElementById('testNotifBtn');

    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        dot.className = 'status-dot inactive';
        text.textContent = 'Browser tidak mendukung push notification';
        return;
    }

    if (Notification.permission === 'denied') {
        dot.className = 'status-dot inactive';
        text.textContent = 'Notifikasi diblokir oleh browser. Ubah di pengaturan browser.';
        return;
    }

    try {
        const registration = await navigator.serviceWorker.getRegistration();
        if (registration) {
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                dot.className = 'status-dot active';
                text.textContent = 'Notifikasi aktif';
                disableBtn.style.display = 'block';
                testBtn.style.display = 'block';
                return;
            }
        }
    } catch(e) {}

    dot.className = 'status-dot inactive';
    text.textContent = 'Notifikasi belum diaktifkan';
    enableBtn.style.display = 'block';
}

async function enableNotifications() {
    try {
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            showToast('Izin notifikasi ditolak', 'error');
            return;
        }

        // Register service worker
        const registration = await navigator.serviceWorker.register(APP_URL + '/sw.js?v=5');
        await navigator.serviceWorker.ready;

        // Get VAPID key
        const vapidRes = await apiCall('notifications', { action: 'vapid_key' });
        if (!vapidRes.key) {
            showToast('VAPID key belum dikonfigurasi. Set di config.php', 'error');
            return;
        }

        // Subscribe
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(vapidRes.key)
        });

        const sub = subscription.toJSON();
        const res = await apiCall('notifications', {
            action: 'subscribe',
            endpoint: sub.endpoint,
            p256dh: sub.keys.p256dh,
            auth: sub.keys.auth
        });

        if (res.success) {
            showToast('Notifikasi diaktifkan!');
            checkNotificationStatus();
        }
    } catch(err) {
        console.error('Push subscription error:', err);
        showToast('Gagal mengaktifkan notifikasi: ' + err.message, 'error');
    }
}

async function disableNotifications() {
    try {
        const registration = await navigator.serviceWorker.getRegistration();
        if (registration) {
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await apiCall('notifications', {
                    action: 'unsubscribe',
                    endpoint: subscription.endpoint
                });
                await subscription.unsubscribe();
            }
        }
        showToast('Notifikasi dinonaktifkan');
        checkNotificationStatus();
    } catch(err) {
        showToast('Gagal menonaktifkan', 'error');
    }
}

async function testPushNotification() {
    if (Notification.permission === 'granted') {
        try {
            // Send server-side push notification (works on mobile even when browser is backgrounded)
            const serverRes = await apiCall('notifications', { action: 'test' });
            console.log('[TestPush] Server response:', serverRes);
            
            // Also show a local notification as immediate feedback
            const registration = await navigator.serviceWorker.getRegistration();
            if (registration) {
                await registration.showNotification('MyOwnCloud', {
                    body: 'Test notifikasi lokal berhasil! Push server juga dikirim.',
                    icon: APP_URL + '/assets/icons/icon-192.png',
                    badge: APP_URL + '/assets/icons/icon-72.png',
                    tag: 'test-local-notification',
                    vibrate: [200, 100, 200],
                    requireInteraction: true,
                    actions: [
                        { action: 'open', title: 'Buka' },
                        { action: 'dismiss', title: 'Tutup' }
                    ]
                });
            }
            showToast(`Test notifikasi dikirim! ${serverRes.message || ''}`);
        } catch (err) {
            console.error('Test notification failed:', err);
            showToast('Gagal mengirim test notifikasi: ' + err.message, 'error');
        }
    }
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}
