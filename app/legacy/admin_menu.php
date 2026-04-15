<?php
// admin_menu.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once 'check_admin.php';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$action  = $_GET['action'] ?? 'list';
$message = '';
$error   = '';

// ── УДАЛЕНИЕ блюда ─────────────────────────────────────────────────────────
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)$_POST['id'];
    try {
        // Удаляем фото
        $img = $pdo->prepare("SELECT image FROM dishes WHERE id = ?")->execute([$id]);
        $row = $pdo->prepare("SELECT image FROM dishes WHERE id = ?");
        $row->execute([$id]);
        $imgFile = $row->fetchColumn();
        if ($imgFile && $imgFile !== 'dish-default.jpg') {
            $path = __DIR__ . '/images/' . $imgFile;
            if (file_exists($path)) @unlink($path);
        }
        $pdo->prepare("DELETE FROM dish_ingredients WHERE dish_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM dishes WHERE id = ?")->execute([$id]);
        $message = 'Блюдо удалено.';
        $action  = 'list';
    } catch (PDOException $e) {
        $error  = 'Ошибка: ' . $e->getMessage();
        $action = 'list';
    }
}

// ── TOGGLE ACTIVE ──────────────────────────────────────────────────────────
if ($action === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("UPDATE dishes SET is_active = !is_active WHERE id = ?")->execute([$id]);
    header('Location: admin_menu.php');
    exit;
}

// ── СОХРАНЕНИЕ (ADD / EDIT) ────────────────────────────────────────────────
if (in_array($action, ['add', 'edit']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $restaurant_id  = (int)$_POST['restaurant_id'];
    $category_id    = $_POST['category_id'] ? (int)$_POST['category_id'] : null;
    $name           = trim($_POST['name']);
    $description    = trim($_POST['description']);
    $price          = (float)$_POST['price'];
    $image          = trim($_POST['image']) ?: 'dish-default.jpg';
    $customizable   = isset($_POST['customizable']) ? 1 : 0;
    $is_active      = isset($_POST['is_active']) ? 1 : 0;
    $edit_id        = (int)($_POST['edit_id'] ?? 0);

    if (empty($name) || $restaurant_id <= 0 || $price <= 0) {
        $error = 'Заполните обязательные поля (название, ресторан, цена).';
    } else {
        try {
            if ($edit_id > 0) {
                $pdo->prepare("
                    UPDATE dishes SET restaurant_id=?, category_id=?, name=?, description=?,
                    price=?, image=?, customizable=?, is_active=? WHERE id=?
                ")->execute([$restaurant_id, $category_id, $name, $description,
                              $price, $image, $customizable, $is_active, $edit_id]);
                $message = 'Блюдо обновлено.';
            } else {
                $pdo->prepare("
                    INSERT INTO dishes (restaurant_id, category_id, name, description,
                    price, image, customizable, is_active)
                    VALUES (?,?,?,?,?,?,?,?)
                ")->execute([$restaurant_id, $category_id, $name, $description,
                              $price, $image, $customizable, $is_active]);
                $message = 'Блюдо добавлено.';
            }
            $action = 'list';
        } catch (PDOException $e) {
            $error = 'Ошибка БД: ' . $e->getMessage();
        }
    }
}

// ── Данные для формы редактирования ───────────────────────────────────────
$editDish = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT * FROM dishes WHERE id = ?");
    $stmt->execute([(int)$_GET['id']]);
    $editDish = $stmt->fetch();
    if (!$editDish) $action = 'list';
}

