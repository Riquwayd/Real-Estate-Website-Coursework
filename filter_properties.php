<?php
require_once 'db_config.php';
header('Content-Type: application/json');

$response = ['success' => false, 'properties' => [], 'message' => 'No filter parameters received.'];

if (!isset($pdo) || !$pdo instanceof PDO) {
    error_log("FATAL ERROR in filter_properties.php: \$pdo is NOT SET or not a PDO instance after including db_config.php.");
    $response['message'] = 'Critical database connection error (PDO Object).';
    echo json_encode($response);
    exit;
}

// --- Отримання параметрів фільтра з GET-запиту ---
$listing_type_filter_array = $_GET['listing_type'] ?? [];
$status_filter_array = $_GET['status'] ?? [];
$mls_filter = trim($_GET['mls'] ?? '');
$city_filter_array = $_GET['city'] ?? [];
$state_filter_array = $_GET['state'] ?? [];
$zip_filter_array = $_GET['zip'] ?? [];
$price_min_str = trim($_GET['price_range_min'] ?? '');
$price_max_str = trim($_GET['price_range_max'] ?? '');
$bedrooms_filter_array = $_GET['bedrooms'] ?? [];
$bathrooms_filter_array = $_GET['bathrooms'] ?? [];

// Параметри для пошуку за радіусом
$selected_lat_str = trim($_GET['selected_lat'] ?? '');
$selected_lng_str = trim($_GET['selected_lng'] ?? '');
$search_radius_str = trim($_GET['search_radius'] ?? '');

// --- Перетворення та базова валідація параметрів ---
$price_min = (is_numeric($price_min_str) && $price_min_str !== '') ? (float)$price_min_str : null;
$price_max = (is_numeric($price_max_str) && $price_max_str !== '') ? (float)$price_max_str : null;

$center_lat = (is_numeric($selected_lat_str) && $selected_lat_str !== '') ? (float)$selected_lat_str : null;
$center_lon = (is_numeric($selected_lng_str) && $selected_lng_str !== '') ? (float)$selected_lng_str : null;
$radius_km = (is_numeric($search_radius_str) && $search_radius_str !== '' && (float)$search_radius_str > 0) ? (float)$search_radius_str : null;

// Валідація координат та радіуса
if (($center_lat !== null && ($center_lat < -90 || $center_lat > 90)) ||
    ($center_lon !== null && ($center_lon < -180 || $center_lon > 180))) {
    $center_lat = $center_lon = null; // Скидання не валідних координат
    error_log("Invalid coordinates for area search: lat=$selected_lat_str, lng=$selected_lng_str");
}
if ($radius_km !== null && ($radius_km <= 0 || $radius_km > 2000)) { // Обмеження радіуса (наприклад, до 2000 км)
    $radius_km = null;
    error_log("Invalid radius for area search: radius=$search_radius_str");
}

// Допоміжна функція для підготовки масивів рядкових значень для SQL IN clauses
function prepare_string_array_for_sql(array $input_array): array {
    $valid_values = [];
    if (!empty($input_array)) {
        foreach ($input_array as $val_str) {
            $trimmed_val = trim((string)$val_str);
            if (!empty($trimmed_val)) {
                $valid_values[] = $trimmed_val;
            }
        }
    }
    return $valid_values;
}

// Підготовка масивів для фільтрів
$valid_listing_types = prepare_string_array_for_sql($listing_type_filter_array);
$valid_statuses = prepare_string_array_for_sql($status_filter_array);
$valid_cities = prepare_string_array_for_sql($city_filter_array);
$valid_states = prepare_string_array_for_sql($state_filter_array);
$valid_zips = prepare_string_array_for_sql($zip_filter_array);

