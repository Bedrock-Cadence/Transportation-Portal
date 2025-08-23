<?php
// FILE: public_html/portal/search.php

require_once __DIR__ . '/../../app/init.php';

// Security: Only Admins can access this page.
if (!Auth::can('system_search')) {
    Utils::redirect('index.php');
}

$page_title = 'Perform System Search';
$page_error = '';
$results = [];

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $searchTerm = trim($_POST['search_term'] ?? '');
    $searchType = $_POST['search_type'] ?? '';
    
    $db = Database::getInstance();
    $sql = '';
    $params = [];

    try {
        if (empty($searchTerm)) {
            $page_error = "Please enter a search term.";
        } else {
            // Adjust the query based on the search type.
            switch ($searchType) {
                case 'uuid':
                    $sql = "SELECT t.*, p.*, c.name AS carrier_name, f.name AS facility_name
                            FROM trips t
                            LEFT JOIN trips_phi p ON t.id = p.trip_id
                            LEFT JOIN carriers c ON t.carrier_id = c.id
                            LEFT JOIN facilities f ON t.facility_id = f.id
                            WHERE t.uuid = :search_term
                            LIMIT 1";
                    $params[':search_term'] = $searchTerm;
                    $results = $db->fetchAll($sql, $params);
                    break;

                case 'carrier':
                    $sql = "SELECT t.*, p.*, c.name AS carrier_name, f.name AS facility_name
                            FROM trips t
                            JOIN carriers c ON t.carrier_id = c.id
                            LEFT JOIN trips_phi p ON t.id = p.trip_id
                            LEFT JOIN facilities f ON t.facility_id = f.id
                            WHERE c.name LIKE :search_term";
                    $params[':search_term'] = "%" . $searchTerm . "%";
                    $results = $db->fetchAll($sql, $params);
                    break;
                
                case 'facility':
                    $sql = "SELECT t.*, p.*, c.name AS carrier_name, f.name AS facility_name
                            FROM trips t
                            JOIN facilities f ON t.facility_id = f.id
                            LEFT JOIN trips_phi p ON t.id = p.trip_id
                            LEFT JOIN carriers c ON t.carrier_id = c.id
                            WHERE f.name LIKE :search_term";
                    $params[':search_term'] = "%" . $searchTerm . "%";
                    $results = $db->fetchAll($sql, $params);
                    break;

                default:
                    $page_error = "Invalid search type selected.";
            }

            if (empty($results)) {
                $page_error = "No results found for your search.";
            }
        }
    } catch (Exception $e) {
        $page_error = "A database error occurred: " . $e->getMessage();
        LoggingService::log(Auth::user('user_id'), null, 'system_search_error', $e->getMessage());
    }
}
?>

<?php require_once 'header.php'; ?>

<h1 class="text-3xl font-bold text-gray-800 mb-6">System Search</h1>

<div class="max-w-4xl mx-auto bg-white shadow-md rounded-lg border border-gray-200">
    <div class="p-6">
        <form action="search.php" method="post" class="space-y-6">
            <?php if ($page_error): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4" role="alert">
                    <p class="font-bold">Error</p>
                    <p><?= Utils::e($page_error) ?></p>
                </div>
            <?php endif; ?>

            <div class="flex flex-col sm:flex-row gap-4">
                <div class="flex-grow">
                    <label for="search_term" class="block text-sm font-medium text-gray-700">Search Term</label>
                    <input type="text" name="search_term" id="search_term" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" placeholder="Enter UUID, carrier name, or facility name..." required>
                </div>
                <div>
                    <label for="search_type" class="block text-sm font-medium text-gray-700">Search By</label>
                    <select name="search_type" id="search_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="uuid">Trip UUID</option>
                        <option value="carrier">Carrier Name</option>
                        <option value="facility">Facility Name</option>
                    </select>
                </div>
                <div class="sm:self-end">
                    <button type="submit" class="w-full sm:w-auto inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-base font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Search
                    </button>
                </div>
            </div>
        </form>

        <?php if (!empty($results)): ?>
            <div class="mt-8 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trip UUID</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Facility</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Awarded Carrier</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Patient Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pick-up</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Drop-off</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($results as $trip): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    <a href="view_trip.php?uuid=<?= urlencode($trip['uuid']) ?>" class="text-indigo-600 hover:text-indigo-900"><?= Utils::e($trip['uuid']); ?></a>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= Utils::formatTripStatus($trip['status']); ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= Utils::e($trip['facility_name'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= Utils::e($trip['carrier_name'] ?? 'Not Awarded'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php
                                    $phiExists = !empty($trip['patient_first_name_encrypted']);
                                    if ($phiExists) {
                                        $firstName = (new EncryptionService(ENCRYPTION_KEY))->decrypt($trip['patient_first_name_encrypted']);
                                        $lastName = (new EncryptionService(ENCRYPTION_KEY))->decrypt($trip['patient_last_name_encrypted']);
                                        echo Utils::e($firstName . ' ' . $lastName);
                                    } else {
                                        echo '[PHI Purged]';
                                    }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= Utils::e($trip['origin_city'] ?? 'N/A') . ', ' . Utils::e($trip['origin_state'] ?? 'N/A'); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= Utils::e($trip['destination_city'] ?? 'N/A') . ', ' . Utils::e($trip['destination_state'] ?? 'N/A'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'footer.php'; ?>