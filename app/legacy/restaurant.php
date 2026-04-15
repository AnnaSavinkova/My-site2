<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$restaurant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$category_id   = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;

if ($restaurant_id <= 0) { header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT * FROM restaurants WHERE id = ?");
$stmt->execute([$restaurant_id]);
$restaurant = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$restaurant) { header('Location: index.php'); exit; }

$cats = $pdo->prepare("SELECT id, name FROM categories WHERE restaurant_id = ? ORDER BY sort_order, name");
$cats->execute([$restaurant_id]);
$categories = $cats->fetchAll(PDO::FETCH_ASSOC);

$sql    = "SELECT * FROM dishes WHERE restaurant_id = ? AND is_active = 1";
$params = [$restaurant_id];
if ($category_id > 0) { $sql .= " AND category_id = ?"; $params[] = $category_id; }
$sql .= " ORDER BY name";
$dstmt = $pdo->prepare($sql);
$dstmt->execute($params);
$dishes = $dstmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($restaurant['name']) ?> — Курьер Экспресс</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f5f5f5; }
        .page-wrap { max-width: 1200px; margin: 0 auto; padding: 90px 20px 60px; }

        .rest-header {
            background: white; border-radius: 16px; overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.09); margin-bottom: 28px; display: flex;
        }
        .rest-header-img { width: 260px; min-height: 160px; object-fit: cover; flex-shrink: 0; }
        .rest-header-info { padding: 28px 30px; display: flex; flex-direction: column; justify-content: center; }
        .rest-header-info h1 { font-size: 28px; font-weight: 700; color: #1e293b; margin-bottom: 8px; }
        .rest-header-info p  { color: #666; font-size: 15px; line-height: 1.5; margin-bottom: 14px; }
        .rest-meta { display: flex; gap: 12px; flex-wrap: wrap; }
        .rest-meta span { background: #f8f9fa; padding: 6px 14px; border-radius: 20px; font-size: 13px; color: #555; }

        .menu-search { position: relative; margin-bottom: 22px; }
        .menu-search input {
            width: 100%; padding: 13px 50px 13px 18px; border: 2px solid #eee;
            border-radius: 30px; font-size: 15px; box-sizing: border-box; background: white; transition: border 0.2s;
        }
        .menu-search input:focus { border-color: #ee5a24; outline: none; }
        .menu-search .s-btn {
            position: absolute; right: 8px; top: 50%; transform: translateY(-50%);
            background: #ee5a24; color: white; border: none; width: 38px; height: 38px;
            border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center;
        }
        .menu-search .s-clear {
            position: absolute; right: 54px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #aaa; cursor: pointer; font-size: 18px; display: none; padding: 4px 8px;
        }

        .cat-filters { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 28px; }
        .cat-btn {
            padding: 9px 20px; background: white; border: 2px solid #eee; border-radius: 25px;
            cursor: pointer; font-weight: 500; font-size: 14px; color: #555;
            text-decoration: none; transition: all 0.2s; display: inline-block;
        }
        .cat-btn:hover  { border-color: #ee5a24; color: #ee5a24; }
        .cat-btn.active { background: #ee5a24; border-color: #ee5a24; color: white; }

        /* ═══════════════════════════════════════════════
           GRID — выровненные карточки
           ═══════════════════════════════════════════════ */
        .dishes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 20px;
            align-items: stretch;    /* все карточки одной высоты в строке */
        }

        .dish-card {
            position: relative; background: white; border-radius: 16px; overflow: hidden;
            box-shadow: 0 3px 12px rgba(0,0,0,.08); transition: all 0.25s;
            display: flex; flex-direction: column;
        }
        .dish-card::before {
            content: '';
            position: absolute; top: 0; left: 0; right: 0; height: 4px;
            background: linear-gradient(90deg, #ee5a24, #ff6b6b);
            z-index: 5;
        }
        .dish-card:hover { transform: translateY(-4px); box-shadow: 0 10px 25px rgba(0,0,0,.13); }
        .dish-card.hidden { display: none; }

        .dish-img { width: 100%; height: 180px; object-fit: contain; display: block; flex-shrink: 0; background: white; padding: 8px; }

        .dish-body {
            padding: 16px; display: flex; flex-direction: column; flex: 1;
        }
        .dish-name { font-size: 16px; font-weight: 700; color: #1e293b; margin-bottom: 6px; line-height: 1.3; }
        .dish-desc {
            font-size: 13px; color: #888; line-height: 1.5; margin-bottom: 12px;
            min-height: 40px;
            display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
        }

        /* Цена и кнопка — отдельный блок внизу */
        .dish-footer { display: flex; flex-direction: column; gap: 10px; margin-top: auto; }

        /* FIX: цена с рублём — убрали ::after: none, рубль выводится в PHP */
        .dish-price { font-size: 22px; font-weight: 700; color: #ee5a24; line-height: 1; }

        .btn-add {
            background: linear-gradient(135deg, #ee5a24, #ff6b6b); color: white; border: none;
            padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: all 0.2s; width: 100%;
        }
        .btn-add:hover { opacity: .9; box-shadow: 0 4px 12px rgba(238,90,36,.35); }
        .btn-constructor {
            background: linear-gradient(135deg, #9b59b6, #8e44ad); color: white; border: none;
            padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 600;
            cursor: pointer; transition: all 0.2s; width: 100%;
        }
        .btn-constructor:hover { opacity: .9; box-shadow: 0 4px 12px rgba(142,68,173,.4); }

        /* FIX: плашка поверх картинки — z-index:10 */
        .badge-custom {
            position: absolute; top: 12px; left: 12px;
            z-index: 10;
            background: linear-gradient(135deg, #9b59b6, #8e44ad);
            color: white; padding: 5px 11px; border-radius: 14px; font-size: 11px; font-weight: 700;
            pointer-events: none;
        }

        .empty-state { grid-column: 1/-1; text-align: center; padding: 60px 20px; color: #aaa; }
        .empty-state i { font-size: 52px; display: block; margin-bottom: 16px; opacity: .4; }
        #search-notice {
            display: none; background: #fff5f0; border-radius: 10px;
            padding: 10px 18px; margin-bottom: 18px; font-size: 14px; color: #ee5a24; font-weight: 500;
        }

        /* ══ КОНСТРУКТОР ═════════════════════════════════════════════════════ */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
            z-index: 3000; justify-content: center; align-items: center; padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .constructor-modal {
            background: white; border-radius: 20px; width: 100%; max-width: 820px;
            max-height: 90vh; overflow: hidden; display: flex; flex-direction: column;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
        }
        .cm-header {
            background: linear-gradient(135deg, #1e293b, #334155); color: white;
            padding: 20px 28px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;
        }
        .cm-header h3 { font-size: 20px; font-weight: 700; margin: 0; }
        .cm-close {
            background: rgba(255,255,255,0.15); border: none; color: white;
            width: 36px; height: 36px; border-radius: 50%; cursor: pointer; font-size: 18px;
            display: flex; align-items: center; justify-content: center; transition: background 0.2s;
        }
        .cm-close:hover { background: rgba(255,255,255,0.3); }
        .cm-body { display: flex; overflow: hidden; flex: 1; }
        .cm-left {
            width: 280px; flex-shrink: 0; border-right: 1px solid #f0f0f0;
            overflow-y: auto; padding: 24px;
        }
        .cm-dish-img { width: 100%; height: 180px; object-fit: cover; border-radius: 12px; margin-bottom: 16px; }
        .cm-dish-name { font-size: 18px; font-weight: 700; color: #1e293b; margin-bottom: 6px; }
        .cm-dish-rest { font-size: 13px; color: #ee5a24; font-weight: 600; margin-bottom: 10px; }
        .cm-dish-desc { font-size: 13px; color: #888; line-height: 1.5; margin-bottom: 16px; }
        .cm-base-title {
            font-size: 12px; font-weight: 700; color: #555; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 10px;
        }
        .cm-base-list { display: flex; flex-wrap: wrap; gap: 6px; }
        .cm-base-tag {
            background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534;
            padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 500;
        }
        .cm-base-tag.removing {
            background: #fef2f2; border-color: #fecaca; color: #991b1b;
            text-decoration: line-through; opacity: 0.6;
        }
        .cm-right { flex: 1; overflow-y: auto; padding: 24px; }
        .cm-right-title { font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 16px; }
        .ing-category { margin-bottom: 20px; }
        .ing-cat-name {
            font-size: 12px; font-weight: 700; color: #94a3b8; text-transform: uppercase;
            letter-spacing: 0.5px; margin-bottom: 10px; padding-bottom: 6px; border-bottom: 1px solid #f0f0f0;
        }
        .ing-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .ing-item {
            display: flex; align-items: center; gap: 10px; padding: 10px 12px;
            border: 2px solid #f0f0f0; border-radius: 10px; cursor: pointer;
            transition: all 0.2s; user-select: none;
        }
        .ing-item:hover  { border-color: #9b59b6; background: #faf5ff; }
        .ing-item.checked { border-color: #9b59b6; background: #faf5ff; }
        .ing-check {
            width: 20px; height: 20px; border: 2px solid #d0d0d0; border-radius: 5px;
            flex-shrink: 0; display: flex; align-items: center; justify-content: center;
            transition: all 0.2s; font-size: 12px;
        }
        .ing-item.checked .ing-check { background: #9b59b6; border-color: #9b59b6; color: white; }
        .ing-info { flex: 1; min-width: 0; }
        .ing-name { font-size: 13px; font-weight: 600; color: #333; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ing-price { font-size: 12px; color: #9b59b6; font-weight: 600; }
        .cm-footer {
            border-top: 2px solid #f0f0f0; padding: 18px 28px;
            display: flex; justify-content: space-between; align-items: center;
            background: #fafafa; flex-shrink: 0;
        }
        .cm-price-base  { font-size: 13px; color: #888; }
        .cm-price-extra { font-size: 13px; color: #9b59b6; font-weight: 600; }
        .cm-price-total { font-size: 26px; font-weight: 700; color: #ee5a24; margin-top: 2px; }
        .cm-add-btn {
            background: linear-gradient(135deg, #ee5a24, #ff6b6b); color: white; border: none;
            padding: 14px 32px; border-radius: 12px; font-size: 16px; font-weight: 700;
            cursor: pointer; transition: all 0.3s;
        }
        .cm-add-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(238,90,36,.4); }
        .cm-loading {
            display: flex; align-items: center; justify-content: center;
            height: 200px; width: 100%; color: #888; font-size: 16px; gap: 12px;
        }
        .cm-loading i { font-size: 28px; color: #9b59b6; }

        @media (max-width: 700px) {
            .rest-header { flex-direction: column; }
            .rest-header-img { width: 100%; height: 180px; }
            .cm-body { flex-direction: column; }
            .cm-left { width: 100%; border-right: none; border-bottom: 1px solid #f0f0f0; }
            .ing-grid { grid-template-columns: 1fr; }
        }

        /* ══ АДАПТИВ ══════════════════════════════════════════════════════ */
        @media (max-width: 768px) {
            .rest-header { flex-direction: column; }
            .rest-header-img { width: 100%; height: 200px; }
            .rest-header-info { padding: 20px; }
            .dishes-grid { grid-template-columns: repeat(2, 1fr); gap: 14px; }
        }
        @media (max-width: 480px) {
            .page-wrap { padding: 80px 12px 40px; }
            .rest-header-img { height: 160px; }
            .rest-header-info h1 { font-size: 20px; }
            .rest-meta span { font-size: 12px; padding: 4px 10px; }
            .dishes-grid { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .dish-img { height: 130px; }
            .dish-body { padding: 10px; }
            .dish-name { font-size: 13px; }
            .dish-price { font-size: 18px; }
            .btn-add, .btn-constructor { padding: 10px 6px; font-size: 12px; }
            .cat-btn { padding: 7px 12px; font-size: 12px; }
            .cm-body { flex-direction: column; }
            .cm-left { width: 100%; border-right: none; border-bottom: 1px solid #f0f0f0; }
            .ing-grid { grid-template-columns: 1fr; }
            .cm-footer { flex-direction: column; gap: 10px; }
            .cm-add-btn { width: 100%; }
        }
        @media (max-width: 360px) {
            .dishes-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="page-wrap">
    <a href="index.php" style="color:#ee5a24; text-decoration:none; font-weight:500; display:inline-flex; align-items:center; gap:6px; margin-bottom:18px;">
        <i class="fas fa-arrow-left"></i> Назад к ресторанам
    </a>

    <div class="rest-header">
        <img src="images/<?= h($restaurant['logo_url'] ?: 'restaurant-default.jpg') ?>"
             class="rest-header-img" onerror="this.src='images/restaurant-default.jpg'"
             alt="<?= h($restaurant['name']) ?>">
        <div class="rest-header-info">
            <h1><?= h($restaurant['name']) ?></h1>
            <p><?= h($restaurant['description'] ?: 'Вкусная еда с доставкой') ?></p>
            <div class="rest-meta">
                <span>⭐ 4.8</span>
                <span>⏱ 30–45 мин</span>
                <span>🚚 от 2000₽ бесплатно</span>
                <span><?= count($dishes) ?> блюд</span>
                <?php if ($restaurant_id == 1): ?>
                    <span style="background:#faf5ff;color:#9b59b6;border:1px solid #e9d5ff;">🍕 Конструктор пиццы</span>
                <?php elseif ($restaurant_id == 2): ?>
                    <span style="background:#faf5ff;color:#9b59b6;border:1px solid #e9d5ff;">🥢 Конструктор вока</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="menu-search">
        <input type="text" id="menu-search" placeholder="Поиск по меню <?= h($restaurant['name']) ?>...">
        <button class="s-clear" id="s-clear" onclick="clearMenuSearch()">✕</button>
        <button class="s-btn"><i class="fas fa-search"></i></button>
    </div>
    <div id="search-notice"></div>

    <?php if (!empty($categories)): ?>
    <div class="cat-filters">
        <a href="restaurant.php?id=<?= $restaurant_id ?>" class="cat-btn <?= $category_id === 0 ? 'active' : '' ?>">
            <i class="fas fa-utensils"></i> Все
        </a>
        <?php foreach ($categories as $c): ?>
            <a href="restaurant.php?id=<?= $restaurant_id ?>&cat=<?= $c['id'] ?>"
               class="cat-btn <?= $category_id === (int)$c['id'] ? 'active' : '' ?>">
                <?= h($c['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="dishes-grid" id="dishes-grid">
        <?php if (empty($dishes)): ?>
            <div class="empty-state"><i class="fas fa-utensils"></i><h3>Блюд пока нет</h3></div>
        <?php else: ?>
            <?php foreach ($dishes as $dish): ?>
                <div class="dish-card"
                     data-name="<?= h(mb_strtolower($dish['name'])) ?>"
                     data-desc="<?= h(mb_strtolower($dish['description'] ?? '')) ?>">
                    <?php if ($dish['customizable']): ?>
                        <span class="badge-custom"><i class="fas fa-sliders-h"></i> Конструктор</span>
                    <?php endif; ?>
                    <img src="images/<?= h($dish['image'] ?: 'dish-default.jpg') ?>"
                         alt="<?= h($dish['name']) ?>" class="dish-img"
                         onerror="this.src='images/dish-default.jpg'">
                    <div class="dish-body">
                        <div class="dish-name"><?= h($dish['name']) ?></div>
                        <div class="dish-desc"><?= h($dish['description'] ?? '') ?></div>
                        <div class="dish-footer">
                            <!-- FIX: ₽ выводится в PHP, не через ::after псевдоэлемент -->
                            <div class="dish-price"><?= number_format($dish['price'], 0, '', ' ') ?> ₽</div>
                            <?php if ($dish['customizable']): ?>
                                <button class="btn-constructor" onclick="openConstructor(<?= $dish['id'] ?>)">
                                    <i class="fas fa-sliders-h"></i> Собрать
                                </button>
                            <?php else: ?>
                                <button class="btn-add" onclick="Cart.addItem(<?= $dish['id'] ?>)">
                                    <i class="fas fa-shopping-cart"></i> В корзину
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="empty-state" id="no-results" style="display:none">
                <i class="fas fa-search"></i><h3>Ничего не найдено</h3><p>Попробуйте другой запрос</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer style="text-align:center;padding:25px;color:#888;font-size:14px;border-top:1px solid #eee;margin-top:40px;">
    © 2026 Курьер Экспресс
</footer>

<!-- КОНСТРУКТОР -->
<div class="modal-overlay" id="constructor-modal" onclick="handleOverlayClick(event)">
    <div class="constructor-modal">
        <div class="cm-header">
            <h3 id="cm-title"><i class="fas fa-sliders-h" style="margin-right:8px;"></i>Конструктор</h3>
            <button class="cm-close" onclick="closeConstructor()">✕</button>
        </div>
        <div class="cm-body" id="cm-body">
            <div class="cm-loading"><i class="fas fa-spinner fa-spin"></i> Загружаем...</div>
        </div>
        <div class="cm-footer" id="cm-footer" style="display:none">
            <div>
                <div class="cm-price-base">Базовая цена: <span id="cm-base-price">0 ₽</span></div>
                <div class="cm-price-extra" id="cm-extra-line" style="display:none">Добавки: +<span id="cm-extra-price">0</span> ₽</div>
                <div class="cm-price-total"><span id="cm-total-price">0</span> ₽</div>
            </div>
            <button class="cm-add-btn" onclick="addConstructorToCart()">
                <i class="fas fa-shopping-cart" style="margin-right:8px;"></i>Добавить в корзину
            </button>
        </div>
    </div>
</div>

<script>
var constructorData = null;
var selectedExtras  = {};

// ── Поиск ──────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    var inp = document.getElementById('menu-search');
    var clr = document.getElementById('s-clear');
    var timer;
    inp.addEventListener('input', function() {
        clr.style.display = this.value ? 'block' : 'none';
        clearTimeout(timer);
        var q = this.value.trim();
        timer = setTimeout(function() { filterDishes(q); }, 250);
    });
});

function filterDishes(q) {
    var cards  = document.querySelectorAll('#dishes-grid .dish-card[data-name]');
    var noRes  = document.getElementById('no-results');
    var notice = document.getElementById('search-notice');
    var ql = q.toLowerCase(); var visible = 0;
    cards.forEach(function(c) {
        var show = !ql || c.getAttribute('data-name').indexOf(ql) !== -1 || c.getAttribute('data-desc').indexOf(ql) !== -1;
        c.classList.toggle('hidden', !show);
        if (show) visible++;
    });
    if (noRes) noRes.style.display = (visible === 0 && ql) ? 'block' : 'none';
    notice.style.display = ql ? 'block' : 'none';
    if (ql) notice.innerHTML = '<i class="fas fa-search" style="margin-right:6px;"></i>Поиск: «' + esc(q) + '» — ' + visible + ' блюд';
}
function clearMenuSearch() {
    document.getElementById('menu-search').value = '';
    document.getElementById('s-clear').style.display = 'none';
    document.getElementById('search-notice').style.display = 'none';
    filterDishes('');
}

// ── Конструктор ────────────────────────────────────────────────────────────
function openConstructor(dishId) {
    selectedExtras = {}; constructorData = null;
    var modal  = document.getElementById('constructor-modal');
    var body   = document.getElementById('cm-body');
    var footer = document.getElementById('cm-footer');
    body.innerHTML = '<div class="cm-loading"><i class="fas fa-spinner fa-spin"></i> Загружаем...</div>';
    footer.style.display = 'none';
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';

    fetch('get_constructor.php?id=' + dishId)
        .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function(data) {
            if (data.error) throw new Error(data.error);
            constructorData = data;
            renderConstructor(data);
        })
        .catch(function(err) {
            body.innerHTML = '<div class="cm-loading" style="color:#e74c3c;"><i class="fas fa-exclamation-circle"></i> ' + esc(err.message) + '</div>';
        });
}

function renderConstructor(data) {
    var dish  = data.dish;
    var base  = data.base_ingredients;
    var byCat = data.by_category;

    document.getElementById('cm-title').innerHTML = '<i class="fas fa-sliders-h" style="margin-right:8px;"></i>' + esc(dish.name);

    var baseIds  = base.map(function(i) { return parseInt(i.id); });
    var baseTags = base.map(function(i) {
        return '<span class="cm-base-tag" id="basetag-' + i.id + '">' + esc(i.name) + '</span>';
    }).join('');

    var catIcons = {
        'Сыры':'fas fa-cheese','Мясо':'fas fa-drumstick-bite',
        'Морепродукты':'fas fa-fish','Овощи':'fas fa-leaf',
        'Соусы':'fas fa-tint','Дополнительно':'fas fa-plus-circle','Фрукты':'fas fa-apple-alt'
    };

    var catsHtml = '';
    for (var catName in byCat) {
        var items = byCat[catName];
        var icon  = catIcons[catName] || 'fas fa-circle';
        var itemsHtml = items.map(function(ing) {
            var isBase = baseIds.indexOf(parseInt(ing.id)) !== -1;
            return '<div class="ing-item' + (isBase ? ' checked' : '') + '" ' +
                       'data-id="' + ing.id + '" data-name="' + esc(ing.name) + '" ' +
                       'data-price="' + ing.price + '" data-base="' + (isBase ? '1' : '0') + '" ' +
                       'onclick="toggleIngredient(this)">' +
                   '<div class="ing-check">' + (isBase ? '✓' : '') + '</div>' +
                   '<div class="ing-info">' +
                       '<div class="ing-name">' + esc(ing.name) + '</div>' +
                       '<div class="ing-price">+' + parseFloat(ing.price).toFixed(0) + ' ₽</div>' +
                   '</div></div>';
        }).join('');
        catsHtml += '<div class="ing-category">' +
            '<div class="ing-cat-name"><i class="' + icon + '" style="margin-right:6px;"></i>' + esc(catName) + '</div>' +
            '<div class="ing-grid">' + itemsHtml + '</div></div>';
    }

    document.getElementById('cm-body').innerHTML =
        '<div class="cm-left">' +
            '<img src="images/' + esc(dish.image || 'dish-default.jpg') + '" class="cm-dish-img" onerror="this.src=\'images/dish-default.jpg\'">' +
            '<div class="cm-dish-name">' + esc(dish.name) + '</div>' +
            '<div class="cm-dish-rest"><i class="fas fa-store" style="margin-right:4px;"></i>' + esc(dish.restaurant_name) + '</div>' +
            '<div class="cm-dish-desc">' + esc(dish.description || '') + '</div>' +
            (base.length ? '<div class="cm-base-title">В составе:</div><div class="cm-base-list">' + baseTags + '</div>' : '') +
        '</div>' +
        '<div class="cm-right">' +
            '<div class="cm-right-title"><i class="fas fa-plus-circle" style="color:#9b59b6;margin-right:8px;"></i>Настройте состав:</div>' +
            catsHtml +
        '</div>';

    document.getElementById('cm-base-price').textContent = parseFloat(dish.price).toFixed(0) + ' ₽';
    document.getElementById('cm-total-price').textContent = parseFloat(dish.price).toFixed(0);
    document.getElementById('cm-footer').style.display = 'flex';
}

function toggleIngredient(el) {
    var id     = parseInt(el.getAttribute('data-id'));
    var name   = el.getAttribute('data-name');
    var price  = parseFloat(el.getAttribute('data-price'));
    var isBase = el.getAttribute('data-base') === '1';
    var check  = el.querySelector('.ing-check');
    var tag    = document.getElementById('basetag-' + id);

    if (isBase) {
        el.classList.toggle('checked');
        if (!el.classList.contains('checked')) {
            selectedExtras['remove_' + id] = { name: name, price: 0, remove: true };
            check.textContent = '✕';
            check.style.background = '#fee2e2'; check.style.borderColor = '#fca5a5'; check.style.color = '#991b1b';
            if (tag) tag.classList.add('removing');
        } else {
            delete selectedExtras['remove_' + id];
            check.textContent = '✓';
            check.style.background = '#9b59b6'; check.style.borderColor = '#9b59b6'; check.style.color = 'white';
            if (tag) tag.classList.remove('removing');
        }
    } else {
        el.classList.toggle('checked');
        if (el.classList.contains('checked')) {
            selectedExtras[id] = { name: name, price: price };
            check.textContent = '✓';
        } else {
            delete selectedExtras[id];
            check.textContent = '';
        }
    }
    recalcPrice();
}

function recalcPrice() {
    if (!constructorData) return;
    var base = parseFloat(constructorData.dish.price);
    var extra = 0;
    for (var k in selectedExtras) { if (!selectedExtras[k].remove) extra += selectedExtras[k].price; }
    document.getElementById('cm-total-price').textContent = (base + extra).toFixed(0);
    if (extra > 0) {
        document.getElementById('cm-extra-price').textContent = extra.toFixed(0);
        document.getElementById('cm-extra-line').style.display = 'block';
    } else {
        document.getElementById('cm-extra-line').style.display = 'none';
    }
}

function addConstructorToCart() {
    if (!constructorData) return;
    var dish = constructorData.dish;

    // Собираем добавки для серверного API
    var customizations = [];
    for (var k in selectedExtras) {
        if (!selectedExtras[k].remove) {
            customizations.push({
                id:    parseInt(k),
                name:  selectedExtras[k].name,
                price: selectedExtras[k].price
            });
        }
    }

    Cart.addItem(parseInt(dish.id), 1, customizations)
        .then(function() { closeConstructor(); });
}

function closeConstructor() {
    document.getElementById('constructor-modal').classList.remove('open');
    document.body.style.overflow = '';
    constructorData = null; selectedExtras = {};
}
function handleOverlayClick(e) {
    if (e.target === document.getElementById('constructor-modal')) closeConstructor();
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeConstructor(); });

function esc(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
