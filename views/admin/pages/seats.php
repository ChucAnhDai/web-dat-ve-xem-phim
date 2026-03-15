<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">&gt;</span><span>Cinema Management</span><span class="sep">&gt;</span><span>Seats</span></div>
      <h1 class="page-title">Seat Management</h1>
      <p class="page-sub">Edit room layouts from persisted seat data and publish consistent capacity totals.</p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
      <select class="select-filter" id="seatCinemaFilter">
        <option value="">Select Cinema</option>
      </select>
      <select class="select-filter" id="seatRoomFilter">
        <option value="">Select Room</option>
      </select>
      <button class="btn btn-primary btn-sm" id="saveSeatLayoutBtn" type="button">Save Layout</button>
    </div>
  </div>

  <div class="grid-main-side">
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title" id="seatRoomTitle">Select a room</div>
          <div class="card-sub" id="seatRoomMeta">Choose a cinema and room to manage the persisted layout.</div>
        </div>
        <span class="table-meta-text" id="seatRequestStatus">Ready</span>
      </div>
      <div class="card-body">
        <div class="surface-card" style="margin-bottom:18px;">
          <div class="surface-card-title">Layout Rules</div>
          <div class="surface-card-copy">Layouts are replaced transactionally. Saving will recompute room capacity from the current seat list and will be blocked if future published showtimes or booked tickets exist.</div>
        </div>
        <div id="seatLayoutState" class="table-empty-state">
          <strong>Select a room to load its seat map.</strong>
          <div class="table-meta-text">The live layout, seat status, and room capacity will appear here.</div>
        </div>
        <div class="seat-map" id="seatMap" hidden></div>
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
      <div class="card">
        <div class="card-header"><div class="card-title">Seat Statistics</div></div>
        <div class="card-body">
          <div style="display:flex;flex-direction:column;gap:14px;">
            <div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:12px;color:var(--text-muted);">Total Seats</span><span id="seatTotalStat">0</span></div>
              <div class="progress-bar"><div class="progress-fill" id="seatTotalProgress" style="width:0%;background:var(--blue);"></div></div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:12px;color:var(--text-muted);">VIP Seats</span><span id="seatVipStat">0</span></div>
              <div class="progress-bar"><div class="progress-fill" id="seatVipProgress" style="width:0%;background:var(--gold);"></div></div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:12px;color:var(--text-muted);">Couple Seats</span><span id="seatCoupleStat">0</span></div>
              <div class="progress-bar"><div class="progress-fill" id="seatCoupleProgress" style="width:0%;background:var(--purple);"></div></div>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span style="font-size:12px;color:var(--text-muted);">Blocked Seats</span><span id="seatBlockedStat">0</span></div>
              <div class="progress-bar"><div class="progress-fill" id="seatBlockedProgress" style="width:0%;background:var(--orange);"></div></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Edit Selected Seat</div></div>
        <div class="card-body">
          <div class="field" style="margin-bottom:12px;">
            <label>Selected Seat</label>
            <input class="input" id="selectedSeatLabel" value="Click a seat to select" readonly>
          </div>
          <div class="field" style="margin-bottom:12px;">
            <label>Seat Type</label>
            <select class="select" id="selectedSeatType">
              <option value="normal">Normal</option>
              <option value="vip">VIP</option>
              <option value="couple">Couple</option>
            </select>
          </div>
          <div class="field" style="margin-bottom:16px;">
            <label>Status</label>
            <select class="select" id="selectedSeatStatus">
              <option value="available">Available</option>
              <option value="maintenance">Maintenance</option>
              <option value="disabled">Disabled</option>
            </select>
          </div>
          <div style="display:flex;gap:8px;">
            <button class="btn btn-primary" id="updateSelectedSeatBtn" style="width:100%;" type="button">Update Seat</button>
            <button class="btn btn-ghost" id="removeSelectedSeatBtn" style="width:100%;" type="button">Remove</button>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Bulk Layout Tools</div></div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">
          <div class="field">
            <label>Generate Rows</label>
            <input class="input" id="seatPresetRows" placeholder="A,B,C,D,E,F,G,H" value="A,B,C,D,E,F,G,H">
          </div>
          <div class="field">
            <label>Seats Per Row</label>
            <input class="input" id="seatPresetCount" type="number" min="1" max="40" value="12">
          </div>
          <div class="field">
            <label>Default Seat Type</label>
            <select class="select" id="seatPresetType">
              <option value="normal">Normal</option>
              <option value="vip">VIP</option>
              <option value="couple">Couple</option>
            </select>
          </div>
          <div class="field">
            <label>Default Status</label>
            <select class="select" id="seatPresetStatus">
              <option value="available">Available</option>
              <option value="maintenance">Maintenance</option>
              <option value="disabled">Disabled</option>
            </select>
          </div>
          <button class="btn btn-secondary" id="generateSeatLayoutBtn" type="button">Generate Layout</button>
          <button class="btn btn-ghost" id="appendSeatRowBtn" type="button">Append One More Row</button>
          <button class="btn btn-ghost" id="clearSeatLayoutBtn" type="button">Clear Layout</button>
        </div>
      </div>
    </div>
  </div>
</div>

<?php $seatManagementScriptVersion = @filemtime(__DIR__ . '/../../../public/assets/admin/cinema-management-seats.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/cinema-management-seats.js?v=<?php echo urlencode((string) $seatManagementScriptVersion); ?>"></script>
