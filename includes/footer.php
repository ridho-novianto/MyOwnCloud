        </div><!-- /.content-body -->
    </main>

    <!-- Modal Overlay -->
    <div class="modal-overlay" id="modalOverlay" onclick="closeModal()"></div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script src="<?= APP_URL ?>/assets/js/app.js"></script>
    <?php if (isset($pageScripts)): ?>
        <?php foreach ($pageScripts as $script): ?>
        <script src="<?= APP_URL ?>/assets/js/<?= $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
