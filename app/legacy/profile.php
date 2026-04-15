<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../../config/db.php';

function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$userId = (int)$_SESSION['user_id'];

// Обработка смены аватара
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_avatar'])) {
    $allowed = ['1.png','2.png','3.png','4.png','5.png'];
    $newAvatar = $_POST['avatar'] ?? '';
    if (in_array($newAvatar, $allowed)) {
        $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?")->execute([$newAvatar, $userId]);
        $_SESSION['user_avatar'] = $newAvatar;
    }
    header('Location: profile.php?msg=avatar_updated');
    exit;
}

// Получаем пользователя
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
if (!$user) { session_destroy(); header('Location: login.php'); exit; }

// Последние 5 заказов
$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT o.id, o.order_number, o.total, o.status, o.created_at,
               r.name AS restaurant_name
        FROM orders o
        JOIN restaurants r ON o.restaurant_id = r.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {}

$msg   = '';
$error = '';
if (($_GET['msg'] ?? '') === 'avatar_updated') { $msg = 'Фото профиля обновлено!'; }

// ── Обновление данных профиля ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // CSRF-проверка
    if (empty($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {

        if ($_POST['action'] === 'update_profile') {
            $username = trim($_POST['username'] ?? '');
            $lastname = trim($_POST['lastname'] ?? '');
            $phone    = trim($_POST['phone']    ?? '');

            if (empty($username)) {
                $error = 'Имя не может быть пустым.';
            } else {
                $pdo->prepare("UPDATE users SET username=?, lastname=?, phone=? WHERE id=?")
                    ->execute([$username, $lastname, $phone, $userId]);
                $_SESSION['user_name'] = $username;
                $user['username'] = $username;
                $user['lastname'] = $lastname;
                $user['phone']    = $phone;
                $msg = 'Данные обновлены.';
            }

        } elseif ($_POST['action'] === 'change_password') {
            $old     = $_POST['old_password']     ?? '';
            $new     = $_POST['new_password']     ?? '';
            $confirm = $_POST['confirm_password'] ?? '';

            if (!password_verify($old, $user['password_hash'])) {
                $error = 'Неверный текущий пароль.';
            } elseif (strlen($new) < 6) {
                $error = 'Новый пароль должен содержать минимум 6 символов.';
            } elseif ($new !== $confirm) {
                $error = 'Пароли не совпадают.';
            } else {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $userId]);
                $msg = 'Пароль успешно изменён.';
            }
        }
    }
}

