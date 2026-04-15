<?php
// header.php - общий хедер для всех страниц
// Гарантированно запускаем сессию, если она еще не запущена
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Корзина управляется через Cart (js/cart.js) — серверное хранение
?>
<header class="header">
    <a href="index" class="logo">
        <div class="logo-img">🍕</div>
        <div class="logo-text">
            <h1>Курьер Экспресс</h1>
            <p>Доставка еды к вашему столу!</p>
        </div>
    </a>
    
    <div class="user-actions">
        <?php if(isset($_SESSION['user_id'])): ?>
            <div class="user-profile">
                <a href="profile" class="user-btn">
                    <?php
                        // Загружаем аватар если не в сессии
                        if (!isset($_SESSION['user_avatar']) && isset($_SESSION['user_id'])) {
                            require_once __DIR__ . '/../../config/db.php';
                            $s = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
                            $s->execute([$_SESSION['user_id']]);
                            $row = $s->fetch();
                            $_SESSION['user_avatar'] = $row['avatar'] ?? '1.png';
                        }
                        $av = $_SESSION['user_avatar'] ?? '1.png';
                    ?>
                    <img src="images/avatars/<?= htmlspecialchars($av) ?>"
                         onerror="this.style.display='none';this.nextElementSibling.style.display='inline'"
                         style="width:28px;height:28px;border-radius:50%;object-fit:cover;margin-right:6px;vertical-align:middle;border:2px solid rgba(255,255,255,.4);">
                    <i class="fas fa-user" style="display:none"></i>
                    <?php
                        if(isset($_SESSION['user_name'])) echo htmlspecialchars($_SESSION['user_name']);
                        elseif(isset($_SESSION['user_email'])) echo htmlspecialchars($_SESSION['user_email']);
                        else echo 'Пользователь';
                    ?>
                </a>
            </div>
            <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                <a href="admin_dashboard" class="user-btn" style="margin-left: 10px;">
                    <i class="fas fa-cog"></i> Админ
                </a>
            <?php endif; ?>
            <a href="logout" class="user-btn" style="margin-left: 10px;">
                <i class="fas fa-sign-out-alt"></i> Выйти
            </a>
        <?php else: ?>
            <a href="login" class="user-btn">
                <i class="fas fa-sign-in-alt"></i> Войти
            </a>
            <a href="register" class="user-btn" style="margin-left: 10px;">
                <i class="fas fa-user-plus"></i> Регистрация
            </a>
        <?php endif; ?>
        
        <a href="cart" class="cart-btn">
            <i class="fas fa-shopping-cart"></i> Корзина
            <span class="cart-count" id="cart-count" style="display:none">0</span>
        </a>
    </div>
</header>
<script src="js/cart.js"></script>
<script>
    Cart.init(<?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>);
</script>
