<?php
/** @var string $content */
/** @var string $title */

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$publicBase = rtrim(dirname($scriptName), '/');
$publicBase = $publicBase === '.' ? '' : $publicBase;
$appBase = preg_replace('#/public$#', '', $publicBase) ?: '';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($title ?? 'CinemaX - Premium Cinema Experience'); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@400;500;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($publicBase); ?>/assets/css/app.css">
</head>
<body data-active-page="<?php echo $activePage ?? ''; ?>">
<div class="app-shell">
  <?php include __DIR__ . '/../partials/header.php'; ?>
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <main class="main" id="mainContent">
    <?php echo $content; ?>
  </main>
  <footer class="footer" id="footerMount">
    <?php include __DIR__ . '/../partials/footer.php'; ?>
  </footer>
</div>
<div class="toast-container" id="toastContainer"></div>
<script>
window.APP_BASE_PATH = <?php echo json_encode($appBase, JSON_UNESCAPED_UNICODE); ?>;
window.PUBLIC_BASE_PATH = <?php echo json_encode($publicBase, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars($publicBase); ?>/assets/js/app.js"></script>
</body>
</html>
