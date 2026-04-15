<?php
// cart_api.php — серверное API корзины
// Все операции через POST action= или GET action=get

// ── ВРЕМЕННАЯ ДИАГНОСТИКА (удалить после исправления) ──────────────────
ini_set('display_errors', 0);   // не выводим HTML-ошибки — сломают JSON
ini_set('log_errors', 1);
error_reporting(E_ALL);
set_exception_handler(function($e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    exit;
});
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});
// ── КОНЕЦ ДИАГНОСТИКИ ───────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Универсальный путь к db.php — работает и на Beget и локально
$db_paths = [
    __DIR__ . '/../../config/db.php',   // правильный путь к корневой папке config
    __DIR__ . '/../config/db.php',
    __DIR__ . '/config/db.php',
];

$db_loaded = false;
foreach ($db_paths as $db_path) {
    if (file_exists($db_path)) { require_once $db_path; $db_loaded = true; break; }
}
if (!$db_loaded) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB config not found']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Только для авторизованных
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Необходима авторизация', 'redirect' => 'login.php']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// ── Вспомогательные функции ────────────────────────────────────────────────

function getCart(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare("
        SELECT c.id, c.dish_id, c.restaurant_id, c.quantity,
               c.customizations, c.item_price,
               d.name AS dish_name, d.image AS dish_image,
               r.name AS restaurant_name
        FROM cart c
        JOIN dishes d ON c.dish_id = d.id
        JOIN restaurants r ON c.restaurant_id = r.id
        WHERE c.user_id = ?
        ORDER BY c.created_at ASC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $items = [];
    $subtotal = 0;
    $restaurantId = null;
    $restaurantName = '';
    $mixedRestaurants = false;

    foreach ($rows as $row) {
        $customizations = $row['customizations'] ? json_decode($row['customizations'], true) : [];
        $customTotal = array_sum(array_column($customizations, 'price'));
        $linePrice   = ($row['item_price'] + $customTotal) * $row['quantity'];
        $subtotal   += $linePrice;

        if ($restaurantId === null) {
            $restaurantId   = $row['restaurant_id'];
            $restaurantName = $row['restaurant_name'];
        } elseif ($restaurantId !== (int)$row['restaurant_id']) {
            $mixedRestaurants = true;
        }

        $items[] = [
            'id'              => (int)$row['id'],
            'dish_id'         => (int)$row['dish_id'],
            'dish_name'       => $row['dish_name'],
            'dish_image'      => $row['dish_image'],
            'restaurant_id'   => (int)$row['restaurant_id'],
            'restaurant_name' => $row['restaurant_name'],
            'quantity'        => (int)$row['quantity'],
            'item_price'      => (float)$row['item_price'],
            'customizations'  => $customizations,
            'custom_total'    => (float)$customTotal,
            'line_price'      => (float)$linePrice,
        ];
    }

    $deliveryFee = ($subtotal > 0 && $subtotal < 2000) ? 150 : 0;

    return [
        'items'            => $items,
        'count'            => array_sum(array_column($items, 'quantity')),
        'subtotal'         => (float)$subtotal,
        'delivery_fee'     => (float)$deliveryFee,
        'total'            => (float)($subtotal + $deliveryFee),
        'restaurant_id'    => $restaurantId,
        'restaurant_name'  => $restaurantName,
        'mixed_restaurants'=> $mixedRestaurants,
    ];
}

function jsonError(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
// РОУТИНГ
// ══════════════════════════════════════════════════════════════════════════════

try {

switch ($action) {

// ── GET: получить корзину ─────────────────────────────────────────────────
case 'get':
    echo json_encode(['success' => true, 'cart' => getCart($pdo, $userId)]);
    break;

// ── ADD: добавить блюдо ───────────────────────────────────────────────────
case 'add':
    $dishId        = (int)($_POST['dish_id'] ?? 0);
    $quantity      = max(1, (int)($_POST['quantity'] ?? 1));
    $customRaw     = $_POST['customizations'] ?? '[]';

    if ($dishId <= 0) jsonError('Не указан dish_id');

    // Проверяем блюдо
    $dish = $pdo->prepare("
        SELECT d.id, d.price, d.restaurant_id, d.is_active
        FROM dishes d WHERE d.id = ? AND d.is_active = 1
    ");
    $dish->execute([$dishId]);
    $dish = $dish->fetch();
    if (!$dish) jsonError('Блюдо не найдено или недоступно');

    $restaurantId = (int)$dish['restaurant_id'];

    // Проверяем — нет ли уже блюд из ДРУГОГО ресторана
    $existingRest = $pdo->prepare(
        "SELECT DISTINCT restaurant_id FROM cart WHERE user_id = ?"
    );
    $existingRest->execute([$userId]);
    $existingRestIds = array_column($existingRest->fetchAll(), 'restaurant_id');

    if (!empty($existingRestIds) && !in_array($restaurantId, $existingRestIds)) {
        // Другой ресторан — спрашиваем подтверждение (клиент должен передать confirm=1)
        if (empty($_POST['confirm_clear'])) {
            echo json_encode([
                'success'             => false,
                'error'               => 'different_restaurant',
                'message'             => 'В корзине уже есть блюда из другого ресторана. Очистить корзину и добавить новое блюдо?',
                'current_restaurant'  => $pdo->query("SELECT name FROM restaurants WHERE id = {$existingRestIds[0]}")->fetchColumn(),
                'new_restaurant'      => (function() use ($pdo, $restaurantId) { $s = $pdo->prepare("SELECT name FROM restaurants WHERE id = ?"); $s->execute([$restaurantId]); return $s->fetchColumn(); })(),
            ]);
            exit;
        }
        // Подтверждение получено — очищаем
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
    }

    // Валидируем и декодируем кастомизации
    $customizations = [];
    $customDecoded  = json_decode($customRaw, true);
    if (is_array($customDecoded) && !empty($customDecoded)) {
        // Проверяем каждый ингредиент по БД
        $ingIds = array_filter(array_map('intval', array_column($customDecoded, 'id')));
        if (!empty($ingIds)) {
            $placeholders = implode(',', array_fill(0, count($ingIds), '?'));
            $ingStmt = $pdo->prepare(
                "SELECT id, name, price FROM ingredients WHERE id IN ($placeholders)"
            );
            $ingStmt->execute($ingIds);
            $validIngredients = [];
            foreach ($ingStmt->fetchAll() as $ing) {
                $validIngredients[$ing['id']] = $ing;
            }
            foreach ($customDecoded as $c) {
                $id = (int)($c['id'] ?? 0);
                if (isset($validIngredients[$id])) {
                    $customizations[] = [
                        'id'    => $id,
                        'name'  => $validIngredients[$id]['name'],
                        'price' => (float)$validIngredients[$id]['price'],
                    ];
                }
            }
        }
    }

    $customJson = empty($customizations) ? null : json_encode($customizations, JSON_UNESCAPED_UNICODE);

    // Проверяем — есть ли уже такое же блюдо с теми же кастомизациями
    $existing = $pdo->prepare("
        SELECT id, quantity FROM cart
        WHERE user_id = ? AND dish_id = ?
          AND (customizations <=> ?)
    ");
    $existing->execute([$userId, $dishId, $customJson]);
    $existingRow = $existing->fetch();

    if ($existingRow) {
        // Увеличиваем количество
        $newQty = min(99, $existingRow['quantity'] + $quantity);
        $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?")
            ->execute([$newQty, $existingRow['id']]);
    } else {
        // Новая позиция
        $pdo->prepare("
            INSERT INTO cart (user_id, dish_id, restaurant_id, quantity, customizations, item_price)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$userId, $dishId, $restaurantId, $quantity, $customJson, $dish['price']]);
    }

    echo json_encode(['success' => true, 'cart' => getCart($pdo, $userId)]);
    break;

// ── UPDATE: изменить количество ───────────────────────────────────────────
case 'update':
    $cartId  = (int)($_POST['cart_id'] ?? 0);
    $qty     = (int)($_POST['quantity'] ?? 0);

    if ($cartId <= 0) jsonError('Не указан cart_id');

    // IDOR-защита: только своя корзина
    $check = $pdo->prepare("SELECT id FROM cart WHERE id = ? AND user_id = ?");
    $check->execute([$cartId, $userId]);
    if (!$check->fetch()) jsonError('Позиция не найдена', 404);

    if ($qty <= 0) {
        $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?")->execute([$cartId, $userId]);
    } else {
        $pdo->prepare("UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ? AND user_id = ?")
            ->execute([min(99, $qty), $cartId, $userId]);
    }

    echo json_encode(['success' => true, 'cart' => getCart($pdo, $userId)]);
    break;

// ── REMOVE: удалить позицию ───────────────────────────────────────────────
case 'remove':
    $cartId = (int)($_POST['cart_id'] ?? 0);
    if ($cartId <= 0) jsonError('Не указан cart_id');

    $pdo->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?")->execute([$cartId, $userId]);

    echo json_encode(['success' => true, 'cart' => getCart($pdo, $userId)]);
    break;

// ── CLEAR: очистить корзину ───────────────────────────────────────────────
case 'clear':
    $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);
    echo json_encode(['success' => true, 'cart' => getCart($pdo, $userId)]);
    break;

default:
    jsonError('Неизвестный action');
}

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'line'    => $e->getLine(),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ]);
}
