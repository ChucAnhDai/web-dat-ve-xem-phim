<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;">
    <div id="productImageTotalStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Media Assets</div>
  </div>
  <div class="stat-card gold" style="padding:16px;">
    <div id="productImageBannerStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Banners</div>
  </div>
  <div class="stat-card green" style="padding:16px;">
    <div id="productImageActiveStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Active</div>
  </div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
    <div id="productImageDraftStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Draft</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="productImageSearchInput" type="text" placeholder="Search image assets by product, alt text, URL...">
    </div>
    <select id="productImageProductFilter" class="select-filter">
      <option value="">All Products</option>
    </select>
    <select id="productImageTypeFilter" class="select-filter">
      <option value="">All Types</option>
      <option value="thumbnail">Thumbnail</option>
      <option value="gallery">Gallery</option>
      <option value="banner">Banner</option>
      <option value="lifestyle">Lifestyle</option>
    </select>
    <select id="productImageStatusFilter" class="select-filter">
      <option value="">All Status</option>
      <option value="draft">Draft</option>
      <option value="active">Active</option>
      <option value="archived">Archived</option>
    </select>
    <div class="toolbar-right">
      <span id="productImageCount" style="font-size:12px;color:var(--text-dim);">0 assets</span>
      <span class="table-meta-text" id="productImageRequestStatus">Ready</span>
      <button class="btn btn-ghost btn-sm" id="productImageExportBtn" type="button">Export CSV</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Preview</th><th>Product</th><th>Type</th><th>Alt Text</th><th>Order</th><th>Primary</th><th>Status</th><th>Updated</th><th>Actions</th>
      </tr></thead>
      <tbody id="productImagesBody"></tbody>
    </table>
  </div>
  <div id="productImagesPagination"></div>
</div>

<?php $productImageScriptVersion = @filemtime(__DIR__ . '/../../../../../public/assets/admin/product-management-images.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/product-management-images.js?v=<?php echo urlencode((string) $productImageScriptVersion); ?>"></script>
