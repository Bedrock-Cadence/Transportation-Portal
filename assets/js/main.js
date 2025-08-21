// FILE: /public_html/assets/js/main.js

document.addEventListener('DOMContentLoaded', function () {

    /**
     * Handles all dropdown menus in the header.
     */
    function initializeDropdowns() {
        const dropdownToggles = document.querySelectorAll('[data-dropdown-toggle]');
        if (dropdownToggles.length === 0) return;

        dropdownToggles.forEach(toggle => {
            toggle.addEventListener('click', function (event) {
                event.stopPropagation(); // Prevents the window click listener from firing immediately
                const targetMenuId = this.getAttribute('data-dropdown-toggle');
                const targetMenu = document.getElementById(targetMenuId);

                // Close all other open dropdowns
                document.querySelectorAll('.dropdown-menu-custom').forEach(menu => {
                    if (menu !== targetMenu && !menu.classList.contains('hidden')) {
                        menu.classList.add('hidden');
                    }
                });

                // Toggle the clicked dropdown
                if (targetMenu) {
                    targetMenu.classList.toggle('hidden');
                }
            });
        });

        // Add a listener to close dropdowns when clicking anywhere else on the page
        window.addEventListener('click', function () {
            document.querySelectorAll('.dropdown-menu-custom').forEach(menu => {
                if (!menu.classList.contains('hidden')) {
                    menu.classList.add('hidden');
                }
            });
        });
    }

    /**
     * Initializes the live clock in the header.
     */
    function initializeLiveClock() {
        const clockElement = document.getElementById('liveClock');
        if (!clockElement) return;

        function updateClock() {
            const now = new Date();
            const timeOptions = { hour: 'numeric', minute: '2-digit', hour12: true };
            const dateOptions = { weekday: 'short', month: 'short', day: 'numeric' };
            
            // Timezone is automatically handled by the user's browser, reflecting their local time.
            const timeString = now.toLocaleTimeString('en-US', timeOptions);
            const dateString = now.toLocaleDateString('en-US', dateOptions);

            clockElement.innerHTML = `${dateString} &bull; ${timeString}`;
        }

        updateClock(); // Run once immediately
        setInterval(updateClock, 1000); // Update every second
    }

    /**
     * Handles the mobile menu toggle.
     */
    function initializeMobileMenu() {
        const menuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const openIcon = document.getElementById('menu-open-icon');
        const closedIcon = document.getElementById('menu-closed-icon');

        if (!menuButton || !mobileMenu) return;

        menuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
            openIcon.classList.toggle('hidden');
            closedIcon.classList.toggle('hidden');
        });
    }

    // Initialize all components if the user is logged in.
    // This check is duplicated from PHP to ensure JS only runs when the elements exist.
    if (document.querySelectorAll('[data-dropdown-toggle]').length > 0) {
        initializeDropdowns();
        initializeLiveClock();
        initializeMobileMenu();
    }

    /**
     * Initializes the real-time notification system.
     */
    function initializeNotifications() {
        const notificationsLink = document.getElementById('nav-notifications-link');
        const notificationBadge = document.getElementById('notification-badge');
        let lastNotificationTimestamp = 0;

        if (!notificationsLink || !notificationBadge) return;

        async function checkNotifications() {
            try {
                // Use the new API endpoint
                const response = await fetch('/api/notifications_api.php?action=get_unread');
                if (!response.ok) return;

                const data = await response.json();
                if (!data.success || !data.notifications) return;

                const unreadCount = data.notifications.length;

                // Update the badge in the header
                if (unreadCount > 0) {
                    notificationBadge.textContent = unreadCount;
                    notificationBadge.classList.remove('hidden');
                } else {
                    notificationBadge.classList.add('hidden');
                }

                // Check for the newest notification and show a pop-up if it's new
                if (unreadCount > 0) {
                    const latestNotification = data.notifications[0]; // API returns newest first
                    const newTimestamp = new Date(latestNotification.created_at).getTime();

                    if (newTimestamp > lastNotificationTimestamp) {
                        showToastNotification(latestNotification);
                        lastNotificationTimestamp = newTimestamp;
                    }
                }

            } catch (error) {
                console.error("Could not check notifications:", error);
            }
        }

        function showToastNotification(notification) {
            const toast = document.createElement('div');
            toast.className = 'notification-toast';
            toast.innerHTML = `
                <div class="toast-content">
                    <strong class="toast-title">New Notification</strong>
                    <p class="toast-message">${notification.message.substring(0, 100)}...</p>
                </div>
            `;

            toast.addEventListener('click', () => {
                if (notification.link) {
                    window.location.href = notification.link;
                }
                document.body.removeChild(toast);
            });

            document.body.appendChild(toast);

            // Auto-remove after 8 seconds
            setTimeout(() => {
                if (document.body.contains(toast)) {
                    document.body.removeChild(toast);
                }
            }, 8000);
        }

        checkNotifications(); // Initial check
        setInterval(checkNotifications, 10000); // Poll every 10 seconds
    }

    // Add this to the final initialization block
    if (document.querySelectorAll('[data-dropdown-toggle]').length > 0) {
        initializeDropdowns();
        initializeLiveClock();
        initializeMobileMenu();
        initializeNotifications(); // <-- ADD THIS LINE
    }
});