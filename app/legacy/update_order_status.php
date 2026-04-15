<?php
// update_order_status.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once 'check_admin.php';

// Теперь через POST (безопасно)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_orders.php');
    exit;
}

$id     = (int)($_POST['id'] ?? 0);
$status = $_POST['status'] ?? '';

$allowed = ['new', 'confirmed', 'preparing', 'ready', 'delivering', 'delivered', 'cancelled'];

if ($id <= 0 || !in_array($status, $allowed)) {
    die('Некорректные данные');
}

$stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
$stmt->execute([$status, $id]);

header('Location: admin_orders.php');
exit;
