<?php
// admin_ingredients.php — управление типами ингредиентов
// Доступен только для администраторов
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once 'check_admin.php';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$msg   = '';
$error = '';

// Проверяем: выполнена ли миграция
$has_dish_type = false;
try {
    $check = $pdo->query("SHOW COLUMNS FROM ingredients LIKE 'dish_type'");
    $has_dish_type = ($check->rowCount() > 0);
} catch (PDOException $e) {}

// Выполняем миграцию если нужно
if (isset($_POST['run_migration']) && !$has_dish_type) {
    try {
        $pdo->exec("
            ALTER TABLE ingredients
            ADD COLUMN dish_type ENUM('pizza','wok','both') NOT NULL DEFAULT 'both'
            AFTER category
        ");
        $has_dish_type = true;
        $msg = 'Миграция выполнена — колонка dish_type добавлена.';
    } catch (PDOException $e) {
        $error = 'Ошибка миграции: ' . $e->getMessage();
    }
}

// Сохраняем тип ингредиента
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ing_id'], $_POST['dish_type']) && $has_dish_type) {
    $ingId    = (int)$_POST['ing_id'];
    $dishType = $_POST['dish_type'];
    if (in_array($dishType, ['pizza', 'wok', 'both'])) {
        $pdo->prepare("UPDATE ingredients SET dish_type = ? WHERE id = ?")
            ->execute([$dishType, $ingId]);
        $msg = 'Сохранено.';
    }
}

// Массовое обновление
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update']) && $has_dish_type) {
    foreach ($_POST['types'] as $ingId => $dishType) {
        $ingId = (int)$ingId;
        if (in_array($dishType, ['pizza', 'wok', 'both'])) {
            $pdo->prepare("UPDATE ingredients SET dish_type = ? WHERE id = ?")
                ->execute([$dishType, $ingId]);
        }
    }
    $msg = 'Все изменения сохранены.';
}

