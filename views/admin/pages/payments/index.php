<?php
$paymentSection = $activePage ?? 'payments';
$sectionMeta = [
    'payments' => [
        'breadcrumb' => 'Payments',
        'title' => 'Payment Management',
        'subtitle' => 'Track transactions, revenue, and refund health',
        'button' => 'Export Report',
        'buttonClass' => 'btn btn-ghost',
    ],
    'payment-methods' => [
        'breadcrumb' => 'Payment Methods',
        'title' => 'Payment Methods',
        'subtitle' => 'Manage payment channels and settlement rules',
        'button' => 'Add Method',
        'buttonClass' => 'btn btn-primary',
    ],
];
$sectionViews = [
    'payments' => __DIR__ . '/sections/payments.php',
    'payment-methods' => __DIR__ . '/sections/payment-methods.php',
];
$meta = $sectionMeta[$paymentSection] ?? $sectionMeta['payments'];
$sectionView = $sectionViews[$paymentSection] ?? $sectionViews['payments'];
?>
<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">&gt;</span><span><?php echo htmlspecialchars($meta['breadcrumb'], ENT_QUOTES, 'UTF-8'); ?></span></div>
      <h1 class="page-title"><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="page-sub"><?php echo htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <button class="<?php echo htmlspecialchars($meta['buttonClass'], ENT_QUOTES, 'UTF-8'); ?>" onclick="handlePaymentsSectionAction()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?php echo htmlspecialchars($meta['button'], ENT_QUOTES, 'UTF-8'); ?>
    </button>
  </div>

  <?php include $sectionView; ?>
</div>
