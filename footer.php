</main>
    
    <footer class="mt-8 bg-gray-800 text-white py-6 shadow-inner">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="flex flex-col items-center justify-center">
                <div class="flex items-center space-x-2 text-xl font-bold mb-2">
                    <span class="text-gradient">Bedrock Cadence</span>
                </div>
                <p class="text-sm text-gray-400 mb-2">
                    Delivering Excellence, One Trip at a Time.
                </p>
                <p class="text-xs text-gray-500">
                    &copy; <?php echo date("Y"); ?> Bedrock Cadence. All Rights Reserved.
                </p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" xintegrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {

        <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>

        // --- Dropdown Menu Logic ---
        const dropdownToggles = document.querySelectorAll('[data-dropdown-toggle]');
        if (dropdownToggles.length > 0) {
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
        
        <?php endif; ?>
    });
    </script>

</body>
</html>