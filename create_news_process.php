<?php
session_start();
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in create_news_process.php: \$pdo is NOT SET.");
    $response['message'] = 'Database connection error.';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['access_right']) || $_SESSION['access_right'] < 10) {
    $response['message'] = 'Access Denied.';
    echo json_encode($response);
    exit;
}

// Визначення, чи це створення нової новини, чи редагування що існує
$editing_news_id = filter_input(INPUT_POST, 'editing_news_id', FILTER_VALIDATE_INT);
$is_editing = ($editing_news_id && $editing_news_id > 0);

// Обробка POST-запиту
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Отримання даних з форми
    $title = trim($_POST['news_title'] ?? '');
    $text = trim($_POST['news_text'] ?? '');
    $date_str = trim($_POST['news_date'] ?? '');
    $source = trim($_POST['news_source'] ?? '');
    $new_photo_path_relative = null;
    $old_photo_path_full = null;

    if (empty($title) || empty($text) || empty($date_str)) {
        $response['message'] = 'Title, Text, and Date are required.';
        echo json_encode($response);
        exit;
    }

    $date_obj = DateTime::createFromFormat('Y-m-d', $date_str);
    if ($date_obj === false || $date_obj->format('Y-m-d') !== $date_str) {
        $response['message'] = 'Invalid date format. Please use YYYY-MM-DD.';
        echo json_encode($response);
        exit;
    }

    // Якщо це редагування, отримуємо поточний шлях до фото
    if ($is_editing) {
        try {
            $stmt_curr = $pdo->prepare("SELECT photo FROM news WHERE id = ?");
            $stmt_curr->execute([$editing_news_id]);
            $current_news_data = $stmt_curr->fetch(PDO::FETCH_ASSOC);
            if ($current_news_data) {
                if (!empty($current_news_data['photo'])) {
                    $old_photo_path_full = __DIR__ . '/' . ltrim($current_news_data['photo'], '/');
                }
                $new_photo_path_relative = $current_news_data['photo']; // За замовчуванням залишаємо старе фото
            } else {
                $response['message'] = 'News item to edit not found.';
                echo json_encode($response);
                exit;
            }
        } catch (PDOException $e) {
            error_log("DB error fetching current photo for news ID $editing_news_id: " . $e->getMessage());
            $response['message'] = 'Database error: Could not retrieve current news data.';
            echo json_encode($response);
            exit;
        }
    }

    $file_upload_succeeded = false;
    if (isset($_FILES['news_photo']) && $_FILES['news_photo']['error'] == UPLOAD_ERR_OK) {
        $upload_dir_relative = 'images/news/';
        $upload_dir_full = __DIR__ . '/' . $upload_dir_relative;

        if (!is_dir($upload_dir_full)) {
            if (!mkdir($upload_dir_full, 0775, true)) {
                $response['message'] = 'Failed to create image directory.';
                echo json_encode($response);
                exit;
            }
        }

        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_info = new finfo(FILEINFO_MIME_TYPE);
        $file_mime_type = $file_info->file($_FILES['news_photo']['tmp_name']);

        if (in_array($file_mime_type, $allowed_mime_types)) {
            if ($_FILES['news_photo']['size'] <= 2 * 1024 * 1024) {
                $file_extension = strtolower(pathinfo($_FILES['news_photo']['name'], PATHINFO_EXTENSION));
                $unique_filename = uniqid('newsimg_', true) . '.' . $file_extension;
                $destination_full = $upload_dir_full . $unique_filename;

                if (move_uploaded_file($_FILES['news_photo']['tmp_name'], $destination_full)) {
                    $new_photo_path_relative = $upload_dir_relative . $unique_filename;
                    $file_upload_succeeded = true;
                } else {
                    $response['message'] = 'Failed to move uploaded photo to destination.';
                    echo json_encode($response);
                    exit;
                }
            } else {
                $response['message'] = 'Uploaded photo is too large (max 2MB).';
                echo json_encode($response);
                exit;
            }
        } else {
            $response['message'] = 'Invalid photo file type. Allowed: JPG, PNG, GIF.';
            echo json_encode($response);
            exit;
        }
    } elseif (isset($_FILES['news_photo']) && $_FILES['news_photo']['error'] != UPLOAD_ERR_NO_FILE) {
        $response['message'] = 'Error uploading photo: ' . $_FILES['news_photo']['error'];
        echo json_encode($response);
        exit;
    }

    try {
        $pdo->beginTransaction();

        if ($is_editing) {
            $stmt = $pdo->prepare("UPDATE news SET title = ?, text = ?, photo = ?, date = ?, source = ? WHERE id = ?");
            $success = $stmt->execute([$title, $text, $new_photo_path_relative, $date_str, $source ?: NULL, $editing_news_id]);
            $action_message = 'News article updated successfully!';
        } else {
            $stmt = $pdo->prepare("INSERT INTO news (title, text, photo, date, source) VALUES (?, ?, ?, ?, ?)");
            $success = $stmt->execute([$title, $text, $new_photo_path_relative, $date_str, $source ?: NULL]);
            $action_message = 'News article created successfully!';
        }

        if ($success) {
            if ($file_upload_succeeded && $is_editing && $old_photo_path_full && file_exists($old_photo_path_full)) {
                if (!@unlink($old_photo_path_full)) {
                    error_log("Could not delete old news image: " . $old_photo_path_full . " for news ID " . $editing_news_id);
                }
            }
            $pdo->commit();
            $response['success'] = true;
            $response['message'] = $action_message;
        } else {
            $pdo->rollBack();
            $response['message'] = $is_editing ? 'Failed to update news article in database.' : 'Failed to create news article in database.';
            if ($file_upload_succeeded && $new_photo_path_relative) {
                $orphan_photo_full_path = __DIR__ . '/' . ltrim($new_photo_path_relative, '/');
                if (file_exists($orphan_photo_full_path)) @unlink($orphan_photo_full_path);
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Error saving news article (ID: " . ($editing_news_id ?: 'new') . "): " . $e->getMessage());
        $response['message'] = 'Database error: Could not save news article. ' . $e->getMessage();
        if ($file_upload_succeeded && $new_photo_path_relative) {
            $orphan_photo_full_path = __DIR__ . '/' . ltrim($new_photo_path_relative, '/');
            if (file_exists($orphan_photo_full_path)) @unlink($orphan_photo_full_path);
        }
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("General error saving news (ID: " . ($editing_news_id ?: 'new') . "): " . $e->getMessage());
        $response['message'] = 'An unexpected error occurred: ' . $e->getMessage();
        if ($file_upload_succeeded && $new_photo_path_relative) {
            $orphan_photo_full_path = __DIR__ . '/' . ltrim($new_photo_path_relative, '/');
            if (file_exists($orphan_photo_full_path)) @unlink($orphan_photo_full_path);
        }
    }
} else {
    $response['message'] = 'Invalid request.';
}

echo json_encode($response);
