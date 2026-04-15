<?php
// test_restaurants.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';

echo "<h1>Тест get_restaurants.php</h1>";

try {
    $stmt = $pdo->query("SELECT id, name FROM restaurants");
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Рестораны в БД:</h2>";
    echo "<pre>";
    print_r($restaurants);
    echo "</pre>";
    
    echo "<h2>JSON, который должен приходить в JS:</h2>";
    echo "<pre>";
    echo json_encode($restaurants, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "Ошибка БД: " . $e->getMessage();
}
?>