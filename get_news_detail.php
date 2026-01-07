<?php
require_once 'db_config.php';

header('Content-Type: application/json');
$response = ['success' => false, 'news' => null, 'message' => 'News ID not provided.'];

if (isset($_GET['id'])) {
    $news_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($news_id && $news_id > 0) {
        if (!isset($pdo) || !$pdo instanceof PDO) {
            error_log("FATAL ERROR in get_news_detail.php: \$pdo is NOT SET or not a PDO instance.");
            $response['message'] = 'Database connection error.';
            echo json_encode($response);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, title, text, photo, date, source FROM news WHERE id = ?");
            $stmt->execute([$news_id]);
            $news_item = $stmt->fetch();

            if ($news_item) {
                $response['success'] = true;
                $response['news'] = $news_item;
                $response['message'] = 'News item fetched successfully.';
            } else {
                $response['message'] = 'News item not found.';
            }
        } catch (PDOException $e) {
            error_log("Error fetching news detail: " . $e->getMessage());
            $response['message'] = 'Database error: Could not retrieve news details. ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Invalid News ID provided.';
    }
}

echo json_encode($response);