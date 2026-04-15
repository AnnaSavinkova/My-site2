<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    if (!empty($search)) {
        $stmt = $pdo->prepare("SELECT id, name, description, logo_url FROM restaurants WHERE name LIKE ? OR description LIKE ? ORDER BY name");
        $searchTerm = "%{$search}%";
        $stmt->execute([$searchTerm, $searchTerm]);
    } else {
        $stmt = $pdo->query("SELECT id, name, description, logo_url FROM restaurants ORDER BY name");
    }
    
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($restaurants, JSON_UNESCAPED_UNICODE);
    
} catch(PDOException $e) {
    // В случае ошибки возвращаем пустой массив
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
?>