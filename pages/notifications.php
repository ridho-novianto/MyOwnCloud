<?php
/**
 * Notifications Page
 */
$pageTitle = 'Aktifkan Notifikasi';
$pageIcon = 'bell';
$pageScripts = ['push.js?v=2'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="notification-container">
    <div class="notification-card">
        <div class="notification-hero">
            <div class="notif-icon-large">
                <i class="fas fa-bell"></i>
            </div>
            <h2>Push Notifications</h2>
            <p>Terima notifikasi deadline task 7 hari sebelumnya, bahkan saat browser ditutup.</p>
        </div>

        <div class="notif-status" id="notifStatus">
            <div class="status-indicator">
                <div class="status-dot" id="notifStatusDot"></div>
                <span id="notifStatusText">Memeriksa...</span>
            </div>
        </div>

        <div class="notif-actions">
            <button class="btn btn-primary btn-block" id="enableNotifBtn" onclick="enableNotifications()" style="display:none">
                <i class="fas fa-bell"></i> Aktifkan Notifikasi
            </button>
            <button class="btn btn-danger btn-block" id="disableNotifBtn" onclick="disableNotifications()" style="display:none">
                <i class="fas fa-bell-slash"></i> Nonaktifkan Notifikasi
            </button>
            <button class="btn btn-secondary btn-block" onclick="testPushNotification()" id="testNotifBtn" style="display:none">
                <i class="fas fa-paper-plane"></i> Kirim Test Notifikasi
            </button>
        </div>

        <div class="notif-info">
            <h4><i class="fas fa-info-circle"></i> Cara kerja:</h4>
            <ul>
                <li><i class="fas fa-check"></i> Notifikasi dikirim 7 hari sebelum deadline task</li>
                <li><i class="fas fa-check"></i> Notifikasi tetap diterima walaupun browser ditutup</li>
                <li><i class="fas fa-check"></i> Bekerja di Chrome, Firefox, Edge (Android & Desktop)</li>
                <li><i class="fas fa-info-circle"></i> iOS Safari: perlu "Add to Home Screen" terlebih dahulu</li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
