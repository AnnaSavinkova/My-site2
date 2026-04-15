<?php
// admin_upload_image.php — загрузка и удаление фото блюд
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';
require_once 'check_admin.php';

header('Content-Type: application/json; charset=utf-8');

function jsonOut(array $data): void {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$dishId = (int)($_POST['dish_id'] ?? $_GET['dish_id'] ?? 0);

// ── Папка для изображений ─────────────────────────────────────────────────
$uploadDir = __DIR__ . '/images/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// ── UPLOAD ────────────────────────────────────────────────────────────────
if ($action === 'upload') {
    if ($dishId <= 0) jsonOut(['success' => false, 'error' => 'Не указан dish_id']);
    if (empty($_FILES['image'])) jsonOut(['success' => false, 'error' => 'Файл не передан']);

    $file    = $_FILES['image'];
    $error   = $file['error'];
    $tmpPath = $file['tmp_name'];

    if ($error !== UPLOAD_ERR_OK) {
        $msgs = [
            UPLOAD_ERR_INI_SIZE   => 'Файл превышает upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE  => 'Файл превышает MAX_FILE_SIZE формы',
            UPLOAD_ERR_PARTIAL    => 'Файл загружен частично',
            UPLOAD_ERR_NO_FILE    => 'Файл не выбран',
            UPLOAD_ERR_NO_TMP_DIR => 'Нет временной папки',
            UPLOAD_ERR_CANT_WRITE => 'Нет прав на запись',
        ];
        jsonOut(['success' => false, 'error' => $msgs[$error] ?? "Ошибка загрузки ($error)"]);
    }

    // Проверка MIME
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($tmpPath);
    $allowed  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) {
        jsonOut(['success' => false, 'error' => 'Разрешены только JPG, PNG, GIF, WEBP']);
    }

    // Проверка размера (5 МБ)
    if ($file['size'] > 5 * 1024 * 1024) {
        jsonOut(['success' => false, 'error' => 'Размер файла не должен превышать 5 МБ']);
    }

    $ext      = $allowed[$mime];
    $filename = 'dish_' . $dishId . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . $filename;

    if (!move_uploaded_file($tmpPath, $destPath)) {
        jsonOut(['success' => false, 'error' => 'Не удалось сохранить файл']);
    }

    // Удаляем старое фото (если не дефолтное)
    $old = $pdo->prepare("SELECT image FROM dishes WHERE id = ?");
    $old->execute([$dishId]);
    $oldImage = $old->fetchColumn();
    if ($oldImage && $oldImage !== 'dish-default.jpg' && file_exists($uploadDir . $oldImage)) {
        @unlink($uploadDir . $oldImage);
    }

    // Обновляем БД
    $pdo->prepare("UPDATE dishes SET image = ? WHERE id = ?")->execute([$filename, $dishId]);

    jsonOut(['success' => true, 'filename' => $filename, 'url' => 'images/' . $filename]);
}

// ── DELETE ────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    if ($dishId <= 0) jsonOut(['success' => false, 'error' => 'Не указан dish_id']);

    $stmt = $pdo->prepare("SELECT image FROM dishes WHERE id = ?");
    $stmt->execute([$dishId]);
    $image = $stmt->fetchColumn();

    if ($image && $image !== 'dish-default.jpg' && file_exists($uploadDir . $image)) {
        @unlink($uploadDir . $image);
    }

    $pdo->prepare("UPDATE dishes SET image = 'dish-default.jpg' WHERE id = ?")->execute([$dishId]);

    jsonOut(['success' => true]);
}

jsonOut(['success' => false, 'error' => 'Неизвестный action']);
