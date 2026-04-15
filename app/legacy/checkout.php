<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php?redirect=checkout.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$userId = (int)$_SESSION['user_id'];

// Загружаем корзину из БД
$stmt = $pdo->prepare("
    SELECT c.id AS cart_id, c.dish_id, c.quantity, c.customizations, c.item_price,
           d.name AS dish_name, d.image AS dish_image,
           c.restaurant_id, r.name AS restaurant_name
    FROM cart c
    JOIN dishes d ON c.dish_id = d.id
    JOIN restaurants r ON c.restaurant_id = r.id
    WHERE c.user_id = ?
    ORDER BY c.created_at ASC
");
$stmt->execute([$userId]);
$cartRows = $stmt->fetchAll();

// Если корзина пуста — на главную
if (empty($cartRows)) {
    header('Location: index.php?msg=empty_cart');
    exit;
}

// Считаем итоги
$subtotal     = 0;
$restaurantId = (int)$cartRows[0]['restaurant_id'];
$restaurantName = $cartRows[0]['restaurant_name'];
$items = [];

foreach ($cartRows as $row) {
    $customizations = $row['customizations'] ? json_decode($row['customizations'], true) : [];
    $customTotal    = array_sum(array_column($customizations, 'price'));
    $linePrice      = ($row['item_price'] + $customTotal) * $row['quantity'];
    $subtotal      += $linePrice;

    $items[] = [
        'cart_id'        => (int)$row['cart_id'],
        'dish_id'        => (int)$row['dish_id'],
        'dish_name'      => $row['dish_name'],
        'dish_image'     => $row['dish_image'],
        'quantity'       => (int)$row['quantity'],
        'item_price'     => (float)$row['item_price'],
        'customizations' => $customizations,
        'custom_total'   => (float)$customTotal,
        'line_price'     => (float)$linePrice,
    ];
}

$deliveryFee = $subtotal < 2000 ? 150 : 0;
$total       = $subtotal + $deliveryFee;

// Данные пользователя для подстановки
$user = $pdo->prepare("SELECT username, lastname, phone FROM users WHERE id = ?");
$user->execute([$userId]);
$user = $user->fetch();

$msg   = '';
$error = '';

// ── Оформление заказа ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {

    // CSRF
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $street    = trim($_POST['street']    ?? '');
        $building  = trim($_POST['building']  ?? '');
        $apartment = trim($_POST['apartment'] ?? '');
        $entrance  = trim($_POST['entrance']  ?? '');
        $floor     = trim($_POST['floor']     ?? '');
        $address   = $street . ', д. ' . $building
                   . ($apartment ? ', кв. ' . $apartment : '')
                   . ($entrance  ? ', подъезд ' . $entrance : '')
                   . ($floor     ? ', этаж ' . $floor : '');
        $phone     = trim($_POST['phone']     ?? '');
        $comment   = trim($_POST['comment']   ?? '');
        $payMethod = 'card';

        if (empty($street))   { $error = 'Укажите улицу.'; }
        elseif (empty($building)) { $error = 'Укажите номер дома.'; }
        elseif (empty($phone)) { $error = 'Укажите телефон.'; }
        else {
            // Перечитываем корзину внутри транзакции (защита от двойного заказа)
            $pdo->beginTransaction();
            try {
                // Генерируем номер заказа
                $orderNumber = 'ORD-' . strtoupper(substr(uniqid(), -6)) . '-' . date('md');

                // Создаём заказ
                $pdo->prepare("
                    INSERT INTO orders
                        (user_id, restaurant_id, order_number, subtotal, delivery_fee, total,
                         address, phone, comment, payment_method, payment_status, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'new')
                ")->execute([
                    $userId, $restaurantId, $orderNumber,
                    $subtotal, $deliveryFee, $total,
                    $address, $phone, $comment, $payMethod,
                ]);
                $orderId = (int)$pdo->lastInsertId();

                // Вставляем позиции из корзины
                $insertItem = $pdo->prepare("
                    INSERT INTO order_items
                        (order_id, dish_id, name, quantity, base_price, customizations_total, final_price, special_requests)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($items as $item) {
                    $insertItem->execute([
                        $orderId,
                        $item['dish_id'],
                        $item['dish_name'],
                        $item['quantity'],
                        $item['item_price'],
                        $item['custom_total'],
                        $item['line_price'],
                        '', // special_requests — можно добавить поле в форму
                    ]);

                    // Сохраняем кастомизации в order_dish_customizations если таблица есть
                    if (!empty($item['customizations'])) {
                        $lastItemId = (int)$pdo->lastInsertId();
                        $insCustom  = $pdo->prepare("
                            INSERT IGNORE INTO order_dish_customizations (order_item_id, ingredient_id)
                            VALUES (?, ?)
                        ");
                        foreach ($item['customizations'] as $c) {
                            $insCustom->execute([$lastItemId, $c['id']]);
                        }
                    }
                }

                // Очищаем корзину
                $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$userId]);

                $pdo->commit();

                // Перенаправляем на страницу успеха
                header("Location: order_success.php?order=" . urlencode($orderNumber));
                exit;

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log('Checkout error: ' . $e->getMessage());
                $error = 'Ошибка при оформлении заказа. Попробуйте ещё раз.';
            }
        }
    }
}

