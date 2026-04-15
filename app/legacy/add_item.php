<?php
require_once __DIR__ . '/../../config/db.php';
require 'check_admin.php';

$message = '';

// Получаем список ресторанов
$restaurants = [];
try {
    $restaurant_stmt = $pdo->query("SELECT id, name FROM restaurants ORDER BY name");
    $restaurants = $restaurant_stmt->fetchAll();
} catch (PDOException $e) {
    $message = '<div class="alert alert-danger">Ошибка загрузки ресторанов: ' . $e->getMessage() . '</div>';
}

// Если нажата кнопка "Сохранить"
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restaurant_id = $_POST['restaurant_id'];
    $name = trim($_POST['name']);
    $price = $_POST['price'];
    $description = trim($_POST['description']);
    $image = $_POST['image'] ?? 'dish-default.jpg';
    $customizable = isset($_POST['customizable']) ? 1 : 0;

    if (empty($restaurant_id) || empty($name) || empty($price)) {
        $message = '<div class="alert alert-danger">Заполните обязательные поля!</div>';
    } elseif (!is_numeric($price) || $price <= 0) {
        $message = '<div class="alert alert-danger">Цена должна быть положительным числом!</div>';
    } else {
        $sql = "INSERT INTO dishes (restaurant_id, name, description, price, image, customizable) 
                VALUES (:rid, :name, :desc, :price, :img, :custom)";
        $stmt = $pdo->prepare($sql);
        
        try {
            $stmt->execute([
                ':rid' => $restaurant_id,
                ':name' => $name,
                ':desc' => $description,
                ':price' => $price,
                ':img' => $image,
                ':custom' => $customizable
            ]);
            $message = '<div class="alert alert-success">Блюдо успешно добавлено!</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Ошибка БД: ' . $e->getMessage() . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить блюдо</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .required:after { content: " *"; color: red; }
    </style>
</head>
<body class="p-4">
    <div class="container">
        <h1>Добавление нового блюда</h1>
        <a href="admin_dashboard.php" class="btn btn-secondary mb-3">← В админку</a>
        
        <?= $message ?>

        <form method="POST" class="card p-4 shadow-sm">
            <!-- Выбор ресторана -->
            <div class="mb-3">
                <label class="required">Ресторан:</label>
                <select name="restaurant_id" class="form-select" required>
                    <option value="">-- Выберите ресторан --</option>
                    <?php foreach ($restaurants as $restaurant): ?>
                        <option value="<?= htmlspecialchars($restaurant['id']) ?>" 
                            <?= isset($_POST['restaurant_id']) && $_POST['restaurant_id'] == $restaurant['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($restaurant['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Название блюда -->
            <div class="mb-3">
                <label class="required">Название блюда:</label>
                <input type="text" name="name" class="form-control" 
                       value="<?= isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '' ?>" 
                       required placeholder="Например: Пицца Маргарита">
            </div>
            
            <!-- Цена -->
            <div class="mb-3">
                <label class="required">Цена (руб):</label>
                <div class="input-group">
                    <input type="number" name="price" class="form-control" 
                           value="<?= isset($_POST['price']) ? htmlspecialchars($_POST['price']) : '' ?>" 
                           step="0.01" min="0" required placeholder="350.00">
                    <span class="input-group-text">₽</span>
                </div>
            </div>

            <!-- Конструктор -->
            <div class="mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="customizable" id="customizable" value="1"
                           <?= isset($_POST['customizable']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="customizable">
                        <strong>Доступен конструктор ингредиентов</strong>
                    </label>
                    <small class="d-block text-muted">Отметьте, если для этого блюда можно выбирать ингредиенты (пицца, вок)</small>
                </div>
            </div>

            <!-- Картинка -->
            <div class="mb-3">
                <label>Название файла картинки:</label>
                <input type="text" name="image" class="form-control" 
                       value="<?= isset($_POST['image']) ? htmlspecialchars($_POST['image']) : 'dish-default.jpg' ?>" 
                       placeholder="dish-default.jpg">
                <small class="text-muted">Имя файла в папке images/</small>
            </div>

            <!-- Описание -->
            <div class="mb-3">
                <label>Описание:</label>
                <textarea name="description" class="form-control" rows="3" 
                          placeholder="Состав, особенности блюда..."><?= isset($_POST['description']) ? htmlspecialchars($_POST['description']) : '' ?></textarea>
            </div>

            <!-- Кнопки -->
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success flex-grow-1">Сохранить блюдо</button>
            </div>
        </form>
    </div>
</body>
</html>