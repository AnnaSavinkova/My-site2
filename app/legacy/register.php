<?php
require_once __DIR__ . '/../../config/db.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username  = trim($_POST['username']  ?? '');
    $lastname  = trim($_POST['lastname']  ?? '');
    $email     = trim($_POST['email']     ?? '');
    $phone     = trim($_POST['phone']     ?? '');
    $password  = trim($_POST['password']  ?? '');
    $confirm   = trim($_POST['confirm_password'] ?? '');

    if (empty($username) || empty($lastname) || empty($email) || empty($phone) || empty($password) || empty($confirm)) {
        $error = 'Заполните все поля';
    } elseif ($password !== $confirm) {
        $error = 'Пароли не совпадают';
    } elseif (strlen($password) < 6) {
        $error = 'Пароль должен быть не менее 6 символов';
    } else {
        $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $check->execute([$email]);
        if ($check->rowCount() > 0) {
            $error = 'Этот email уже используется';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            // username хранит имя, lastname — отдельное поле
            // Если в вашей таблице нет поля lastname — добавьте через phpMyAdmin:
            // ALTER TABLE users ADD COLUMN lastname VARCHAR(100) DEFAULT '' AFTER username;
            $stmt = $pdo->prepare("
                INSERT INTO users (username, lastname, email, password_hash, phone, role)
                VALUES (?, ?, ?, ?, ?, 'client')
            ");
            $stmt->execute([$username, $lastname, $email, $hash, $phone]);
            $success = 'Регистрация успешна!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Регистрация — Курьер Экспресс</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0; min-height: 100vh;
            display: flex; justify-content: center; align-items: center; padding: 20px;
        }
        .register-box {
            background: white; padding: 40px; border-radius: 15px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%; max-width: 460px; text-align: center;
        }
        .logo { font-size: 48px; color: #ee5a24; margin-bottom: 10px; }
        h2 { color: #333; margin-bottom: 5px; }
        .subtitle { color: #666; margin-bottom: 25px; font-size: 14px; }
        .input-group { position: relative; margin-bottom: 14px; text-align: left; }
        .input-label { font-size: 13px; color: #555; margin-bottom: 4px; display: block; font-weight: 500; }
        .req { color: #ee5a24; }
        .input-field {
            width: 100%; padding: 13px 15px; border: 2px solid #eee;
            border-radius: 8px; font-size: 15px; box-sizing: border-box; transition: border 0.2s;
        }
        .input-field:focus { border-color: #ee5a24; outline: none; }
        .input-with-eye { position: relative; }
        .input-with-eye .input-field { padding-right: 50px; }
        .eye-btn {
            position: absolute; right: 13px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #999; cursor: pointer; font-size: 14px;
        }
        .eye-btn:hover { color: #ee5a24; }
        .register-btn {
            width: 100%; padding: 15px; margin-top: 10px;
            background: linear-gradient(135deg, #ee5a24, #ff6b6b);
            color: white; border: none; border-radius: 8px;
            font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s;
        }
        .register-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(238,90,36,0.4); }
        .alert-error   { background: #ff6b6b; color: white; padding: 12px; border-radius: 8px; margin-bottom: 18px; font-size: 14px; }
        .alert-success { background: #4CAF50; color: white; padding: 14px; border-radius: 8px; margin-bottom: 18px; }
        .link { color: #ee5a24; text-decoration: none; margin-top: 18px; display: inline-block; font-size: 14px; }
        .link:hover { text-decoration: underline; }
        .row2 { display: flex; gap: 12px; }
        .row2 .input-group { flex: 1; }
    </style>
</head>
<body>
<div class="register-box">
    <div class="logo">🍕</div>
    <h2>Курьер Экспресс</h2>
    <p class="subtitle">Регистрируйтесь и заказывайте вкусную еду</p>

    <?php if ($error): ?>
        <div class="alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert-success">🎉 <?= $success ?></div>
        <a href="login.php" class="link">Войти в аккаунт →</a>
    <?php else: ?>
    <form method="POST">
        <!-- Имя и Фамилия в одну строку -->
        <div class="row2">
            <div class="input-group">
                <label class="input-label">Имя <span class="req">*</span></label>
                <input type="text" name="username" class="input-field"
                       placeholder="Иван" required
                       value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </div>
            <div class="input-group">
                <label class="input-label">Фамилия <span class="req">*</span></label>
                <input type="text" name="lastname" class="input-field"
                       placeholder="Иванов" required
                       value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>">
            </div>
        </div>

        <div class="input-group">
            <label class="input-label">Email <span class="req">*</span></label>
            <input type="email" name="email" class="input-field"
                   placeholder="ivan@mail.ru" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>

        <div class="input-group">
            <label class="input-label">Телефон <span class="req">*</span></label>
            <input type="tel" name="phone" class="input-field"
                   placeholder="+7 (999) 123-45-67" required
                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>

        <div class="input-group">
            <label class="input-label">Пароль <span class="req">*</span></label>
            <div class="input-with-eye">
                <input type="password" name="password" id="password" class="input-field"
                       placeholder="Минимум 6 символов" required>
                <button type="button" class="eye-btn" onclick="togglePwd('password', this)">👁</button>
            </div>
        </div>

        <div class="input-group">
            <label class="input-label">Повторите пароль <span class="req">*</span></label>
            <div class="input-with-eye">
                <input type="password" name="confirm_password" id="confirm_password" class="input-field"
                       placeholder="Повторите пароль" required>
                <button type="button" class="eye-btn" onclick="togglePwd('confirm_password', this)">👁</button>
            </div>
        </div>

        <button type="submit" class="register-btn">Зарегистрироваться</button>
    </form>
    <a href="login.php" class="link">Уже есть аккаунт? Войти →</a>
    <?php endif; ?>
</div>

<script>
function togglePwd(id, btn) {
    var inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}
</script>
</body>
</html>
