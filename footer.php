</main> <footer class="text-center text-muted mt-4">
    <p>&copy; <?php echo date("Y"); ?> Bedrock Cadence. All Rights Reserved.</p>
</footer>

<div class="toast-container position-fixed bottom-0 end-0 p-3">
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
    <?php if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true): ?>
        if (typeof(EventSource) !== "undefined") {
            // We connect to the server script. It will use our session ID.
            var source = new EventSource("/../app/sse_server.php");
            
            // This is the listener for our custom "new_notification" event
            source.addEventListener('new_notification', function(event) {
                const notification = JSON.parse(event.data);
                
                const toastBody = document.getElementById('liveToastBody');
                let messageHTML = notification.message;
                // If there's a link, make it clickable
                if (notification.link) {
                    messageHTML += ` <a href="${notification.link}" class="alert-link">View now.</a>`;
                }
                toastBody.innerHTML = messageHTML;
                
                const toastElement = document.getElementById('liveToast');
                const toast = new bootstrap.Toast(toastElement);
                toast.show();
            });

        }
    <?php endif; ?>
</script>

</body>
</html>