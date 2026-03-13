<div style="margin-bottom:16px">
  <a href="<?php echo $publicBase; ?>/movie-detail" class="btn btn-ghost btn-sm">← Back to Showtimes</a>
</div>

<div class="page-header">
  <h1 class="page-title">Select Your Seats</h1>
  <p class="page-subtitle">Dune: Part Two — Hall 1 IMAX — Tue, Mar 11 · 4:45 PM</p>
</div>

<div class="seat-layout">
  <div class="seat-map-wrap">
    <div class="screen-label">◀ — — — SCREEN — — — ▶</div>
    <div class="seat-grid" id="seatGrid"></div>
    <div class="seat-legend">
      <div class="legend-item"><div class="legend-seat available"></div> Available</div>
      <div class="legend-item"><div class="legend-seat selected"></div> Selected</div>
      <div class="legend-item"><div class="legend-seat booked"></div> Booked</div>
      <div class="legend-item"><div class="legend-seat vip"></div> VIP (+$5)</div>
    </div>
  </div>
  <div class="summary-panel">
    <div class="summary-title">Booking Summary</div>
    <div class="summary-movie">
      <img src="https://image.tmdb.org/t/p/w200/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg" alt="">
      <div class="summary-movie-info">
        <h4>Dune: Part Two</h4>
        <p>Hall 1 — IMAX</p>
        <p>Tue, Mar 11 · 4:45 PM</p>
      </div>
    </div>
    <div class="divider"></div>
    <div class="summary-row"><label>Selected Seats</label></div>
    <div class="seats-display" id="selectedSeatsDisplay"><span class="seat-tag">None selected</span></div>
    <div class="divider"></div>
    <div class="summary-row"><label>Ticket Type</label><span>IMAX Standard</span></div>
    <div class="summary-row"><label>Price per seat</label><span>$18.00</span></div>
    <div class="summary-row"><label>Seats</label><span id="seatCount">0</span></div>
    <div class="divider"></div>
    <div class="summary-row total"><label>Total</label><span id="seatTotal" style="color:var(--red)">$0.00</span></div>
    <div style="margin-top:16px">
      <button class="btn btn-primary btn-full btn-lg" onclick="proceedCheckout()">Proceed to Checkout →</button>
      <a href="<?php echo $publicBase; ?>/movie-detail" class="btn btn-ghost btn-full btn-sm" style="margin-top:8px;display:flex">← Back to Showtimes</a>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const ROWS = ['A','B','C','D','E','F','G','H','I','J'];
    const BOOKED = ['A3','A4','B7','B8','C1','C2','D5','D6','E9','E10','F3','F4','G7','G8'];
    const VIP_ROWS = ['H','I','J'];
    let selected = [];

    function buildSeatMap() {
      const grid = document.getElementById('seatGrid');
      if (!grid) return;
      grid.innerHTML = '';
      ROWS.forEach(row => {
        const rowEl = document.createElement('div');
        rowEl.className = 'seat-row';
        const label = document.createElement('div');
        label.className = 'row-label'; label.textContent = row;
        rowEl.appendChild(label);
        for (let s = 1; s <= 12; s++) {
          if (s === 7) { const a = document.createElement('div'); a.className = 'seat-aisle'; rowEl.appendChild(a); }
          const id = `${row}${s}`;
          const isBooked = BOOKED.includes(id);
          const isVip = VIP_ROWS.includes(row);
          const seat = document.createElement('div');
          seat.className = `seat ${isBooked ? 'booked' : isVip ? 'vip' : 'available'}`;
          seat.textContent = s; seat.dataset.id = id;
          if (!isBooked) {
            seat.addEventListener('click', () => {
              const idx = selected.indexOf(id);
              if (idx === -1) {
                selected.push(id);
                seat.classList.remove('available','vip');
                seat.classList.add('selected');
              } else {
                selected.splice(idx,1);
                seat.classList.remove('selected');
                seat.classList.add(isVip?'vip':'available');
              }
              updateSummary();
            });
          }
          rowEl.appendChild(seat);
        }
        grid.appendChild(rowEl);
      });
    }

    function updateSummary() {
      const d = document.getElementById('selectedSeatsDisplay');
      const countEl = document.getElementById('seatCount');
      const totalEl = document.getElementById('seatTotal');
      
      if (countEl) countEl.textContent = selected.length;
      if (totalEl) totalEl.textContent = `$${(selected.length * 18).toFixed(2)}`;
      if (d) {
        d.innerHTML = selected.length
          ? selected.map(s => `<span class="seat-tag has-seat">${s}</span>`).join('')
          : '<span class="seat-tag">None selected</span>';
      }
    }

    window.proceedCheckout = function() {
      if (!selected.length) {
        if (typeof showToast === 'function') {
          showToast('⚠️','No Seats','Please select at least one seat.');
        } else {
          alert('Please select at least one seat.');
        }
        return;
      }
      
      if (typeof showToast === 'function') {
        showToast('🎫','Seats Reserved',`${selected.length} seat(s) held for 10 minutes.`);
      }
      
      setTimeout(() => {
        location.href = window.PUBLIC_BASE_PATH + '/cart';
      }, 1200);
    };

    buildSeatMap();
  });
</script>
