<?php
$shopOrderSection = $activePage ?? 'shop-orders';
$sectionMeta = [
    'shop-orders' => [
        'breadcrumb' => 'Shop Orders',
        'title' => 'Shop Orders',
        'subtitle' => 'Live shop orders synced from checkout, payments, and fulfillment state',
        'button' => 'Refresh Orders',
        'buttonClass' => 'btn btn-ghost',
    ],
    'order-details' => [
        'breadcrumb' => 'Order Details',
        'title' => 'Order Details',
        'subtitle' => 'Live order lines and fulfillment queue synced from order_details',
        'button' => 'Refresh Details',
        'buttonClass' => 'btn btn-primary',
    ],
];
$sectionViews = [
    'shop-orders' => __DIR__ . '/sections/shop-orders.php',
    'order-details' => __DIR__ . '/sections/order-details.php',
];
$meta = $sectionMeta[$shopOrderSection] ?? $sectionMeta['shop-orders'];
$sectionView = $sectionViews[$shopOrderSection] ?? $sectionViews['shop-orders'];
?>
<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">&gt;</span><span><?php echo htmlspecialchars($meta['breadcrumb'], ENT_QUOTES, 'UTF-8'); ?></span></div>
      <h1 class="page-title"><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="page-sub"><?php echo htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <button class="<?php echo htmlspecialchars($meta['buttonClass'], ENT_QUOTES, 'UTF-8'); ?>" onclick="handleShopOrderSectionAction()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?php echo htmlspecialchars($meta['button'], ENT_QUOTES, 'UTF-8'); ?>
    </button>
  </div>

  <?php include $sectionView; ?>
</div>
