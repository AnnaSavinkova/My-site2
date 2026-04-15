<?php
// admin_categories.php
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
        // Обнуляем category_id у блюд этой категории
        $pdo->prepare("UPDATE dishes SET category_id = NULL WHERE category_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
        $message = 'Категория удалена. Блюда переведены в «Без категории».';
        $action  = 'list';
    } catch (PDOException $e) {
        $error  = 'Ошибка: ' . $e->getMessage();
        $action = 'list';
    }
}

// ── СОХРАНЕНИЕ ────────────────────────────────────────────
if (in_array($action, ['add', 'edit']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $restaurant_id = (int)$_POST['restaurant_id'];
    $name          = trim($_POST['name']);
    $slug          = trim($_POST['slug']);
    $sort_order    = (int)($_POST['sort_order'] ?? 0);
    $edit_id       = (int)($_POST['edit_id'] ?? 0);

    // Автогенерация slug если пустой
    if (empty($slug)) {
        $slug = preg_replace('/[^a-z0-9-]/', '-', mb_strtolower($name));
    }

    if (empty($name) || $restaurant_id <= 0) {
        $error = 'Заполните название и выберите ресторан.';
    } else {
        try {
            if ($edit_id > 0) {
                $pdo->prepare("UPDATE categories SET restaurant_id=?, name=?, slug=?, sort_order=? WHERE id=?")
                    ->execute([$restaurant_id, $name, $slug, $sort_order, $edit_id]);
                $message = 'Категория обновлена.';
            } else {
                $pdo->prepare("INSERT INTO categories (restaurant_id, name, slug, sort_order) VALUES (?,?,?,?)")
                    ->execute([$restaurant_id, $name, $slug, $sort_order]);
                $message = 'Категория добавлена.';
            }
            $action = 'list';
        } catch (PDOException $e) {
            $error = 'Ошибка БД: ' . $e->getMessage();
        }
    }
}

// ── ДАННЫЕ ДЛЯ РЕДАКТИРОВАНИЯ ─────────────────────────────
$editCat = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editCat = $stmt->fetch();
    if (!$editCat) $action = 'list';
}

$restaurants = $pdo->query("SELECT id, name FROM restaurants ORDER BY name")->fetchAll();

$categories = $pdo->query("
    SELECT c.*, r.name AS r_name,
           (SELECT COUNT(*) FROM dishes WHERE category_id = c.id) AS dish_count
    FROM categories c
    JOIN restaurants r ON c.restaurant_id = r.id
    ORDER BY r.name, c.sort_order, c.name
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Категории — Админ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .sidebar { width: 240px; min-height: 100vh; background: #1e293b; position: fixed; top: 0; left: 0; padding-top: 20px; z-index: 100; }
        .sidebar .brand { color: #f97316; font-size: 20px; font-weight: 700; padding: 10px 20px 20px; border-bottom: 1px solid #334155; }
        .sidebar a { display: block; color: #94a3b8; padding: 12px 20px; text-decoration: none; transition: all 0.2s; }
        .sidebar a:hover, .sidebar a.active { color: #fff; background: #334155; }
        .sidebar a i { width: 20px; margin-right: 8px; }
        .main-content { margin-left: 240px; padding: 30px; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="fas fa-utensils"></i> Админ-панель</div>
    <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Дашборд</a>
    <a href="admin_orders.php"><i class="fas fa-box"></i> Заказы</a>
    <a href="admin_restaurants.php"><i class="fas fa-store"></i> Рестораны</a>
    <a href="admin_menu.php"><i class="fas fa-utensils"></i> Меню</a>
    <a href="admin_categories.php" class="active"><i class="fas fa-tags"></i> Категории</a>
    <a href="admin_ingredients.php"><i class="fas fa-pepper-hot"></i> Ингредиенты</a>
    <a href="index.php" style="margin-top:20px; border-top:1px solid #334155;"><i class="fas fa-home"></i> На сайт</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти</a>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-tags text-warning me-2"></i>Категории</h2>
        <a href="?action=add" class="btn btn-warning text-white"><i class="fas fa-plus me-1"></i> Добавить категорию</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error)   ?></div><?php endif; ?>

    <!-- Форма -->
    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">
            <?= $action === 'edit' ? 'Редактировать категорию' : 'Добавить категорию' ?>
        </div>
        <div class="card-body">
            <form method="POST" action="?action=<?= h($action) ?>">
                <?php if ($editCat): ?>
                    <input type="hidden" name="edit_id" value="<?= $editCat['id'] ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Ресторан <span class="text-danger">*</span></label>
                        <select name="restaurant_id" class="form-select" required>
                            <option value="">— Выберите ресторан —</option>
                            <?php foreach ($restaurants as $r): ?>
                                <option value="<?= $r['id'] ?>"
                                    <?= ($editCat['restaurant_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>>
                                    <?= h($r['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold">Название <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= h($editCat['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Slug</label>
                        <input type="text" name="slug" class="form-control"
                               placeholder="auto"
                               value="<?= h($editCat['slug'] ?? '') ?>">
                        <div class="form-text">Авто, если пусто</div>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label class="form-label fw-bold">Порядок</label>
                        <input type="number" name="sort_order" class="form-control" min="0"
                               value="<?= (int)($editCat['sort_order'] ?? 0) ?>">
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-warning text-white"><i class="fas fa-save me-1"></i> Сохранить</button>
                    <a href="admin_categories.php" class="btn btn-secondary">Отмена</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Список -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">ID</th>
                        <th>Ресторан</th>
                        <th>Название</th>
                        <th>Slug</th>
                        <th>Порядок</th>
                        <th>Блюд</th>
                        <th class="text-end pe-3">Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categories as $c): ?>
                    <tr>
                        <td class="ps-3"><?= $c['id'] ?></td>
                        <td><small class="text-muted"><?= h($c['r_name']) ?></small></td>
                        <td><strong><?= h($c['name']) ?></strong></td>
                        <td><code><?= h($c['slug']) ?></code></td>
                        <td><?= $c['sort_order'] ?></td>
                        <td><span class="badge bg-secondary"><?= $c['dish_count'] ?></span></td>
                        <td class="text-end pe-3">
                            <a href="?action=edit&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-warning">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="?action=delete" class="d-inline"
                                  onsubmit="return confirm('Удалить категорию «<?= h(addslashes($c['name'])) ?>»?\nБлюда перейдут в «Без категории».')">
                                <input type="hidden" name="id" value="<?= $c['id'] ?>">
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
