<?php
session_start();
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in delete_property_process.php: \$pdo is NOT SET.");
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
$current_user_access_right = $_SESSION['access_right'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_property_submit']) && isset($_POST['property_id'])) {
    $property_id_to_delete = filter_var($_POST['property_id'], FILTER_VALIDATE_INT);

    if (!$property_id_to_delete || $property_id_to_delete <= 0) {
        $response['message'] = 'Invalid Property ID for deletion.';
        echo json_encode($response);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt_check_owner = $pdo->prepare("SELECT agent_id FROM properties WHERE id = ?");
        $stmt_check_owner->execute([$property_id_to_delete]);
        $property_owner_data = $stmt_check_owner->fetch();

        if (!$property_owner_data) {
            $response['message'] = 'Property not found.';
            $pdo->rollBack();
            echo json_encode($response);
            exit;
        }

        if ($property_owner_data['agent_id'] != $current_user_id && $current_user_access_right < 10) {
            $response['message'] = 'Access Denied. You are not authorized to delete this property.';
            $pdo->rollBack();
            echo json_encode($response);
            exit;
        }

        $stmt_get_photos = $pdo->prepare("SELECT image_path FROM property_photos WHERE property_id = ?");
        $stmt_get_photos->execute([$property_id_to_delete]);
        $photo_paths = $stmt_get_photos->fetchAll(PDO::FETCH_COLUMN);

        $stmt_delete_db_photos = $pdo->prepare("DELETE FROM property_photos WHERE property_id = ?");
        $stmt_delete_db_photos->execute([$property_id_to_delete]);

        foreach ($photo_paths as $path) {
            if (!empty($path)) {
                $full_file_path = __DIR__ . '/' . ltrim($path, '/');
                if (file_exists($full_file_path)) {
                    if (!@unlink($full_file_path)) {
                        error_log("Could not delete property image file: " . $full_file_path . " for property ID: " . $property_id_to_delete);
                    }
                } else {
                    error_log("Property image file not found for deletion (already deleted or wrong path?): " . $full_file_path . " for property ID: " . $property_id_to_delete);
                }
            }
        }

        $stmt_delete_property = $pdo->prepare("DELETE FROM properties WHERE id = ?");
        if ($stmt_delete_property->execute([$property_id_to_delete])) {
            if ($stmt_delete_property->rowCount() > 0) {
                $response['success'] = true;
                $response['message'] = 'Property deleted successfully!';
            } else {
                $response['message'] = 'Property not found or already deleted during the process.';
            }
        } else {
            $response['message'] = 'Failed to delete property from database.';
        }

        $pdo->commit();

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error deleting property: " . $e->getMessage());
        $response['message'] = 'Database error: Could not delete property. ' . $e->getMessage();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("General error deleting property: " . $e->getMessage());
        $response['message'] = 'An error occurred: ' . $e->getMessage();
    }
} else {
    $response['message'] = 'Invalid request or missing parameters for deletion.';
}

echo json_encode($response);