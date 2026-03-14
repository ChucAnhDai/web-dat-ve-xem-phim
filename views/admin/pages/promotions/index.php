<?php
$promotionSection = $activePage ?? 'promotions';
$sectionMeta = [
    'promotions' => [
        'breadcrumb' => 'Promotions',
        'title' => 'Promotions',
        'subtitle' => 'Create and manage discount campaigns',
        'button' => 'New Promotion',
        'buttonClass' => 'btn btn-gold',
    ],
    'product-promotions' => [
        'breadcrumb' => 'Product Promotions',
        'title' => 'Product Promotions',
        'subtitle' => 'Assign deals to merch, combos, and shop items',
        'button' => 'Assign Promotion',
        'buttonClass' => 'btn btn-primary',
    ],
];
$sectionViews = [
    'promotions' => __DIR__ . '/sections/promotions.php',
    'product-promotions' => __DIR__ . '/sections/product-promotions.php',
];
$meta = $sectionMeta[$promotionSection] ?? $sectionMeta['promotions'];
$sectionView = $sectionViews[$promotionSection] ?? $sectionViews['promotions'];
?>
<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">&gt;</span><span><?php echo htmlspecialchars($meta['breadcrumb'], ENT_QUOTES, 'UTF-8'); ?></span></div>
      <h1 class="page-title"><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="page-sub"><?php echo htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <button class="<?php echo htmlspecialchars($meta['buttonClass'], ENT_QUOTES, 'UTF-8'); ?>" onclick="handlePromotionSectionAction()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?php echo htmlspecialchars($meta['button'], ENT_QUOTES, 'UTF-8'); ?>
    </button>
  </div>

  <?php include $sectionView; ?>
</div>
