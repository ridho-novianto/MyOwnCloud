<?php
/**
 * Login Page
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/../includes/db.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_once __DIR__ . '/../includes/functions.php';
}
$error = '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-cloud"></i>
                </div>
                <h1><?= APP_NAME ?></h1>
                <p>Masuk ke workspace Anda</p>
            </div>

            <div class="login-error" id="loginError" style="display:none"></div>

            <form id="loginForm" onsubmit="handleLogin(event)">
                <div class="form-group">
                    <label for="login_email">
                        <i class="fas fa-envelope"></i> Email
                    </label>
                    <input type="email" id="login_email" name="email" required placeholder="email@example.com" autocomplete="email">
                </div>
                <div class="form-group">
                    <label for="login_password">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div class="password-input">
                        <input type="password" id="login_password" name="password" required placeholder="Masukkan password" autocomplete="current-password">
                        <button type="button" class="password-toggle" onclick="togglePassword('login_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i> Masuk
                </button>
            </form>

            <div class="login-footer">
                <p>Belum punya akun? <a href="?page=register">Daftar</a></p>
            </div>
        </div>

        <div class="login-decoration">
            <div class="glow glow-1"></div>
            <div class="glow glow-2"></div>
            <div class="glow glow-3"></div>
        </div>
    </div>

    <script>
        async function handleLogin(e) {
            e.preventDefault();
            const btn = document.getElementById('loginBtn');
            const errDiv = document.getElementById('loginError');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            errDiv.style.display = 'none';

            try {
                const res = await fetch('<?= APP_URL ?>/?page=api/auth', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'login',
                        email: document.getElementById('login_email').value,
                        password: document.getElementById('login_password').value
                    })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = '<?= APP_URL ?>/?page=dashboard';
                } else {
                    errDiv.textContent = data.error || 'Login gagal';
                    errDiv.style.display = 'block';
                }
            } catch (err) {
                errDiv.textContent = 'Terjadi kesalahan jaringan';
                errDiv.style.display = 'block';
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Masuk';
        }

        function togglePassword(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    </script>
</body>
</html>
