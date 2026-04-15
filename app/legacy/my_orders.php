<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=my_orders.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$userId = (int)$_SESSION['user_id'];

// Все заказы пользователя с позициями
$orders = [];
try {
    // Сначала получаем сами заказы
    $stmtOrders = $pdo->prepare("
        SELECT o.id, o.order_number, o.subtotal, o.delivery_fee, o.total,
               o.address, o.phone, o.status, o.payment_method, o.payment_status,
               o.comment, o.created_at,
               r.name AS restaurant_name
        FROM orders o
        JOIN restaurants r ON o.restaurant_id = r.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmtOrders->execute([$userId]);
    $rawOrders = $stmtOrders->fetchAll();

    // Для каждого заказа подтягиваем позиции
    $stmtItems = $pdo->prepare("
        SELECT oi.name, oi.quantity, oi.base_price, oi.final_price,
               oi.customizations_total, oi.special_requests
        FROM order_items oi
        WHERE oi.order_id = ?
        ORDER BY oi.id
    ");

    foreach ($rawOrders as $o) {
        $stmtItems->execute([$o['id']]);
        $o['items'] = $stmtItems->fetchAll();
        $orders[] = $o;
    }

} catch (PDOException $e) {
    // Если ошибка — покажем пустой список, не ломаем страницу
    $orders = [];
}

$statusLabels = [
    'new'        => 'Новый',
    'confirmed'  => 'Подтверждён',
    'preparing'  => 'Готовится',
    'ready'      => 'Готов',
    'delivering' => 'В доставке',
    'delivered'  => 'Доставлен',
    'cancelled'  => 'Отменён',
];
$statusColors = [
    'new'        => ['bg' => '#eff6ff', 'text' => '#2563eb', 'dot' => '#3b82f6'],
    'confirmed'  => ['bg' => '#ecfeff', 'text' => '#0891b2', 'dot' => '#06b6d4'],
    'preparing'  => ['bg' => '#fffbeb', 'text' => '#d97706', 'dot' => '#f59e0b'],
    'ready'      => ['bg' => '#f0fdf4', 'text' => '#16a34a', 'dot' => '#22c55e'],
    'delivering' => ['bg' => '#fff7ed', 'text' => '#ea580c', 'dot' => '#f97316'],
    'delivered'  => ['bg' => '#f0fdf4', 'text' => '#16a34a', 'dot' => '#22c55e'],
    'cancelled'  => ['bg' => '#fef2f2', 'text' => '#dc2626', 'dot' => '#ef4444'],
];
$paymentLabels = [
    'cash'   => '💵 Наличными',
    'card'   => '💳 Картой',
    'online' => '🌐 Онлайн',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Мои заказы — Курьер Экспресс</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .orders-wrap { max-width: 860px; margin: 0 auto; padding: 90px 20px 60px; }

        /* ── Хедер ───────────────────────────────────────────────────────── */
        .orders-hero {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 20px; padding: 30px 32px; color: white;
            margin-bottom: 28px; position: relative; overflow: hidden;
        }
        .orders-hero::after {
            content: '📦';
            position: absolute; right: 28px; top: 50%; transform: translateY(-50%);
            font-size: 72px; opacity: .12;
        }
        .orders-hero h1 { font-size: 24px; font-weight: 700; margin: 0 0 6px; }
        .orders-hero p  { color: rgba(255,255,255,0.6); font-size: 14px; margin: 0; }
        .orders-hero-nav {
            display: flex; align-items: center; gap: 16px; margin-bottom: 16px;
        }
        .hero-back {
            color: rgba(255,255,255,0.7); text-decoration: none; font-size: 14px;
            display: inline-flex; align-items: center; gap: 6px; transition: color 0.2s;
        }
        .hero-back:hover { color: white; }
        .orders-count {
            background: rgba(255,255,255,0.15); padding: 4px 12px;
            border-radius: 20px; font-size: 13px; font-weight: 600;
        }

        /* ── Карточка заказа ─────────────────────────────────────────────── */
        .order-card {
            background: white; border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
            margin-bottom: 16px; overflow: hidden;
            transition: box-shadow 0.2s;
        }
        .order-card:hover { box-shadow: 0 4px 24px rgba(0,0,0,0.11); }

        .order-head {
            padding: 18px 22px;
            display: flex; justify-content: space-between; align-items: flex-start;
            gap: 12px; cursor: pointer; user-select: none;
        }
        .order-head:hover { background: #fafafa; }

        .order-number { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .order-meta   { font-size: 13px; color: #94a3b8; display: flex; flex-wrap: wrap; gap: 10px; }
        .order-meta span { display: flex; align-items: center; gap: 4px; }

        .order-right { display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0; }
        .order-total { font-size: 20px; font-weight: 700; color: #ee5a24; }

        /* Статус-бейдж */
        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 5px 12px; border-radius: 20px; font-size: 13px; font-weight: 600;
        }
        .status-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }

        /* Шеврон раскрытия */
        .chevron {
            font-size: 14px; color: #94a3b8; transition: transform 0.25s;
            align-self: center; flex-shrink: 0;
        }
        .order-card.open .chevron { transform: rotate(180deg); }

        /* Детали заказа */
        .order-details {
            display: none; border-top: 1px solid #f1f5f9;
        }
        .order-card.open .order-details { display: block; }

        /* Таблица позиций */
        .items-table { width: 100%; border-collapse: collapse; }
        .items-table th {
            background: #f8fafc; padding: 10px 16px; text-align: left;
            font-size: 12px; font-weight: 700; color: #64748b;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        .items-table th:last-child { text-align: right; }
        .items-table td {
            padding: 12px 16px; border-top: 1px solid #f1f5f9;
            font-size: 14px; color: #334155; vertical-align: top;
        }
        .items-table td:last-child { text-align: right; font-weight: 700; color: #ee5a24; }
        .item-name { font-weight: 600; color: #1e293b; }
        .item-custom { font-size: 12px; color: #9b59b6; margin-top: 3px; }
        .item-wishes { font-size: 12px; color: #94a3b8; margin-top: 3px; font-style: italic; }

        /* Итоги */
        .order-totals {
            padding: 16px 20px; background: #f8fafc;
            display: flex; justify-content: space-between; align-items: flex-start;
            flex-wrap: wrap; gap: 12px;
        }
        .totals-info { font-size: 13px; color: #64748b; display: flex; flex-direction: column; gap: 4px; }
        .totals-info strong { color: #334155; }
        .totals-breakdown { text-align: right; }
        .total-line { font-size: 14px; color: #64748b; margin-bottom: 3px; }
        .total-line span { color: #334155; font-weight: 600; margin-left: 8px; }
        .total-grand { font-size: 18px; font-weight: 700; color: #ee5a24; margin-top: 6px; }

        /* Пустой список */
        .empty-orders {
            background: white; border-radius: 16px; padding: 60px 20px;
            text-align: center; box-shadow: 0 2px 16px rgba(0,0,0,0.07);
        }
        .empty-orders .icon { font-size: 64px; margin-bottom: 16px; opacity: .4; }
        .empty-orders h3 { color: #1e293b; font-size: 20px; margin-bottom: 8px; }
        .empty-orders p  { color: #94a3b8; margin-bottom: 24px; }

        .btn-primary {
            background: linear-gradient(135deg, #ee5a24, #ff6b6b);
            color: white; border: none; padding: 12px 24px; border-radius: 10px;
            font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 8px; text-decoration: none;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 5px 15px rgba(238,90,36,.35); color: white; }

        @media (max-width: 580px) {
            .order-head { flex-wrap: wrap; }
            .order-right { flex-direction: row; align-items: center; width: 100%; justify-content: space-between; }
            .items-table th:nth-child(3),
            .items-table td:nth-child(3) { display: none; }
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="orders-wrap">

    <!-- Хедер -->
    <div class="orders-hero">
        <div class="orders-hero-nav">
            <a href="index.php" class="hero-back"><i class="fas fa-arrow-left"></i> На главную</a>
            <span class="orders-count"><?= count($orders) ?> заказ<?= count($orders) === 1 ? '' : (count($orders) >= 2 && count($orders) <= 4 ? 'а' : 'ов') ?></span>
        </div>
        <h1>Мои заказы</h1>
        <p>История всех ваших заказов в Курьер Экспресс</p>
    </div>

    <?php if (empty($orders)): ?>
        <!-- Нет заказов -->
        <div class="empty-orders">
            <div class="icon">📦</div>
            <h3>Заказов пока нет</h3>
            <p>Оформите первый заказ — мы доставим за 30–45 минут</p>
            <a href="index.php" class="btn-primary">
                <i class="fas fa-utensils"></i> Перейти в меню
            </a>
        </div>

    <?php else: ?>

        <?php foreach ($orders as $o):
            $sc = $statusColors[$o['status']] ?? ['bg'=>'#f1f5f9','text'=>'#64748b','dot'=>'#94a3b8'];
            $paid = $o['payment_status'] === 'paid';
        ?>
        <div class="order-card" id="order-<?= $o['id'] ?>">

            <!-- Шапка — кликабельна для раскрытия -->
            <div class="order-head" onclick="toggleOrder(<?= $o['id'] ?>)">
                <div style="flex:1; min-width:0;">
                    <div class="order-number">
                        <i class="fas fa-receipt" style="color:#ee5a24; margin-right:6px; font-size:14px;"></i>
                        <?= h($o['order_number']) ?>
                    </div>
                    <div class="order-meta">
                        <span><i class="fas fa-store"></i><?= h($o['restaurant_name']) ?></span>
                        <span><i class="fas fa-calendar"></i><?= date('d.m.Y', strtotime($o['created_at'])) ?></span>
                        <span><i class="fas fa-clock"></i><?= date('H:i', strtotime($o['created_at'])) ?></span>
                    </div>
                </div>
                <div class="order-right">
                    <div class="order-total"><?= number_format($o['total'], 0) ?> ₽</div>
                    <span class="status-badge"
                          style="background:<?= $sc['bg'] ?>; color:<?= $sc['text'] ?>;">
                        <span class="status-dot" style="background:<?= $sc['dot'] ?>;"></span>
                        <?= $statusLabels[$o['status']] ?? h($o['status']) ?>
                    </span>
                </div>
                <i class="fas fa-chevron-down chevron"></i>
            </div>

            <!-- Детали (раскрываются по клику) -->
            <div class="order-details">

                <!-- Позиции -->
                <?php if (!empty($o['items'])): ?>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>Блюдо</th>
                            <th style="text-align:center;">Кол-во</th>
                            <th>Базовая цена</th>
                            <th>Итого</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($o['items'] as $item): ?>
                        <tr>
                            <td>
                                <div class="item-name"><?= h($item['name']) ?></div>
                                <?php if ($item['customizations_total'] > 0): ?>
                                    <div class="item-custom">
                                        <i class="fas fa-sliders-h" style="margin-right:3px;"></i>
                                        Добавки: +<?= number_format($item['customizations_total'], 0) ?> ₽
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($item['special_requests'])): ?>
                                    <div class="item-wishes">
                                        <i class="fas fa-comment" style="margin-right:3px;"></i>
                                        <?= h($item['special_requests']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center; color:#64748b; font-weight:600;">
                                × <?= (int)$item['quantity'] ?>
                            </td>
                            <td style="color:#64748b;">
                                <?= number_format($item['base_price'], 0) ?> ₽
                            </td>
                            <td><?= number_format($item['final_price'], 0) ?> ₽</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>

                <!-- Итоги + доп. инфо -->
                <div class="order-totals">
                    <div class="totals-info">
                        <div><i class="fas fa-map-marker-alt" style="color:#ee5a24; width:16px;"></i>
                            <strong><?= h($o['address']) ?></strong></div>
                        <div><i class="fas fa-phone" style="color:#ee5a24; width:16px;"></i>
                            <?= h($o['phone']) ?></div>
                        <div><i class="fas fa-credit-card" style="color:#ee5a24; width:16px;"></i>
                            <?= $paymentLabels[$o['payment_method']] ?? h($o['payment_method']) ?>
                            &nbsp;·&nbsp;
                            <span style="color:<?= $paid ? '#16a34a' : '#d97706' ?>; font-weight:600;">
                                <?= $paid ? '✓ Оплачен' : '⏳ Ожидает' ?>
                            </span>
                        </div>
                        <?php if (!empty($o['comment'])): ?>
                        <div><i class="fas fa-comment" style="color:#ee5a24; width:16px;"></i>
                            <?= h($o['comment']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="totals-breakdown">
                        <div class="total-line">Сумма заказа <span><?= number_format($o['subtotal'], 0) ?> ₽</span></div>
                        <div class="total-line">Доставка
                            <span><?= $o['delivery_fee'] > 0
                                ? number_format($o['delivery_fee'], 0) . ' ₽'
                                : '<span style="color:#16a34a">Бесплатно</span>' ?></span>
                        </div>
                        <div class="total-grand"><?= number_format($o['total'], 0) ?> ₽</div>
                    </div>
                </div>

            </div><!-- /.order-details -->
        </div><!-- /.order-card -->
        <?php endforeach; ?>

        <div style="text-align:center; margin-top:24px;">
            <a href="index.php" class="btn-primary">
                <i class="fas fa-plus"></i> Новый заказ
            </a>
        </div>

    <?php endif; ?>

</div><!-- /.orders-wrap -->

<script src="js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    updateCartCount();

    // Открываем первый заказ автоматически
    var first = document.querySelector('.order-card');
    if (first) first.classList.add('open');
});

function toggleOrder(id) {
    var card = document.getElementById('order-' + id);
    if (card) card.classList.toggle('open');
}
</script>
</body>
</html>
