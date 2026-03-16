<?php
$selectedMovieSlug = trim((string) ($_GET['slug'] ?? ''));
$backToMovieDetail = $selectedMovieSlug !== ''
    ? $publicBase . '/movie-detail?slug=' . rawurlencode($selectedMovieSlug) . '#movieDetailShowtimesSection'
    : $publicBase . '/movies';
?>

<div style="margin-bottom:16px">
  <a id="seatSelectionBackLink" href="<?php echo htmlspecialchars($backToMovieDetail, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-ghost btn-sm">Back to Showtimes</a>
</div>

<div id="seatSelectionState"></div>

<div id="seatSelectionContent" hidden>
  <div class="page-header">
    <h1 class="page-title">Select Your Seats</h1>
    <p class="page-subtitle" id="seatSelectionSubtitle">Loading screening details...</p>
  </div>

  <div class="seat-layout">
    <div class="seat-map-wrap">
      <div class="screen-label">SCREEN</div>
      <div class="seat-grid" id="seatGrid"></div>
      <div class="seat-legend">
        <div class="legend-item"><div class="legend-seat available"></div> Available</div>
        <div class="legend-item"><div class="legend-seat selected"></div> Selected</div>
        <div class="legend-item"><div class="legend-seat booked"></div> Paid</div>
        <div class="legend-item"><div class="legend-seat payment-pending"></div> Pending Payment</div>
        <div class="legend-item"><div class="legend-seat payment-pending-current"></div> Pending By You</div>
        <div class="legend-item"><div class="legend-seat held"></div> Held</div>
        <div class="legend-item"><div class="legend-seat held-current"></div> Held By You</div>
        <div class="legend-item"><div class="legend-seat maintenance"></div> Maintenance</div>
        <div class="legend-item"><div class="legend-seat disabled"></div> Disabled</div>
        <div class="legend-item"><div class="legend-seat vip"></div> VIP (+15,000 VND)</div>
        <div class="legend-item"><div class="legend-seat couple"></div> Couple (+30,000 VND)</div>
      </div>
    </div>

    <div class="summary-panel">
      <div class="summary-title">Booking Summary</div>
      <div class="summary-movie">
        <div class="order-item-img" style="width:70px;height:100px;border-radius:6px;overflow:hidden" id="seatSelectionPoster"></div>
        <div class="summary-movie-info">
          <h4 id="seatSelectionMovieTitle">Movie Title</h4>
          <p id="seatSelectionVenue">Cinema - Room</p>
          <p id="seatSelectionDateTime">Date - Time</p>
        </div>
      </div>
      <div class="divider"></div>
      <div class="summary-row"><label>Selected Seats</label></div>
      <div class="seats-display" id="selectedSeatsDisplay"><span class="seat-tag">None selected</span></div>
      <div class="divider"></div>
      <div class="summary-row"><label>Base Price</label><span id="seatBasePrice">0 VND</span></div>
      <div class="summary-row"><label>Seat Surcharge</label><span id="seatSurcharge">0 VND</span></div>
      <div class="summary-row"><label>Seats</label><span id="seatCount">0</span></div>
      <div class="divider"></div>
      <div class="summary-row total"><label>Total</label><span id="seatTotal" style="color:var(--red)">0 VND</span></div>
      <div style="margin-top:10px;color:var(--text2);font-size:12px;line-height:1.6;">
        Seats are held briefly for your session before checkout. Unfinished checkout sessions are released automatically so inventory can return to sale quickly.
      </div>
      <div style="margin-top:16px">
        <button class="btn btn-primary btn-full btn-lg" id="seatSelectionCheckoutBtn" type="button">Proceed to Checkout</button>
        <a href="<?php echo htmlspecialchars($backToMovieDetail, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-ghost btn-full btn-sm" style="margin-top:8px;display:flex">Back to Showtimes</a>
      </div>
    </div>
  </div>
</div>

<?php $seatSelectionScriptVersion = @filemtime(__DIR__ . '/../../public/assets/js/seat-selection.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/js/seat-selection.js?v=<?php echo urlencode((string) $seatSelectionScriptVersion); ?>"></script>
