<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$orderNumber = $_GET['order'] ?? '';
$order = null;

if ($orderNumber) {
    $stmt = $pdo->prepare("
        SELECT o.*, r.name AS restaurant_name
        FROM orders o
        JOIN restaurants r ON o.restaurant_id = r.id
        WHERE o.order_number = ? AND o.user_id = ?
    ");
    $stmt->execute([$orderNumber, (int)$_SESSION['user_id']]);
    $order = $stmt->fetch();
}

if (!$order) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ оформлен — Курьер Экспресс</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .success-wrap { max-width: 520px; margin: 0 auto; padding: 100px 20px 60px; text-align: center; }
        .success-icon {
            width: 90px; height: 90px; background: linear-gradient(135deg, #10b981, #34d399);
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px; font-size: 40px;
            box-shadow: 0 8px 30px rgba(16,185,129,.35);
            animation: popIn .5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        @keyframes popIn { from { transform: scale(0); opacity: 0; } to { transform: scale(1); opacity: 1; } }
        h1 { font-size: 28px; font-weight: 800; color: #1e293b; margin: 0 0 8px; }
        .sub { color: #64748b; font-size: 16px; margin-bottom: 28px; }

        .order-card { background: white; border-radius: 16px; box-shadow: 0 2px 16px rgba(0,0,0,0.07); padding: 24px; text-align: left; margin-bottom: 24px; }
        .order-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .order-row:last-child { border-bottom: none; }
        .order-row .label { color: #94a3b8; }
        .order-row .value { font-weight: 600; color: #1e293b; }
        .order-number-big { font-size: 20px; font-weight: 800; color: #ee5a24; }

        .btn-primary { display: inline-flex; align-items: center; gap: 8px; padding: 13px 28px; background: linear-gradient(135deg,#ee5a24,#ff6b6b); color: white; border: none; border-radius: 12px; font-size: 15px; font-weight: 600; text-decoration: none; transition: all .2s; }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(238,90,36,.35); color: white; }
        .btn-secondary { display: inline-flex; align-items: center; gap: 8px; padding: 13px 28px; background: #f1f5f9; color: #475569; border: none; border-radius: 12px; font-size: 15px; font-weight: 600; text-decoration: none; transition: all .2s; margin-left: 10px; }
        .btn-secondary:hover { background: #e2e8f0; color: #334155; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="success-wrap">
    <div class="success-icon">✓</div>
    <h1>Заказ принят!</h1>
    <p class="sub">Мы уже готовим ваш заказ. Ожидайте доставку в течение 30–45 минут.</p>

    <div class="order-card">
        <div class="order-row">
            <span class="label">Номер заказа</span>
            <span class="value order-number-big"><?= h($order['order_number']) ?></span>
        </div>
        <div class="order-row">
            <span class="label">Ресторан</span>
            <span class="value"><?= h($order['restaurant_name']) ?></span>
        </div>
        <div class="order-row">
            <span class="label">Адрес доставки</span>
            <span class="value"><?= h($order['address']) ?></span>
        </div>
        <div class="order-row">
            <span class="label">Сумма заказа</span>
            <span class="value"><?= number_format($order['subtotal'], 0, '', ' ') ?> ₽</span>
        </div>
        <div class="order-row">
            <span class="label">Доставка</span>
            <span class="value" style="color:<?= $order['delivery_fee'] > 0 ? '#1e293b' : '#16a34a' ?>">
                <?= $order['delivery_fee'] > 0 ? number_format($order['delivery_fee'], 0, '', ' ') . ' ₽' : 'Бесплатно' ?>
            </span>
        </div>
        <div class="order-row">
            <span class="label" style="font-weight:700;">Итого</span>
            <span class="value" style="font-size:18px; color:#ee5a24;"><?= number_format($order['total'], 0, '', ' ') ?> ₽</span>
        </div>
    </div>

    <a href="index.php" class="btn-primary"><i class="fas fa-utensils"></i> На главную</a>
    <a href="my_orders.php" class="btn-secondary"><i class="fas fa-box"></i> Мои заказы</a>
</div>

<script src="js/cart.js"></script>
<script>
    // Корзина уже очищена на сервере, просто обновляем UI
    Cart.init(true);
</script>
</body>
</html>
