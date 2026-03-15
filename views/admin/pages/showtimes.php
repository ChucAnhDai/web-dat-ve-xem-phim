<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">&gt;</span><span>Showtimes</span></div>
      <h1 class="page-title">Showtime Management</h1>
      <p class="page-sub">Create and operate published screenings from the live cinema schema.</p>
    </div>
    <button class="btn btn-primary" id="showtimeCreateBtn" type="button">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Showtime
    </button>
  </div>

  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
    <div class="stat-card red" style="padding:16px;">
      <div id="showtimeTotalStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
      <div class="stat-label">Filtered Showtimes</div>
    </div>
    <div class="stat-card green" style="padding:16px;">
      <div id="showtimePublishedStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
      <div class="stat-label">Published</div>
    </div>
    <div class="stat-card blue" style="padding:16px;">
      <div id="showtimeTodayStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
      <div class="stat-label">Today</div>
    </div>
    <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
      <div id="showtimeSoldOutStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
      <div class="stat-label">Sold Out</div>
    </div>
  </div>

  <div class="card">
    <div class="toolbar">
      <div class="toolbar-scope" aria-label="Showtime list scope">
        <button class="btn btn-primary btn-sm" id="showtimeScopeActiveBtn" type="button">Current</button>
        <button class="btn btn-ghost btn-sm" id="showtimeScopeArchivedBtn" type="button">Archived</button>
      </div>
      <div class="toolbar-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
        <input id="showtimeSearchInput" type="text" placeholder="Search movie, cinema, or room...">
      </div>
      <select id="showtimeMovieFilter" class="select-filter">
        <option value="">All Movies</option>
      </select>
      <select id="showtimeCinemaFilter" class="select-filter">
        <option value="">All Cinemas</option>
      </select>
      <select id="showtimeStatusFilter" class="select-filter">
        <option value="">All Current Status</option>
        <option value="draft">Draft</option>
        <option value="published">Published</option>
        <option value="cancelled">Cancelled</option>
        <option value="completed">Completed</option>
      </select>
      <input id="showtimeDateFilter" class="select-filter" type="date">
      <div class="toolbar-right">
        <span id="showtimeCount" style="font-size:12px;color:var(--text-dim);">0 showtimes</span>
        <span class="table-meta-text" id="showtimeRequestStatus">Ready</span>
      </div>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr>
          <th>Movie</th>
          <th>Cinema</th>
          <th>Room</th>
          <th>Date</th>
          <th>Time</th>
          <th>Price</th>
          <th>Seats</th>
          <th>Status</th>
          <th>Availability</th>
          <th>Actions</th>
        </tr></thead>
        <tbody id="showtimesBody"></tbody>
      </table>
    </div>
    <div id="showtimesPagination"></div>
  </div>
</div>

<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/showtime-management.js?v=20260315a"></script>