// CSRF-токен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$statusLabels = [
    'new'        => 'Новый',
    'confirmed'  => 'Подтверждён',
    'preparing'  => 'Готовится',
    'ready'      => 'Готов',
    'delivering' => 'В доставке',
    'delivered'  => 'Доставлен',
    'cancelled'  => 'Отменён',
];
$statusColors = [
    'new'        => '#3b82f6',
    'confirmed'  => '#06b6d4',
    'preparing'  => '#f59e0b',
    'ready'      => '#10b981',
    'delivering' => '#f59e0b',
    'delivered'  => '#10b981',
    'cancelled'  => '#ef4444',
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Профиль — Курьер Экспресс</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f0f2f5; }
        .profile-wrap {
            max-width: 900px; margin: 0 auto; padding: 90px 20px 60px;
        }

        /* ── Хедер профиля ─────────────────────────────────────────────── */
        .profile-hero {
            background: linear-gradient(135deg, #ee5a24 0%, #ff6b6b 50%, #9b59b6 100%);
            border-radius: 20px; padding: 24px 36px 28px; color: white;
            position: relative;
        }
        .profile-hero::before {
            content: '';
            position: absolute; inset: 0;
            background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Ccircle cx='30' cy='30' r='20'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .profile-hero-content { position: relative; }
        .profile-hero h1 { font-size: 26px; font-weight: 700; margin: 10px 0 6px; }
        .profile-hero > .profile-hero-content > p { opacity: 0.85; font-size: 14px; margin: 0 0 0 0; }
        .profile-avatar-wrap {
            display: flex; align-items: center; gap: 16px; margin-top: 20px;
        }
        .profile-avatar {
            width: 72px; height: 72px; border-radius: 50%;
            border: 3px solid rgba(255,255,255,0.85);
            box-shadow: 0 4px 16px rgba(0,0,0,0.2);
            flex-shrink: 0; overflow: hidden; cursor: pointer;
            position: relative; transition: transform .2s;
        }
        .profile-avatar:hover { transform: scale(1.05); }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .profile-avatar-overlay {
            position: absolute; inset: 0; background: rgba(0,0,0,.4);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity .2s; font-size: 16px; color: white;
        }
        .profile-avatar:hover .profile-avatar-overlay { opacity: 1; }
        .profile-avatar-name h2 { font-size: 18px; font-weight: 700; color: white; margin: 0 0 3px; }
        .profile-avatar-name p  { font-size: 13px; color: rgba(255,255,255,0.75); margin: 0; }
        .profile-hero-back {
            color: rgba(255,255,255,0.8); text-decoration: none; font-size: 14px;
            display: inline-flex; align-items: center; gap: 6px; margin-bottom: 20px;
            transition: color 0.2s;
        }
        .profile-hero-back:hover { color: white; }

        /* ── Аватар ─────────────────────────────────────────────────────── */
        .profile-avatar-wrap {
            position: relative; display: flex; align-items: center;
            gap: 20px; padding: 0 28px 20px;
        }
        .profile-avatar {
            width: 100px; height: 100px; border-radius: 50%;
            background: linear-gradient(135deg, #ee5a24, #ff6b6b);
            border: 4px solid white; box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: flex; align-items: center; justify-content: center;
            font-size: 38px; color: white; font-weight: 700; flex-shrink: 0;
            overflow: hidden; cursor: pointer; position: relative; transition: transform .2s;
        }
        .profile-avatar:hover { transform: scale(1.05); }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; display: block; border-radius: 50%; }
        .profile-avatar-overlay {
            position: absolute; inset: 0; background: rgba(0,0,0,.45);
            display: flex; align-items: center; justify-content: center;
            opacity: 0; transition: opacity .2s; border-radius: 50%;
            font-size: 18px; color: white;
        }
        .profile-avatar:hover .profile-avatar-overlay { opacity: 1; }
        .profile-avatar-name { align-self: flex-end; padding-bottom: 4px; }
        .profile-avatar-name p  { font-size: 13px; color: #64748b; margin: 0; }

        /* ── Карточки ───────────────────────────────────────────────────── */
        .profile-card {
            background: white; border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,0.07);
        }
        .profile-card + .profile-card { margin-top: 20px; }
        .card-header {
            padding: 18px 24px; border-bottom: 1px solid #f1f5f9;
            display: flex; justify-content: space-between; align-items: center;
        }
        .card-header h3 {
            font-size: 15px; font-weight: 700; color: #1e293b; margin: 0;
            display: flex; align-items: center; gap: 8px;
        }
        .card-header h3 i { color: #ee5a24; }
        .card-body { padding: 24px; }

        /* ── Поля профиля ───────────────────────────────────────────────── */
        .info-grid {
            display: grid; grid-template-columns: 1fr 1fr; gap: 16px;
        }
        .info-item label { font-size: 12px; color: #94a3b8; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 4px; }
        .info-item .info-val { font-size: 15px; color: #1e293b; font-weight: 500; }
        .info-item .info-val.empty { color: #cbd5e1; font-style: italic; }

        /* ── Кнопки ─────────────────────────────────────────────────────── */
        .btn-primary {
            background: linear-gradient(135deg, #ee5a24, #ff6b6b);
            color: white; border: none; padding: 10px 20px; border-radius: 10px;
            font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.2s;
            display: inline-flex; align-items: center; gap: 7px; text-decoration: none;
        }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 5px 15px rgba(238,90,36,.35); color: white; }
        .btn-secondary {
            background: #f1f5f9; color: #475569; border: none; padding: 10px 20px;
            border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 7px;
        }
        .btn-secondary:hover { background: #e2e8f0; }
        .btn-danger {
            background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; padding: 10px 20px;
            border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer;
            transition: all 0.2s; display: inline-flex; align-items: center; gap: 7px; text-decoration: none;
        }
        .btn-danger:hover { background: #fee2e2; color: #ef4444; }

        /* ── Заказы ─────────────────────────────────────────────────────── */
        .order-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 14px 0; border-bottom: 1px solid #f1f5f9;
        }
        .order-row:last-child { border-bottom: none; }
        .order-num { font-weight: 700; color: #1e293b; font-size: 14px; }
        .order-meta { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .order-badge {
            padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; color: white;
        }
        .order-total { font-weight: 700; color: #ee5a24; font-size: 15px; margin-left: 16px; white-space: nowrap; }
        .no-orders { text-align: center; padding: 30px; color: #94a3b8; }
        .no-orders i { font-size: 40px; display: block; margin-bottom: 10px; opacity: .35; }

        /* ── Модалки ─────────────────────────────────────────────────────── */
        .modal-overlay {
            display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5);
            z-index: 4000; justify-content: center; align-items: center; padding: 20px;
        }
        .modal-overlay.open { display: flex; }
        .modal-box {
            background: white; border-radius: 18px; width: 100%; max-width: 460px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25); overflow: hidden;
        }
        .modal-header {
            background: linear-gradient(135deg, #ee5a24, #ff6b6b);
            padding: 18px 24px; color: white;
            display: flex; justify-content: space-between; align-items: center;
        }
        .modal-header h4 { margin: 0; font-size: 16px; font-weight: 700; }
        .modal-close {
            background: rgba(255,255,255,0.2); border: none; color: white;
            width: 32px; height: 32px; border-radius: 50%; cursor: pointer; font-size: 16px;
            display: flex; align-items: center; justify-content: center; transition: background 0.2s;
        }
        .modal-close:hover { background: rgba(255,255,255,0.35); }
        .modal-body { padding: 24px; }
        .form-group { margin-bottom: 16px; }
        .form-group label { font-size: 13px; font-weight: 600; color: #475569; display: block; margin-bottom: 6px; }
        .form-control {
            width: 100%; padding: 11px 14px; border: 2px solid #e2e8f0;
            border-radius: 10px; font-size: 15px; box-sizing: border-box; transition: border 0.2s;
        }
        .form-control:focus { border-color: #ee5a24; outline: none; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .modal-footer { padding: 0 24px 24px; display: flex; gap: 10px; justify-content: flex-end; }

        /* Алерты */
        .alert {
            padding: 12px 16px; border-radius: 10px; font-size: 14px;
            margin-bottom: 20px; display: flex; align-items: center; gap: 10px;
        }
        .alert-success { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        .pwd-wrap { position: relative; }
        .pwd-wrap .form-control { padding-right: 44px; }
        .pwd-eye {
            position: absolute; right: 12px; top: 50%; transform: translateY(-50%);
            background: none; border: none; color: #94a3b8; cursor: pointer; font-size: 15px; padding: 4px;
        }
        .pwd-eye:hover { color: #ee5a24; }

        @media (max-width: 600px) {
            .info-grid  { grid-template-columns: 1fr; }
            .form-row   { grid-template-columns: 1fr; }
            .profile-avatar-wrap { flex-direction: column; align-items: flex-start; }
        }

        /* ── Аватар пользователя ──────────────────────────────────────── */
        /* аватар — стили выше */

        /* Модалка выбора аватара */
        .avatar-modal-backdrop {
            display: none; position: fixed; inset: 0;
            background: rgba(0,0,0,.55); z-index: 1000;
            align-items: center; justify-content: center;
        }
        .avatar-modal-backdrop.open { display: flex; }
        .avatar-modal {
            background: white; border-radius: 20px; padding: 28px;
            width: 500px; max-width: 95vw; box-shadow: 0 20px 60px rgba(0,0,0,.3);
        }
        .avatar-modal h3 { font-size: 17px; font-weight: 700; margin: 0 0 20px; color: #1e293b; }
        .avatar-grid {
            display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px;
            margin-bottom: 20px;
        }
        .avatar-option {
            width: 100%; aspect-ratio: 1; border-radius: 50%; overflow: hidden;
            border: 3px solid transparent; cursor: pointer; transition: all .2s;
        }
        .avatar-option img { width: 100%; height: 100%; object-fit: cover; }
        .avatar-option.selected,
        .avatar-option:hover { border-color: #ee5a24; transform: scale(1.07); }
        .avatar-modal-actions { display: flex; gap: 10px; justify-content: flex-end; }
        .btn-avatar-save {
            background: linear-gradient(135deg,#ee5a24,#ff6b6b); color: white;
            border: none; border-radius: 10px; padding: 10px 24px;
            font-size: 14px; font-weight: 600; cursor: pointer;
        }
        .btn-avatar-cancel {
            background: #f1f5f9; color: #64748b; border: none;
            border-radius: 10px; padding: 10px 24px; font-size: 14px; cursor: pointer;
        }
    </style>
</head>
<body>
<?php include 'header.php'; ?>

<div class="profile-wrap">

    <!-- Градиентный хедер с аватаром -->
    <div class="profile-hero" style="margin-bottom: 20px;">
        <div class="profile-hero-content">
            <a href="index.php" class="profile-hero-back">
                <i class="fas fa-arrow-left"></i> На главную
            </a>
            <h1>Личный кабинет</h1>
            <p>Управляйте данными аккаунта и следите за заказами</p>
            <div class="profile-avatar-wrap">
                <div class="profile-avatar" onclick="openAvatarModal()" title="Сменить фото">
                    <img src="images/avatars/<?= h($user['avatar'] ?? '1.png') ?>"
                         alt="Аватар" onerror="this.src='images/avatars/1.png'">
                    <div class="profile-avatar-overlay"><i class="fas fa-camera"></i></div>
                </div>
                <div class="profile-avatar-name">
                    <h2><?= h(trim(($user['username'] ?? '') . ' ' . ($user['lastname'] ?? ''))) ?: 'Пользователь' ?></h2>
                    <p><?= h($user['email']) ?></p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success" style="margin-bottom: 16px;">
            <i class="fas fa-check-circle"></i> <?= h($msg) ?>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-bottom: 16px;">
            <i class="fas fa-exclamation-circle"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <!-- Личные данные -->
    <div class="profile-card">
        <div class="card-header">
            <h3><i class="fas fa-user"></i> Личные данные</h3>
            <button class="btn-primary" onclick="openModal('edit-modal')">
                <i class="fas fa-pen"></i> Редактировать
            </button>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Имя</label>
                    <div class="info-val <?= empty($user['username']) ? 'empty' : '' ?>">
                        <?= $user['username'] ? h($user['username']) : 'Не указано' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Фамилия</label>
                    <div class="info-val <?= empty($user['lastname']) ? 'empty' : '' ?>">
                        <?= $user['lastname'] ? h($user['lastname']) : 'Не указано' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <div class="info-val"><?= h($user['email']) ?></div>
                </div>
                <div class="info-item">
                    <label>Телефон</label>
                    <div class="info-val <?= empty($user['phone']) ? 'empty' : '' ?>">
                        <?= $user['phone'] ? h($user['phone']) : 'Не указан' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Роль</label>
                    <div class="info-val">
                        <?= $user['role'] === 'admin' ? '👑 Администратор' : '👤 Клиент' ?>
                    </div>
                </div>
                <div class="info-item">
                    <label>Дата регистрации</label>
                    <div class="info-val">
                        <?= isset($user['created_at']) ? date('d.m.Y', strtotime($user['created_at'])) : '—' ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Безопасность -->
    <div class="profile-card">
        <div class="card-header">
            <h3><i class="fas fa-lock"></i> Безопасность</h3>
            <button class="btn-secondary" onclick="openModal('pwd-modal')">
                <i class="fas fa-key"></i> Сменить пароль
            </button>
        </div>
        <div class="card-body" style="display:flex; align-items:center; gap:14px; padding: 20px 24px;">
            <div style="width:42px; height:42px; background:#f0fdf4; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                <i class="fas fa-shield-alt" style="color:#16a34a; font-size:18px;"></i>
            </div>
            <div>
                <div style="font-weight:600; color:#1e293b; font-size:14px;">Пароль задан</div>
                <div style="font-size:13px; color:#94a3b8;">Для смены пароля нажмите кнопку справа</div>
            </div>
        </div>
    </div>

    <!-- Последние заказы -->
    <div class="profile-card">
        <div class="card-header">
            <h3><i class="fas fa-box"></i> Последние заказы</h3>
            <a href="my_orders.php" class="btn-secondary" style="text-decoration:none;">
                <i class="fas fa-list"></i> Все заказы
            </a>
        </div>
        <div class="card-body" style="padding: 0 24px;">
            <?php if (empty($orders)): ?>
                <div class="no-orders">
                    <i class="fas fa-box-open"></i>
                    <p>Заказов пока нет</p>
                    <a href="index.php" class="btn-primary" style="margin-top:10px; display:inline-flex;">
                        <i class="fas fa-utensils"></i> Заказать еду
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($orders as $o): ?>
                    <div class="order-row">
                        <div>
                            <div class="order-num"><?= h($o['order_number']) ?></div>
                            <div class="order-meta">
                                <i class="fas fa-store" style="margin-right:4px;"></i><?= h($o['restaurant_name']) ?>
                                &nbsp;·&nbsp;
                                <?= date('d.m.Y H:i', strtotime($o['created_at'])) ?>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <span class="order-badge" style="background:<?= $statusColors[$o['status']] ?? '#64748b' ?>">
                                <?= $statusLabels[$o['status']] ?? h($o['status']) ?>
                            </span>
                            <span class="order-total"><?= number_format($o['total'], 0) ?> ₽</span>
                        </div>
                    </div>
                <?php endforeach; ?>
                
            <?php endif; ?>
        </div>
    </div>

    <!-- Выход -->
    <div style="margin-top: 20px; text-align: right;">
        <a href="logout.php" class="btn-danger">
            <i class="fas fa-sign-out-alt"></i> Выйти из аккаунта
        </a>
    </div>

</div><!-- /.profile-wrap -->


<!-- ── Модалка: Редактировать данные ──────────────────────────────────────── -->
<div class="modal-overlay" id="edit-modal" onclick="handleOverlay(event,'edit-modal')">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-user-edit" style="margin-right:8px;"></i>Редактировать данные</h4>
            <button class="modal-close" onclick="closeModal('edit-modal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="update_profile">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label>Имя <span style="color:#ee5a24">*</span></label>
                        <input type="text" name="username" class="form-control" required
                               value="<?= h($user['username'] ?? '') ?>" placeholder="Иван">
                    </div>
                    <div class="form-group">
                        <label>Фамилия</label>
                        <input type="text" name="lastname" class="form-control"
                               value="<?= h($user['lastname'] ?? '') ?>" placeholder="Иванов">
                    </div>
                </div>
                <div class="form-group">
                    <label>Телефон</label>
                    <input type="tel" name="phone" class="form-control"
                           value="<?= h($user['phone'] ?? '') ?>" placeholder="+7 (999) 000-00-00">
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Email</label>
                    <input type="text" class="form-control" value="<?= h($user['email']) ?>" readonly
                           style="background:#f8fafc; color:#94a3b8; cursor:not-allowed;">
                    <small style="color:#94a3b8; font-size:12px;">Email изменить нельзя</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-modal')">Отмена</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Сохранить</button>
            </div>
        </form>
    </div>
</div>


<!-- ── Модалка: Смена пароля ──────────────────────────────────────────────── -->
<div class="modal-overlay" id="pwd-modal" onclick="handleOverlay(event,'pwd-modal')">
    <div class="modal-box">
        <div class="modal-header">
            <h4><i class="fas fa-key" style="margin-right:8px;"></i>Смена пароля</h4>
            <button class="modal-close" onclick="closeModal('pwd-modal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Текущий пароль</label>
                    <div class="pwd-wrap">
                        <input type="password" name="old_password" id="pwd-old" class="form-control" required placeholder="••••••••">
                        <button type="button" class="pwd-eye" onclick="togglePwd('pwd-old',this)">👁</button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Новый пароль</label>
                    <div class="pwd-wrap">
                        <input type="password" name="new_password" id="pwd-new" class="form-control" required placeholder="Минимум 6 символов">
                        <button type="button" class="pwd-eye" onclick="togglePwd('pwd-new',this)">👁</button>
                    </div>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label>Повторите новый пароль</label>
                    <div class="pwd-wrap">
                        <input type="password" name="confirm_password" id="pwd-confirm" class="form-control" required placeholder="Повторите пароль">
                        <button type="button" class="pwd-eye" onclick="togglePwd('pwd-confirm',this)">👁</button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('pwd-modal')">Отмена</button>
                <button type="submit" class="btn-primary"><i class="fas fa-lock"></i> Сменить</button>
            </div>
        </form>
    </div>
</div>

<script src="js/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    updateCartCount();

    // Если после POST есть msg/error — открываем нужную модалку обратно
    <?php if ($error && isset($_POST['action'])): ?>
        <?php if ($_POST['action'] === 'change_password'): ?>
            openModal('pwd-modal');
        <?php elseif ($_POST['action'] === 'update_profile'): ?>
            openModal('edit-modal');
        <?php endif; ?>
    <?php endif; ?>
});

function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
function handleOverlay(e, id) {
    if (e.target === document.getElementById(id)) closeModal(id);
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(function(m) {
            m.classList.remove('open');
        });
        document.body.style.overflow = '';
    }
});

function togglePwd(id, btn) {
    var inp = document.getElementById(id);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    btn.textContent = inp.type === 'password' ? '👁' : '🙈';
}
</script>

<!-- Модалка выбора аватара -->
<div class="avatar-modal-backdrop" id="avatar-modal">
    <div class="avatar-modal">
        <h3><i class="fas fa-user-circle" style="color:#ee5a24;margin-right:8px;"></i>Выберите фото профиля</h3>
        <form method="POST">
            <input type="hidden" name="change_avatar" value="1">
            <input type="hidden" name="avatar" id="avatar-input" value="<?= h($user['avatar'] ?? '1.png') ?>">
            <div class="avatar-grid">
                <?php foreach (['1','2','3','4','5'] as $i): ?>
                <div class="avatar-option <?= ($user['avatar'] ?? '1.png') === "$i.png" ? 'selected' : '' ?>"
                     onclick="selectAvatar('<?= $i ?>.png', this)">
                    <img src="images/avatars/<?= $i ?>.png"
                         alt="Аватар <?= $i ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <div class="avatar-modal-actions">
                <button type="button" class="btn-avatar-cancel" onclick="closeAvatarModal()">Отмена</button>
                <button type="submit" class="btn-avatar-save"><i class="fas fa-check"></i> Сохранить</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAvatarModal()  { document.getElementById('avatar-modal').classList.add('open'); }
function closeAvatarModal() { document.getElementById('avatar-modal').classList.remove('open'); }
function selectAvatar(name, el) {
    document.querySelectorAll('.avatar-option').forEach(e => e.classList.remove('selected'));
    el.classList.add('selected');
    document.getElementById('avatar-input').value = name;
}
document.getElementById('avatar-modal').addEventListener('click', function(e) {
    if (e.target === this) closeAvatarModal();
});
</script>
</body>
</html>
