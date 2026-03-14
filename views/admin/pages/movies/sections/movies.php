<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card red" style="padding:16px;">
    <div id="movieTotalStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Total Movies</div>
  </div>
  <div class="stat-card green" style="padding:16px;">
    <div id="movieNowShowingStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Now Showing</div>
  </div>
  <div class="stat-card blue" style="padding:16px;">
    <div id="movieComingSoonStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Coming Soon</div>
  </div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
    <div id="movieDraftStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Draft</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="movieSearchInput" type="text" placeholder="Search movies by title, slug, director, category...">
    </div>
    <select id="movieCategoryFilter" class="select-filter">
      <option value="">All Categories</option>
    </select>
    <select id="movieStatusFilter" class="select-filter">
      <option value="">All Status</option>
      <option value="draft">Draft</option>
      <option value="coming_soon">Coming Soon</option>
      <option value="now_showing">Now Showing</option>
      <option value="ended">Ended</option>
      <option value="archived">Archived</option>
    </select>
    <div class="toolbar-right">
      <span style="font-size:12px;color:var(--text-dim);" id="movieCount">0 movies</span>
      <span class="table-meta-text" id="movieRequestStatus">Ready</span>
      <button class="btn btn-ghost btn-sm" id="movieImportOphimBtn" type="button">Import OPhim</button>
      <button class="btn btn-ghost btn-sm" id="movieBatchImportOphimBtn" type="button">Batch Sync OPhim</button>
      <button class="btn btn-ghost btn-sm" id="movieExportBtn" type="button">Export CSV</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Poster</th><th>Movie Title</th><th>Category</th><th>Duration</th>
        <th>Release Date</th><th>Rating</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody id="moviesBody"></tbody>
    </table>
  </div>
  <div id="moviesPagination"></div>
</div>

<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/movie-management-movies.js"></script>
