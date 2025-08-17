<?php
// FILE: public_html/portal/check_address.php

// The init file handles autoloading, sessions, and error handling.
require_once __DIR__ . '/../../app/init.php';

// Set the content type to JSON for all responses.
header('Content-Type: application/json');

$response_data = ['prompt_room_number' => false]; // Default response

try {
    // Ensure the user is logged in to use this API endpoint.
    if (!Auth::isLoggedIn()) {
        http_response_code(401); // Unauthorized
        throw new Exception('Authentication required.');
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json);
    $address = trim($data->address ?? '');

    if (empty($address)) {
        http_response_code(400); // Bad Request
        throw new Exception('Missing address data.');
    }

    // Instantiate our new service and call the logic method.
    $addressService = new AddressService();
    $response_data['prompt_room_number'] = $addressService->shouldPromptForRoomNumber($address);

} catch (Exception $e) {
    // Log the error for debugging, but don't expose details in the API response.
    // The default response of 'false' is a safe fallback.
    error_log("Check Address API Error: " . $e->getMessage());
}

// Ensure a clean JSON output.
echo json_encode($response_data);
exit();