// ── Справочники ───────────────────────────────────────────────────────────
$restaurants = $pdo->query("SELECT id, name FROM restaurants ORDER BY name")->fetchAll();
$categories  = $pdo->query("
    SELECT c.id, c.name, r.name as r_name
    FROM categories c
    JOIN restaurants r ON c.restaurant_id=r.id
    ORDER BY r.name, c.name
")->fetchAll();

// ── Список блюд ───────────────────────────────────────────────────────────
$filterRest = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0;

if ($filterRest > 0) {
    $stmt = $pdo->prepare("
        SELECT d.*, r.name AS r_name, c.name AS c_name
        FROM dishes d
        JOIN restaurants r ON d.restaurant_id = r.id
        LEFT JOIN categories c ON d.category_id = c.id
        WHERE d.restaurant_id = ?
        ORDER BY r.name, c.name, d.name
    ");
    $stmt->execute([$filterRest]);
} else {
    $stmt = $pdo->query("
        SELECT d.*, r.name AS r_name, c.name AS c_name
        FROM dishes d
        JOIN restaurants r ON d.restaurant_id = r.id
        LEFT JOIN categories c ON d.category_id = c.id
        ORDER BY r.name, c.name, d.name
    ");
}
$dishes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Меню — Админ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6f9; }
        .sidebar { width: 240px; min-height: 100vh; background: #1e293b; position: fixed; top: 0; left: 0; padding-top: 20px; z-index: 100; }
        .sidebar .brand { color: #f97316; font-size: 20px; font-weight: 700; padding: 10px 20px 20px; border-bottom: 1px solid #334155; }
        .sidebar a { display: block; color: #94a3b8; padding: 12px 20px; text-decoration: none; transition: all 0.2s; }
        .sidebar a:hover, .sidebar a.active { color: #fff; background: #334155; }
        .sidebar a i { width: 20px; margin-right: 8px; }
        .main-content { margin-left: 240px; padding: 30px; }

        /* ── Миниатюра в таблице ── */
        .dish-thumb {
            width: 60px; height: 60px; object-fit: cover; border-radius: 10px;
            background: #e2e8f0; cursor: pointer; border: 2px solid #e2e8f0;
            transition: border-color .2s;
        }
        .dish-thumb:hover { border-color: #f97316; }

        /* ── Модальное окно загрузки фото ── */
        .photo-modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,0.55); z-index: 2000;
            justify-content: center; align-items: center; padding: 20px;
        }
        .photo-modal-overlay.open { display: flex; }
        .photo-modal {
            background: white; border-radius: 18px; width: 100%; max-width: 480px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3); overflow: hidden;
        }
        .photo-modal-header {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: white; padding: 18px 24px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .photo-modal-header h5 { margin: 0; font-size: 16px; font-weight: 700; }
        .photo-modal-close {
            background: rgba(255,255,255,.15); border: none; color: white;
            width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 16px;
            display: flex; align-items: center; justify-content: center; transition: background .2s;
        }
        .photo-modal-close:hover { background: rgba(255,255,255,.3); }
        .photo-modal-body { padding: 24px; }

        /* ── Дроп-зона ── */
        .drop-zone {
            border: 2px dashed #cbd5e1; border-radius: 14px; padding: 32px 16px;
            text-align: center; cursor: pointer; transition: all .2s;
            background: #f8fafc;
        }
        .drop-zone:hover, .drop-zone.dragover { border-color: #f97316; background: #fff5f0; }
        .drop-zone i { font-size: 40px; color: #cbd5e1; display: block; margin-bottom: 12px; }
        .drop-zone.dragover i { color: #f97316; }
        .drop-zone p { margin: 0; color: #94a3b8; font-size: 14px; }
        .drop-zone strong { color: #475569; }

        /* ── Превью загружаемого фото ── */
        #photo-preview-wrap { display: none; margin-top: 16px; text-align: center; }
        #photo-preview-img {
            max-width: 100%; max-height: 200px; border-radius: 10px;
            border: 2px solid #e2e8f0; object-fit: cover;
        }
        #photo-preview-name { font-size: 13px; color: #64748b; margin-top: 6px; }

        /* ── Текущее фото ── */
        #current-photo-wrap { margin-bottom: 16px; }
        #current-photo-img {
            width: 100%; max-height: 180px; object-fit: cover;
            border-radius: 12px; border: 2px solid #e2e8f0;
        }

        /* ── Прогресс загрузки ── */
        #upload-progress { display: none; margin-top: 12px; }

        /* Алерты внутри модала */
        #photo-alert { display: none; }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="brand"><i class="fas fa-utensils"></i> Админ-панель</div>
    <a href="admin_dashboard.php"><i class="fas fa-chart-line"></i> Дашборд</a>
    <a href="admin_orders.php"><i class="fas fa-box"></i> Заказы</a>
    <a href="admin_restaurants.php"><i class="fas fa-store"></i> Рестораны</a>
    <a href="admin_menu.php" class="active"><i class="fas fa-utensils"></i> Меню</a>
    <a href="admin_categories.php"><i class="fas fa-tags"></i> Категории</a>
    <a href="admin_ingredients.php"><i class="fas fa-pepper-hot"></i> Ингредиенты</a>
    <a href="index.php" style="margin-top:20px; border-top:1px solid #334155;"><i class="fas fa-home"></i> На сайт</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Выйти</a>
</div>

<div class="main-content">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fas fa-utensils text-success me-2"></i>Управление меню</h2>
        <a href="?action=add" class="btn btn-success"><i class="fas fa-plus me-1"></i> Добавить блюдо</a>
    </div>

    <?php if ($message): ?><div class="alert alert-success"><?= h($message) ?></div><?php endif; ?>
    <?php if ($error):   ?><div class="alert alert-danger"><?= h($error)   ?></div><?php endif; ?>

    <!-- ── Форма добавления/редактирования ─────────────────────────────── -->
    <?php if ($action === 'add' || $action === 'edit'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white fw-bold">
            <?= $action === 'edit' ? 'Редактировать блюдо' : 'Добавить блюдо' ?>
        </div>
        <div class="card-body">
            <form method="POST" action="?action=<?= h($action) ?>">
                <?php if ($editDish): ?>
                    <input type="hidden" name="edit_id" value="<?= $editDish['id'] ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Ресторан <span class="text-danger">*</span></label>
                        <select name="restaurant_id" class="form-select" required>
                            <option value="">— Выберите ресторан —</option>
                            <?php foreach ($restaurants as $r): ?>
                                <option value="<?= $r['id'] ?>"
                                    <?= ($editDish['restaurant_id'] ?? 0) == $r['id'] ? 'selected' : '' ?>>
                                    <?= h($r['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Категория</label>
                        <select name="category_id" class="form-select">
                            <option value="">— Без категории —</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= $c['id'] ?>"
                                    <?= ($editDish['category_id'] ?? null) == $c['id'] ? 'selected' : '' ?>>
                                    <?= h($c['r_name']) ?> → <?= h($c['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold">Название <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required
                               value="<?= h($editDish['name'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Цена (₽) <span class="text-danger">*</span></label>
                        <input type="number" name="price" class="form-control" step="0.01" min="0" required
                               value="<?= h($editDish['price'] ?? '') ?>">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label fw-bold">Файл изображения</label>
                        <input type="text" name="image" class="form-control"
                               placeholder="dish-default.jpg"
                               value="<?= h($editDish['image'] ?? 'dish-default.jpg') ?>">
                        <div class="form-text">
                            <?php if ($action === 'edit' && isset($editDish['id'])): ?>
                                После сохранения загрузите фото через кнопку 📷 в таблице
                            <?php else: ?>
                                Загрузить фото можно после добавления блюда
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold">Описание</label>
                    <textarea name="description" class="form-control" rows="3"><?= h($editDish['description'] ?? '') ?></textarea>
                </div>

                <div class="d-flex gap-4 mb-4">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="customizable" id="customizable" value="1"
                               <?= ($editDish['customizable'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="customizable">
                            <strong>Конструктор ингредиентов</strong>
                            <small class="d-block text-muted">Пицца / Вок</small>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1"
                               <?= ($editDish === null || $editDish['is_active']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            <strong>Активно</strong>
                            <small class="d-block text-muted">Отображается на сайте</small>
                        </label>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i> Сохранить</button>
                    <a href="admin_menu.php" class="btn btn-secondary">Отмена</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── Фильтр по ресторану ──────────────────────────────────────────── -->
    <div class="mb-3 d-flex gap-2 align-items-center flex-wrap">
        <span class="text-muted">Фильтр:</span>
        <a href="admin_menu.php" class="btn btn-sm <?= !$filterRest ? 'btn-primary' : 'btn-outline-secondary' ?>">Все</a>
        <?php foreach ($restaurants as $r): ?>
            <a href="?restaurant_id=<?= $r['id'] ?>" class="btn btn-sm <?= $filterRest == $r['id'] ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <?= h($r['name']) ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ── Таблица блюд ──────────────────────────────────────────────────── -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width:70px">Фото</th>
                        <th>Название</th>
                        <th>Ресторан / Категория</th>
                        <th>Цена</th>
                        <th>Конструктор</th>
                        <th>Статус</th>
                        <th class="text-end pe-3">Действия</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($dishes as $d): ?>
                    <tr class="<?= !$d['is_active'] ? 'table-secondary' : '' ?>">
                        <td class="ps-3">
                            <!-- Клик по фото открывает модал загрузки -->
                            <img src="images/<?= h($d['image'] ?: 'dish-default.jpg') ?>"
                                 class="dish-thumb"
                                 onerror="this.src='images/dish-default.jpg'"
                                 title="Нажмите, чтобы сменить фото"
                                 onclick="openPhotoModal(<?= $d['id'] ?>, '<?= h(addslashes($d['name'])) ?>', '<?= h(addslashes($d['image'] ?: 'dish-default.jpg')) ?>')"
                                 alt="">
                        </td>
                        <td>
                            <strong><?= h($d['name']) ?></strong>
                            <small class="d-block text-muted"><?= h(mb_substr($d['description'] ?? '', 0, 55)) ?></small>
                            <small class="text-secondary" style="font-size:11px;"><?= h($d['image'] ?: '—') ?></small>
                        </td>
                        <td>
                            <small><?= h($d['r_name']) ?></small><br>
                            <span class="badge bg-light text-dark"><?= h($d['c_name'] ?? '—') ?></span>
                        </td>
                        <td><strong><?= number_format($d['price'], 0) ?> ₽</strong></td>
                        <td>
                            <?= $d['customizable']
                                ? '<span class="badge" style="background:#8b5cf6">Да</span>'
                                : '<span class="badge bg-light text-dark">Нет</span>' ?>
                        </td>
                        <td>
                            <a href="?action=toggle&id=<?= $d['id'] ?>" class="badge text-decoration-none <?= $d['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $d['is_active'] ? 'Активно' : 'Скрыто' ?>
                            </a>
                        </td>
                        <td class="text-end pe-3">
                            <!-- Состав блюда (только для customizable) -->
                            <?php if ($d['customizable']): ?>
                            <a href="admin_dish_ingredients.php?dish_id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-purple" title="Состав" style="border-color:#9b59b6;color:#9b59b6;">
                                <i class="fas fa-list-ul"></i>
                            </a>
                            <?php endif; ?>
                            <!-- Кнопка загрузки фото -->
                            <button class="btn btn-sm btn-outline-warning"
                                    onclick="openPhotoModal(<?= $d['id'] ?>, '<?= h(addslashes($d['name'])) ?>', '<?= h(addslashes($d['image'] ?: 'dish-default.jpg')) ?>')"
                                    title="Загрузить фото">
                                <i class="fas fa-camera"></i>
                            </button>
                            <a href="?action=edit&id=<?= $d['id'] ?>" class="btn btn-sm btn-outline-primary" title="Редактировать">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form method="POST" action="?action=delete" class="d-inline"
                                  onsubmit="return confirm('Удалить «<?= h(addslashes($d['name'])) ?>»?')">
                                <input type="hidden" name="id" value="<?= $d['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Удалить">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /.main-content -->


<!-- ══════════════════════════════════════════════════════════════════════ -->
<!--  МОДАЛЬНОЕ ОКНО ЗАГРУЗКИ ФОТО                                        -->
<!-- ══════════════════════════════════════════════════════════════════════ -->
<div class="photo-modal-overlay" id="photo-modal" onclick="handleOverlayClick(event)">
    <div class="photo-modal">

        <div class="photo-modal-header">
            <h5 id="photo-modal-title"><i class="fas fa-camera me-2"></i>Фото блюда</h5>
            <button class="photo-modal-close" onclick="closePhotoModal()">✕</button>
        </div>

        <div class="photo-modal-body">

            <!-- Текущее фото -->
            <div id="current-photo-wrap">
                <p class="text-muted mb-2" style="font-size:13px;"><strong>Текущее фото:</strong></p>
                <img id="current-photo-img" src="" alt="Текущее фото" onerror="this.src='images/dish-default.jpg'">
                <div class="d-flex justify-content-end mt-2">
                    <button class="btn btn-sm btn-outline-danger" onclick="deletePhoto()">
                        <i class="fas fa-trash me-1"></i>Удалить фото
                    </button>
                </div>
            </div>

            <hr>

            <!-- Дроп-зона -->
            <div class="drop-zone" id="drop-zone"
                 onclick="document.getElementById('file-input').click()"
                 ondragover="handleDragOver(event)"
                 ondragleave="handleDragLeave(event)"
                 ondrop="handleDrop(event)">
                <i class="fas fa-cloud-upload-alt"></i>
                <p><strong>Нажмите или перетащите</strong> фото сюда</p>
                <p>JPG, PNG, WEBP — до 5 МБ</p>
            </div>
            <input type="file" id="file-input" accept="image/jpeg,image/png,image/gif,image/webp" style="display:none" onchange="handleFileSelect(this.files[0])">

            <!-- Превью нового фото -->
            <div id="photo-preview-wrap">
                <img id="photo-preview-img" src="" alt="Превью">
                <div id="photo-preview-name"></div>
            </div>

            <!-- Прогресс -->
            <div id="upload-progress">
                <div class="progress mt-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning"
                         id="progress-bar" role="progressbar" style="width:0%"></div>
                </div>
                <div class="text-center text-muted mt-1" style="font-size:13px;" id="progress-text">Загружаем...</div>
            </div>

            <!-- Алерт результата -->
            <div id="photo-alert" class="alert mt-3 mb-0"></div>

            <!-- Кнопки -->
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-secondary flex-grow-1" onclick="closePhotoModal()">Закрыть</button>
                <button class="btn btn-warning flex-grow-1" id="upload-btn" onclick="uploadPhoto()" style="display:none">
                    <i class="fas fa-upload me-1"></i>Загрузить
                </button>
            </div>
        </div><!-- /.photo-modal-body -->
    </div><!-- /.photo-modal -->
</div><!-- /.photo-modal-overlay -->


<script>
var currentDishId   = null;
var selectedFile    = null;

// ── Открыть модал ─────────────────────────────────────────────────────────
function openPhotoModal(dishId, dishName, currentImage) {
    currentDishId = dishId;
    selectedFile  = null;

    document.getElementById('photo-modal-title').innerHTML = '<i class="fas fa-camera me-2"></i>' + escHtml(dishName);
    document.getElementById('current-photo-img').src = 'images/' + currentImage + '?t=' + Date.now();

    var isDef = (currentImage === 'dish-default.jpg');
    document.getElementById('current-photo-wrap').style.display = isDef ? 'none' : 'block';

    // Сброс формы
    document.getElementById('file-input').value      = '';
    document.getElementById('photo-preview-wrap').style.display = 'none';
    document.getElementById('upload-btn').style.display = 'none';
    document.getElementById('upload-progress').style.display = 'none';
    document.getElementById('progress-bar').style.width = '0%';
    hideAlert();

    document.getElementById('photo-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closePhotoModal() {
    document.getElementById('photo-modal').classList.remove('open');
    document.body.style.overflow = '';
}

function handleOverlayClick(e) {
    if (e.target === document.getElementById('photo-modal')) closePhotoModal();
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closePhotoModal();
});

// ── Drag & Drop ───────────────────────────────────────────────────────────
function handleDragOver(e) {
    e.preventDefault();
    document.getElementById('drop-zone').classList.add('dragover');
}
function handleDragLeave(e) {
    document.getElementById('drop-zone').classList.remove('dragover');
}
function handleDrop(e) {
    e.preventDefault();
    document.getElementById('drop-zone').classList.remove('dragover');
    var file = e.dataTransfer.files[0];
    if (file) handleFileSelect(file);
}

// ── Выбор файла ───────────────────────────────────────────────────────────
function handleFileSelect(file) {
    if (!file) return;

    var allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!allowed.includes(file.type)) {
        showAlert('Разрешены только JPG, PNG, GIF, WEBP', 'danger');
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        showAlert('Размер файла не должен превышать 5 МБ', 'danger');
        return;
    }

    selectedFile = file;
    hideAlert();

    // Показываем превью
    var reader = new FileReader();
    reader.onload = function(e) {
        document.getElementById('photo-preview-img').src = e.target.result;
        document.getElementById('photo-preview-name').textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' КБ)';
        document.getElementById('photo-preview-wrap').style.display = 'block';
        document.getElementById('upload-btn').style.display = 'inline-block';
    };
    reader.readAsDataURL(file);
}

// ── Загрузить фото ────────────────────────────────────────────────────────
function uploadPhoto() {
    if (!selectedFile || !currentDishId) return;

    var formData = new FormData();
    formData.append('action',  'upload');
    formData.append('dish_id', currentDishId);
    formData.append('image',   selectedFile);

    document.getElementById('upload-progress').style.display = 'block';
    document.getElementById('upload-btn').disabled = true;
    hideAlert();

    var xhr = new XMLHttpRequest();
    xhr.open('POST', 'admin_upload_image.php');

    xhr.upload.addEventListener('progress', function(e) {
        if (e.lengthComputable) {
            var pct = Math.round(e.loaded / e.total * 100);
            document.getElementById('progress-bar').style.width = pct + '%';
            document.getElementById('progress-text').textContent = 'Загружаем... ' + pct + '%';
        }
    });

    xhr.onload = function() {
        document.getElementById('upload-btn').disabled = false;
        document.getElementById('upload-progress').style.display = 'none';

        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.success) {
                // Обновляем миниатюру в таблице
                var thumbs = document.querySelectorAll('img.dish-thumb');
                thumbs.forEach(function(img) {
                    if (img.getAttribute('onclick') && img.getAttribute('onclick').indexOf('openPhotoModal(' + currentDishId + ',') === 0) {
                        img.src = resp.url + '?t=' + Date.now();
                    }
                });
                // Обновляем текущее фото в модале
                document.getElementById('current-photo-img').src = resp.url + '?t=' + Date.now();
                document.getElementById('current-photo-wrap').style.display = 'block';
                // Обновляем атрибут onclick
                updateThumbOnclick(currentDishId, resp.filename);

                showAlert('✅ Фото успешно загружено!', 'success');
                document.getElementById('photo-preview-wrap').style.display = 'none';
                document.getElementById('upload-btn').style.display = 'none';
                selectedFile = null;
            } else {
                showAlert('Ошибка: ' + (resp.error || 'Неизвестная ошибка'), 'danger');
            }
        } catch(e) {
            showAlert('Ошибка ответа сервера', 'danger');
        }
    };

    xhr.onerror = function() {
        document.getElementById('upload-btn').disabled = false;
        document.getElementById('upload-progress').style.display = 'none';
        showAlert('Ошибка сети при загрузке', 'danger');
    };

    xhr.send(formData);
}

// ── Удалить фото ──────────────────────────────────────────────────────────
function deletePhoto() {
    if (!currentDishId) return;
    if (!confirm('Удалить фото? Будет установлено изображение по умолчанию.')) return;

    hideAlert();

    fetch('admin_upload_image.php', {
        method: 'POST',
        body: new URLSearchParams({ action: 'delete', dish_id: currentDishId })
    })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
        if (resp.success) {
            document.getElementById('current-photo-wrap').style.display = 'none';
            // Сбрасываем миниатюру в таблице на дефолт
            var thumbs = document.querySelectorAll('img.dish-thumb');
            thumbs.forEach(function(img) {
                if (img.getAttribute('onclick') && img.getAttribute('onclick').indexOf('openPhotoModal(' + currentDishId + ',') === 0) {
                    img.src = 'images/dish-default.jpg?t=' + Date.now();
                }
            });
            updateThumbOnclick(currentDishId, 'dish-default.jpg');
            showAlert('Фото удалено. Используется изображение по умолчанию.', 'warning');
        } else {
            showAlert('Ошибка: ' + (resp.error || 'Не удалось удалить'), 'danger');
        }
    })
    .catch(function() {
        showAlert('Ошибка сети', 'danger');
    });
}

// ── Обновляем onclick на кнопке и миниатюре после загрузки/удаления ──────
function updateThumbOnclick(dishId, newFilename) {
    // Обновляем все элементы, связанные с этим блюдом
    document.querySelectorAll('[onclick]').forEach(function(el) {
        var oc = el.getAttribute('onclick');
        if (oc && oc.indexOf('openPhotoModal(' + dishId + ',') === 0) {
            // Заменяем третий аргумент (имя файла)
            el.setAttribute('onclick', oc.replace(/openPhotoModal\((\d+),\s*'([^']+)',\s*'([^']*)'\)/, function(match, id, name) {
                return 'openPhotoModal(' + id + ', \'' + name + '\', \'' + newFilename + '\')';
            }));
        }
    });
}

// ── Утилиты ───────────────────────────────────────────────────────────────
function showAlert(msg, type) {
    var el = document.getElementById('photo-alert');
    el.className = 'alert alert-' + type + ' mt-3 mb-0';
    el.textContent = msg;
    el.style.display = 'block';
}
function hideAlert() {
    document.getElementById('photo-alert').style.display = 'none';
}
function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>