// Валідація для числових масивів (кімнати, ванні)
$valid_bedrooms = [];
if (is_array($bedrooms_filter_array) && !empty($bedrooms_filter_array)) {
    foreach ($bedrooms_filter_array as $bed_str) {
        if (ctype_digit((string)$bed_str) && (int)$bed_str >= 0) { $valid_bedrooms[] = (int)$bed_str; }
    }
}
$valid_bathrooms = [];
if (is_array($bathrooms_filter_array) && !empty($bathrooms_filter_array)) {
    foreach ($bathrooms_filter_array as $bath_str) {
        if (is_numeric($bath_str) && (float)$bath_str >= 0) { $valid_bathrooms[] = (float)$bath_str; } // Дозволяє дробові значення, напр. 1.5
    }
}

$sql_select_fields = "p.id, p.title, p.description, p.price, p.currency, p.listing_type, p.status, p.agent_id,
    (SELECT pp.image_path FROM property_photos pp WHERE pp.property_id = p.id AND pp.is_primary = 1 LIMIT 1) as primary_image,
    (SELECT pp.image_path FROM property_photos pp WHERE pp.property_id = p.id ORDER BY pp.sort_order ASC LIMIT 1) as first_image";

$sql_from_where = " FROM properties p WHERE 1=1";
$params = [];
$filters_applied = false;

// Динамічне додавання умов фільтрації
if (!empty($valid_listing_types)) {
    $placeholders = []; foreach ($valid_listing_types as $i => $value) { $key = ":listing_type_" . $i; $placeholders[] = $key; $params[$key] = $value; }
    $sql_from_where .= " AND p.listing_type IN (" . implode(',', $placeholders) . ")"; $filters_applied = true;
}
if (!empty($valid_statuses)) {
    $placeholders = []; foreach ($valid_statuses as $i => $value) { $key = ":status_" . $i; $placeholders[] = $key; $params[$key] = $value; }
    $sql_from_where .= " AND p.status IN (" . implode(',', $placeholders) . ")"; $filters_applied = true;
}
if (!empty($mls_filter)) { $sql_from_where .= " AND p.mls_number LIKE :mls"; $params[':mls'] = '%' . $mls_filter . '%'; $filters_applied = true; }
if (!empty($valid_cities)) {
    $placeholders = []; foreach ($valid_cities as $i => $value) { $key = ":city_" . $i; $placeholders[] = $key; $params[$key] = $value; }
    $sql_from_where .= " AND p.city IN (" . implode(',', $placeholders) . ")"; $filters_applied = true;
}
if (!empty($valid_states)) {
    $placeholders = []; foreach ($valid_states as $i => $value) { $key = ":state_" . $i; $placeholders[] = $key; $params[$key] = $value; }
    $sql_from_where .= " AND p.state IN (" . implode(',', $placeholders) . ")"; $filters_applied = true;
}
if (!empty($valid_zips)) {
    $placeholders = []; foreach ($valid_zips as $i => $value) { $key = ":zip_" . $i; $placeholders[] = $key; $params[$key] = $value; }
    $sql_from_where .= " AND p.zip_code IN (" . implode(',', $placeholders) . ")"; $filters_applied = true;
}
if ($price_min !== null) { $sql_from_where .= " AND p.price >= :price_min"; $params[':price_min'] = $price_min; $filters_applied = true; }
if ($price_max !== null) { $sql_from_where .= " AND p.price <= :price_max"; $params[':price_max'] = $price_max; $filters_applied = true; }
if (!empty($valid_bedrooms)) {
    $placeholders = []; foreach ($valid_bedrooms as $i => $value) { $key = ":bedrooms_" . $i; $placeholders[] = $key; $params[$key] = $value; }
    $sql_from_where .= " AND p.bedrooms IN (" . implode(',', $placeholders) . ")"; $filters_applied = true;
}
if (!empty($valid_bathrooms)) {
    $placeholders = []; foreach ($valid_bathrooms as $i => $value) { $key = ":bathrooms_" . $i; $placeholders[] = $key; $params[$key] = $value; }
    $sql_from_where .= " AND p.bathrooms IN (" . implode(',', $placeholders) . ")"; $filters_applied = true;
}

