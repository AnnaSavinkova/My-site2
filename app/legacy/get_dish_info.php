<?php
// get_dish_info.php — возвращает данные блюда для корзины
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json; charset=utf-8');

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT d.id, d.name, d.price, d.image, d.customizable, r.name AS restaurant_name, r.id AS restaurant_id
    FROM dishes d
    JOIN restaurants r ON d.restaurant_id = r.id
    WHERE d.id = ? AND d.is_active = 1
");
$stmt->execute([$id]);
$dish = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dish) {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
    exit;
}

echo json_encode([
    'id'              => (int)$dish['id'],
    'dish_id'         => (int)$dish['id'],
    'name'            => $dish['name'],
    'price'           => (float)$dish['price'],
    'image'           => $dish['image'] ?: 'dish-default.jpg',
    'customizable'    => (int)$dish['customizable'],
    'restaurant_id'   => (int)$dish['restaurant_id'],
    'restaurant_name' => $dish['restaurant_name'],
]);
