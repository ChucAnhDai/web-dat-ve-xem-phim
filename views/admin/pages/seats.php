<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">›</span><span>Cinema Management</span><span class="sep">›</span><span>Seats</span></div>
      <h1 class="page-title">Seat Management</h1>
      <p class="page-sub">Visual seat layout editor</p>
    </div>
    <div style="display:flex;gap:10px;">
      <select class="select-filter"><option>CineShop Galaxy</option><option>CineShop Premier</option><option>CineShop Landmark</option></select>
      <select class="select-filter"><option>Room 1 — Deluxe</option><option>Room 2 — VIP</option><option>Room 3 — IMAX</option></select>
    </div>
  </div>

  <div class="grid-main-side">
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Room 1 — Deluxe · CineShop Galaxy</div>
          <div class="card-sub">Click seat to select / edit type</div>
        </div>
        <button class="btn btn-primary btn-sm" onclick="showToast('Seat layout saved!','success')">Save Layout</button>
      </div>
      <div class="seat-map" id="seatMap"></div>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
      <div class="card">
        <div class="card-header"><div class="card-title">Seat Statistics</div></div>
        <div class="card-body">
          <div style="display:flex;flex-direction:column;gap:14px;">
            <div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:12px;color:var(--text-muted);">Normal Seats</span><span style="font-size:12px;font-weight:600;">120 / 140</span></div>
              <div class="progress-bar"><div class="progress-fill" style="width:85%;background:var(--blue);"></div></div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:12px;color:var(--text-muted);">VIP Seats</span><span style="font-size:12px;font-weight:600;">24 / 30</span></div>
              <div class="progress-bar"><div class="progress-fill" style="width:80%;background:var(--gold);"></div></div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:12px;color:var(--text-muted);">Couple Seats</span><span style="font-size:12px;font-weight:600;">8 / 10</span></div>
              <div class="progress-bar"><div class="progress-fill" style="width:80%;background:var(--purple);"></div></div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:12px;color:var(--text-muted);">Currently Booked</span><span style="font-size:12px;font-weight:600;color:var(--red);">94 seats</span></div>
              <div class="progress-bar"><div class="progress-fill" style="width:52%;background:var(--red);"></div></div>
            </div>
          </div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Edit Selected Seat</div></div>
        <div class="card-body">
          <div class="field" style="margin-bottom:12px;"><label>Selected Seat</label><input class="input" id="selectedSeat" value="Click a seat to select" readonly></div>
          <div class="field" style="margin-bottom:12px;"><label>Seat Type</label>
            <select class="select" id="seatType"><option>Normal</option><option>VIP</option><option>Couple</option></select>
          </div>
          <div class="field" style="margin-bottom:16px;"><label>Status</label>
            <select class="select"><option>Available</option><option>Maintenance</option><option>Disabled</option></select>
          </div>
          <button class="btn btn-primary" style="width:100%;" onclick="showToast('Seat updated!','success')">Update Seat</button>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Bulk Actions</div></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
          <button class="btn btn-secondary" style="width:100%;" onclick="showToast('All seats reset to Available','info')">Reset All to Available</button>
          <button class="btn btn-ghost" style="width:100%;border-color:var(--gold);color:var(--gold);" onclick="showToast('Row A set to VIP','info')">Set Row A as VIP</button>
          <button class="btn btn-ghost" style="width:100%;" onclick="showToast('Last row set to Couple','info')">Set Last Row as Couple</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function renderSeatMap() {
  const el = document.getElementById('seatMap');
  const rows = ['A','B','C','D','E','F','G','H'];
  let html = '<div class="screen-bar"></div>';
  rows.forEach((row, ri) => {
    html += '<div class="seat-row">';
    html += `<div class="seat-label">${row}</div>`;
    if (ri < 2) {
      for(let c=1;c<=12;c++){
        const booked = Math.random()<0.3;
        html += `<div class="seat vip ${booked?'booked':''}" title="${row}${c} — VIP" onclick="selectSeat(this,'${row}${c}','VIP')"></div>`;
      }
    } else if (ri >= 6) {
      for(let c=1;c<=5;c++){
        html += `<div class="seat couple" title="${row}${(c-1)*2+1}-${(c-1)*2+2} — Couple" onclick="selectSeat(this,'${row}${(c-1)*2+1}','Couple')"></div>`;
      }
    } else {
      for(let c=1;c<=16;c++){
        const booked = Math.random()<0.45;
        html += `<div class="seat normal ${booked?'booked':''}" title="${row}${c}" onclick="selectSeat(this,'${row}${c}','Normal')"></div>`;
      }
    }
    html += `<div class="seat-label">${row}</div></div>`;
  });
  html += `<div class="seat-legend">
    <div class="legend-item"><div class="legend-box" style="background:var(--bg3);border-color:var(--border);"></div>Normal</div>
    <div class="legend-item"><div class="legend-box" style="background:rgba(201,168,76,0.2);border-color:var(--gold);"></div>VIP</div>
    <div class="legend-item"><div class="legend-box" style="background:rgba(168,85,247,0.2);border-color:var(--purple);width:32px;"></div>Couple</div>
    <div class="legend-item"><div class="legend-box" style="background:rgba(229,9,20,0.2);border-color:var(--red);"></div>Booked</div>
    <div class="legend-item"><div class="legend-box" style="background:var(--red);border-color:var(--red);"></div>Selected</div>
  </div>`;
  el.innerHTML = html;
}

function selectSeat(el, id, type) {
  if (el.classList.contains('booked')) { showToast(`Seat ${id} is already booked`, 'warning'); return; }
  document.querySelectorAll('.seat.selected').forEach(s => {
    if (!s.classList.contains('booked')) s.classList.remove('selected');
  });
  el.classList.toggle('selected');
  document.getElementById('selectedSeat').value = `Row ${id.charAt(0)}, Seat ${id.slice(1)} — ${type}`;
  document.getElementById('seatType').value = type;
  showToast(`Seat ${id} selected`, 'info');
}

document.addEventListener('DOMContentLoaded', function(){
  renderSeatMap();
});
</script>
