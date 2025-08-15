<?php
// Start output buffering to capture and discard any stray output or errors.
ob_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../../app/db_connect.php';

// Get the raw POST data
$json = file_get_contents('php://input');
$data = json_decode($json);
$address = $data->address ?? '';

if (empty($address)) {
    ob_end_clean();
    echo json_encode(['error' => 'Missing address data.']);
    exit;
}

// Prepare the address for the database query.
$address_for_db = '%' . $address . '%';

// Use a prepared statement for security to query the database.
$sql = "SELECT dropoff_room, pickup_room FROM trips WHERE origin_address LIKE ? OR destination_address LIKE ?";
$total_records = 0;
$records_with_room = 0;

if ($stmt = $mysqli->prepare($sql)) {
    $stmt->bind_param("ss", $address_for_db, $address_for_db);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $total_records++;
            if (!empty($row['dropoff_room']) || !empty($row['pickup_room'])) {
                $records_with_room++;
            }
        }
    }
    $stmt->close();
}

$prompt_room_number = false;
if ($total_records > 0) {
    $percentage = ($records_with_room / $total_records) * 100;
    if ($percentage > 80) {
        $prompt_room_number = true;
    }
}

// End output buffering and discard its contents.
ob_end_clean();

echo json_encode([
    'prompt_room_number' => $prompt_room_number
]);

// Ensure no other output is sent after the JSON.
die();

?>