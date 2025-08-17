<?php
// FILE: public/check_address.php

ob_start(); // Start output buffering to prevent any stray output from breaking JSON

require_once __DIR__ . '/../../app/init.php'; // Use the main init file

header('Content-Type: application/json');

$response_data = ['prompt_room_number' => false]; // Default response

try {
    $json = file_get_contents('php://input');
    $data = json_decode($json);
    $address = $data->address ?? '';

    if (empty($address)) {
        throw new Exception('Missing address data.');
    }

    $db = Database::getInstance();
    $address_for_db = '%' . $address . '%';

    // This query is an approximation. For better accuracy, you might need geospatial data.
    $sql = "SELECT destination_room, origin_room FROM trips WHERE destination_street LIKE ? OR origin_street LIKE ?";
    $stmt = $db->query($sql, [$address_for_db, $address_for_db]);
    $results = $stmt->fetchAll();

    $total_records = count($results);
    $records_with_room = 0;

    if ($total_records > 0) {
        foreach ($results as $row) {
            if (!empty($row['destination_room']) || !empty($row['origin_room'])) {
                $records_with_room++;
            }
        }
        // If over 80% of past trips to a similar street have a room number, prompt the user.
        if (($records_with_room / $total_records) > 0.8) {
            $response_data['prompt_room_number'] = true;
        }
    }

} catch (Exception $e) {
    error_log("Check Address API Error: " . $e->getMessage());
    // Do not expose error details in the API response
    // The default response of 'false' is safe.
}

ob_end_clean(); // Discard any buffer content
echo json_encode($response_data);
exit();