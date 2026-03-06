<?php
/**
 * scripts.php — Universal JS includes
 * Place at the bottom of every page's <body>
 */
$base_path = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false ||
              strpos($_SERVER['SCRIPT_NAME'], '/student/') !== false) ? '../' : '';
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // Set base path for AJAX calls
    const ANU_BASE = '<?= $base_path ?>';
</script>
<script src="<?= $base_path ?>public/js/app.js"></script>
