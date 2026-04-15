<?php
// get_dishes.php — API поиска блюд
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json; charset=utf-8');

$search        = trim($_GET['search']        ?? '');
$restaurant_id = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0;
$category_id   = isset($_GET['category_id'])   ? (int)$_GET['category_id']   : 0;
$limit         = isset($_GET['limit'])         ? (int)$_GET['limit']         : 60;

$params = [];
$where  = ['d.is_active = 1'];

if ($restaurant_id > 0) {
    $where[]  = 'd.restaurant_id = ?';
    $params[] = $restaurant_id;
}
if ($category_id > 0) {
    $where[]  = 'd.category_id = ?';
    $params[] = $category_id;
}
if ($search !== '') {
    $where[]  = '(d.name LIKE ? OR d.description LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like;
    $params[] = $like;
}

$sql = "
    SELECT d.id, d.name, d.description, d.price, d.image, d.customizable,
           d.restaurant_id, r.name AS restaurant_name, d.category_id,
           c.name AS category_name
    FROM dishes d
    JOIN restaurants r ON d.restaurant_id = r.id
    LEFT JOIN categories c ON d.category_id = c.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY d.name
    LIMIT " . (int)$limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
