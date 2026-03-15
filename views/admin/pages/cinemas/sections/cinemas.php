<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card red" style="padding:16px;">
    <div id="cinemaTotalStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Cinema Locations</div>
  </div>
  <div class="stat-card blue" style="padding:16px;">
    <div id="cinemaCityStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Operating Cities</div>
  </div>
  <div class="stat-card green" style="padding:16px;">
    <div id="cinemaRoomStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Tracked Rooms</div>
  </div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
    <div id="cinemaSeatStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Total Seats</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-scope" aria-label="Cinema list scope">
      <button class="btn btn-primary btn-sm" id="cinemaScopeActiveBtn" type="button">Current</button>
      <button class="btn btn-ghost btn-sm" id="cinemaScopeArchivedBtn" type="button">Archived</button>
    </div>
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="cinemaSearchInput" type="text" placeholder="Search cinemas by name, slug, city, manager...">
    </div>
    <select id="cinemaCityFilter" class="select-filter">
      <option value="">All Cities</option>
    </select>
    <select id="cinemaStatusFilter" class="select-filter">
      <option value="">All Current Status</option>
      <option value="active">Active</option>
      <option value="renovation">Renovation</option>
      <option value="closed">Closed</option>
    </select>
    <div class="toolbar-right">
      <span id="cinemaCount" style="font-size:12px;color:var(--text-dim);">0 cinemas</span>
      <span class="table-meta-text" id="cinemaRequestStatus">Ready</span>
      <a class="btn btn-ghost btn-sm" href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/cinemas?section=rooms">View Rooms</a>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Cinema</th>
        <th>City</th>
        <th>Address</th>
        <th>Rooms</th>
        <th>Total Seats</th>
        <th>Manager</th>
        <th>Status</th>
        <th>Actions</th>
      </tr></thead>
      <tbody id="cinemasBody"></tbody>
    </table>
  </div>
  <div id="cinemasPagination"></div>
</div>

<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/cinema-management-cinemas.js?v=20260315a"></script>
