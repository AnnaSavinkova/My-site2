<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    try {
        // Используем PDO из db.php
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_name'] = $user['username'] ?: explode('@', $user['email'])[0];
            
            // Перенаправляем в зависимости от роли
            if ($user['role'] === 'admin') {
                header('Location: admin_dashboard.php');
            } else {
                header('Location: index.php');
            }
            exit;
        } else {
            $error = 'Неверный email или пароль';
        }
    } catch (PDOException $e) {
        $error = 'Ошибка базы данных';
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход - Курьер Экспресс</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0; 
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .login-box { 
            background: white; 
            padding: 40px; 
            border-radius: 15px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .logo { 
            font-size: 48px; 
            color: #ee5a24; 
            margin-bottom: 20px; 
        }
        h2 { 
            color: #333; 
            margin-bottom: 10px; 
        }
        .subtitle { 
            color: #666; 
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        /* Стили для полей ввода с кнопкой показа пароля */
        .input-group {
            position: relative;
            margin-bottom: 15px;
        }
        
        .input-field { 
            width: 100%; 
            padding: 15px 50px 15px 15px; 
            border: 2px solid #eee; 
            border-radius: 8px; 
            font-size: 16px; 
            box-sizing: border-box;
        }
        .input-field:focus { 
            border-color: #ee5a24; 
            outline: none; 
        }
        
        /* Кнопка показа пароля */
        .show-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            font-size: 14px;
            padding: 5px;
            transition: color 0.3s;
        }
        
        .show-password:hover {
            color: #ee5a24;
        }
        
        .login-btn { 
            width: 100%; 
            padding: 15px; 
            background: linear-gradient(135deg, #ee5a24 0%, #ff6b6b 100%); 
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-size: 16px; 
            font-weight: 600; 
            cursor: pointer; 
            margin-top: 10px;
            transition: all 0.3s;
        }
        .login-btn:hover { 
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(238, 90, 36, 0.4);
        }
        .error { 
            background: #ff6b6b; 
            color: white; 
            padding: 12px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
            font-size: 14px; 
        }
        .link { 
            color: #ee5a24; 
            text-decoration: none; 
            margin-top: 20px; 
            display: inline-block; 
            font-size: 14px;
        }
        .link:hover { 
            text-decoration: underline; 
        }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">🍕</div>
        <h2>Курьер Экспресс</h2>
        <p class="subtitle">Добро пожаловать! Войдите в свой аккаунт</p>
        
        <?php if(!empty($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="input-group">
                <input type="email" name="email" class="input-field" 
                       placeholder="Электронная почта" 
                       required>
            </div>
            
            <div class="input-group">
                <input type="password" name="password" id="password" 
                       class="input-field" 
                       placeholder="Пароль" 
                       required>
                <button type="button" class="show-password" onclick="togglePassword()">
                    <span id="toggleText">Показать</span>
                </button>
            </div>
            
            <button type="submit" class="login-btn">Войти</button>
        </form>
        
        <a href="register.php" class="link">Нет аккаунта? Зарегистрироваться</a>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleText = document.getElementById('toggleText');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleText.textContent = 'Скрыть';
            } else {
                passwordInput.type = 'password';
                toggleText.textContent = 'Показать';
            }
        }
    </script>
</body>
</html>