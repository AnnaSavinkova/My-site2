<?php
// admin_restaurants.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once 'check_admin.php';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$action  = $_GET['action'] ?? 'list';
$message = '';
$error   = '';

// ── УДАЛЕНИЕ ──────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    try {
        // Каскадное удаление через FK, либо вручную:
        $pdo->prepare("DELETE FROM dishes WHERE restaurant_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM categories WHERE restaurant_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM restaurants WHERE id = ?")->execute([$id]);
        $message = 'Ресторан удалён.';
        $action  = 'list';
    } catch (PDOException $e) {
        $error  = 'Ошибка удаления: ' . $e->getMessage();
        $action = 'list';
    }
}

// ── СОХРАНЕНИЕ (ADD / EDIT) ───────────────────────────────
if (in_array($action, ['add', 'edit']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $name        = trim($_POST['name']);
    $description = trim($_POST['description']);
    $logo_url    = trim($_POST['logo_url']);
    $edit_id     = (int)($_POST['edit_id'] ?? 0);

    if (empty($name)) {
        $error = 'Введите название ресторана.';
    } else {
        try {
            if ($edit_id > 0) {
                $pdo->prepare("UPDATE restaurants SET name=?, description=?, logo_url=? WHERE id=?")
                    ->execute([$name, $description, $logo_url, $edit_id]);
                $message = 'Ресторан обновлён.';
            } else {
                $pdo->prepare("INSERT INTO restaurants (name, description, logo_url) VALUES (?,?,?)")
                    ->execute([$name, $description, $logo_url]);
                $message = 'Ресторан добавлен.';
            }
            $action = 'list';
        } catch (PDOException $e) {
            $error = 'Ошибка БД: ' . $e->getMessage();
        }
    }
}

// ── ДАННЫЕ ДЛЯ ФОРМЫ РЕДАКТИРОВАНИЯ ──────────────────────
$editRestaurant = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editRestaurant = $stmt->fetch();
    if (!$editRestaurant) { $action = 'list'; }
}

// ── СПИСОК ────────────────────────────────────────────────
$restaurants = $pdo->query("
    SELECT r.*, COUNT(d.id) AS dish_count
    FROM restaurants r
    LEFT JOIN dishes d ON d.restaurant_id = r.id
    GROUP BY r.id
    ORDER BY r.id
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Рестораны — Админ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .sidebar {
            width: 240px; min-height: 100vh; background: #1e293b;
            position: fixed; top: 0; left: 0; padding-top: 20px; z-index: 100;
        }
        .sidebar .brand { color: #f97316; font-size: 20px; font-weight: 700; padding: 10px 20px 20px; border-bottom: 1px solid #334155; }
        .sidebar a { display: block; color: #94a3b8; padding: 12px 20px; text-decoration: none; transition: all 0.2s; }
        .sidebar a:hover, .sidebar a.active { color: #fff; background: #334155; }
        .sidebar a i { width: 20px; margin-right: 8px; }
        .main-content { margin-left: 240px; padding: 30px; }
        .rest-logo { width: 60px; height: 60px; object-fit: cover; border-radius: 10px; background: #e2e8f0; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="fas fa-utensils"></i> Админ-панель</div>
    <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Дашборд</a>
    <a href="admin_orders.php"><i class="fas fa-box"></i> Заказы</a>
    <a href="admin_restaurants.php" class="active"><i class="fas fa-store"></i> Рестораны</a>
    <a href="admin_menu.php"><i class="fas fa-utensils"></i> Меню</a>
    <a href="admin_categories.php"><i class="fas fa-tags"></i> Категории</a>
    <a href="admin_ingredients.php"><i class="fas fa-pepper-hot"></i> Ингредиенты</a>
    <a href="index.php" style="margin-top:20px; border-top:1px solid #334155;"><i class="fas fa-home"></i> На сайт</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти</a>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-store text-primary me-2"></i>Рестораны</h2>
        <a href="?action=add" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i> Добавить ресторан
        </a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error)   ?></div><?php endif; ?>

    <!-- Форма добавления/редактирования -->
    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">
            <?= $action === 'edit' ? 'Редактировать ресторан' : 'Добавить ресторан' ?>
        </div>
        <div class="card-body">
            <form method="POST" action="?action=<?= h($action) ?>">
                <?php if ($editRestaurant): ?>
                    <input type="hidden" name="edit_id" value="<?= $editRestaurant['id'] ?>">
                <?php endif; ?>
                <div class="mb-3">
                    <label class="form-label fw-bold">Название <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required
                           value="<?= h($editRestaurant['name'] ?? $_POST['name'] ?? '') ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Описание</label>
                    <textarea name="description" class="form-control" rows="3"><?= h($editRestaurant['description'] ?? $_POST['description'] ?? '') ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Имя файла логотипа</label>
                    <input type="text" name="logo_url" class="form-control"
                           placeholder="pizza_house.jpg"
                           value="<?= h($editRestaurant['logo_url'] ?? $_POST['logo_url'] ?? '') ?>">
                    <div class="form-text">Файл должен лежать в папке <code>public_html/images/</code></div>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-1"></i> Сохранить
                    </button>
                    <a href="admin_restaurants.php" class="btn btn-secondary">Отмена</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Список ресторанов -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Логотип</th>
                        <th>Название</th>
                        <th>Описание</th>
                        <th>Блюд</th>
                        <th class="text-end pe-3">Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($restaurants as $r): ?>
                    <tr>
                        <td class="ps-3"><?= $r['id'] ?></td>
                        <td>
                            <img src="images/<?= h($r['logo_url'] ?: 'restaurant-default.jpg') ?>"
                                 class="rest-logo"
                                 onerror="this.src='images/restaurant-default.jpg'"
                                 alt="">
                        </td>
                        <td><strong><?= h($r['name']) ?></strong></td>
                        <td><small class="text-muted"><?= h(mb_substr($r['description'] ?? '', 0, 80)) ?>...</small></td>
                        <td><span class="badge bg-secondary"><?= $r['dish_count'] ?></span></td>
                        <td class="text-end pe-3">
                            <a href="?action=edit&id=<?= $r['id'] ?>" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="?action=delete" class="d-inline"
                                  onsubmit="return confirm('Удалить «<?= h(addslashes($r['name'])) ?>»?\nВсе блюда и категории этого ресторана тоже будут удалены!')">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
