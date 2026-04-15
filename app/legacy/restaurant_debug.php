<?php
// restaurant_debug.php - ДИАГНОСТИЧЕСКАЯ ВЕРСИЯ
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// АБСОЛЮТНЫЙ путь к db.php
require_once __DIR__ . '/../../config/db.php';
echo "<!-- Подключаем db.php из: $db_path -->\n";

if (!file_exists($db_path)) {
    die("CRITICAL: Файл db.php не найден по пути: $db_path");
}

require_once $db_path;
echo "<!-- db.php успешно подключен -->\n";

// Проверяем PDO
if (!isset($pdo)) {
    die("CRITICAL: Переменная \$pdo не определена после подключения db.php");
}
echo "<!-- PDO объект существует -->\n";

// Получаем ID ресторана
$restaurant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
echo "<!-- Restaurant ID: $restaurant_id -->\n";

if ($restaurant_id <= 0) {
    die("ID ресторана не указан или равен 0");
}

try {
    // ТЕСТ 1: Проверяем соединение с БД
    $test = $pdo->query("SELECT 1");
    echo "<!-- Соединение с БД работает -->\n";
    
    // ТЕСТ 2: Проверяем таблицу restaurants
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<!-- Таблицы в БД: " . implode(', ', $tables) . " -->\n";
    
    if (!in_array('restaurants', $tables)) {
        die("CRITICAL: Таблица 'restaurants' не существует в БД!");
    }
    
    // ТЕСТ 3: Пытаемся получить ресторан
    $stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
    $stmt->execute([$restaurant_id]);
    $restaurant = $stmt->fetch();
    
    if (!$restaurant) {
        die("Ресторан с ID $restaurant_id не найден");
    }
    echo "<!-- Ресторан найден: {$restaurant['name']} -->\n";
    
    // ТЕСТ 4: Проверяем структуру таблицы dishes
    $columns = $pdo->query("SHOW COLUMNS FROM dishes")->fetchAll(PDO::FETCH_COLUMN);
    echo "<!-- Поля в dishes: " . implode(', ', $columns) . " -->\n";
    
    // Проверяем наличие поля customizable
    if (!in_array('customizable', $columns)) {
        echo "<!-- ВНИМАНИЕ: Поле 'customizable' отсутствует в таблице dishes -->\n";
        // Создаем упрощенный запрос без customizable
        $dishes_stmt = $pdo->prepare("
            SELECT d.*, 
                   (SELECT COUNT(*) FROM dish_ingredients WHERE dish_id = d.id) as ingredients_count
            FROM dishes d 
            WHERE d.restaurant_id = ? 
            ORDER BY d.name ASC
        ");
    } else {
        // Оригинальный запрос с customizable
        $dishes_stmt = $pdo->prepare("
            SELECT d.*, 
                   (SELECT COUNT(*) FROM dish_ingredients WHERE dish_id = d.id) as ingredients_count
            FROM dishes d 
            WHERE d.restaurant_id = ? 
            ORDER BY d.customizable DESC, d.name ASC
        ");
    }
    
    $dishes_stmt->execute([$restaurant_id]);
    $dishes = $dishes_stmt->fetchAll();
    echo "<!-- Найдено блюд: " . count($dishes) . " -->\n";
    
    // ТЕСТ 5: Проверяем таблицу ingredients
    if (in_array('ingredients', $tables)) {
        $ingredients_stmt = $pdo->query("SELECT COUNT(*) as cnt FROM ingredients");
        $ing_count = $ingredients_stmt->fetchColumn();
        echo "<!-- Ингредиентов в БД: $ing_count -->\n";
    } else {
        echo "<!-- Таблица ingredients не существует -->\n";
    }
    
} catch (PDOException $e) {
    die("ОШИБКА SQL: " . $e->getMessage() . "<br>Код ошибки: " . $e->getCode());
} catch (Exception $e) {
    die("ОБЩАЯ ОШИБКА: " . $e->getMessage());
}

// Если дошли до сюда - значит всё работает
echo "<h1 style='color:green;'>✅ Диагностика прошла успешно! Ресторан: " . htmlspecialchars($restaurant['name']) . "</h1>";
echo "<p>Теперь загрузите эту страницу и посмотрите исходный код (Ctrl+U) - там будут комментарии с диагностикой.</p>";
echo "<p><a href='restaurant.php?id=$restaurant_id'>Перейти к оригинальному restaurant.php</a></p>";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Диагностика - <?= htmlspecialchars($restaurant['name']) ?></title>
</head>
<body>
    <h2>Данные ресторана:</h2>
    <pre><?php print_r($restaurant); ?></pre>
    
    <h2>Блюда (<?= count($dishes) ?>):</h2>
    <pre><?php print_r($dishes); ?></pre>
</body>
</html>