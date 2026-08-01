</main>

<!-- Footer -->
<footer class="bg-gray-800 text-white py-6 mt-12 no-print">
    <div class="container mx-auto px-4 text-center">
        <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
        <p class="text-sm text-gray-400 mt-2">Version <?= APP_VERSION ?></p>
    </div>
</footer>

<!-- Custom JavaScript -->
<script>
    // Auto-hide flash messages after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('[role="alert"]');
        alerts.forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);

    // Confirm delete actions
    function confirmDelete(message) {
        return confirm(message || 'Are you sure you want to delete this item?');
    }
</script>
</body>

</html>
<?php clearOldInput(); // Clear old form input after page load 
?>