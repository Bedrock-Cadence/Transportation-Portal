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

    /**
     * Initializes the automated notification pop-up.
     */
    function initializeNotificationPopUp() {
        // Function to fetch unread notifications
        async function fetchNotifications() {
            try {
                // --- FIX STARTS HERE ---
                // Change the URL to point to the new local proxy script.
                // This is now a same-origin request, so authentication cookies will be sent automatically.
                const response = await fetch('/portal/notification_proxy.php?action=get_all');
                // --- FIX ENDS HERE ---

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const data = await response.json();

                // Check for new, unread notifications
                if (data.success) {
                    // Use a loose equality check (==) to handle potential type differences
                    const unreadNotifications = data.notifications.filter(notification => notification.is_read == 0);
                    return unreadNotifications.length > 0;
                }
                return false;
            } catch (error) {
                console.error('Error fetching notifications:', error);
                return false;
            }
        }

        // Function to show the modal
        function showModal() {
            const modal = document.getElementById('notificationModal');
            if (modal) {
                // --- FIX STARTS HERE ---
                // Remove the inline 'display' style set by hideModal().
                // This ensures the CSS 'is-active' class can make the modal visible again.
                modal.style.display = ''; 
                // --- FIX ENDS HERE ---
                modal.classList.add('is-active');
            }
        }

        // Function to hide the modal
        function hideModal() {
            const modal = document.getElementById('notificationModal');
            if (modal) {
                modal.classList.remove('is-active');
                setTimeout(() => {
                    modal.style.display = 'none';
                }, 300); // Wait for the transition to finish
            }
        }

        // Event listener for the close button
        const closeModalBtn = document.getElementById('closeModalBtn');
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', hideModal);
        }

        // Function to run the check
        async function checkForNewNotifications() {
            const hasNew = await fetchNotifications();
            if (hasNew) {
                showModal();
            }
        }

        // Run the check every 60 seconds (60000 milliseconds)
        setInterval(checkForNewNotifications, 60000);

        // Run an initial check on page load
        checkForNewNotifications();
    }

    // Initialize all components if the user is logged in.
    // This check is duplicated from PHP to ensure JS only runs when the elements exist.
    if (document.querySelectorAll('[data-dropdown-toggle]').length > 0) {
        initializeDropdowns();
        initializeLiveClock();
        initializeMobileMenu();
        // Add the new function here
        initializeNotificationPopUp();
    }
});