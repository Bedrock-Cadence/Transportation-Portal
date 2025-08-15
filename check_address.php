<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../app/db_connect.php';
$api_key = GOOGLE_MAPS_API_KEY;

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json);

$street = $data->street ?? '';
$city = $data->city ?? '';
$state = $data->state ?? '';
$zip = $data->zip ?? '';

if (empty($street) || empty($city) || empty($state) || empty($zip)) {
    echo json_encode(['error' => 'Missing address data.']);
    exit;
}

$address = urlencode("$street, $city, $state $zip");
$url = "https://maps.googleapis.com/maps/api/geocode/json?address=$address&key=$api_key";

// Use cURL for a more robust request than file_get_contents
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$response = curl_exec($ch);
curl_close($ch);

$decoded_response = json_decode($response, true);

$is_facility = false;
$standardized_address = '';
$standardized_city = '';
$standardized_state = '';
$standardized_zip = '';
$standardized_street = '';

if ($decoded_response['status'] == 'OK' && !empty($decoded_response['results'])) {
    $result = $decoded_response['results'][0];
    
    // Check if the location is a hospital, nursing home, or other relevant type
    $facility_types = ['hospital', 'nursing_home', 'health', 'physiotherapist', 'doctor', 'clinic', 'assisted_living'];
    $types = $result['types'];
    
    foreach ($types as $type) {
        if (in_array($type, $facility_types)) {
            $is_facility = true;
            break;
        }
    }
    
    // Extract standardized address components
    $standardized_address = $result['formatted_address'];
    foreach ($result['address_components'] as $component) {
        if (in_array('street_number', $component['types']) && in_array('route', $component['types'])) {
            $standardized_street = $component['long_name'];
        } elseif (in_array('locality', $component['types'])) {
            $standardized_city = $component['long_name'];
        } elseif (in_array('administrative_area_level_1', $component['types'])) {
            $standardized_state = $component['short_name'];
        } elseif (in_array('postal_code', $component['types'])) {
            $standardized_zip = $component['long_name'];
        }
    }
}

echo json_encode([
    'is_facility' => $is_facility,
    'standardized_address' => $standardized_address,
    'standardized_street' => $standardized_street,
    'standardized_city' => $standardized_city,
    'standardized_state' => $standardized_state,
    'standardized_zip' => $standardized_zip
]);

?>