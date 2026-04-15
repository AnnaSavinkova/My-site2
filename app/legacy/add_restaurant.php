<?php
require_once __DIR__ . '/../../config/db.php';
require 'check_admin.php'; // Убрали __DIR__ . 


$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $logo_url = trim($_POST['logo_url']);

    if (empty($name)) {
        $message = '<div class="alert alert-danger">Введите название ресторана!</div>';
    } else {
        $sql = "INSERT INTO restaurants (name, description, logo_url) VALUES (:n, :d, :logo)";
        $stmt = $pdo->prepare($sql);
        
        try {
            $stmt->execute([
                ':n' => $name,
                ':d' => $description,
                ':logo' => $logo_url
            ]);
            $message = '<div class="alert alert-success">Ресторан добавлен! Теперь можно добавлять блюда.</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-danger">Ошибка: ' . $e->getMessage() . '</div>';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Добавить ресторан</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
    <div class="container">
        <h1>Добавить ресторан</h1>
        <a href="add_item.php" class="btn btn-secondary mb-3">← Назад к добавлению блюд</a>
        
        <?= $message ?>
        
        <form method="POST" class="card p-4 shadow-sm">
            <div class="mb-3">
                <label>Название ресторана:</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            
            <div class="mb-3">
                <label>Описание:</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="mb-3">
                <label>Логотип (URL):</label>
                <input type="url" name="logo_url" class="form-control" placeholder="https://...">
            </div>
            
            <button type="submit" class="btn btn-success">Добавить ресторан</button>
        </form>
    </div>
</body>
</html>