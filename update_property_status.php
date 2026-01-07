<?php
session_start();
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in update_property_status.php: \$pdo is NOT SET.");
    $response['message'] = 'Database connection error.';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Access Denied. You must be logged in.';
    echo json_encode($response);
    exit;
}
$current_user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_pending_submit'])) {
    $property_id = filter_input(INPUT_POST, 'property_id', FILTER_VALIDATE_INT);
    $new_status = trim($_POST['new_status'] ?? '');

    if (!$property_id || $property_id <= 0) {
        $response['message'] = 'Invalid Property ID.';
        echo json_encode($response);
        exit;
    }
    if ($new_status !== 'pending') {
        $response['message'] = 'Invalid status update requested.';
        echo json_encode($response);
        exit;
    }

    try {
        // Перевірка поточного статусу нерухомості перед оновленням
        $stmt_check = $pdo->prepare("SELECT status, agent_id FROM properties WHERE id = ?");
        $stmt_check->execute([$property_id]);
        $property = $stmt_check->fetch();

        if (!$property) {
            $response['message'] = 'Property not found.';
            echo json_encode($response);
            exit;
        }

        if ($property['status'] !== 'active') {
            $response['message'] = 'This property is no longer active and cannot be processed for ' . htmlspecialchars($_POST['action_type'] ?? 'this action') . '.';
            echo json_encode($response);
            exit;
        }

        if ($property['agent_id'] == $current_user_id && ($_SESSION['access_right'] ?? 0) < 10) {
            $response['message'] = 'You cannot perform this action on your own listing.';
            echo json_encode($response);
            exit;
        }

        $stmt_update = $pdo->prepare("UPDATE properties SET status = ? WHERE id = ? AND status = 'active'");
        if ($stmt_update->execute([$new_status, $property_id])) {
            if ($stmt_update->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'Property status updated to pending.';
            } else {
                $response['message'] = 'Could not update property status. It might have been updated recently or is not active.';
            }
        } else {
            $response['message'] = 'Failed to update property status in database.';
        }
    } catch (PDOException $e) {
        error_log("Error updating property status: " . $e->getMessage());
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request.';
}

echo json_encode($response);
