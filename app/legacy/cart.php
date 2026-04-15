<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Корзина — Курьер Экспресс</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f5f5f5; }
        .cart-container { max-width: 860px; margin: 90px auto 60px; padding: 0 20px; }
        .cart-top {
            background: linear-gradient(135deg, #ee5a24, #ff6b6b);
            color: white; padding: 20px 28px; border-radius: 16px 16px 0 0;
            display: flex; justify-content: space-between; align-items: center;
        }
        .cart-top h1 { font-size: 22px; font-weight: 700; margin: 0; }
        .cart-top a  { color: white; text-decoration: none; font-size: 14px; opacity: .85; }
        .cart-top a:hover { opacity: 1; }

        /* Загрузка */
        .cart-loading {
            background: white; padding: 60px 20px; text-align: center;
            border-radius: 0 0 16px 16px; box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }
        .cart-loading i { font-size: 40px; color: #ee5a24; margin-bottom: 16px; display: block; }

        /* Не авторизован */
        .cart-auth {
            background: white; padding: 60px 20px; text-align: center;
            border-radius: 0 0 16px 16px; box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }

        /* Пустая корзина */
        .empty-cart {
            text-align: center; padding: 80px 20px; background: white;
            border-radius: 0 0 16px 16px; box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }

        /* Позиции */
        .cart-items-wrap { background: white; box-shadow: 0 4px 20px rgba(0,0,0,.08); }
        .cart-item {
            display: flex; padding: 20px 24px;
            border-bottom: 1px solid #f0f0f0; align-items: flex-start; gap: 20px;
        }
        .cart-item:last-child { border-bottom: none; }
        .item-img {
            width: 90px; height: 90px; border-radius: 10px;
            object-fit: cover; flex-shrink: 0; background: #f0f0f0;
        }
        .item-info { flex: 1; min-width: 0; }
        .item-name { font-size: 17px; font-weight: 700; color: #1e293b; margin-bottom: 4px; }
        .item-restaurant { font-size: 13px; color: #ee5a24; margin-bottom: 6px; }
        .item-customs {
            margin-top: 6px; padding: 8px 12px;
            background: #faf5ff; border-radius: 8px; border-left: 3px solid #9b59b6;
            font-size: 12px; color: #555;
        }

        .item-price-block { text-align: right; flex-shrink: 0; }
        .item-price { font-size: 20px; font-weight: 700; color: #ee5a24; }

        .qty-row { display: flex; align-items: center; gap: 8px; justify-content: flex-end; margin-top: 8px; }
        .qty-btn {
            width: 30px; height: 30px; border: 2px solid #eee; background: white;
            border-radius: 50%; cursor: pointer; font-size: 16px; color: #333;
            display: flex; align-items: center; justify-content: center; transition: all 0.2s;
        }
        .qty-btn:hover { border-color: #ee5a24; color: #ee5a24; }
        .qty-val { font-size: 16px; font-weight: 700; min-width: 24px; text-align: center; }
        .remove-btn {
            background: none; border: none; color: #ccc; cursor: pointer;
            font-size: 18px; padding: 4px; transition: color 0.2s; margin-top: 6px; display: block;
        }
        .remove-btn:hover { color: #ef4444; }

        /* Итог */
        .cart-total {
            background: white; padding: 24px 28px; border-radius: 16px;
            margin-top: 16px; box-shadow: 0 4px 20px rgba(0,0,0,.08);
        }
        .total-row { display: flex; justify-content: space-between; font-size: 15px; color: #555; margin-bottom: 8px; }
        .total-row.grand { font-size: 22px; font-weight: 700; color: #1e293b; margin-top: 12px; padding-top: 12px; border-top: 2px solid #f0f0f0; }
        .delivery-free { color: #16a34a; font-weight: 600; }
        .delivery-hint { font-size: 13px; color: #888; margin-bottom: 16px; }
        .checkout-btn {
            background: linear-gradient(135deg, #ee5a24, #ff6b6b);
            color: white; border: none; padding: 16px; border-radius: 12px;
            font-size: 17px; font-weight: 700; width: 100%; cursor: pointer; transition: all 0.3s;
        }
        .checkout-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(238,90,36,.4); }
        .clear-btn {
            background: none; border: 2px solid #eee; color: #999;
            padding: 10px 20px; border-radius: 10px; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: all 0.2s; margin-top: 10px; width: 100%;
        }
        .clear-btn:hover { border-color: #ef4444; color: #ef4444; }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="cart-container">
    <div class="cart-top">
        <h1><i class="fas fa-shopping-cart" style="margin-right:10px;"></i>Корзина</h1>
        <a href="index.php">← Продолжить покупки</a>
    </div>

    <div id="cart-content">
        <div class="cart-loading">
            <i class="fas fa-spinner fa-spin"></i>
            <p style="color:#888;">Загружаем корзину...</p>
        </div>
    </div>
</div>

<script src="js/cart.js"></script>
<script>
// Инициализируем Cart — он сам загрузит данные с сервера
Cart.init(<?= isset($_SESSION['user_id']) ? 'true' : 'false' ?>);

// Ждём загрузки и рендерим
document.addEventListener('DOMContentLoaded', function() {
    // Даём Cart время загрузить данные с сервера
    setTimeout(renderCartPage, 600);
});

function renderCartPage() {
    var state = Cart.getState();
    var container = document.getElementById('cart-content');

    <?php if (!isset($_SESSION['user_id'])): ?>
    // Не авторизован
    container.innerHTML =
        '<div class="cart-auth">' +
            '<div style="font-size:64px;margin-bottom:16px;">🔐</div>' +
            '<h2 style="color:#1e293b;margin-bottom:8px;">Войдите в аккаунт</h2>' +
            '<p style="color:#888;margin-bottom:24px;">Чтобы видеть корзину, необходимо авторизоваться</p>' +
            '<a href="login.php" style="display:inline-block;padding:12px 28px;background:#ee5a24;color:white;text-decoration:none;border-radius:10px;font-weight:600;">Войти</a>' +
        '</div>';
    return;
    <?php endif; ?>

    if (state.items.length === 0) {
        container.innerHTML =
            '<div class="empty-cart">' +
                '<div style="font-size:64px;margin-bottom:16px;">🛒</div>' +
                '<h2 style="color:#1e293b;margin-bottom:8px;">Корзина пуста</h2>' +
                '<p style="color:#888;margin-bottom:24px;">Добавьте блюда из меню</p>' +
                '<a href="index.php" style="display:inline-block;padding:12px 28px;background:#ee5a24;color:white;text-decoration:none;border-radius:10px;font-weight:600;">Перейти в меню</a>' +
            '</div>';
        return;
    }

    var itemsHtml = state.items.map(function(item) {
        var img = item.dish_image ? 'images/' + esc(item.dish_image) : 'images/dish-default.jpg';
        var customsHtml = '';
        if (item.customizations && item.customizations.length) {
            customsHtml = '<div class="item-customs"><i class="fas fa-sliders-h" style="color:#9b59b6;margin-right:4px;"></i>' +
                item.customizations.map(function(c) { return esc(c.name); }).join(', ') +
                '</div>';
        }
        return '<div class="cart-item">' +
            '<img src="' + img + '" class="item-img" onerror="this.src=\'images/dish-default.jpg\'">' +
            '<div class="item-info">' +
                '<div class="item-name">' + esc(item.dish_name) + '</div>' +
                '<div class="item-restaurant"><i class="fas fa-store" style="margin-right:4px;"></i>' + esc(item.restaurant_name) + '</div>' +
                customsHtml +
            '</div>' +
            '<div class="item-price-block">' +
                '<div class="item-price">' + fmt(item.line_price) + ' ₽</div>' +
                '<div class="qty-row">' +
                    '<button class="qty-btn" onclick="changeQty(' + item.id + ', ' + (item.quantity - 1) + ')">−</button>' +
                    '<span class="qty-val">' + item.quantity + '</span>' +
                    '<button class="qty-btn" onclick="changeQty(' + item.id + ', ' + (item.quantity + 1) + ')">+</button>' +
                '</div>' +
                '<button class="remove-btn" onclick="removeItem(' + item.id + ')" title="Удалить"><i class="fas fa-trash-alt"></i></button>' +
            '</div>' +
        '</div>';
    }).join('');

    var delivery     = state.delivery_fee;
    var deliveryHtml = delivery === 0
        ? '<span class="delivery-free"><i class="fas fa-check-circle"></i> Бесплатно</span>'
        : '<span style="color:#ee5a24;font-weight:600;">' + fmt(delivery) + ' ₽</span>';
    var hintHtml = delivery > 0
        ? '<div class="delivery-hint">До бесплатной доставки осталось ' + fmt(2000 - state.subtotal) + ' ₽</div>'
        : '<div class="delivery-hint" style="color:#16a34a;">🎉 У вас бесплатная доставка!</div>';

    container.innerHTML =
        '<div class="cart-items-wrap">' + itemsHtml + '</div>' +
        '<div class="cart-total">' +
            '<div class="total-row"><span>Сумма заказа</span><span>' + fmt(state.subtotal) + ' ₽</span></div>' +
            '<div class="total-row"><span>Доставка</span>' + deliveryHtml + '</div>' +
            hintHtml +
            '<div class="total-row grand"><span>Итого</span><span>' + fmt(state.total) + ' ₽</span></div>' +
            '<button class="checkout-btn" onclick="window.location.href=\'checkout.php\'" style="margin-top:16px;">' +
                '<i class="fas fa-shopping-bag" style="margin-right:8px;"></i>Оформить заказ' +
            '</button>' +
            '<button class="clear-btn" onclick="clearAll()"><i class="fas fa-trash" style="margin-right:6px;"></i>Очистить корзину</button>' +
        '</div>';
}

function changeQty(cartId, newQty) {
    Cart.updateItem(cartId, newQty).then(function() {
        renderCartPage();
    });
}

function removeItem(cartId) {
    Cart.removeItem(cartId).then(function() {
        renderCartPage();
    });
}

function clearAll() {
    if (!confirm('Очистить всю корзину?')) return;
    Cart.clearCart().then(function() {
        renderCartPage();
    });
}

function fmt(n) { return Number(n).toLocaleString('ru-RU', { maximumFractionDigits: 0 }); }
function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
