<?php
// admin_dish_ingredients.php — управление составом блюд (базовые ингредиенты)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once 'check_admin.php';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$dishId  = (int)($_GET['dish_id'] ?? 0);
$message = '';
$error   = '';

// Загружаем блюдо
$dish = null;
if ($dishId > 0) {
    $stmt = $pdo->prepare("SELECT d.*, r.name AS r_name FROM dishes d JOIN restaurants r ON d.restaurant_id = r.id WHERE d.id = ?");
    $stmt->execute([$dishId]);
    $dish = $stmt->fetch();
}

// Если блюдо не найдено — редирект
if (!$dish) {
    header('Location: admin_menu.php');
    exit;
}

// ── СОХРАНЕНИЕ состава ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ingredients'])) {
    $selectedIds = isset($_POST['ingredients']) ? array_map('intval', $_POST['ingredients']) : [];

    try {
        // Удаляем старый состав
        $pdo->prepare("DELETE FROM dish_ingredients WHERE dish_id = ?")->execute([$dishId]);

        // Вставляем новый
        if (!empty($selectedIds)) {
            $stmt = $pdo->prepare("INSERT INTO dish_ingredients (dish_id, ingredient_id) VALUES (?, ?)");
            foreach ($selectedIds as $ingId) {
                if ($ingId > 0) $stmt->execute([$dishId, $ingId]);
            }
        }
        $message = 'Состав блюда обновлён.';
    } catch (PDOException $e) {
        $error = 'Ошибка: ' . $e->getMessage();
    }
}

// Текущие ингредиенты блюда
$currentStmt = $pdo->prepare("SELECT ingredient_id FROM dish_ingredients WHERE dish_id = ?");
$currentStmt->execute([$dishId]);
$currentIds = array_column($currentStmt->fetchAll(), 'ingredient_id');
$currentIds = array_map('intval', $currentIds);

// Все ингредиенты — фильтруем по типу блюда
$restaurantId = (int)$dish['restaurant_id'];
if ($restaurantId === 1) {
    $dish_type = 'pizza';
} elseif ($restaurantId === 2) {
    $dish_type = 'wok';
} else {
    $dish_type = 'both';
}

// Проверяем наличие колонки dish_type
$hasDishType = false;
try {
    $check = $pdo->query("SHOW COLUMNS FROM ingredients LIKE 'dish_type'");
    $hasDishType = ($check->rowCount() > 0);
} catch (PDOException $e) {}

