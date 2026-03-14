<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card red" style="padding:16px;">
    <div id="movieCategoryTotalStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Total Categories</div>
  </div>
  <div class="stat-card green" style="padding:16px;">
    <div id="movieCategoryActiveStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Active</div>
  </div>
  <div class="stat-card blue" style="padding:16px;">
    <div id="movieCategoryMovieLinkStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Movies Tagged</div>
  </div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
    <div id="movieCategoryInactiveStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Inactive</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="movieCategorySearchInput" type="text" placeholder="Search categories...">
    </div>
    <select id="movieCategoryStatusFilter" class="select-filter">
      <option value="">All Status</option>
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
    </select>
    <div class="toolbar-right">
      <span style="font-size:12px;color:var(--text-dim);" id="movieCategoryCount">0 categories</span>
      <span class="table-meta-text" id="movieCategoryRequestStatus">Ready</span>
      <button class="btn btn-ghost btn-sm" id="movieCategoryExportBtn" type="button">Export CSV</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Name</th><th>Slug</th><th>Description</th><th>Movies</th><th>Sort Order</th><th>Status</th><th>Updated</th><th>Actions</th>
      </tr></thead>
      <tbody id="movieCategoriesBody"></tbody>
    </table>
  </div>
  <div id="movieCategoriesPagination"></div>
</div>

<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/movie-management-categories.js"></script>
