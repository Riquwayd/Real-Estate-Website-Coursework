<?php
session_start();
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'user' => null, 'message' => 'Not logged in.'];

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    if (!isset($pdo) || !$pdo instanceof PDO) {
        error_log("FATAL ERROR in get_user_profile.php: \$pdo is NOT SET.");
        $response['message'] = 'Database connection error.';
        echo json_encode($response);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT login, name, email, access_right, (photo IS NOT NULL AND photo != '') AS has_photo FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch();

        if ($user_data) {
            $user_data['id'] = $user_id;
            $response['success'] = true;
            $response['user'] = $user_data;
            $response['message'] = 'Profile data fetched.';
        } else {
            $response['message'] = 'User not found.';
        }
    } catch (PDOException $e) {
        error_log("Error fetching user profile: " . $e->getMessage());
        $response['message'] = 'Database error fetching profile.';
    }
}

echo json_encode($response);