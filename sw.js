/**
 * MyOwnCloud Service Worker
 * Handles push notifications
 */

self.addEventListener('install', event => {
    self.skipWaiting();
});

self.addEventListener('activate', event => {
    event.waitUntil(clients.claim());
});

self.addEventListener('push', event => {
    let data = {
        title: 'MyOwnCloud',
        body: 'Anda memiliki notifikasi baru',
        icon: '/myowncloud/assets/icons/icon-192.png',
        badge: '/myowncloud/assets/icons/icon-72.png',
        url: '/myowncloud/?page=tasks'
    };

    if (event.data) {
        try {
            const payload = event.data.json();
            data = { ...data, ...payload };
        } catch (e) {
            data.body = event.data.text();
        }
    }

    const options = {
        body: data.body,
        icon: data.icon,
        badge: data.badge,
        vibrate: [200, 100, 200],
        tag: data.tag || 'myowncloud-notification',
        data: { url: data.url },
        actions: [
            { action: 'open', title: 'Buka' },
            { action: 'dismiss', title: 'Tutup' }
        ]
    };

    event.waitUntil(
        self.registration.showNotification(data.title, options)
    );
});

self.addEventListener('notificationclick', event => {
    event.notification.close();

    if (event.action === 'dismiss') return;

    const url = event.notification.data?.url || '/myowncloud/?page=dashboard';

    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clientList => {
            for (const client of clientList) {
                if (client.url.includes('/myowncloud/') && 'focus' in client) {
                    client.navigate(url);
                    return client.focus();
                }
            }
            return clients.openWindow(url);
        })
    );
});
