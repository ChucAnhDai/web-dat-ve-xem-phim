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
  <title><?php echo htmlspecialchars($title ?? 'CineShop Admin'); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo htmlspecialchars($publicBase); ?>/assets/admin/shared.css">
</head>
<body data-active-page="<?php echo htmlspecialchars($activePage ?? '', ENT_QUOTES, 'UTF-8'); ?>">
<div class="layout">
  <?php include __DIR__ . '/../partials/sidebar.php'; ?>
  <div class="main-wrap" id="mainWrap">
    <?php include __DIR__ . '/../partials/header.php'; ?>
    <?php echo $content; ?>
    <?php include __DIR__ . '/../partials/footer.php'; ?>
  </div>
</div>
<script>
window.APP_BASE_PATH = <?php echo json_encode($appBase, JSON_UNESCAPED_UNICODE); ?>;
window.PUBLIC_BASE_PATH = <?php echo json_encode($publicBase, JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="<?php echo htmlspecialchars($publicBase); ?>/assets/admin/shared.js"></script>
</body>
</html>
