<?php
session_start(); // Запуск сесії

// Підключення конфігурації бази даних
$db_config_path = __DIR__ . '/db_config.php';
if (file_exists($db_config_path)) {
    require_once $db_config_path;
} else {
    error_log("FATAL ERROR in update_selected_news.php: db_config.php NOT FOUND at " . $db_config_path);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Critical server configuration error (DB Config).']);
    exit;
}

// Критична перевірка наявності об'єкта PDO одразу після підключення db_config.php
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in update_selected_news.php: \$pdo is NOT SET or not a PDO instance immediately after including db_config.php.");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Critical database connection error (PDO Object).']);
    exit;
}

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

// Перевірка прав доступу користувача
if (!isset($_SESSION['access_right']) || $_SESSION['access_right'] != 10) {
    $response['message'] = 'Access denied.';
    echo json_encode($response);
    exit;
}

// Отримання та валідація вхідних даних
$box_key = $_POST['box_key'] ?? '';
$news_id_raw = $_POST['news_id'] ?? null;
// Валідація news_id: має бути позитивним цілим числом, інакше null
$news_id = ($news_id_raw !== null && $news_id_raw !== '' && ctype_digit((string)$news_id_raw) && (int)$news_id_raw > 0) ? (int)$news_id_raw : null;

// Валідація ключа блоку
if (empty($box_key) || !in_array($box_key, ['banner1_id', 'banner2_id'])) {
    $response['message'] = 'Invalid box key specified.';
    echo json_encode($response);
    exit;
}

// Якщо news_id наданий, перевіряємо його існування в базі
if ($news_id !== null) {
    try {
        $stmt_check = $pdo->prepare("SELECT id FROM news WHERE id = ?");
        $stmt_check->execute([$news_id]);
        if (!$stmt_check->fetch()) {
            $response['message'] = 'Selected news article (ID: '.$news_id.') does not exist.';
            echo json_encode($response);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error checking news ID in update_selected_news.php: " . $e->getMessage());
        $response['message'] = 'Database error while checking news ID.';
        echo json_encode($response);
        exit;
    }
}

// Оновлення налаштування в базі даних
try {
    $stmt = $pdo->prepare("UPDATE site_settings SET setting_value = :news_id WHERE setting_key = :box_key");
    if ($stmt->execute(['news_id' => $news_id, 'box_key' => $box_key])) {
        $response['success'] = true;
        $response['message'] = 'Banner news selection updated successfully.';
    } else {
        $response['message'] = 'Failed to update database setting.';
    }
} catch (PDOException $e) {
    error_log("Error in update_selected_news.php: " . $e->getMessage());
    $response['message'] = 'Database error while updating setting.';
}


echo json_encode($response);