if ($hasDishType && $dish_type !== 'both') {
    $allStmt = $pdo->prepare("
        SELECT * FROM ingredients WHERE dish_type IN (?, 'both') ORDER BY category, name
    ");
    $allStmt->execute([$dish_type]);
} else {
    $allStmt = $pdo->query("SELECT * FROM ingredients ORDER BY category, name");
}
$allIngredients = $allStmt->fetchAll();

// Группируем по категориям
$byCategory = [];
foreach ($allIngredients as $ing) {
    $byCategory[$ing['category']][] = $ing;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Состав блюда — Админ</title>
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

        /* Карточки ингредиентов */
        .ing-card {
            border: 2px solid #e2e8f0; border-radius: 12px; padding: 12px 14px;
            cursor: pointer; transition: all .2s; user-select: none;
            display: flex; align-items: center; gap: 10px; background: white;
        }
        .ing-card:hover { border-color: #9b59b6; background: #faf5ff; }
        .ing-card.selected { border-color: #9b59b6; background: #faf5ff; }
        .ing-card.selected .ing-check { background: #9b59b6; border-color: #9b59b6; }
        .ing-card.selected .ing-check i { display: block; }

        .ing-check {
            width: 22px; height: 22px; border: 2px solid #cbd5e1; border-radius: 6px;
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0; transition: all .2s;
        }
        .ing-check i { color: white; font-size: 11px; display: none; }

        .ing-label { flex: 1; min-width: 0; }
        .ing-name-text { font-size: 14px; font-weight: 600; color: #1e293b; display: block; }
        .ing-price-text { font-size: 12px; color: #9b59b6; font-weight: 600; }

        /* Выбранные */
        .selected-preview {
            display: flex; flex-wrap: wrap; gap: 8px; min-height: 40px;
            padding: 10px; background: #f8fafc; border-radius: 10px; border: 1px solid #e2e8f0;
        }
        .sel-tag {
            background: #faf5ff; border: 1px solid #d8b4fe;
            color: #7c3aed; padding: 4px 12px; border-radius: 20px;
            font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 6px;
        }
        .sel-tag button {
            background: none; border: none; color: #9b59b6; cursor: pointer;
            font-size: 14px; padding: 0; line-height: 1;
        }
        .sel-tag button:hover { color: #dc2626; }

        .dish-hero {
            background: linear-gradient(135deg, #1e293b, #334155);
            border-radius: 16px; padding: 20px 24px; color: white; margin-bottom: 24px;
        }
        .dish-hero h2 { font-size: 18px; margin: 0 0 4px; }
        .dish-hero p  { font-size: 13px; color: rgba(255,255,255,0.6); margin: 0; }

        .cat-header {
            font-size: 13px; font-weight: 700; color: #64748b; text-transform: uppercase;
            letter-spacing: 0.5px; padding: 10px 0 6px; margin-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }

        .ing-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px;
            margin-bottom: 24px;
        }

        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 16px; }
            .sidebar { display: none; }
            .ing-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="fas fa-utensils"></i> Админ-панель</div>
    <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Дашборд</a>
    <a href="admin_orders.php"><i class="fas fa-box"></i> Заказы</a>
    <a href="admin_restaurants.php"><i class="fas fa-store"></i> Рестораны</a>
    <a href="admin_menu.php" class="active"><i class="fas fa-utensils"></i> Меню</a>
    <a href="admin_categories.php"><i class="fas fa-tags"></i> Категории</a>
    <a href="admin_ingredients.php"><i class="fas fa-pepper-hot"></i> Ингредиенты</a>
    <a href="index.php" style="margin-top:20px; border-top:1px solid #334155;"><i class="fas fa-home"></i> На сайт</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти</a>
</div>

<div class="main-content">

    <a href="admin_menu.php" class="btn btn-outline-secondary btn-sm mb-3">
        <i class="fas fa-arrow-left me-1"></i> Назад к меню
    </a>

    <div class="dish-hero">
        <h2><i class="fas fa-list-ul me-2"></i><?= h($dish['name']) ?></h2>
        <p><?= h($dish['r_name']) ?> · Базовый состав для конструктора</p>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle me-2"></i><?= h($message) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i><?= h($error) ?></div>
    <?php endif; ?>

    <!-- Выбранные ингредиенты -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
            <span><i class="fas fa-check-circle text-success me-2"></i>Выбранные ингредиенты</span>
            <span class="badge bg-purple text-white" id="count-badge" style="background:#9b59b6"><?= count($currentIds) ?> выбрано</span>
        </div>
        <div class="card-body">
            <div class="selected-preview" id="selected-preview">
                <?php if (empty($currentIds)): ?>
                    <span class="text-muted small" id="empty-hint">Не выбрано ни одного ингредиента</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Форма -->
    <form method="POST" id="ing-form">
        <input type="hidden" name="save_ingredients" value="1">

        <?php foreach ($byCategory as $catName => $items): ?>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body pb-2">
                <div class="cat-header">
                    <?= h($catName) ?>
                    <span class="badge bg-secondary ms-1"><?= count($items) ?></span>
                </div>
                <div class="ing-grid">
                    <?php foreach ($items as $ing): ?>
                        <?php $isSelected = in_array((int)$ing['id'], $currentIds); ?>
                        <div class="ing-card <?= $isSelected ? 'selected' : '' ?>"
                             onclick="toggleIng(this, <?= $ing['id'] ?>, '<?= h(addslashes($ing['name'])) ?>')"
                             data-id="<?= $ing['id'] ?>"
                             data-name="<?= h(addslashes($ing['name'])) ?>">
                            <div class="ing-check">
                                <i class="fas fa-check"></i>
                            </div>
                            <div class="ing-label">
                                <span class="ing-name-text"><?= h($ing['name']) ?></span>
                                <span class="ing-price-text">+<?= number_format($ing['price'], 0) ?> ₽</span>
                            </div>
                            <?php if ($isSelected): ?>
                                <input type="hidden" name="ingredients[]" value="<?= $ing['id'] ?>" class="ing-input" data-id="<?= $ing['id'] ?>">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Кнопка сохранения -->
        <div class="d-flex gap-2 mt-2 mb-5">
            <button type="submit" class="btn btn-success btn-lg">
                <i class="fas fa-save me-2"></i>Сохранить состав
            </button>
            <a href="admin_menu.php" class="btn btn-outline-secondary btn-lg">Отмена</a>
        </div>
    </form>

</div>

<script>
// Текущие выбранные ID
var selectedIds = <?= json_encode($currentIds) ?>;
var selectedNames = {};

// Заполняем имена из DOM
document.querySelectorAll('.ing-card').forEach(function(card) {
    var id = parseInt(card.getAttribute('data-id'));
    var name = card.getAttribute('data-name');
    if (selectedIds.indexOf(id) !== -1) {
        selectedNames[id] = name;
    }
});

// Рисуем превью при загрузке
renderPreview();

function toggleIng(card, id, name) {
    var idx = selectedIds.indexOf(id);
    if (idx === -1) {
        // Добавляем
        selectedIds.push(id);
        selectedNames[id] = name;
        card.classList.add('selected');
        // Добавляем hidden input
        var inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'ingredients[]';
        inp.value = id;
        inp.className = 'ing-input';
        inp.setAttribute('data-id', id);
        card.appendChild(inp);
    } else {
        // Убираем
        selectedIds.splice(idx, 1);
        delete selectedNames[id];
        card.classList.remove('selected');
        var inp = card.querySelector('.ing-input[data-id="' + id + '"]');
        if (inp) inp.remove();
    }
    renderPreview();
    updateCount();
}

function removeFromPreview(id) {
    var card = document.querySelector('.ing-card[data-id="' + id + '"]');
    if (card) toggleIng(card, id, selectedNames[id] || '');
}

function renderPreview() {
    var preview = document.getElementById('selected-preview');
    var hint    = document.getElementById('empty-hint');

    if (selectedIds.length === 0) {
        preview.innerHTML = '<span class="text-muted small" id="empty-hint">Не выбрано ни одного ингредиента</span>';
        return;
    }

    preview.innerHTML = selectedIds.map(function(id) {
        var name = selectedNames[id] || ('Ингредиент #' + id);
        return '<span class="sel-tag">' +
            escHtml(name) +
            '<button type="button" onclick="removeFromPreview(' + id + ')" title="Убрать">✕</button>' +
            '</span>';
    }).join('');
}

function updateCount() {
    var badge = document.getElementById('count-badge');
    if (badge) badge.textContent = selectedIds.length + ' выбрано';
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body>
</html>
