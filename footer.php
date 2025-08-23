<?php
// FILE: public_html/portal/footer.php
?>
</main>
    
    <footer class="mt-auto bg-gray-800 text-white py-6 shadow-inner">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <div class="flex flex-col items-center justify-center">
                <div class="flex items-center space-x-2 text-xl font-bold mb-2">
                    <span class="text-gradient">Bedrock Cadence</span>
                </div>
                <p class="text-sm text-gray-400 mb-2">
                    Delivering Excellence, One Trip at a Time.
                </p>
                <p class="text-xs text-gray-500">
                    &copy; <?= date("Y"); ?> Bedrock Cadence LLC. All Rights Reserved.
                </p>
                
                <?php // Development-only debug information
                if (defined('APP_ENV') && APP_ENV === 'development'): ?>
                    <div class="mt-4 pt-2 border-t border-gray-700 text-xs text-gray-600">
                        <p>Bedrock Cadence Development Environment</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </footer>

    <?php // This single script file will handle all custom behaviors like dropdowns and the live clock. ?>
    <?php // The `nonce` attribute makes this script compliant with our Content Security Policy. ?>
        <div id="notificationModal" class="modal">
        <div class="modal-content">
            <h2 class="text-xl font-bold text-red-700 mb-2">New Notification</h2>
            <p class="text-sm text-gray-700 mb-4">You've received a new notification. Would you like to check it out?</p>
            <div class="flex justify-center space-x-4">
                <button id="closeModalBtn" class="bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-full hover:bg-gray-400 transition-colors duration-300 shadow-md">
                    Close
                </button>
                <a href="/notifications.php" class="bg-brand-primary text-white font-semibold py-2 px-6 rounded-full hover:bg-red-800 transition-colors duration-300 shadow-md">
                    Go to Notifications
                </a>
            </div>
        </div>
    </div>
    <script nonce="<?= $cspNonce ?>" src="/assets/js/main.js" defer></script>

</body>
</html>