// Фільтрація за областю на карті (пошук за радіусом)
if ($center_lat !== null && $center_lon !== null && $radius_km !== null) {
    $filters_applied = true;

    // Додавання обчислення відстані до SELECT для сортування (якщо ще не додано)
    if (strpos($sql_select_fields, "AS distance") === false) {
        $sql_select_fields .= ", (
            6371 * ACOS(
                COS(RADIANS(:center_lat_dist)) * COS(RADIANS(p.latitude)) *
                COS(RADIANS(p.longitude) - RADIANS(:center_lon_dist)) +
                SIN(RADIANS(:center_lat_dist)) * SIN(RADIANS(p.latitude))
            )
        ) AS distance"; // 6371 - радіус Землі в км
        $params[':center_lat_dist'] = $center_lat;
        $params[':center_lon_dist'] = $center_lon;
    }

    // 1. Попередній фільтр за "що обмежує прямокутником" (Bounding Box) для оптимізації
    $earth_radius_km = 6371;
    $lat_rad = deg2rad($center_lat);

    $delta_lat_rad = $radius_km / $earth_radius_km;
    $delta_lon_rad = $radius_km / ($earth_radius_km * cos($lat_rad)); // Корекція для довготи залежно від широти

    $min_lat = rad2deg($lat_rad - $delta_lat_rad);
    $max_lat = rad2deg($lat_rad + $delta_lat_rad);
    $min_lon = rad2deg(deg2rad($center_lon) - $delta_lon_rad);
    $max_lon = rad2deg(deg2rad($center_lon) + $delta_lon_rad);

    $sql_from_where .= " AND (p.latitude BETWEEN :min_lat AND :max_lat)";
    $sql_from_where .= " AND (p.longitude BETWEEN :min_lon AND :max_lon)";
    $params[':min_lat'] = $min_lat; $params[':max_lat'] = $max_lat;
    $params[':min_lon'] = $min_lon; $params[':max_lon'] = $max_lon;

    // 2. Точний фільтр за колом (використовуючи формулу Гаверсинуса)
    // Застосовується до результатів, вже відфільтрованих за bounding box.
    $sql_from_where .= " AND (6371 * ACOS(
                            COS(RADIANS(:center_lat_cond)) * COS(RADIANS(p.latitude)) *
                            COS(RADIANS(p.longitude) - RADIANS(:center_lon_cond)) +
                            SIN(RADIANS(:center_lat_cond)) * SIN(RADIANS(p.latitude))
                        )) <= :radius_km_cond";
    $params[':center_lat_cond'] = $center_lat;
    $params[':center_lon_cond'] = $center_lon;
    $params[':radius_km_cond'] = $radius_km;
}

// --- Формування кінцевого SQL запиту ---
$sql_final = "SELECT " . $sql_select_fields . $sql_from_where;

// Додавання сортування
if ($center_lat !== null && $center_lon !== null && $radius_km !== null && strpos($sql_select_fields, "AS distance") !== false) {
    $sql_final .= " ORDER BY distance ASC, p.date_listed DESC, p.id DESC";
} else {
    $sql_final .= " ORDER BY p.date_listed DESC, p.id DESC";
}
$sql_final .= " LIMIT 20";

try {
    $stmt = $pdo->prepare($sql_final);
    $stmt->execute($params);
    $properties = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $response['success'] = true;
    $response['properties'] = $properties;

    // Формування повідомлення для користувача залежно від результатів
    if (count($properties) > 0) {
        $response['message'] = count($properties) . ' properties found.';
    } elseif ($filters_applied) {
        $response['message'] = 'No properties found matching your criteria.';
    } else {
        $response['message'] = 'Showing available properties. Please refine your search if needed.';
    }

} catch (PDOException $e) {
    error_log("Error filtering properties: " . $e->getMessage() . " --- SQL: " . $sql_final . " --- PARAMS: " . print_r($params, true));
    $response['message'] = 'Database error during search.';
} catch (Exception $e) {
    error_log("General error filtering properties: " . $e->getMessage());
    $response['message'] = 'Server error during search.';
}

echo json_encode($response);
