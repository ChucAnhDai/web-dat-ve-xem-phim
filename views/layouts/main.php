<?php
/** @var string $content */
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CinemaX — Premium Cinema Experience</title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/web-dat-ve-xem-phim/public/assets/css/app.css">
</head>
<body>
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
<script src="/web-dat-ve-xem-phim/public/assets/js/app.js"></script>
</body>
</html>
