<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';

try {
    // Получаем 3 случайных блюда как "популярные"
    // В реальном приложении здесь должна быть логика подсчета популярности
    $stmt = $pdo->query("
        SELECT * FROM dishes 
        WHERE id IN (
            SELECT id FROM dishes 
            ORDER BY RAND() 
            LIMIT 3
        )
    ");
    
    $dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Если блюд мало или нет, возвращаем тестовые данные
    if (count($dishes) < 3) {
        // Дополняем тестовыми данными если нужно
        $testDishes = [
            [
                'id' => 1,
                'name' => 'Пицца Маргарита',
                'description' => 'Классическая итальянская пицца с томатами и моцареллой',
                'price' => 580,
                'image' => 'margherita.jpg',
                'customizable' => 1
            ],
            [
                'id' => 4,
                'name' => 'Чеснокный хлеб',
                'description' => 'Хрустящий хлеб с чесночным маслом и зеленью',
                'price' => 250,
                'image' => 'garlic_bread.jpg',
                'customizable' => 0
            ],
            [
                'id' => 7,
                'name' => 'Wok с курицей',
                'description' => 'Лапша удон с курицей и овощами в соевом соусе',
                'price' => 420,
                'image' => 'chicken_wok.jpg',
                'customizable' => 1
            ]
        ];
        
        // Объединяем реальные и тестовые данные
        $dishes = array_merge($dishes, array_slice($testDishes, 0, 3 - count($dishes)));
        $dishes = array_slice($dishes, 0, 3);
    }
    
    echo json_encode($dishes, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // В случае ошибки возвращаем тестовые данные
    $dishes = [
        [
            'id' => 1,
            'name' => 'Пицца Маргарита',
            'description' => 'Классическая итальянская пицца с томатами и моцареллой',
            'price' => 580,
            'image' => 'margherita.jpg',
            'customizable' => 1
        ],
        [
            'id' => 4,
            'name' => 'Чеснокный хлеб',
            'description' => 'Хрустящий хлеб с чесночным маслом и зеленью',
            'price' => 250,
            'image' => 'garlic_bread.jpg',
            'customizable' => 0
        ],
        [
            'id' => 7,
            'name' => 'Wok с курицей',
            'description' => 'Лапша удон с курицей и овощами в соевом соусе',
            'price' => 420,
            'image' => 'chicken_wok.jpg',
            'customizable' => 1
        ]
    ];
    
    echo json_encode($dishes, JSON_UNESCAPED_UNICODE);
}
?>