</main>
<script src="assets/app.js?v=<?= filemtime(__DIR__ . '/../assets/app.js') ?>"></script>
<?php if (!empty($mode)): ?><script src="assets/kiosk.js?v=<?= filemtime(__DIR__ . '/../assets/kiosk.js') ?>"></script><?php endif; ?>
<?= isset($extraScripts) ? $extraScripts : '' ?>
</body>
</html>
