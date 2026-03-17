<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;">
    <div id="productTotalStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Total Products</div>
  </div>
  <div class="stat-card green" style="padding:16px;">
    <div id="productInStockStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">In Stock</div>
  </div>
  <div class="stat-card orange" style="padding:16px;">
    <div id="productLowStockStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Low Stock</div>
  </div>
  <div class="stat-card red" style="padding:16px;">
    <div id="productOutOfStockStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">0</div>
    <div class="stat-label">Out of Stock</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="productSearchInput" type="text" placeholder="Search products by name, slug, SKU, brand...">
    </div>
    <select id="productCategoryFilter" class="select-filter">
      <option value="">All Categories</option>
    </select>
    <select id="productStockFilter" class="select-filter">
      <option value="">All Stock</option>
      <option value="in_stock">In Stock</option>
      <option value="low_stock">Low Stock</option>
      <option value="out_of_stock">Out of Stock</option>
    </select>
    <select id="productStatusFilter" class="select-filter">
      <option value="">All Status</option>
      <option value="draft">Draft</option>
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
      <option value="archived">Archived</option>
    </select>
    <div class="toolbar-right">
      <span id="productCount" style="font-size:12px;color:var(--text-dim);">0 products</span>
      <span class="table-meta-text" id="productRequestStatus">Ready</span>
      <button class="btn btn-ghost btn-sm" id="productExportBtn" type="button">Export CSV</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Item</th><th>SKU</th><th>Category</th><th>Price</th><th>Stock</th><th>Visibility</th><th>Status</th><th>Updated</th><th>Actions</th>
      </tr></thead>
      <tbody id="productsBody"></tbody>
    </table>
  </div>
  <div id="productsPagination"></div>
</div>

<?php $productManagementScriptVersion = @filemtime(__DIR__ . '/../../../../../public/assets/admin/product-management-products.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/admin/product-management-products.js?v=<?php echo urlencode((string) $productManagementScriptVersion); ?>"></script>
