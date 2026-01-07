<?php
$placeholder_path = __DIR__ . '/images/ico/nophoto.jpg'; // Стандартна заглушка

// Функція для посилання зображення-заглушки
function send_placeholder_image($path_to_placeholder) {
    if (file_exists($path_to_placeholder)) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime_type = $finfo->file($path_to_placeholder);
        header("Content-Type: " . ($mime_type ?: 'image/jpeg'));
        readfile($path_to_placeholder);
    } else {
        // Якщо навіть файл-заглушка не знайдений, генеруємо просте зображення
        error_log("Placeholder image NOT FOUND at: " . $path_to_placeholder . " in get_user_photo.php");
        header("Content-Type: image/png");
        $im = imagecreatetruecolor(60, 60);
        $bg = imagecolorallocate($im, 200, 200, 200); // Сірий фон
        imagefill($im, 0, 0, $bg);
        imagepng($im);
        imagedestroy($im);
    }
    exit;
}

$db_config_path = __DIR__ . '/db_config.php';
if (file_exists($db_config_path)) {
    require_once $db_config_path;
} else {
    error_log("FATAL ERROR in get_user_photo.php: db_config.php NOT FOUND at " . $db_config_path);
    send_placeholder_image($placeholder_path);
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in get_user_photo.php: \$pdo is NOT SET or not a PDO instance after including db_config.php.");
    send_placeholder_image($placeholder_path);
}

// Отримання та валідація ID користувача з GET-параметра
$user_id_from_get = $_GET['id'] ?? 0;
$user_id = filter_var($user_id_from_get, FILTER_VALIDATE_INT);

if ($user_id && $user_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT photo FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_photo_data = $stmt->fetchColumn();

        if ($user_photo_data) {
            // Визначення MIME-типу зображення з BLOB-даних
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->buffer($user_photo_data);

            // Перевірка, чи отриманий MIME-тип є зображенням
            $is_image = $mime_type && (strpos($mime_type, 'image/') === 0);

            if ($is_image) {
                header("Content-Type: " . $mime_type);
                echo $user_photo_data;
                exit;
            } else {
                error_log("Invalid MIME type for user ID $user_id: " . ($mime_type ?: 'empty') . " in get_user_photo.php. Serving placeholder.");
            }
        }
    } catch (PDOException $e) {
        error_log("Photo retrieval PDOException for user ID $user_id: " . $e->getMessage() . " in get_user_photo.php. Serving placeholder.");
    }
}

send_placeholder_image($placeholder_path);
