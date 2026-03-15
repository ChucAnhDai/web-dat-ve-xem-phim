<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card red" style="padding:16px;">
    <div id="roomTotalStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Total Rooms</div>
  </div>
  <div class="stat-card gold" style="padding:16px;">
    <div id="roomTypeStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Formats</div>
  </div>
  <div class="stat-card blue" style="padding:16px;">
    <div id="roomActiveStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Active Rooms</div>
  </div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
    <div id="roomSeatStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Tracked Capacity</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-scope" aria-label="Room list scope">
      <button class="btn btn-primary btn-sm" id="roomScopeActiveBtn" type="button">Current</button>
      <button class="btn btn-ghost btn-sm" id="roomScopeArchivedBtn" type="button">Archived</button>
    </div>
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="roomSearchInput" type="text" placeholder="Search rooms by cinema, room name, or screen label...">
    </div>
    <select id="roomCinemaFilter" class="select-filter">
      <option value="">All Cinemas</option>
    </select>
    <select id="roomTypeFilter" class="select-filter">
      <option value="">All Types</option>
      <option value="standard_2d">Standard 2D</option>
      <option value="premium_3d">Premium 3D</option>
      <option value="vip_recliner">VIP Recliner</option>
      <option value="imax">IMAX</option>
      <option value="4dx">4DX</option>
      <option value="screenx">ScreenX</option>
      <option value="dolby_atmos">Dolby Atmos</option>
    </select>
    <select id="roomStatusFilter" class="select-filter">
      <option value="">All Current Status</option>
      <option value="active">Active</option>
      <option value="maintenance">Maintenance</option>
      <option value="closed">Closed</option>
    </select>
    <div class="toolbar-right">
      <span id="roomCount" style="font-size:12px;color:var(--text-dim);">0 rooms</span>
      <span class="table-meta-text" id="roomRequestStatus">Ready</span>
      <a class="btn btn-ghost btn-sm" href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/seats">Manage Seats</a>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Room</th>
        <th>Cinema</th>
        <th>Type</th>
        <th>Screen</th>
        <th>Buffer</th>
        <th>Capacity</th>
        <th>Status</th>
        <th>Actions</th>
      </tr></thead>
      <tbody id="roomsBody"></tbody>
    </table>
  </div>
  <div id="roomsPagination"></div>
</div>

<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/cinema-management-rooms.js?v=20260315a"></script>