// Загружаем ингредиенты
$ingredients = [];
try {
    $ingredients = $pdo->query("
        SELECT * FROM ingredients ORDER BY category, name
    ")->fetchAll();
} catch (PDOException $e) {}

// Группируем по категориям
$byCategory = [];
foreach ($ingredients as $ing) {
    $byCategory[$ing['category']][] = $ing;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ингредиенты — Админ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .sidebar { width:240px; min-height:100vh; background:#1e293b; position:fixed; top:0; left:0; padding-top:20px; z-index:100; }
        .sidebar .brand { color:#f97316; font-size:20px; font-weight:700; padding:10px 20px 20px; border-bottom:1px solid #334155; }
        .sidebar a { display:block; color:#94a3b8; padding:12px 20px; text-decoration:none; transition:all .2s; }
        .sidebar a:hover, .sidebar a.active { color:#fff; background:#334155; }
        .sidebar a i { width:20px; margin-right:8px; }
        .main-content { margin-left:240px; padding:30px; }
        .type-badge-pizza { background:#fff0eb; color:#ee5a24; border:1px solid #ffd4c2; }
        .type-badge-wok   { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
        .type-badge-both  { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="fas fa-utensils"></i> Админ-панель</div>
    <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Дашборд</a>
    <a href="admin_orders.php"><i class="fas fa-box"></i> Заказы</a>
    <a href="admin_restaurants.php"><i class="fas fa-store"></i> Рестораны</a>
    <a href="admin_menu.php"><i class="fas fa-utensils"></i> Меню</a>
    <a href="admin_categories.php"><i class="fas fa-tags"></i> Категории</a>
    <a href="admin_ingredients.php" class="active"><i class="fas fa-pepper-hot"></i> Ингредиенты</a>
    <a href="index.php" style="margin-top:20px; border-top:1px solid #334155;"><i class="fas fa-home"></i> На сайт</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти</a>
</div>

<div class="main-content">
    <h2 class="mb-1"><i class="fas fa-pepper-hot text-warning me-2"></i>Ингредиенты</h2>
    <p class="text-muted mb-4">Назначьте каждому ингредиенту тип блюда: пицца, вок или оба.</p>

    <?php if ($msg):   ?><div class="alert alert-success"><?= h($msg) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>

    <?php if (!$has_dish_type): ?>
    <!-- Миграция не выполнена -->
    <div class="card border-warning mb-4">
        <div class="card-body">
            <h5 class="text-warning"><i class="fas fa-exclamation-triangle me-2"></i>Требуется миграция БД</h5>
            <p>В таблице <code>ingredients</code> отсутствует колонка <code>dish_type</code>.<br>
               Нажмите кнопку — она будет добавлена автоматически.</p>
            <form method="POST">
                <button type="submit" name="run_migration" class="btn btn-warning">
                    <i class="fas fa-database me-1"></i> Выполнить миграцию
                </button>
            </form>
        </div>
    </div>
    <?php else: ?>

    <!-- Быстрые кнопки -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body d-flex gap-2 flex-wrap align-items-center">
            <span class="text-muted me-2">Выделить всё как:</span>
            <button class="btn btn-sm btn-outline-warning" onclick="setAll('pizza')">🍕 Всё → Пицца</button>
            <button class="btn btn-sm btn-outline-success" onclick="setAll('wok')">🥢 Всё → Вок</button>
            <button class="btn btn-sm btn-outline-primary" onclick="setAll('both')">🔀 Всё → Оба</button>
        </div>
    </div>

    <form method="POST">
        <input type="hidden" name="bulk_update" value="1">

        <?php foreach ($byCategory as $catName => $items): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-bold">
                <i class="fas fa-layer-group text-secondary me-2"></i><?= h($catName) ?>
                <span class="badge bg-secondary ms-2"><?= count($items) ?></span>
                <div class="float-end d-flex gap-1">
                    <button type="button" class="btn btn-xs btn-outline-warning btn-sm py-0 px-2"
                            onclick="setCat('<?= h(addslashes($catName)) ?>', 'pizza')">🍕</button>
                    <button type="button" class="btn btn-xs btn-outline-success btn-sm py-0 px-2"
                            onclick="setCat('<?= h(addslashes($catName)) ?>', 'wok')">🥢</button>
                    <button type="button" class="btn btn-xs btn-outline-primary btn-sm py-0 px-2"
                            onclick="setCat('<?= h(addslashes($catName)) ?>', 'both')">🔀</button>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Ингредиент</th>
                            <th>Цена</th>
                            <th style="width:280px">Тип блюда</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $ing): ?>
                        <tr data-cat="<?= h($catName) ?>">
                            <td class="ps-3 fw-semibold"><?= h($ing['name']) ?></td>
                            <td><?= number_format($ing['price'], 0) ?> ₽</td>
                            <td>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" autocomplete="off"
                                           name="types[<?= $ing['id'] ?>]" id="t<?= $ing['id'] ?>_pizza"
                                           value="pizza" <?= ($ing['dish_type'] ?? 'both') === 'pizza' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-warning" for="t<?= $ing['id'] ?>_pizza">
                                        🍕 Пицца
                                    </label>

                                    <input type="radio" class="btn-check" autocomplete="off"
                                           name="types[<?= $ing['id'] ?>]" id="t<?= $ing['id'] ?>_wok"
                                           value="wok" <?= ($ing['dish_type'] ?? '') === 'wok' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-success" for="t<?= $ing['id'] ?>_wok">
                                        🥢 Вок
                                    </label>

                                    <input type="radio" class="btn-check" autocomplete="off"
                                           name="types[<?= $ing['id'] ?>]" id="t<?= $ing['id'] ?>_both"
                                           value="both" <?= ($ing['dish_type'] ?? 'both') === 'both' ? 'checked' : '' ?>>
                                    <label class="btn btn-outline-primary" for="t<?= $ing['id'] ?>_both">
                                        🔀 Оба
                                    </label>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-save me-2"></i>Сохранить все изменения
            </button>
        </div>
    </form>

    <?php endif; ?>
</div>

<script>
function setAll(type) {
    document.querySelectorAll('input[type=radio][value=' + type + ']').forEach(function(r) {
        r.checked = true;
    });
}
function setCat(cat, type) {
    document.querySelectorAll('tr[data-cat="' + cat + '"] input[value=' + type + ']').forEach(function(r) {
        r.checked = true;
    });
}
</script>
</body>
</html>
