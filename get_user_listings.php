<?php
session_start();
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'listings' => [], 'message' => 'An unknown error occurred.'];

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in get_user_listings.php: \$pdo is NOT SET.");
    $response['message'] = 'Database connection error.';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Not logged in.';
    echo json_encode($response);
    exit;
}

$current_user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id, p.title, p.listing_type, p.price, p.status,
            (SELECT pp.image_path FROM property_photos pp WHERE pp.property_id = p.id ORDER BY pp.is_primary DESC, pp.sort_order ASC LIMIT 1) as first_image
        FROM properties p
        WHERE p.agent_id = ?
        ORDER BY p.date_listed DESC, p.id DESC
    ");
    $stmt->execute([$current_user_id]);
    $listings = $stmt->fetchAll();

    $response['success'] = true;
    $response['listings'] = $listings;
    $response['message'] = 'User listings fetched successfully.';

} catch (PDOException $e) {
    error_log("Error fetching user listings: " . $e->getMessage());
    $response['message'] = 'Database error: Could not retrieve your listings. ' . $e->getMessage();
}

echo json_encode($response);