<?php
// FILE: index.php

// 1. Set the page title for the header.
$page_title = 'User Management';

// 2. Include the header, which also handles session startup.
require_once 'header.php';

// 3. Security Check: If the user isn't logged in, send them to the login page.
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("location: login.php");
    exit;
}

require_once __DIR__ . '/../../app/db_connect.php';

?>

<div id="dashboard-container">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-800">User Management</h1>
            <p id="last-updated" class="text-sm text-gray-500"></p>

    </div>
<div class="bg-white shadow-md rounded-lg overflow-hidden border border-gray-200">
                <div class="px-6 py-4 border-b bg-gray-50">
                    <h2 class="text-xl font-semibold text-gray-800">Company Users</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Access</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                    <tr class="hover:bg-gray-50 transition-colors duration-150 ease-in-out">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-mono"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800">
                            <a href="view_trip.php?uuid=${trip.uuid}" class="btn btn-info">View</a>
                        </td>
                    </tr>
                        </tbody>
                    </table>
                </div>
            </div>

<?php
// This includes the footer and necessary closing tags.
require_once 'footer.php';
?>