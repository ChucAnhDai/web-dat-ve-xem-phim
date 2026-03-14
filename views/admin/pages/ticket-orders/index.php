<?php
$ticketSection = $activePage ?? 'ticket-orders';
$sectionMeta = [
    'ticket-orders' => [
        'breadcrumb' => 'Ticket Orders',
        'title' => 'Ticket Orders',
        'subtitle' => 'All cinema booking transactions',
        'button' => 'Export CSV',
        'buttonClass' => 'btn btn-ghost',
    ],
    'ticket-details' => [
        'breadcrumb' => 'Ticket Details',
        'title' => 'Ticket Details',
        'subtitle' => 'Inspect seat allocations and booking metadata',
        'button' => 'Open Detail',
        'buttonClass' => 'btn btn-primary',
    ],
];
$sectionViews = [
    'ticket-orders' => __DIR__ . '/sections/ticket-orders.php',
    'ticket-details' => __DIR__ . '/sections/ticket-details.php',
];
$meta = $sectionMeta[$ticketSection] ?? $sectionMeta['ticket-orders'];
$sectionView = $sectionViews[$ticketSection] ?? $sectionViews['ticket-orders'];
?>
<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">&gt;</span><span><?php echo htmlspecialchars($meta['breadcrumb'], ENT_QUOTES, 'UTF-8'); ?></span></div>
      <h1 class="page-title"><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="page-sub"><?php echo htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <button class="<?php echo htmlspecialchars($meta['buttonClass'], ENT_QUOTES, 'UTF-8'); ?>" onclick="handleTicketSectionAction()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?php echo htmlspecialchars($meta['button'], ENT_QUOTES, 'UTF-8'); ?>
    </button>
  </div>

  <?php include $sectionView; ?>
</div>
