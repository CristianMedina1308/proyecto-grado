<?php
require_once __DIR__ . '/app.php';

$appAssetPrefix = isset($appAssetPrefix) ? (string) $appAssetPrefix : '';
$appFlashes = appPullFlashes();
include __DIR__ . '/ui_modal.php';
?>
<script>
window.APP_FLASHES = <?= json_encode(
    $appFlashes,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.2/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.2/js/dataTables.bootstrap5.min.js"></script>
<script src="<?= htmlspecialchars($appAssetPrefix) ?>assets/js/app-ui.js"></script>
