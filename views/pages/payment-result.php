<?php
$paymentResultStatus = strtolower(trim((string) ($_GET['status'] ?? 'issue')));
$paymentResultOrderType = strtolower(trim((string) ($_GET['order_type'] ?? 'ticket')));
$paymentResultOrderCode = trim((string) ($_GET['order_code'] ?? ''));
$paymentResultPaymentStatus = trim((string) ($_GET['payment_status'] ?? ''));
$paymentResultMessage = trim((string) ($_GET['message'] ?? 'Payment result was returned from the gateway.'));

$paymentResultTitle = 'Payment requires attention.';
$paymentResultCopy = $paymentResultMessage;
$paymentResultClass = 'detail-state-card';
$paymentResultSubtitle = 'The result below reflects the latest VNPay callback processed by the payment service.';
$paymentResultPrimaryHref = $publicBase . '/my-tickets';
$paymentResultPrimaryLabel = 'Open My Tickets';
$paymentResultSecondaryHref = $publicBase . '/movies';
$paymentResultSecondaryLabel = 'Back to Movies';

if ($paymentResultOrderType === 'shop') {
    $paymentResultPrimaryHref = $publicBase . '/my-orders';
    $paymentResultPrimaryLabel = 'Open My Orders';
    $paymentResultSecondaryHref = $publicBase . '/shop';
    $paymentResultSecondaryLabel = 'Back to Shop';
}

if ($paymentResultStatus === 'success') {
    $paymentResultTitle = 'Payment completed successfully.';
    $paymentResultCopy = $paymentResultMessage !== ''
        ? $paymentResultMessage
        : ($paymentResultOrderType === 'shop'
            ? 'Your shop order has been confirmed.'
            : 'Your ticket order has been confirmed.');
}
?>

<div class="page-header">
  <h1 class="page-title">Payment Result</h1>
  <p class="page-subtitle"><?php echo htmlspecialchars($paymentResultSubtitle, ENT_QUOTES, 'UTF-8'); ?></p>
</div>

<div class="<?php echo htmlspecialchars($paymentResultClass, ENT_QUOTES, 'UTF-8'); ?>">
  <div>
    <strong><?php echo htmlspecialchars($paymentResultTitle, ENT_QUOTES, 'UTF-8'); ?></strong>
    <div style="margin-top:8px;"><?php echo htmlspecialchars($paymentResultCopy, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php if ($paymentResultOrderCode !== ''): ?>
      <div style="margin-top:8px;color:var(--text2);">Order Code: <?php echo htmlspecialchars($paymentResultOrderCode, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <?php if ($paymentResultPaymentStatus !== ''): ?>
      <div style="margin-top:4px;color:var(--text2);">Payment Status: <?php echo htmlspecialchars($paymentResultPaymentStatus, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;">
      <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($paymentResultPrimaryHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($paymentResultPrimaryLabel, ENT_QUOTES, 'UTF-8'); ?></a>
      <a class="btn btn-ghost btn-sm" href="<?php echo htmlspecialchars($paymentResultSecondaryHref, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($paymentResultSecondaryLabel, ENT_QUOTES, 'UTF-8'); ?></a>
    </div>
  </div>
</div>
