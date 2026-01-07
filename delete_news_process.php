<?php
session_start();
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in delete_news_process.php: \$pdo is NOT SET.");
    $response['message'] = 'Database connection error.';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['access_right']) || $_SESSION['access_right'] < 10) {
    $response['message'] = 'Access Denied.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_news_submit']) && isset($_POST['news_id'])) {
    $news_id_to_delete = filter_var($_POST['news_id'], FILTER_VALIDATE_INT);

    if (!$news_id_to_delete || $news_id_to_delete <= 0) {
        $response['message'] = 'Invalid News ID for deletion.';
        echo json_encode($response);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt_get_photo = $pdo->prepare("SELECT photo FROM news WHERE id = ?");
        $stmt_get_photo->execute([$news_id_to_delete]);
        $news_photo_path_relative = $stmt_get_photo->fetchColumn();
        $photo_full_path_to_delete = null;

        if ($news_photo_path_relative) {
            $photo_full_path_to_delete = __DIR__ . '/' . ltrim($news_photo_path_relative, '/');
        }

        $stmt_delete_news = $pdo->prepare("DELETE FROM news WHERE id = ?");
        $news_deleted = $stmt_delete_news->execute([$news_id_to_delete]);
        $deleted_rows_count = $stmt_delete_news->rowCount();

        if ($news_deleted && $deleted_rows_count > 0) {
            if ($photo_full_path_to_delete && file_exists($photo_full_path_to_delete)) {
                if (!@unlink($photo_full_path_to_delete)) {
                    error_log("Could not delete news image file: " . $photo_full_path_to_delete . " for news ID: " . $news_id_to_delete);
                    $response['warning_message'] = 'News article data deleted, but image file could not be removed.';
                }
            }

            $banner_keys_to_clear = ['banner1_id', 'banner2_id'];
            foreach ($banner_keys_to_clear as $banner_key) {
                $stmt_clear_banner = $pdo->prepare("UPDATE site_settings SET setting_value = NULL WHERE setting_key = ? AND setting_value = ?");
                $stmt_clear_banner->execute([$banner_key, (string)$news_id_to_delete]); // setting_value може бути рядком
            }

            $pdo->commit();
            $response['success'] = true;
            $response['message'] = 'News article deleted successfully!';
            if (isset($response['warning_message'])) {
                $response['message'] .= ' ' . $response['warning_message'];
            }

        } elseif ($news_deleted && $deleted_rows_count === 0) {
            $pdo->rollBack();
            $response['message'] = 'News article not found or already deleted.';
        } else {
            $pdo->rollBack();
            $response['message'] = 'Failed to delete news article from database.';
        }

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error deleting news article ID {$news_id_to_delete}: " . $e->getMessage());
        $response['message'] = 'Database error: Could not delete news article. ' . $e->getMessage();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("General error deleting news article ID {$news_id_to_delete}: " . $e->getMessage());
        $response['message'] = 'An error occurred during deletion: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request or missing parameters.';
}

echo json_encode($response);