<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'news' => [], 'message' => 'An unknown error occurred.'];

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in get_all_news.php: \$pdo is NOT SET.");
    $response['message'] = 'Database connection error.';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, title, text, photo, date, source FROM news ORDER BY date DESC, id DESC LIMIT 50 ");
    $stmt->execute();
    $news_items = $stmt->fetchAll();

    $response['success'] = true;
    $response['news'] = $news_items;
    $response['message'] = 'All news items fetched successfully.';

} catch (PDOException $e) {
    error_log("Error fetching all news: " . $e->getMessage());
    $response['message'] = 'Database error: Could not retrieve news items. ' . $e->getMessage();
}

echo json_encode($response);