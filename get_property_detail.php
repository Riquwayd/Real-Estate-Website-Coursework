<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'property' => null, 'message' => 'Property ID not provided.'];

if (isset($_GET['id'])) {
    $property_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);

    if ($property_id && $property_id > 0) {
        if (!isset($pdo) || !$pdo instanceof PDO) {
            error_log("FATAL ERROR in get_property_detail.php: \$pdo is NOT SET.");
            $response['message'] = 'Database connection error.';
            echo json_encode($response);
            exit;
        }

        try {
            $stmt_prop = $pdo->prepare("SELECT * FROM properties WHERE id = ?");
            $stmt_prop->execute([$property_id]);
            $property_data = $stmt_prop->fetch();

            if ($property_data) {
                $stmt_photos = $pdo->prepare("SELECT id, image_path, caption, is_primary FROM property_photos WHERE property_id = ? ORDER BY sort_order ASC, is_primary DESC");
                $stmt_photos->execute([$property_id]);
                $property_data['photos'] = $stmt_photos->fetchAll();
                if (is_array($property_data['photos'])) {
                    foreach ($property_data['photos'] as $key => $photo) {
                        $property_data['photos'][$key]['is_primary'] = (bool)$photo['is_primary'];
                    }
                }

                $response['success'] = true;
                $response['property'] = $property_data;
                $response['message'] = 'Property details fetched successfully.';
            } else {
                $response['message'] = 'Property not found.';
            }
        } catch (PDOException $e) {
            error_log("Error fetching property detail: " . $e->getMessage());
            $response['message'] = 'Database error: Could not retrieve property details. ' . $e->getMessage();
        }
    } else {
        $response['message'] = 'Invalid Property ID provided.';
    }
}

echo json_encode($response);