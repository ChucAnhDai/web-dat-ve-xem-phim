<?php
$cinemaSection = $activePage ?? 'cinemas';
$sectionMeta = [
    'cinemas' => [
        'breadcrumb' => 'Cinemas',
        'title' => 'Cinema Management',
        'subtitle' => 'Manage locations, managers, and operating status',
        'button' => 'Add Cinema',
    ],
    'rooms' => [
        'breadcrumb' => 'Rooms',
        'title' => 'Room Management',
        'subtitle' => 'Manage screening rooms, formats, and capacity',
        'button' => 'Add Room',
    ],
];
$sectionViews = [
    'cinemas' => __DIR__ . '/sections/cinemas.php',
    'rooms' => __DIR__ . '/sections/rooms.php',
];
$meta = $sectionMeta[$cinemaSection] ?? $sectionMeta['cinemas'];
$sectionView = $sectionViews[$cinemaSection] ?? $sectionViews['cinemas'];
?>
<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">&gt;</span><span><?php echo htmlspecialchars($meta['breadcrumb'], ENT_QUOTES, 'UTF-8'); ?></span></div>
      <h1 class="page-title"><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="page-sub"><?php echo htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <button class="btn btn-primary" onclick="handleCinemaSectionAction()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?php echo htmlspecialchars($meta['button'], ENT_QUOTES, 'UTF-8'); ?>
    </button>
  </div>

  <?php include $sectionView; ?>
</div>
