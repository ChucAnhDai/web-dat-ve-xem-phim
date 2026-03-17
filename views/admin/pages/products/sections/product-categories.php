<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;">
    <div id="productCategoryTotalStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Categories</div>
  </div>
  <div class="stat-card gold" style="padding:16px;">
    <div id="productCategoryFeaturedStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Featured</div>
  </div>
  <div class="stat-card green" style="padding:16px;">
    <div id="productCategoryTaggedStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Products Tagged</div>
  </div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
    <div id="productCategoryHiddenStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Hidden</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="productCategorySearchInput" type="text" placeholder="Search categories by name, slug, description...">
    </div>
    <select id="productCategoryVisibilityFilter" class="select-filter">
      <option value="">All Visibility</option>
      <option value="featured">Featured</option>
      <option value="standard">Standard</option>
      <option value="hidden">Hidden</option>
    </select>
    <select id="productCategoryStatusFilter" class="select-filter">
      <option value="">All Status</option>
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
      <option value="archived">Archived</option>
    </select>
    <div class="toolbar-right">
      <span id="productCategoryCount" style="font-size:12px;color:var(--text-dim);">0 categories</span>
      <span class="table-meta-text" id="productCategoryRequestStatus">Ready</span>
      <button class="btn btn-ghost btn-sm" id="productCategoryExportBtn" type="button">Export CSV</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Name</th><th>Slug</th><th>Description</th><th>Products</th><th>Order</th><th>Visibility</th><th>Status</th><th>Updated</th><th>Actions</th>
      </tr></thead>
      <tbody id="productCategoriesBody"></tbody>
    </table>
  </div>
  <div id="productCategoriesPagination"></div>
</div>

<?php $productCategoryScriptVersion = @filemtime(__DIR__ . '/../../../../../public/assets/admin/product-management-categories.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/product-management-categories.js?v=<?php echo urlencode((string) $productCategoryScriptVersion); ?>"></script>
