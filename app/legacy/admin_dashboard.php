<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';

// Статистика
try {
    $stats = [];
    $stats['orders_total']   = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $stats['orders_new']     = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='new'")->fetchColumn();
    $stats['orders_done']    = $pdo->query("SELECT COUNT(*) FROM orders WHERE status='delivered'")->fetchColumn();
    $stats['revenue']        = $pdo->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE payment_status='paid'")->fetchColumn();
    $stats['dishes']         = $pdo->query("SELECT COUNT(*) FROM dishes")->fetchColumn();
    $stats['restaurants']    = $pdo->query("SELECT COUNT(*) FROM restaurants")->fetchColumn();
    $stats['users']          = $pdo->query("SELECT COUNT(*) FROM users WHERE role='client'")->fetchColumn();

    // Последние 5 заказов
    $recent = $pdo->query("
        SELECT o.id, o.order_number, o.total, o.status, o.created_at, u.email
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.id DESC LIMIT 5
    ")->fetchAll();
} catch (PDOException $e) {
    $stats = array_fill_keys(['orders_total','orders_new','orders_done','revenue','dishes','restaurants','users'], 0);
    $recent = [];
}

$statusColors = [
    'new'        => 'primary',
    'confirmed'  => 'info',
    'preparing'  => 'warning',
    'ready'      => 'success',
    'delivering' => 'warning',
    'delivered'  => 'success',
    'cancelled'  => 'danger',
];
$statusLabels = [
    'new'        => 'Новый',
    'confirmed'  => 'Подтверждён',
    'preparing'  => 'Готовится',
    'ready'      => 'Готов',
    'delivering' => 'В доставке',
    'delivered'  => 'Доставлен',
    'cancelled'  => 'Отменён',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Дашборд — Админ</title>
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
        .stat-card {
            border: none; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,.08);
            padding: 20px; margin-bottom: 20px; transition: transform 0.2s;
        }
        .stat-card:hover { transform: translateY(-3px); }
        .stat-icon {
            width: 56px; height: 56px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; color: white;
        }
        .stat-value { font-size: 32px; font-weight: 700; color: #1e293b; }
        .stat-label { color: #64748b; font-size: 14px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="fas fa-utensils"></i> Админ-панель</div>
    <a href="admin_dashboard.php" class="active"><i class="fas fa-chart-line"></i> Основная панель</a>
    <a href="admin_orders.php"><i class="fas fa-box"></i> Заказы</a>
    <a href="admin_restaurants.php"><i class="fas fa-store"></i> Рестораны</a>
    <a href="admin_menu.php"><i class="fas fa-utensils"></i> Меню</a>
    <a href="admin_categories.php"><i class="fas fa-tags"></i> Категории</a>
    <a href="admin_ingredients.php"><i class="fas fa-pepper-hot"></i> Ингредиенты</a>
    <a href="index.php" style="margin-top:20px; border-top:1px solid #334155;"><i class="fas fa-home"></i> На сайт</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти</a>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0">Дашборд</h2>
            <small class="text-muted">Добро пожаловать, <?= htmlspecialchars($_SESSION['user_name'] ?? 'Администратор') ?></small>
        </div>
    </div>

    <!-- Статистика -->
    <div class="row">
        <div class="col-md-3">
            <div class="stat-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= $stats['orders_new'] ?></div>
                        <div class="stat-label">Новых заказов</div>
                    </div>
                    <div class="stat-icon" style="background:#3b82f6">
                        <i class="fas fa-box-open"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= $stats['orders_total'] ?></div>
                        <div class="stat-label">Всего заказов</div>
                    </div>
                    <div class="stat-icon" style="background:#8b5cf6">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= number_format($stats['revenue'], 0, '', ' ') ?> ₽</div>
                        <div class="stat-label">Выручка (оплачено)</div>
                    </div>
                    <div class="stat-icon" style="background:#10b981">
                        <i class="fas fa-ruble-sign"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card bg-white">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="stat-value"><?= $stats['users'] ?></div>
                        <div class="stat-label">Клиентов</div>
                    </div>
                    <div class="stat-icon" style="background:#f97316">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Быстрые действия -->
    <div class="row mt-2">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Быстрые действия</h6>
                    <div class="d-grid gap-2">
                        <a href="admin_restaurants.php?action=add" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-plus me-1"></i> Добавить ресторан
                        </a>
                        <a href="admin_menu.php?action=add" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-plus me-1"></i> Добавить блюдо
                        </a>
                        <a href="admin_categories.php?action=add" class="btn btn-outline-warning btn-sm">
                            <i class="fas fa-plus me-1"></i> Добавить категорию
                        </a>
                        <a href="admin_orders.php" class="btn btn-outline-danger btn-sm">
                            <i class="fas fa-box me-1"></i> Все заказы
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <h6 class="fw-bold mb-3">Последние заказы</h6>
                    <?php if (empty($recent)): ?>
                        <p class="text-muted mb-0">Заказов пока нет</p>
                    <?php else: ?>
                    <table class="table table-sm table-hover">
                        <thead><tr>
                            <th>Номер</th><th>Клиент</th><th>Сумма</th><th>Статус</th><th>Дата</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><?= htmlspecialchars($r['order_number']) ?></td>
                                <td><?= htmlspecialchars($r['email']) ?></td>
                                <td><?= number_format($r['total'], 0) ?> ₽</td>
                                <td>
                                    <span class="badge bg-<?= $statusColors[$r['status']] ?? 'secondary' ?>">
                                        <?= $statusLabels[$r['status']] ?? $r['status'] ?>
                                    </span>
                                </td>
                                <td><small><?= htmlspecialchars($r['created_at']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <a href="admin_orders.php" class="btn btn-sm btn-outline-secondary">Все заказы →</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
