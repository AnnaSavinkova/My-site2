<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


require_once __DIR__ . '/../../config/db.php';
require_once 'check_admin.php';

function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Получаем заказы + позиции (используем правильные поля из БД: base_price, final_price)
$sql = "
    SELECT 
        o.id           AS order_id,
        o.order_number,
        o.created_at,
        o.status,
        o.total,
        o.address,
        o.phone,
        o.comment,
        o.payment_method,
        o.payment_status,
        u.email,
        u.username,
        d.name         AS dish_name,
        oi.quantity,
        oi.base_price,
        oi.final_price,
        oi.customizations_total,
        oi.special_requests,
        r.name         AS restaurant_name
    FROM orders o
    JOIN users u         ON o.user_id       = u.id
    JOIN restaurants r   ON o.restaurant_id = r.id
    JOIN order_items oi  ON oi.order_id     = o.id
    JOIN dishes d        ON oi.dish_id      = d.id
    ORDER BY o.id DESC
";

try {
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll();
} catch (PDOException $e) {
    die('<div style="padding:20px;color:red;">Ошибка SQL: ' . h($e->getMessage()) . '</div>');
}

// Группируем строки по order_id
$orders = [];
foreach ($rows as $row) {
    $id = $row['order_id'];
    if (!isset($orders[$id])) {
        $orders[$id] = [
            'order_number'    => $row['order_number'],
            'created_at'      => $row['created_at'],
            'status'          => $row['status'],
            'total'           => $row['total'],
            'address'         => $row['address'],
            'phone'           => $row['phone'],
            'comment'         => $row['comment'],
            'payment_method'  => $row['payment_method'],
            'payment_status'  => $row['payment_status'],
            'email'           => $row['email'],
            'username'        => $row['username'],
            'restaurant_name' => $row['restaurant_name'],
            'items'           => []
        ];
    }
    $orders[$id]['items'][] = $row;
}

// Статусы для выпадающего списка
$statusLabels = [
    'new'         => 'Новый',
    'confirmed'   => 'Подтверждён',
    'preparing'   => 'Готовится',
    'ready'       => 'Готов',
    'delivering'  => 'В доставке',
    'delivered'   => 'Доставлен',
    'cancelled'   => 'Отменён',
];

$statusColors = [
    'new'         => 'primary',
    'confirmed'   => 'info',
    'preparing'   => 'warning',
    'ready'       => 'success',
    'delivering'  => 'warning',
    'delivered'   => 'success',
    'cancelled'   => 'danger',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление заказами — Админ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .sidebar {
            width: 240px; min-height: 100vh; background: #1e293b;
            position: fixed; top: 0; left: 0; padding-top: 20px; z-index: 100;
        }
        .sidebar .brand {
            color: #f97316; font-size: 20px; font-weight: 700;
            padding: 10px 20px 20px; border-bottom: 1px solid #334155;
        }
        .sidebar a {
            display: block; color: #94a3b8; padding: 12px 20px;
            text-decoration: none; transition: all 0.2s;
        }
        .sidebar a:hover, .sidebar a.active { color: #fff; background: #334155; }
        .sidebar a i { width: 20px; margin-right: 8px; }
        .main-content { margin-left: 240px; padding: 30px; }
        .order-card { border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.08); margin-bottom: 20px; }
        .order-header { background: #f8fafc; border-radius: 12px 12px 0 0; padding: 15px 20px; border-bottom: 1px solid #e2e8f0; }
        .status-form { display: flex; gap: 8px; align-items: center; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="fas fa-utensils"></i> Админ-панель</div>
    <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Дашборд</a>
    <a href="admin_orders.php" class="active"><i class="fas fa-box"></i> Заказы</a>
    <a href="admin_restaurants.php"><i class="fas fa-store"></i> Рестораны</a>
    <a href="admin_menu.php"><i class="fas fa-utensils"></i> Меню</a>
    <a href="admin_categories.php"><i class="fas fa-tags"></i> Категории</a>
    <a href="admin_ingredients.php"><i class="fas fa-pepper-hot"></i> Ингредиенты</a>
    <a href="index.php" style="margin-top:20px; border-top:1px solid #334155;"><i class="fas fa-home"></i> На сайт</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти</a>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-box text-primary me-2"></i>Все заказы</h2>
        <span class="badge bg-secondary fs-6"><?= count($orders) ?> заказов</span>
    </div>

    <?php if (empty($orders)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>Заказов пока нет
        </div>
    <?php endif; ?>

    <?php foreach ($orders as $order_id => $order): 
        $color = $statusColors[$order['status']] ?? 'secondary';
    ?>
        <div class="card order-card">
            <div class="order-header">
                <div class="row align-items-center">
                    <div class="col-md-5">
                        <strong>Заказ <?= h($order['order_number']) ?></strong>
                        &nbsp;|&nbsp;
                        <span class="badge bg-<?= $color ?>">
                            <?= $statusLabels[$order['status']] ?? h($order['status']) ?>
                        </span>
                        <br>
                        <small class="text-muted">
                            <i class="fas fa-user me-1"></i><?= h($order['username'] ?: $order['email']) ?>
                            &nbsp;|&nbsp;
                            <i class="fas fa-store me-1"></i><?= h($order['restaurant_name']) ?>
                            &nbsp;|&nbsp;
                            <i class="fas fa-clock me-1"></i><?= h($order['created_at']) ?>
                        </small>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted">
                            <i class="fas fa-map-marker-alt me-1"></i><?= h($order['address']) ?><br>
                            <i class="fas fa-phone me-1"></i><?= h($order['phone']) ?>
                        </small>
                    </div>
                    <div class="col-md-4">
                        <!-- Смена статуса через POST -->
                        <form method="POST" action="update_order_status.php" class="status-form">
                            <input type="hidden" name="id" value="<?= $order_id ?>">
                            <select name="status" class="form-select form-select-sm">
                                <?php foreach ($statusLabels as $val => $label): ?>
                                    <option value="<?= $val ?>" <?= $order['status'] === $val ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-check"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Блюдо</th>
                            <th>Кол-во</th>
                            <th>Базовая цена</th>
                            <th>Доп. ингредиенты</th>
                            <th>Итоговая цена</th>
                            <th>Пожелания</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($order['items'] as $item): ?>
                        <tr>
                            <td class="ps-3"><?= h($item['dish_name']) ?></td>
                            <td><?= (int)$item['quantity'] ?></td>
                            <td><?= number_format($item['base_price'], 2) ?> ₽</td>
                            <td>
                                <?php if ($item['customizations_total'] > 0): ?>
                                    <span class="text-success">+<?= number_format($item['customizations_total'], 2) ?> ₽</span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?= number_format($item['final_price'], 2) ?> ₽</strong></td>
                            <td>
                                <small class="text-muted"><?= h($item['special_requests'] ?: '—') ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex justify-content-between align-items-center bg-white rounded-bottom">
                <div>
                    <?php if ($order['comment']): ?>
                        <small><i class="fas fa-comment me-1 text-muted"></i><?= h($order['comment']) ?></small>
                    <?php endif; ?>
                    <small class="ms-3">
                        Оплата: <strong><?= h($order['payment_method']) ?></strong>
                        &nbsp;|&nbsp;
                        Статус оплаты:
                        <span class="badge bg-<?= $order['payment_status'] === 'paid' ? 'success' : 'warning' ?>">
                            <?= $order['payment_status'] === 'paid' ? 'Оплачен' : 'Ожидает' ?>
                        </span>
                    </small>
                </div>
                <div class="fs-5 fw-bold text-primary">
                    Итого: <?= number_format($order['total'], 2) ?> ₽
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
