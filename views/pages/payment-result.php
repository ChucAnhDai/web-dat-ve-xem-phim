<?php
$paymentResultStatus = strtolower(trim((string) ($_GET['status'] ?? 'issue')));
$paymentResultOrderCode = trim((string) ($_GET['order_code'] ?? ''));
$paymentResultPaymentStatus = trim((string) ($_GET['payment_status'] ?? ''));
$paymentResultMessage = trim((string) ($_GET['message'] ?? 'Payment result was returned from the gateway.'));

$paymentResultTitle = 'Payment requires attention.';
$paymentResultCopy = $paymentResultMessage;
$paymentResultClass = 'detail-state-card';

if ($paymentResultStatus === 'success') {
    $paymentResultTitle = 'Payment completed successfully.';
    $paymentResultCopy = $paymentResultMessage !== '' ? $paymentResultMessage : 'Your ticket order has been confirmed.';
}
?>

<div class="page-header">
  <h1 class="page-title">Payment Result</h1>
  <p class="page-subtitle">The result below reflects the latest VNPay callback processed by the Ticket System.</p>
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
      <a class="btn btn-primary btn-sm" href="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/my-tickets">Open My Tickets</a>
      <a class="btn btn-ghost btn-sm" href="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/movies">Back to Movies</a>
    </div>
  </div>
</div>
