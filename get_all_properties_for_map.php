<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'properties' => [], 'message' => 'An unknown error occurred.'];

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in get_all_properties_for_map.php: \$pdo is NOT SET.");
    $response['message'] = 'Database connection error.';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, title, latitude, longitude, status, listing_type FROM properties 
        WHERE latitude IS NOT NULL AND longitude IS NOT NULL AND status != 'inactive' ORDER BY date_listed DESC ");
    $stmt->execute();
    $properties = $stmt->fetchAll();

    $response['success'] = true;
    $response['properties'] = $properties;
    $response['message'] = 'Properties for map fetched successfully.';

} catch (PDOException $e) {
    error_log("Error fetching properties for map: " . $e->getMessage());
    $response['message'] = 'Database error: Could not retrieve properties for map. ' . $e->getMessage();
}

echo json_encode($response);