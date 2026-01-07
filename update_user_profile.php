<?php
session_start();
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Перевірка, чи користувач авторизований
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Not logged in. Cannot update profile.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];

// Перевірка з'єднання з базою даних
if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in update_user_profile.php: \$pdo is NOT SET.");
    $response['message'] = 'Database connection error.';
    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_submit'])) {
    $name = trim($_POST['profile_name'] ?? '');
    $email = trim($_POST['profile_email'] ?? '');
    $current_password_input = $_POST['profile_current_password'] ?? '';
    $new_password = $_POST['profile_new_password'] ?? '';
    $confirm_password = $_POST['profile_confirm_password'] ?? '';
    $photo_updated_flag = false;
    $photo_data_for_db = null;

    // Валідація вхідних даних
    if (empty($name)) {
        $response['message'] = 'Display Name cannot be empty.';
        echo json_encode($response);
        exit;
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Valid Email is required.';
        echo json_encode($response);
        exit;
    }

    // Отримання поточних даних користувача
    try {
        $stmt_current = $pdo->prepare("SELECT email, pass FROM users WHERE id = ?");
        $stmt_current->execute([$user_id]);
        $current_user_data = $stmt_current->fetch();
        if (!$current_user_data) {
            $response['message'] = 'User not found.';
            echo json_encode($response);
            exit;
        }
    } catch (PDOException $e) {
        error_log("Error fetching current user data for profile update: " . $e->getMessage());
        $response['message'] = 'Database error. Could not verify current data.';
        echo json_encode($response);
        exit;
    }

    $email_changed = ($email !== $current_user_data['email']);
    $password_change_attempted = !empty($new_password);

    // Якщо змінюється email або пароль, потрібен поточний пароль
    if ($email_changed || $password_change_attempted) {
        if (empty($current_password_input)) {
            $response['message'] = 'Current password is required to change email or password.';
            echo json_encode($response);
            exit;
        }
        if (!password_verify($current_password_input, $current_user_data['pass'])) {
            $response['message'] = 'Incorrect current password.';
            echo json_encode($response);
            exit;
        }
    }

    // Перевірка унікальності email, якщо він був змінений
    if ($email_changed) {
        try {
            $stmt_email_check = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt_email_check->execute([$email, $user_id]);
            if ($stmt_email_check->fetch()) {
                $response['message'] = 'This email address is already in use by another account.';
                echo json_encode($response);
                exit;
            }
        } catch (PDOException $e) {
            error_log("Error checking email uniqueness: " . $e->getMessage());
            $response['message'] = 'Database error checking email.';
            echo json_encode($response);
            exit;
        }
    }

    // Обробка зміни пароля
    $new_hashed_password = null;
    if ($password_change_attempted) {
        if ($new_password !== $confirm_password) {
            $response['message'] = 'New passwords do not match.';
            echo json_encode($response);
            exit;
        }
        if (strlen($new_password) < 6) {
            $response['message'] = 'New password must be at least 6 characters long.';
            echo json_encode($response);
            exit;
        }
        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    }

    // Обробка завантаження фото
    $photo_sql_part = "";
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == UPLOAD_ERR_OK) {
        $allowed_mime_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_info = new finfo(FILEINFO_MIME_TYPE);
        $file_mime_type = $file_info->file($_FILES['profile_photo']['tmp_name']);

        if (in_array($file_mime_type, $allowed_mime_types)) {
            if ($_FILES['profile_photo']['size'] <= 2 * 1024 * 1024) { // Максимум 2MB
                $photo_data_for_db = file_get_contents($_FILES['profile_photo']['tmp_name']);
                if ($photo_data_for_db === false) {
                    $response['message'] = 'Failed to read uploaded photo file.'; echo json_encode($response); exit;
                }
                $photo_sql_part = ", photo = :photo";
                $photo_updated_flag = true;
            } else { $response['message'] = 'Photo is too large (max 2MB).'; echo json_encode($response); exit; }
        } else { $response['message'] = 'Invalid photo file type. Allowed: JPG, PNG, GIF.'; echo json_encode($response); exit; }
    } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] != UPLOAD_ERR_NO_FILE) {
        $response['message'] = 'Error uploading photo: ' . $_FILES['profile_photo']['error']; echo json_encode($response); exit;
    }

    // Формування SQL запиту на оновлення
    $sql_update = "UPDATE users SET name = :name, email = :email";

    if ($new_hashed_password) {
        $sql_update .= ", pass = :pass";
    }
    if ($photo_sql_part) {
        $sql_update .= $photo_sql_part;
    }
    $sql_update .= " WHERE id = :user_id";

    // Оновлення даних в БД
    try {
        $stmt_update = $pdo->prepare($sql_update);

        $stmt_update->bindParam(':name', $name);
        $stmt_update->bindParam(':email', $email);
        $stmt_update->bindParam(':user_id', $user_id, PDO::PARAM_INT);

        if ($new_hashed_password) {
            $stmt_update->bindParam(':pass', $new_hashed_password);
        }
        if ($photo_data_for_db !== null) {
            $stmt_update->bindParam(':photo', $photo_data_for_db, PDO::PARAM_LOB);
        }

        if ($stmt_update->execute()) {
            $response['success'] = true;
            $response['message'] = 'Profile updated successfully!';
            $_SESSION['user_name'] = $name;
            if ($email_changed) $_SESSION['user_email'] = $email;
            $response['new_name'] = $name;
            if($photo_updated_flag) $response['photo_updated'] = true;
        } else {
            $response['message'] = 'Failed to update profile in database.';
        }
    } catch (PDOException $e) {
        error_log("Error updating profile: " . $e->getMessage());
        if ($e->errorInfo[1] == 1062) {
            $response['message'] = 'This email address is already in use.';
        } else {
            $response['message'] = 'Database error: Could not update profile. ' . $e->getMessage();
        }
    }
} else {
    $response['message'] = 'Invalid request.';
}

echo json_encode($response);