<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card red" style="padding:16px;">
    <div id="movieAssetTotalStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Total Assets</div>
  </div>
  <div class="stat-card green" style="padding:16px;">
    <div id="movieAssetPosterStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Poster Assets</div>
  </div>
  <div class="stat-card blue" style="padding:16px;">
    <div id="movieAssetGalleryStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Gallery Assets</div>
  </div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
    <div id="movieAssetDraftStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Draft Assets</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="movieAssetSearchInput" type="text" placeholder="Search assets...">
    </div>
    <select id="movieAssetMovieFilter" class="select-filter">
      <option value="">All Movies</option>
    </select>
    <select id="movieAssetTypeFilter" class="select-filter">
      <option value="">All Asset Types</option>
      <option value="poster">Poster</option>
      <option value="banner">Banner</option>
      <option value="gallery">Gallery</option>
    </select>
    <select id="movieAssetStatusFilter" class="select-filter">
      <option value="">All Status</option>
      <option value="draft">Draft</option>
      <option value="active">Active</option>
      <option value="archived">Archived</option>
    </select>
    <div class="toolbar-right">
      <span style="font-size:12px;color:var(--text-dim);" id="movieAssetCount">0 assets</span>
      <span class="table-meta-text" id="movieAssetRequestStatus">Ready</span>
      <button class="btn btn-ghost btn-sm" id="movieAssetExportBtn" type="button">Export CSV</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Preview</th><th>Movie</th><th>Asset Type</th><th>Alt Text</th><th>Sort Order</th><th>Primary</th><th>Status</th><th>Updated</th><th>Actions</th>
      </tr></thead>
      <tbody id="movieAssetsBody"></tbody>
    </table>
  </div>
  <div id="movieAssetsPagination"></div>
</div>

<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/movie-management-movie-images.js"></script>
