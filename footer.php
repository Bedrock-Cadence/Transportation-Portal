</main> <footer class="text-center text-muted mt-4">
    <p>&copy; <?php echo date("Y"); ?> Bedrock Cadence. All Rights Reserved.</p>
</footer>

<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1100;">
  <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
    <div class="toast-header">
      <strong class="me-auto text-primary">Live Notification</strong>
      <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
    </div>
    <div class="toast-body" id="liveToastBody">
      </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

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


    // --- Server-Sent Events (SSE) for Live Notifications ---
    if (typeof(EventSource) !== "undefined") {
        const source = new EventSource("/../app/sse_server.php");
        
        source.addEventListener('new_notification', function(event) {
            const notification = JSON.parse(event.data);
            const toastBody = document.getElementById('liveToastBody');
            let messageHTML = notification.message;
            
            if (notification.link) {
                messageHTML += ` <a href="${notification.link}" class="alert-link">View now.</a>`;
            }
            toastBody.innerHTML = messageHTML;
            
            const toastElement = document.getElementById('liveToast');
            // Get the existing toast instance or create one if it doesn't exist
            const toast = bootstrap.Toast.getOrCreateInstance(toastElement);
            toast.show();
        });
    }

    <?php endif; ?>
});
</script>

</body>
</html>