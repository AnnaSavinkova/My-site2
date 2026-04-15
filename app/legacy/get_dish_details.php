<?php
// get_dish_details.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

$dish_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    if ($dish_id > 0) {
        // Получаем основную информацию о блюде
        $stmt = $pdo->prepare("
            SELECT d.*, r.name as restaurant_name, r.id as restaurant_id 
            FROM dishes d
            JOIN restaurants r ON d.restaurant_id = r.id
            WHERE d.id = ?
        ");
        $stmt->execute([$dish_id]);
        $dish = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($dish) {
            // Получаем ингредиенты
            $ing_stmt = $pdo->prepare("
                SELECT i.name, i.price, i.category
                FROM dish_ingredients di
                JOIN ingredients i ON di.ingredient_id = i.id
                WHERE di.dish_id = ?
            ");
            $ing_stmt->execute([$dish_id]);
            $dish['ingredients'] = $ing_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($dish, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['error' => 'Блюдо не найдено'], JSON_UNESCAPED_UNICODE);
        }
    } else {
        echo json_encode(['error' => 'Не указан ID блюда'], JSON_UNESCAPED_UNICODE);
    }
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка базы данных'], JSON_UNESCAPED_UNICODE);
}
?>