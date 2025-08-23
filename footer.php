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
    
    <script nonce="<?= $cspNonce ?>" src="/assets/js/main.js" defer></script>

</body>
</html>