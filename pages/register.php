<?php
/**
 * Register Page
 */
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar - <?= APP_NAME ?></title>
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
                <p>Buat akun baru</p>
            </div>

            <div class="login-error" id="registerError" style="display:none"></div>

            <form id="registerForm" onsubmit="handleRegister(event)">
                <div class="form-group">
                    <label for="reg_username"><i class="fas fa-user"></i> Username</label>
                    <input type="text" id="reg_username" name="username" required placeholder="Username" minlength="3" maxlength="50">
                </div>
                <div class="form-group">
                    <label for="reg_email"><i class="fas fa-envelope"></i> Email</label>
                    <input type="email" id="reg_email" name="email" required placeholder="email@example.com">
                </div>
                <div class="form-group">
                    <label for="reg_password"><i class="fas fa-lock"></i> Password</label>
                    <div class="password-input">
                        <input type="password" id="reg_password" name="password" required placeholder="Min. 6 karakter" minlength="6">
                        <button type="button" class="password-toggle" onclick="togglePassword('reg_password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="reg_password2"><i class="fas fa-lock"></i> Konfirmasi Password</label>
                    <input type="password" id="reg_password2" name="password2" required placeholder="Ulangi password" minlength="6">
                </div>
                <button type="submit" class="btn btn-primary btn-block" id="registerBtn">
                    <i class="fas fa-user-plus"></i> Daftar
                </button>
            </form>

            <div class="login-footer">
                <p>Sudah punya akun? <a href="?page=login">Masuk</a></p>
            </div>
        </div>
        <div class="login-decoration">
            <div class="glow glow-1"></div>
            <div class="glow glow-2"></div>
            <div class="glow glow-3"></div>
        </div>
    </div>
    <script>
        async function handleRegister(e) {
            e.preventDefault();
            const btn = document.getElementById('registerBtn');
            const errDiv = document.getElementById('registerError');
            const pw1 = document.getElementById('reg_password').value;
            const pw2 = document.getElementById('reg_password2').value;

            if (pw1 !== pw2) {
                errDiv.textContent = 'Password tidak cocok';
                errDiv.style.display = 'block';
                return;
            }

            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memproses...';
            errDiv.style.display = 'none';

            try {
                const res = await fetch('<?= APP_URL ?>/?page=api/auth', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'register',
                        username: document.getElementById('reg_username').value,
                        email: document.getElementById('reg_email').value,
                        password: pw1
                    })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = '<?= APP_URL ?>/?page=dashboard';
                } else {
                    errDiv.textContent = data.error || 'Registrasi gagal';
                    errDiv.style.display = 'block';
                }
            } catch (err) {
                errDiv.textContent = 'Terjadi kesalahan jaringan';
                errDiv.style.display = 'block';
            }
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-user-plus"></i> Daftar';
        }

        function togglePassword(id, btn) {
            const input = document.getElementById(id);
            const icon = btn.querySelector('i');
            if (input.type === 'password') { input.type = 'text'; icon.className = 'fas fa-eye-slash'; }
            else { input.type = 'password'; icon.className = 'fas fa-eye'; }
        }
    </script>
</body>
</html>
