<?php
// get_constructor.php — данные для конструктора пиццы/вока
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$dish_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($dish_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

// Блюдо — только customizable=1
$stmt = $pdo->prepare("
    SELECT d.id, d.name, d.description, d.price, d.image,
           d.customizable, d.restaurant_id,
           r.name AS restaurant_name
    FROM dishes d
    JOIN restaurants r ON d.restaurant_id = r.id
    WHERE d.id = ? AND d.customizable = 1 AND d.is_active = 1
");
$stmt->execute([$dish_id]);
$dish = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dish) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

// Определяем тип блюда по ресторану
// Pizza House (id=1) → pizza, Asia House (id=2) → wok
// Можно расширить логику при добавлении новых ресторанов
$restaurantId = (int)$dish['restaurant_id'];
if ($restaurantId === 1) {
    $dish_type = 'pizza';
} elseif ($restaurantId === 2) {
    $dish_type = 'wok';
} else {
    $dish_type = 'both';
}

// Базовые ингредиенты блюда (из dish_ingredients)
$stmt2 = $pdo->prepare("
    SELECT i.id, i.name, i.price, i.category
    FROM dish_ingredients di
    JOIN ingredients i ON di.ingredient_id = i.id
    WHERE di.dish_id = ?
    ORDER BY i.category, i.name
");
$stmt2->execute([$dish_id]);
$base_ingredients = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Все доступные ингредиенты для добавления — фильтруем по типу блюда
// Показываем ингредиенты своего типа + универсальные (both)
// Проверяем: есть ли колонка dish_type в таблице ingredients
$has_dish_type = false;
try {
    $check = $pdo->query("SHOW COLUMNS FROM ingredients LIKE 'dish_type'");
    $has_dish_type = ($check->rowCount() > 0);
} catch (PDOException $e) {
    $has_dish_type = false;
}

if ($has_dish_type && $dish_type !== 'both') {
    // Фильтруем: только свои + универсальные
    $stmtAll = $pdo->prepare("
        SELECT id, name, price, category
        FROM ingredients
        WHERE dish_type IN (?, 'both')
        ORDER BY category, name
    ");
    $stmtAll->execute([$dish_type]);
} else {
    // Миграция ещё не выполнена — показываем все (старое поведение)
    $stmtAll = $pdo->query("
        SELECT id, name, price, category
        FROM ingredients
        ORDER BY category, name
    ");
}

$all = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

// Группируем по категориям
$by_category = [];
foreach ($all as $ing) {
    $by_category[$ing['category']][] = $ing;
}

echo json_encode([
    'dish'             => $dish,
    'dish_type'        => $dish_type,
    'base_ingredients' => $base_ingredients,
    'by_category'      => $by_category,
], JSON_UNESCAPED_UNICODE);
