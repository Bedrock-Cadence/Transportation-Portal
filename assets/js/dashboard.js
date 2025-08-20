// FILE: /public_html/assets/js/dashboard.js

document.addEventListener('DOMContentLoaded', () => {
    const dashboardContent = document.getElementById('dashboard-content');
    const lastUpdatedElement = document.getElementById('last-updated');
    // The userData object is now available globally from the main index.php script
    
    // --- RENDER FUNCTIONS ---
    
    function renderFacilityDashboard(data) {
        if (!data.recent_trips || data.recent_trips.length === 0) {
            return `<div class="text-center p-6 bg-gray-50 rounded-lg"><p class="text-gray-600">You have no recent trips.</p><a href="create_trip.php" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded">Create a New Trip</a></div>`;
        }
        
        let rows = data.recent_trips.map(trip => `
            <tr class="hover:bg-gray-50">
                <td class="p-4 text-sm text-gray-700">${formatStatus(trip.status)}</td>
                <td class="p-4 text-sm text-gray-600">${escapeHTML(trip.origin_street)} to ${escapeHTML(trip.destination_street)}</td>
                <td class="p-4 text-sm text-gray-500">${formatDateTime(trip.created_at)}</td>
                <td class="p-4 text-right"><a href="view_trip.php?uuid=${trip.uuid}" class="text-indigo-600 hover:underline">View</a></td>
            </tr>
        `).join('');

        return `
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <h2 class="text-lg font-semibold p-4 border-b">Recent Trips</h2>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50"><tr>
                        <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase">Route</th>
                        <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase">Created</th>
                        <th class="p-4"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-200">${rows}</tbody>
                </table>
            </div>`;
    }

function renderCarrierDashboard(data) {
        // --- Table for Open Trips ---
        let openTripsRows = '';
        if (!data.open_trips || data.open_trips.length === 0) {
            openTripsRows = `<tr><td colspan="5" class="p-4 text-center text-gray-500">No open trips to bid on.</td></tr>`;
        } else {
            openTripsRows = data.open_trips.map(trip => `
                <tr class="hover:bg-gray-50">
                    <td class="p-4 text-sm text-gray-600">${escapeHTML(trip.origin_street)}</td>
                    <td class="p-4 text-sm text-gray-600">${escapeHTML(trip.destination_street)}</td>
                    <td class="p-4 text-sm text-gray-500">${trip.distance ? trip.distance + ' mi' : 'N/A'}</td>
                    <td class="p-4 text-sm text-gray-500">${formatTripTime(trip)}</td>
                    <td class="p-4 text-right"><a href="view_trip.php?uuid=${trip.uuid}" class="text-indigo-600 hover:underline">View & Bid</a></td>
                </tr>
            `).join('');
        }

        const openTripsTable = `
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <h2 class="text-lg font-semibold p-4 border-b">Open Trips for Bidding</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase">Pickup</th>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase">Dropoff</th>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase">Distance</th>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase">Requested Time</th>
                            <th class="p-4"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-200">${openTripsRows}</tbody>
                    </table>
                </div>
            </div>`;

        // --- Table for Awarded Trips ---
        let awardedTripsRows = '';
        if (!data.awarded_trips || data.awarded_trips.length === 0) {
            awardedTripsRows = `<tr><td colspan="6" class="p-4 text-center text-gray-500">You have no awarded trips.</td></tr>`;
        } else {
            awardedTripsRows = data.awarded_trips.map(trip => `
                <tr class="hover:bg-gray-50">
                    <td class="p-4 text-sm text-gray-600">${escapeHTML(trip.origin_street)}</td>
                    <td class="p-4 text-sm text-gray-600">${escapeHTML(trip.destination_street)}</td>
                    <td class="p-4 text-sm text-gray-500">${trip.distance ? trip.distance + ' mi' : 'N/A'}</td>
                    <td class="p-4 text-sm text-gray-500">${formatTripTime(trip)}</td>
                    <td class="p-4 text-sm font-semibold text-gray-700">${formatDateTime(trip.awarded_eta)}</td>
                    <td class="p-4 text-right"><a href="view_trip.php?uuid=${trip.uuid}" class="text-indigo-600 hover:underline">View Details</a></td>
                </tr>
            `).join('');
        }

        const awardedTripsTable = `
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <h2 class="text-lg font-semibold p-4 border-b">Your Awarded Trips</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase">Pickup</th>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase">Dropoff</th>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase">Distance</th>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase">Requested Time</th>
                            <th class="p-4 text-left text-xs font-medium text-gray-500 uppercase">My ETA</th>
                            <th class="p-4"></th>
                        </tr></thead>
                        <tbody class="divide-y divide-gray-200">${awardedTripsRows}</tbody>
                    </table>
                </div>
            </div>`;
        
        // --- Combine and Return ---
        return `
            <div class="space-y-6">
                ${openTripsTable}
                ${awardedTripsTable}
            </div>`;
    }

    // --- HELPER FUNCTIONS ---
    
    function formatStatus(status) {
        const statuses = {
            bidding: `<span class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Bidding</span>`,
            awarded: `<span class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Awarded</span>`,
            completed: `<span class="bg-gray-200 text-gray-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Completed</span>`,
            cancelled: `<span class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded-full">Cancelled</span>`,
        };
        return statuses[status] || status;
    }

    function formatDateTime(utcString) {
        if (!utcString) return 'N/A';
        const date = new Date(utcString + 'Z');
        return date.toLocaleString(undefined, {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit', timeZone: userData.userTimezone
        });
    }

    function formatTripTime(trip) {
        if (trip.asap) {
            return '<span class="font-semibold text-red-600">ASAP</span>';
        }
        if (trip.appointment_at) {
            // Re-uses your existing formatDateTime helper for consistency
            return `Appt: ${formatDateTime(trip.appointment_at)}`;
        }
        if (trip.requested_pickup_time) {
            // Creates a dummy date to parse and format just the time portion
            const today = new Date().toISOString().slice(0, 10);
            const time = new Date(`${today}T${trip.requested_pickup_time}Z`);
            return `Pickup: ${time.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', timeZone: userData.userTimezone })}`;
        }
        return 'N/A';
    }

    function escapeHTML(str) {
        const p = document.createElement('p');
        p.textContent = str;
        return p.innerHTML;
    }

    // --- MAIN LOGIC ---

    function renderDashboard(data) {
        if (userData.userRole === 'admin') {
             dashboardContent.innerHTML = `Admin dashboard coming soon.`;
        } else if (userData.entityType === 'facility') {
            dashboardContent.innerHTML = renderFacilityDashboard(data);
        } else if (userData.entityType === 'carrier') {
            dashboardContent.innerHTML = renderCarrierDashboard(data);
        } else {
            dashboardContent.innerHTML = `<div class="bg-yellow-100 p-4 rounded-md">Your user role does not have a dashboard view.</div>`;
        }
    }

    async function updateDashboard() {
        try {
            // CORRECTED: Use the full, absolute URL from the userData object
            const response = await fetch(`${userData.apiBaseUrl}/dashboard_data.php`, {
                credentials: 'include'
            });
            
            if (!response.ok) {
                throw new Error(`Server responded with status: ${response.status}`);
            }

            const result = await response.json();
            
            if (result.success) {
                renderDashboard(result.data);
            } else {
                dashboardContent.innerHTML = `<div class="bg-red-100 p-4 rounded-md">Error: ${escapeHTML(result.error) || 'Could not load dashboard data.'}</div>`;
            }
        } catch (error) {
            console.error('Failed to fetch dashboard data:', error);
            dashboardContent.innerHTML = `<div class="bg-red-100 p-4 rounded-md">A network error occurred. Please try again later.</div>`;
        } finally {
            if (lastUpdatedElement) {
                const now = new Date();
                lastUpdatedElement.textContent = `Last Update: ${now.toLocaleTimeString()}`;
            }
        } 
    }

    updateDashboard(); // Initial load
    setInterval(updateDashboard, 60000); // Refresh every 60 seconds
});