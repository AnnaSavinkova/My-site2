<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';

// Рестораны
$restaurants = [];
try {
    $stmt = $pdo->query("SELECT id, name, description, logo_url FROM restaurants ORDER BY name");
    $restaurants = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 3 популярных блюда (случайных активных)
$popular_dishes = [];
try {
    $stmt = $pdo->query("
        SELECT d.id, d.name, d.description, d.price, d.image, d.customizable,
               d.restaurant_id, r.name AS restaurant_name
        FROM dishes d
        JOIN restaurants r ON d.restaurant_id = r.id
        WHERE d.is_active = 1
        ORDER BY RAND() LIMIT 3
    ");
    $popular_dishes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Курьер Экспресс — Доставка еды</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to   { transform: translateX(0);    opacity: 1; }
        }
        .restaurant-section { margin: 50px 0; }
        .restaurant-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 30px; margin-top: 30px;
        }
        .restaurant-card {
            background: white; border-radius: 20px; overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: all 0.3s; cursor: pointer; border: 2px solid transparent;
        }
        .restaurant-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border-color: #ee5a24;
        }
        .restaurant-img { width: 100%; height: 220px; object-fit: cover; transition: transform 0.3s; }
        .restaurant-card:hover .restaurant-img { transform: scale(1.04); }
        .restaurant-info { padding: 25px; }
        .restaurant-name { font-size: 22px; font-weight: 700; margin-bottom: 10px; color: #333; }
        .restaurant-description {
            color: #666; font-size: 14px; line-height: 1.5; margin-bottom: 15px;
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .restaurant-tags { display: flex; gap: 8px; flex-wrap: wrap; }
        .tag {
            background: #f8f9fa; padding: 6px 14px; border-radius: 20px;
            font-size: 13px; color: #666; font-weight: 500; transition: all 0.2s;
        }
        .restaurant-card:hover .tag { background: #fff0eb; color: #ee5a24; }

        /* Поиск */
        .search-container { margin: 30px auto; max-width: 600px; }
        .search-box { position: relative; }
        .search-input {
            width: 100%; padding: 15px 55px 15px 20px;
            border: 2px solid #eee; border-radius: 30px; font-size: 16px;
            box-sizing: border-box; transition: border 0.2s;
        }
        .search-input:focus { border-color: #ee5a24; outline: none; }
        .search-btn {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: #ee5a24; color: white; border: none;
            width: 40px; height: 40px; border-radius: 50%; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
        }
        .search-clear {
            position: absolute; right: 56px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #aaa; cursor: pointer;
            font-size: 18px; display: none; padding: 4px 8px;
        }

        /* Результаты поиска */
        #search-results-section { display: none; margin-bottom: 40px; }
        #search-results-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px; margin-top: 20px;
        }

        /* ═══════════════════════════════════════════════
           КАРТОЧКИ БЛЮД — исправленный flex-layout
           ═══════════════════════════════════════════════ */
        .dishes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 50px;
            /* Выравниваем строки по высоте */
            align-items: stretch;
        }

        .dish-card {
            position: relative;
            background: white;
            border-radius: 16px;
            overflow: hidden;               /* оставляем для скругления */
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;         /* главный фикс: карточка — flex-колонка */
        }
        .dish-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,0.13); }

        .dish-img { width: 100%; height: 180px; object-fit: cover; display: block; flex-shrink: 0; }

        .dish-body {
            padding: 16px;
            display: flex;
            flex-direction: column;
            flex: 1;                        /* занимает оставшееся место */
        }

        .dish-rest-tag {
            font-size: 12px; color: #ee5a24; font-weight: 600;
            margin-bottom: 6px; display: flex; align-items: center; gap: 5px;
        }
        .dish-name {
            font-size: 16px; font-weight: 700; color: #333;
            margin-bottom: 6px; line-height: 1.3;
        }
        .dish-description {
            font-size: 13px; color: #888; line-height: 1.4; margin-bottom: 12px;
            flex: 1;                        /* описание растягивается, выравнивая кнопки */
            display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
        }
        .dish-footer {
            display: flex; flex-direction: column; gap: 10px;
            margin-top: auto;               /* кнопка всегда внизу */
        }
        .dish-price { font-size: 22px; font-weight: 700; color: #ee5a24; line-height: 1; }

        .btn-add {
            background: linear-gradient(135deg, #ee5a24, #ff6b6b); color: white; border: none;
            padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: all 0.2s; width: 100%;
        }
        .btn-add:hover { opacity: .9; box-shadow: 0 4px 12px rgba(238,90,36,.35); }

        /* Плашки — поверх картинки, z-index чтобы не скрывались */
        .badge-pop {
            position: absolute; top: 12px; right: 12px;
            z-index: 10;                    /* FIX: плашка поверх изображения */
            background: linear-gradient(135deg, #ee5a24, #ff6b6b);
            color: white; padding: 5px 11px; border-radius: 15px;
            font-size: 11px; font-weight: 700;
            pointer-events: none;
        }
        .badge-custom {
            position: absolute; top: 12px; left: 12px;
            z-index: 10;                    /* FIX: плашка поверх изображения */
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white; padding: 5px 11px; border-radius: 15px;
            font-size: 11px; font-weight: 700;
            pointer-events: none;
        }

        .simple-footer {
            text-align: center; padding: 30px; color: #888;
            font-size: 14px; margin-top: 60px; border-top: 1px solid #eee;
        }
    </style>
</head>
<body>
    <?php include 'header.php'; ?>

    <!-- Герой -->
    <section class="hero">
        <div class="container">
            <h2>Вкусная еда с доставкой за 30 минут</h2>
            <p>Выбирайте из лучших ресторанов города. Бесплатная доставка от 2000₽</p>
        </div>
    </section>

    <div class="container">

        <!-- Поиск -->
        <div class="search-container">
            <div class="search-box">
                <input type="text" id="search-input" class="search-input"
                       placeholder="Поиск блюд по всем ресторанам...">
                <button class="search-clear" id="search-clear" onclick="clearSearch()">✕</button>
                <button class="search-btn" id="search-btn">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </div>

        <!-- Блок результатов поиска -->
        <div id="search-results-section">
            <h2 class="section-title">
                Результаты поиска: «<span id="search-query-label"></span>»
                <span id="search-count" style="font-size:16px; color:#888; font-weight:400;"></span>
            </h2>
            <div id="search-results-grid"></div>
        </div>

        <!-- Рестораны + Популярные блюда (скрываются при поиске) -->
        <div id="main-content">
            <div class="restaurant-section">
                <h2 class="section-title">Наши рестораны</h2>
                <div class="restaurant-cards">
                    <?php if (empty($restaurants)): ?>
                        <div style="grid-column:1/-1; text-align:center; padding:40px; color:#888;">
                            <i class="fas fa-store" style="font-size:48px; color:#ddd; display:block; margin-bottom:15px;"></i>
                            Рестораны не найдены
                        </div>
                    <?php else: ?>
                        <?php foreach ($restaurants as $r): ?>
                            <div class="restaurant-card"
                                 onclick="window.location.href='restaurant.php?id=<?= (int)$r['id'] ?>'">
                                <img src="images/<?= htmlspecialchars($r['logo_url'] ?: 'restaurant-default.jpg') ?>"
                                     alt="<?= htmlspecialchars($r['name']) ?>"
                                     class="restaurant-img"
                                     onerror="this.src='images/restaurant-default.jpg'">
                                <div class="restaurant-info">
                                    <h3 class="restaurant-name"><?= htmlspecialchars($r['name']) ?></h3>
                                    <p class="restaurant-description">
                                        <?= htmlspecialchars($r['description'] ?: 'Вкусная еда с доставкой') ?>
                                    </p>
                                    <div class="restaurant-tags">
                                        <span class="tag">
                                            <?php if ($r['id'] == 1): ?>🍕 Пицца
                                            <?php elseif ($r['id'] == 2): ?>🥢 Азиатская
                                            <?php else: ?>🍽️ Ресторан<?php endif; ?>
                                        </span>
                                        <span class="tag">⭐ 4.8</span>
                                        <span class="tag">⏱ 30–45 мин</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Популярные блюда — правильная структура карточки -->
            <h2 class="section-title">Популярные блюда</h2>
            <div class="dishes-grid">
                <?php if (empty($popular_dishes)): ?>
                    <div style="grid-column:1/-1; text-align:center; padding:40px; color:#888;">
                        Блюда не найдены
                    </div>
                <?php else: ?>
                    <?php foreach ($popular_dishes as $dish): ?>
                        <div class="dish-card">
                            <!-- Плашки ВНУТРИ карточки, ДО изображения — z-index:10 обеспечивает видимость -->
                            <span class="badge-pop"><i class="fas fa-fire"></i> Популярное</span>
                            <?php if ($dish['customizable']): ?>
                                <span class="badge-custom"><i class="fas fa-sliders-h"></i> Конструктор</span>
                            <?php endif; ?>
                            <img src="images/<?= htmlspecialchars($dish['image'] ?: 'dish-default.jpg') ?>"
                                 alt="<?= htmlspecialchars($dish['name']) ?>"
                                 class="dish-img"
                                 onerror="this.src='images/dish-default.jpg'">
                            <div class="dish-body">
                                <div class="dish-rest-tag">
                                    <i class="fas fa-store"></i>
                                    <?= htmlspecialchars($dish['restaurant_name']) ?>
                                </div>
                                <div class="dish-name"><?= htmlspecialchars($dish['name']) ?></div>
                                <div class="dish-description"><?= htmlspecialchars($dish['description'] ?? '') ?></div>
                                <div class="dish-footer">
                                    <!-- FIX: цена с рублём прямо в PHP, без ::after -->
                                    <div class="dish-price"><?= number_format($dish['price'], 0, '', ' ') ?> ₽</div>
                                    <button class="btn-add" onclick="Cart.addItem(<?= $dish['id'] ?>)">
                                        <i class="fas fa-cart-plus"></i> В корзину
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div><!-- /#main-content -->

    </div><!-- /.container -->

    <footer class="simple-footer">© 2026 Курьер Экспресс. Все права защищены.</footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var inp   = document.getElementById('search-input');
        var btn   = document.getElementById('search-btn');
        var clear = document.getElementById('search-clear');

        btn.addEventListener('click', doSearch);

        inp.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') doSearch();
        });

        var timer;
        inp.addEventListener('input', function() {
            clear.style.display = this.value ? 'block' : 'none';
            clearTimeout(timer);
            var q = this.value.trim();
            if (q.length === 0) { clearSearch(); return; }
            if (q.length < 2)   return;
            timer = setTimeout(doSearch, 400);
        });
    });

    function doSearch() {
        var q = document.getElementById('search-input').value.trim();
        if (!q) { clearSearch(); return; }

        document.getElementById('search-query-label').textContent = q;
        document.getElementById('search-results-grid').innerHTML  =
            '<div style="grid-column:1/-1; text-align:center; padding:30px; color:#888;">' +
            '<i class="fas fa-spinner fa-spin" style="font-size:32px; margin-bottom:12px; display:block;"></i>Ищем...</div>';
        document.getElementById('search-results-section').style.display = 'block';
        document.getElementById('main-content').style.display           = 'none';

        fetch('get_dishes.php?search=' + encodeURIComponent(q))
            .then(function(r) { return r.json(); })
            .then(function(dishes) { renderSearchResults(dishes, q); })
            .catch(function() {
                document.getElementById('search-results-grid').innerHTML =
                    '<div style="grid-column:1/-1; text-align:center; padding:30px; color:#e74c3c;">Ошибка поиска</div>';
            });
    }

    function renderSearchResults(dishes, q) {
        var grid  = document.getElementById('search-results-grid');
        var count = document.getElementById('search-count');

        if (!dishes.length) {
            count.textContent = '';
            grid.innerHTML =
                '<div style="grid-column:1/-1; text-align:center; padding:50px; color:#888;">' +
                '<i class="fas fa-search" style="font-size:48px; opacity:.3; display:block; margin-bottom:15px;"></i>' +
                '<h3>Ничего не найдено</h3><p>Попробуйте другой запрос</p></div>';
            return;
        }

        count.textContent = '— ' + dishes.length + ' блюд';
        grid.innerHTML = dishes.map(function(d) {
            return '<div class="dish-card">' +
                (d.customizable ? '<span class="badge-custom"><i class="fas fa-sliders-h"></i> Конструктор</span>' : '') +
                '<img src="images/' + esc(d.image || 'dish-default.jpg') + '"' +
                     ' alt="' + esc(d.name) + '" class="dish-img"' +
                     ' onerror="this.src=\'images/dish-default.jpg\'">' +
                '<div class="dish-body">' +
                    '<div class="dish-rest-tag"><i class="fas fa-store"></i> ' + esc(d.restaurant_name) + '</div>' +
                    '<div class="dish-name">' + esc(d.name) + '</div>' +
                    '<div class="dish-description">' + esc(d.description || '') + '</div>' +
                    '<div class="dish-footer">' +
                    '<div class="dish-price">' + parseFloat(d.price).toFixed(0) + ' ₽</div>' +
                        '<button class="btn-add" onclick="Cart.addItem(' + d.id + ')">' +
                            '<i class="fas fa-cart-plus"></i> В корзину</button>' +
                    '</div>' +
                '</div></div>';
        }).join('');
    }

    function clearSearch() {
        document.getElementById('search-input').value              = '';
        document.getElementById('search-clear').style.display      = 'none';
        document.getElementById('search-results-section').style.display = 'none';
        document.getElementById('main-content').style.display      = 'block';
    }

    function esc(str) {
        return String(str || '')
            .replace(/&/g,'&amp;').replace(/</g,'&lt;')
            .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    </script>
</body>
</html>