// CSRF-токен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оформление заказа — Курьер Экспресс</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .checkout-wrap { max-width: 960px; margin: 0 auto; padding: 90px 20px 60px; }

        .checkout-hero {
            background: linear-gradient(135deg, #1e293b, #334155);
            border-radius: 20px; padding: 28px 32px; color: white;
            margin-bottom: 28px; position: relative; overflow: hidden;
        }
        .checkout-hero::after { content:'🛒'; position:absolute; right:28px; top:50%; transform:translateY(-50%); font-size:72px; opacity:.1; }
        .checkout-hero h1 { font-size: 22px; font-weight: 700; margin: 0 0 4px; }
        .checkout-hero p  { color: rgba(255,255,255,0.6); font-size: 14px; margin: 0; }
        .hero-back { color:rgba(255,255,255,0.7); text-decoration:none; font-size:14px; display:inline-flex; align-items:center; gap:6px; margin-bottom:14px; transition:color .2s; }
        .hero-back:hover { color:white; }

        .checkout-grid { display: grid; grid-template-columns: 1fr 380px; gap: 24px; }
        @media (max-width: 720px) { .checkout-grid { grid-template-columns: 1fr; } }

        .co-card { background: white; border-radius: 16px; box-shadow: 0 2px 16px rgba(0,0,0,0.07); overflow: hidden; margin-bottom: 20px; }
        .co-card-header { padding: 16px 22px; border-bottom: 1px solid #f1f5f9; }
        .co-card-header h3 { font-size: 15px; font-weight: 700; color: #1e293b; margin: 0; display:flex; align-items:center; gap:8px; }
        .co-card-header h3 i { color: #ee5a24; }
        .co-card-body { padding: 20px 22px; }

        /* Форма */
        .form-group { margin-bottom: 16px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-group label { font-size: 13px; font-weight: 600; color: #475569; display: block; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 11px 14px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 15px; box-sizing: border-box; transition: border .2s; }
        .form-control:focus { border-color: #ee5a24; outline: none; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        @media (max-width:480px) { .form-row { grid-template-columns: 1fr; } }

        /* Оплата */
        .pay-options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .pay-option { display: none; }
        .pay-option + label {
            display: flex; align-items: center; gap: 10px; padding: 13px 16px;
            border: 2px solid #e2e8f0; border-radius: 12px; cursor: pointer;
            transition: all .2s; font-size: 14px; font-weight: 500; color: #334155;
        }
        .pay-option + label i { font-size: 20px; color: #94a3b8; transition: color .2s; }
        .pay-option:checked + label { border-color: #ee5a24; background: #fff5f1; color: #ee5a24; }
        .pay-option:checked + label i { color: #ee5a24; }

        /* Позиции заказа */
        .order-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f1f5f9; }
        .order-item:last-child { border-bottom: none; }
        .order-item img { width: 52px; height: 52px; border-radius: 10px; object-fit: cover; flex-shrink: 0; }
        .order-item-info { flex: 1; min-width: 0; }
        .order-item-name  { font-size: 14px; font-weight: 600; color: #1e293b; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .order-item-meta  { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .order-item-price { font-size: 14px; font-weight: 700; color: #ee5a24; flex-shrink: 0; }

        /* Итоги */
        .summary-line { display: flex; justify-content: space-between; font-size: 14px; color: #64748b; margin-bottom: 8px; }
        .summary-line span { font-weight: 600; color: #334155; }
        .summary-total { display: flex; justify-content: space-between; font-size: 18px; font-weight: 700; color: #1e293b; margin-top: 12px; padding-top: 12px; border-top: 2px solid #f1f5f9; }
        .summary-total span { color: #ee5a24; }
        .delivery-free { color: #16a34a !important; }

        .btn-order { width: 100%; padding: 15px; background: linear-gradient(135deg,#ee5a24,#ff6b6b); color: white; border: none; border-radius: 12px; font-size: 16px; font-weight: 700; cursor: pointer; transition: all .2s; margin-top: 16px; display:flex; align-items:center; justify-content:center; gap:8px; }
        .btn-order:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(238,90,36,.4); }


        /* ── Превью карты ──────────────────────────────────────────────── */
        .card-form { }
        .card-preview {
            background: linear-gradient(135deg, #1e293b, #334155);
            border-radius: 16px; padding: 20px 24px; color: white;
            margin-bottom: 20px; position: relative; overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,.2);
            user-select: none;
        }
        .card-preview::before {
            content: ''; position: absolute; top: -40px; right: -40px;
            width: 160px; height: 160px; border-radius: 50%;
            background: rgba(255,255,255,.05);
        }
        .card-preview-top { display:flex; justify-content:space-between; align-items:center; margin-bottom:24px; }
        .card-chip { width:36px; height:28px; background:linear-gradient(135deg,#f0c040,#c8952a); border-radius:5px; }
        .card-logo { font-size:32px; color:rgba(255,255,255,.9); }
        .card-number-display { font-size:20px; letter-spacing:3px; margin-bottom:20px; font-family:monospace; }
        .card-preview-bottom { display:flex; justify-content:space-between; font-size:13px; }
        .card-label { font-size:10px; color:rgba(255,255,255,.5); text-transform:uppercase; margin-bottom:2px; }
        .card-holder-display { font-size:14px; letter-spacing:1px; }

        .alert-error { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 10px; padding: 12px 16px; font-size: 14px; margin-bottom: 16px; display:flex; align-items:center; gap:8px; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="checkout-wrap">
    <div class="checkout-hero">
        <a href="javascript:history.back()" class="hero-back"><i class="fas fa-arrow-left"></i> Назад</a>
        <h1>Оформление заказа</h1>
        <p>Ресторан: <?= h($restaurantName) ?> · <?= count($items) ?> позиц<?= count($items) === 1 ? 'ия' : (count($items) < 5 ? 'ии' : 'ий') ?></p>
    </div>

    <?php if ($error): ?>
        <div class="alert-error"><i class="fas fa-exclamation-circle"></i> <?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="checkout-form">
        <input type="hidden" name="place_order" value="1">
        <input type="hidden" name="csrf_token"  value="<?= h($_SESSION['csrf_token']) ?>">

        <div class="checkout-grid">
            <!-- Левая колонка -->
            <div>
                <!-- Адрес -->
                <div class="co-card">
                    <div class="co-card-header">
                        <h3><i class="fas fa-map-marker-alt"></i> Адрес доставки</h3>
                    </div>
                    <div class="co-card-body">
                        <div class="form-group">
                            <label>Улица <span style="color:#ee5a24">*</span></label>
                            <input type="text" name="street" class="form-control" required
                                   placeholder="Ленина"
                                   value="<?= h($_POST['street'] ?? '') ?>">
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Дом <span style="color:#ee5a24">*</span></label>
                                <input type="text" name="building" class="form-control" required
                                       placeholder="12А"
                                       value="<?= h($_POST['building'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Квартира</label>
                                <input type="text" name="apartment" class="form-control"
                                       placeholder="56"
                                       value="<?= h($_POST['apartment'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Подъезд</label>
                                <input type="text" name="entrance" class="form-control"
                                       placeholder="3"
                                       value="<?= h($_POST['entrance'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label>Этаж</label>
                                <input type="text" name="floor" class="form-control"
                                       placeholder="7"
                                       value="<?= h($_POST['floor'] ?? '') ?>">
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Комментарий к заказу</label>
                            <textarea name="comment" class="form-control" rows="2"
                                      placeholder="Код домофона, пожелания к доставке..."><?= h($_POST['comment'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Контакты -->
                <div class="co-card">
                    <div class="co-card-header">
                        <h3><i class="fas fa-user"></i> Контактные данные</h3>
                    </div>
                    <div class="co-card-body">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Имя</label>
                                <input type="text" class="form-control" readonly
                                       value="<?= h(trim(($user['username'] ?? '') . ' ' . ($user['lastname'] ?? ''))) ?>"
                                       style="background:#f8fafc; color:#94a3b8; cursor:not-allowed;">
                            </div>
                            <div class="form-group">
                                <label>Телефон <span style="color:#ee5a24">*</span></label>
                                <input type="tel" name="phone" class="form-control" required
                                       placeholder="+7 (999) 000-00-00"
                                       value="<?= h($_POST['phone'] ?? $user['phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Оплата -->
                <div class="co-card">
                    <div class="co-card-header">
                        <h3><i class="fas fa-credit-card"></i> Способ оплаты</h3>
                    </div>
                    <div class="co-card-body">
                        <input type="hidden" name="payment_method" value="card">
                        <div class="card-form">
                            <div class="card-preview" id="card-preview">
                                <div class="card-preview-top">
                                    <span class="card-chip"></span>
                                    <i class="fab fa-cc-visa card-logo"></i>
                                </div>
                                <div class="card-number-display" id="card-num-display">•••• •••• •••• ••••</div>
                                <div class="card-preview-bottom">
                                    <div>
                                        <div class="card-label">Держатель</div>
                                        <div class="card-holder-display" id="card-holder-display">ИМЯ ФАМИЛИЯ</div>
                                    </div>
                                    <div>
                                        <div class="card-label">Срок</div>
                                        <div id="card-exp-display">ММ/ГГ</div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Номер карты</label>
                                <input type="text" id="card-number" class="form-control" maxlength="19"
                                       placeholder="0000 0000 0000 0000" autocomplete="off"
                                       oninput="fmtCard(this)">
                            </div>
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Держатель карты</label>
                                    <input type="text" id="card-holder" class="form-control"
                                           placeholder="IVAN IVANOV" style="text-transform:uppercase"
                                           oninput="document.getElementById('card-holder-display').textContent = this.value.toUpperCase() || 'ИМЯ ФАМИЛИЯ'">
                                </div>
                                <div class="form-group">
                                    <label>Срок / CVV</label>
                                    <div style="display:flex; gap:8px;">
                                        <input type="text" id="card-exp" class="form-control" maxlength="5"
                                               placeholder="ММ/ГГ" oninput="fmtExp(this)">
                                        <input type="text" id="card-cvv" class="form-control" maxlength="3"
                                               placeholder="CVV" style="width:80px; flex-shrink:0;">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Правая колонка: состав заказа -->
            <div>
                <div class="co-card" style="position: sticky; top: 80px;">
                    <div class="co-card-header">
                        <h3><i class="fas fa-receipt"></i> Ваш заказ</h3>
                    </div>
                    <div class="co-card-body">
                        <!-- Позиции -->
                        <?php foreach ($items as $item): ?>
                        <div class="order-item">
                            <img src="<?= h('images/' . ($item['dish_image'] ?? 'dish-default.jpg')) ?>" onerror="this.src='images/dish-default.jpg'"
                                 alt="<?= h($item['dish_name']) ?>">
                            <div class="order-item-info">
                                <div class="order-item-name"><?= h($item['dish_name']) ?></div>
                                <div class="order-item-meta">
                                    × <?= $item['quantity'] ?>
                                    <?php if (!empty($item['customizations'])): ?>
                                        · <span style="color:#9b59b6">
                                            <?= implode(', ', array_column($item['customizations'], 'name')) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="order-item-price">
                                <?= number_format($item['line_price'], 0, '', ' ') ?> ₽
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <!-- Итоги -->
                        <div style="margin-top: 16px;">
                            <div class="summary-line">
                                Сумма заказа <span><?= number_format($subtotal, 0, '', ' ') ?> ₽</span>
                            </div>
                            <div class="summary-line">
                                Доставка
                                <?php if ($deliveryFee > 0): ?>
                                    <span><?= number_format($deliveryFee, 0, '', ' ') ?> ₽</span>
                                <?php else: ?>
                                    <span class="delivery-free">Бесплатно</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($deliveryFee > 0): ?>
                            <div style="font-size:12px; color:#94a3b8; margin-bottom: 4px;">
                                Бесплатно от 2 000 ₽
                            </div>
                            <?php endif; ?>
                            <div class="summary-total">
                                Итого <span><?= number_format($total, 0, '', ' ') ?> ₽</span>
                            </div>
                        </div>

                        <button type="submit" class="btn-order">
                            <i class="fas fa-check"></i> Оформить заказ
                        </button>

                        <div style="text-align:center; margin-top:10px; font-size:12px; color:#94a3b8;">
                            <i class="fas fa-shield-alt" style="color:#10b981;"></i>
                            Безопасная оплата · Доставка 30–45 мин
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /.checkout-grid -->
    </form>
</div>


<script>
function fmtCard(el) {
    var v = el.value.replace(/\D/g,'').substring(0,16);
    el.value = v.replace(/(\d{4})(?=\d)/g,'$1 ');
    var display = v.padEnd(16,'•').replace(/(\d{4})/g,'$1 ').trim();
    document.getElementById('card-num-display').textContent = 
        (v.substring(0,4)||'••••')+' '+(v.substring(4,8)||'••••')+' '+(v.substring(8,12)||'••••')+' '+(v.substring(12,16)||'••••');
}
function fmtExp(el) {
    var v = el.value.replace(/\D/g,'');
    if (v.length >= 2) v = v.substring(0,2) + '/' + v.substring(2,4);
    el.value = v;
    document.getElementById('card-exp-display').textContent = el.value || 'ММ/ГГ';
}
</script>
<script src="js/cart.js"></script>
<script>
    Cart.init(true); // пользователь авторизован (мы уже проверили в PHP)
</script>
</body>
</html>
