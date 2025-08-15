// FILE: /assets/js/main.js

document.addEventListener('DOMContentLoaded', () => {
    
    // --- Live Clock Functionality ---
    const liveClock = document.getElementById('liveClock');
    if (liveClock) {
        function updateClock() {
            const now = new Date();
            const formattedDate = now.toLocaleString('en-US', {
                timeZone: 'America/Chicago', // Central Time
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            liveClock.textContent = formattedDate;
        }
        setInterval(updateClock, 1000);
        updateClock(); // Initial call
    }

    // --- Mobile Menu Toggle ---
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const menuOpenIcon = document.getElementById('menu-open-icon');
    const menuClosedIcon = document.getElementById('menu-closed-icon');

    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', () => {
            mobileMenu.classList.toggle('hidden');
            menuOpenIcon.classList.toggle('hidden');
            menuOpenIcon.classList.toggle('block');
            menuClosedIcon.classList.toggle('hidden');
            menuClosedIcon.classList.toggle('block');
        });
    }

});