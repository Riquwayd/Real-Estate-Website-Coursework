<?php
session_start();
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in create_property_process.php: \$pdo is NOT SET or not a PDO instance after including db_config.php.");
    $response['message'] = 'Critical database connection error (PDO Object).';
    echo json_encode($response);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'Access Denied. You must be logged in to perform this action.';
    echo json_encode($response);
    exit;
}
$current_user_id = $_SESSION['user_id'];
$current_user_access_right = $_SESSION['access_right'] ?? 0;


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $editing_property_id = filter_input(INPUT_POST, 'editing_property_id', FILTER_VALIDATE_INT);
    $is_editing = ($editing_property_id && $editing_property_id > 0);

    $title = trim($_POST['prop_title'] ?? '');
    $mls_number = trim($_POST['prop_mls_number'] ?? '');
    $listing_type = in_array($_POST['prop_listing_type'] ?? '', ['sale', 'rent']) ? $_POST['prop_listing_type'] : null;
    $price_str = str_replace(',', '', trim($_POST['prop_price'] ?? ''));
    $price = filter_var($price_str, FILTER_VALIDATE_FLOAT, FILTER_NULL_ON_FAILURE);
    $description = trim($_POST['prop_description'] ?? '');

    $address1 = trim($_POST['prop_address1'] ?? '');
    $address2 = trim($_POST['prop_address2'] ?? '');
    $city = trim($_POST['prop_city'] ?? '');
    $state = trim($_POST['prop_state'] ?? '');
    $zip_code = trim($_POST['prop_zip'] ?? '');
    $latitude_str = trim($_POST['prop_latitude'] ?? '');
    $longitude_str = trim($_POST['prop_longitude'] ?? '');
    $latitude = (is_numeric($latitude_str) && $latitude_str !== '') ? (float)$latitude_str : null;
    $longitude = (is_numeric($longitude_str) && $longitude_str !== '') ? (float)$longitude_str : null;

    $property_type = trim($_POST['prop_type'] ?? '');
    $bedrooms_str = trim($_POST['prop_bedrooms'] ?? '');
    $bathrooms_str = trim($_POST['prop_bathrooms'] ?? '');
    $area_sqm_str = trim($_POST['prop_area_sqm'] ?? '');
    $lot_size_sqm_str = trim($_POST['prop_lot_size_sqm'] ?? '');
    $year_built_str = trim($_POST['prop_year_built'] ?? '');

    $bedrooms = ($bedrooms_str !== '' && ctype_digit($bedrooms_str)) ? (int)$bedrooms_str : null;
    $bathrooms = ($bathrooms_str !== '' && is_numeric($bathrooms_str)) ? (float)$bathrooms_str : null;
    $area_sqm = ($area_sqm_str !== '' && ctype_digit($area_sqm_str)) ? (int)$area_sqm_str : null;
    $lot_size_sqm = ($lot_size_sqm_str !== '' && ctype_digit($lot_size_sqm_str)) ? (int)$lot_size_sqm_str : null;
    $year_built = ($year_built_str !== '' && ctype_digit($year_built_str)) ? (int)$year_built_str : null;

    $date_listed_for_db = date('Y-m-d');
    $date_available_str = trim($_POST['prop_date_available'] ?? '');
    $date_available_for_db = NULL;
    if (!empty($date_available_str)) {
        $dateTimeObj = DateTime::createFromFormat('Y-m-d', $date_available_str);
        if ($dateTimeObj && $dateTimeObj->format('Y-m-d') === $date_available_str) {
            $date_available_for_db = $date_available_str;
        } else {
            $response['message'] = 'Invalid format for "Date Available". Please use YYYY-MM-DD or leave blank.';
            echo json_encode($response);
            exit;
        }
    }

    $contact_name_input = trim($_POST['prop_contact_name'] ?? '');
    $contact_email_input = trim($_POST['prop_contact_email'] ?? '');
    $contact_phone_input = trim($_POST['prop_contact_phone'] ?? '');

    $contact_name_for_db = $contact_name_input ?: ($_SESSION['user_name'] ?? NULL);
    $contact_email_for_db = NULL;

    if (!empty($contact_email_input)) {
        if (!filter_var($contact_email_input, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'The provided Contact Email format is invalid.';
            echo json_encode($response);
            exit;
        }
        $contact_email_for_db = $contact_email_input;
    } elseif (isset($_SESSION['user_email']) && filter_var($_SESSION['user_email'], FILTER_VALIDATE_EMAIL)) {
        $contact_email_for_db = $_SESSION['user_email'];
    } else if (isset($_SESSION['user_email'])){
        error_log("Invalid email format in session for user ID: " . ($current_user_id ?? 'Unknown'));
    }

    $status_input = $_POST['prop_status'] ?? 'active';
    $valid_statuses = ['active', 'pending', 'purchased', 'inactive'];
    $status = in_array($status_input, $valid_statuses) ? $status_input : 'active';


    if (empty($title) || empty($listing_type) || $price === null || empty($description) || empty($city)) {
        $response['message'] = 'Please fill in: Title, Listing Type, Price, Description, and City.';
        echo json_encode($response); exit;
    }
    if ($price < 0) { $response['message'] = 'Price cannot be negative.'; echo json_encode($response); exit; }
    if ($year_built && ($year_built < 1000 || $year_built > (int)date('Y') + 10)) { $response['message'] = 'Invalid year built.'; echo json_encode($response); exit; }
    if ($bedrooms !== null && $bedrooms < 0) { $response['message'] = 'Bedrooms cannot be negative.'; echo json_encode($response); exit;}
    if ($bathrooms !== null && $bathrooms < 0) { $response['message'] = 'Bathrooms cannot be negative.'; echo json_encode($response); exit;}
    if ($area_sqm !== null && $area_sqm < 0) { $response['message'] = 'Area cannot be negative.'; echo json_encode($response); exit;}
    if ($lot_size_sqm !== null && $lot_size_sqm < 0) { $response['message'] = 'Lot size cannot be negative.'; echo json_encode($response); exit;}

    if (!empty($mls_number)) {
        try {
            $sql_mls_check = "SELECT id FROM properties WHERE mls_number = ?";
            $params_mls_check = [$mls_number];
            if ($is_editing) {
                $sql_mls_check .= " AND id != ?";
                $params_mls_check[] = $editing_property_id;
            }
            $stmt_mls_check = $pdo->prepare($sql_mls_check);
            $stmt_mls_check->execute($params_mls_check);
            if ($stmt_mls_check->fetch()) {
                $response['message'] = 'This MLS Number is already in use.';
                echo json_encode($response);
                exit;
            }
        } catch (PDOException $e) {
            error_log("Error checking MLS number uniqueness: " . $e->getMessage());
            $response['message'] = 'Database error checking MLS number.';
            echo json_encode($response);
            exit;
        }
    }

    if ($is_editing) {
        try {
            $stmt_check_owner = $pdo->prepare("SELECT agent_id FROM properties WHERE id = ?");
            $stmt_check_owner->execute([$editing_property_id]);
            $property_to_edit = $stmt_check_owner->fetch();
            if (!$property_to_edit) {
                $response['message'] = 'Property to edit not found.';
                echo json_encode($response);
                exit;
            }
            if ($property_to_edit['agent_id'] != $current_user_id && $current_user_access_right < 10) {
                $response['message'] = 'Access Denied. You are not authorized to edit this property.';
                echo json_encode($response);
                exit;
            }
        } catch (PDOException $e) {
            error_log("Error checking property owner: " . $e->getMessage());
            $response['message'] = 'Database error checking property ownership.';
            echo json_encode($response);
            exit;
        }
    }

    $property_id_for_photos = null;
    $photos_to_delete_on_error = [];
    try {
        $pdo->beginTransaction();
        $property_id = null;

        if ($is_editing) {
            $sql_update = "UPDATE properties SET 
                title = :title, mls_number = :mls_number, description = :description, 
                address_line1 = :address1, address_line2 = :address2, city = :city, state = :state, zip_code = :zip_code,
                latitude = :latitude, longitude = :longitude, property_type = :property_type, 
                bedrooms = :bedrooms, bathrooms = :bathrooms, area_sqm = :area_sqm, lot_size_sqm = :lot_size_sqm,
                year_built = :year_built, listing_type = :listing_type, price = :price, 
                date_available = :date_available, status = :status, /* !!! ДОБАВЛЕН СТАТУС !!! */
                contact_name = :contact_name, contact_email = :contact_email, contact_phone = :contact_phone
            WHERE id = :property_id";

            $params_sql = [
                ':title' => $title, ':mls_number' => $mls_number ?: NULL, ':description' => $description,
                ':address1' => $address1 ?: NULL, ':address2' => $address2 ?: NULL, ':city' => $city, ':state' => $state ?: NULL, ':zip_code' => $zip_code ?: NULL,
                ':latitude' => $latitude, ':longitude' => $longitude, ':property_type' => $property_type ?: NULL,
                ':bedrooms' => $bedrooms, ':bathrooms' => $bathrooms, ':area_sqm' => $area_sqm, ':lot_size_sqm' => $lot_size_sqm,
                ':year_built' => $year_built, ':listing_type' => $listing_type, ':price' => $price,
                ':date_available' => $date_available_for_db, ':status' => $status, // !!! ДОБАВЛЕН СТАТУС !!!
                ':contact_name' => $contact_name_for_db, ':contact_email' => $contact_email_for_db, ':contact_phone' => $contact_phone_input ?: NULL,
                ':property_id' => $editing_property_id
            ];

            $stmt_update_prop = $pdo->prepare($sql_update);
            $stmt_update_prop->execute($params_sql);
            $property_id = $editing_property_id;
            $response['message'] = 'Property updated successfully!';

        } else {
            $sql_insert = "INSERT INTO properties 
                (title, mls_number, description, address_line1, address_line2, city, state, zip_code, 
                 latitude, longitude, property_type, bedrooms, bathrooms, area_sqm, lot_size_sqm,
                 year_built, listing_type, price, currency, agent_id, date_listed, date_available,
                 contact_name, contact_email, contact_phone /* , status - не нужен, default 'active' */) 
            VALUES 
                (:title, :mls_number, :description, :address1, :address2, :city, :state, :zip_code,
                 :latitude, :longitude, :property_type, :bedrooms, :bathrooms, :area_sqm, :lot_size_sqm,
                 :year_built, :listing_type, :price, 'USD', :agent_id, :date_listed, :date_available,
                 :contact_name, :contact_email, :contact_phone)";
            $stmt_insert_prop = $pdo->prepare($sql_insert);
            $params_sql = [
                ':title' => $title, ':mls_number' => $mls_number ?: NULL, ':description' => $description,
                ':address1' => $address1 ?: NULL, ':address2' => $address2 ?: NULL, ':city' => $city, ':state' => $state ?: NULL, ':zip_code' => $zip_code ?: NULL,
                ':latitude' => $latitude, ':longitude' => $longitude, ':property_type' => $property_type ?: NULL,
                ':bedrooms' => $bedrooms, ':bathrooms' => $bathrooms, ':area_sqm' => $area_sqm, ':lot_size_sqm' => $lot_size_sqm,
                ':year_built' => $year_built, ':listing_type' => $listing_type, ':price' => $price,
                ':agent_id' => $current_user_id,
                ':date_listed' => $date_listed_for_db, ':date_available' => $date_available_for_db,
                ':contact_name' => $contact_name_for_db, ':contact_email' => $contact_email_for_db, ':contact_phone' => $contact_phone_input ?: NULL
            ];
            $stmt_insert_prop->execute($params_sql);
            $property_id = $pdo->lastInsertId();
            $response['message'] = 'Property listed successfully!';
        }

        $new_photos_uploaded = false;
        if (isset($_FILES['prop_photos']) && is_array($_FILES['prop_photos']['name'])) {
            foreach ($_FILES['prop_photos']['error'] as $key => $error) {
                if ($error == UPLOAD_ERR_OK) { $new_photos_uploaded = true; break; }
            }
        }

        if ($new_photos_uploaded) {
            if ($is_editing) {
                $stmt_old_photos = $pdo->prepare("SELECT image_path FROM property_photos WHERE property_id = ?");
                $stmt_old_photos->execute([$property_id]);
                $old_photo_paths = $stmt_old_photos->fetchAll(PDO::FETCH_COLUMN);
                foreach ($old_photo_paths as $old_path) {
                    if ($old_path && file_exists(__DIR__ . '/' . $old_path)) { @unlink(__DIR__ . '/' . $old_path); }
                }
                $stmt_delete_photos_db = $pdo->prepare("DELETE FROM property_photos WHERE property_id = ?");
                $stmt_delete_photos_db->execute([$property_id]);
            }

            $upload_dir_prop = __DIR__ . '/images/properties/';
            if (!is_dir($upload_dir_prop)) { if(!mkdir($upload_dir_prop, 0775, true)) { throw new Exception('Failed to create property images directory.'); }}

            $photo_count = count($_FILES['prop_photos']['name']);
            if ($photo_count > 5) { throw new Exception('You can upload a maximum of 5 photos.'); }

            for ($i = 0; $i < $photo_count; $i++) {
                if (isset($_FILES['prop_photos']['error'][$i]) && $_FILES['prop_photos']['error'][$i] == UPLOAD_ERR_OK) {
                    $tmp_name = $_FILES['prop_photos']['tmp_name'][$i]; $original_name = $_FILES['prop_photos']['name'][$i]; $file_size = $_FILES['prop_photos']['size'][$i];
                    $allowed_mime_types_prop = ['image/jpeg', 'image/png', 'image/gif']; $file_info_prop = new finfo(FILEINFO_MIME_TYPE); $file_mime_type_prop = $file_info_prop->file($tmp_name);
                    if (in_array($file_mime_type_prop, $allowed_mime_types_prop)) {
                        if ($file_size <= 2 * 1024 * 1024) {
                            $file_extension_prop = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                            $unique_filename_prop = 'prop_' . $property_id . '_' . uniqid('', true) . '.' . $file_extension_prop;
                            $destination_prop = $upload_dir_prop . $unique_filename_prop;
                            if (move_uploaded_file($tmp_name, $destination_prop)) {
                                $image_path_db = 'images/properties/' . $unique_filename_prop;
                                $is_primary = ($i == 0); // Первое фото из набора - главное
                                $stmt_photo = $pdo->prepare("INSERT INTO property_photos (property_id, image_path, is_primary, sort_order) VALUES (?, ?, ?, ?)");
                                $stmt_photo->execute([$property_id, $image_path_db, $is_primary, $i]);
                            } else { throw new Exception('Failed to move photo: ' . htmlspecialchars($original_name)); }
                        } else { throw new Exception('Photo too large: ' . htmlspecialchars($original_name)); }
                    } else { throw new Exception('Invalid photo type: ' . htmlspecialchars($original_name)); }
                } elseif (isset($_FILES['prop_photos']['error'][$i]) && $_FILES['prop_photos']['error'][$i] != UPLOAD_ERR_NO_FILE) {
                    throw new Exception('Error uploading ' . htmlspecialchars($_FILES['prop_photos']['name'][$i] ?? 'file') . '. Code: ' . $_FILES['prop_photos']['error'][$i]);
                }
            }
        }

        $pdo->commit();
        $response['success'] = true;
        $response['property_id'] = $property_id;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Error creating/editing property: " . $e->getMessage());
        $response['message'] = 'Error: ' . $e->getMessage();
    }

} else {
    $response['message'] = 'Invalid request or form not submitted correctly.';
}

echo json_encode($response);