<?php
session_start();

$db_config_path = __DIR__ . '/db_config.php';
if (file_exists($db_config_path)) {
    require_once $db_config_path;
} else {
    error_log("FATAL ERROR in get_news_for_selection.php: db_config.php NOT FOUND at " . $db_config_path);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'news' => [], 'message' => 'Critical server configuration error (DB Config).']);
    exit;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in get_news_for_selection.php: \$pdo is NOT SET or not a PDO instance immediately after including db_config.php.");
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'news' => [], 'message' => 'Critical database connection error (PDO Object).']);
    exit;
}

header('Content-Type: application/json');

$response = ['success' => false, 'news' => [], 'message' => ''];

if (!isset($_SESSION['access_right']) || $_SESSION['access_right'] != 10) {
    $response['message'] = 'Access denied.';
    echo json_encode($response);
    exit;
}

$searchTerm = trim($_GET['search'] ?? '');

try {
    if (!empty($searchTerm)) {
        $stmt = $pdo->prepare("SELECT id, title, DATE_FORMAT(date, '%Y-%m-%d') as date_formatted FROM news WHERE title LIKE ? ORDER BY date DESC, id DESC LIMIT 50");
        $stmt->execute(['%' . $searchTerm . '%']);
    } else {
        $stmt = $pdo->prepare("SELECT id, title, DATE_FORMAT(date, '%Y-%m-%d') as date_formatted FROM news ORDER BY date DESC, id DESC LIMIT 50");
        $stmt->execute();
    }
    $response['news'] = $stmt->fetchAll();
    $response['success'] = true;
} catch (PDOException $e) {
    error_log("Error in get_news_for_selection.php: " . $e->getMessage());
    $response['message'] = 'Database error while fetching news.';
}

echo json_encode($response);
