<?php
// Set headers for CORS if needed, though with a proxy this is often not an issue.
// header('Content-Type: application/json');

// The full, absolute URL of the protected API endpoint
$api_url = 'https://www.bedrockcadence.com/api/dashboard_data.php';

// Initialize cURL for making a server-to-server request
$ch = curl_init($api_url);

// Set cURL options for a secure request
// You may need to add authentication headers here if the API requires them
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); // Important for secure connections!
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Execute the cURL request
$response = curl_exec($ch);

// Check for cURL errors
if (curl_errno($ch)) {
    http_response_code(500);
    die(json_encode(['error' => 'API request failed: ' . curl_error($ch)]));
}

// Close cURL resource
curl_close($ch);

// Pass the API response back to the client
echo $response;
?>