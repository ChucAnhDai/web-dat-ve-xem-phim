<div class="page-header">
  <h1 class="page-title">Cinema Shop</h1>
  <p class="page-subtitle">Snacks, drinks, combos & exclusive merchandise</p>
</div>

<div class="category-tabs" id="categoryTabs">
  <button class="cat-tab active" type="button" data-category-slug="all">
    <span class="cat-tab-icon">All</span> All Products
  </button>
</div>

<div class="filter-bar">
  <input id="shopSearchInput" type="text" class="search-input-full" placeholder="Search products..." style="max-width:260px">
  <select id="shopPriceFilter" class="filter-select">
    <option value="all">All Prices</option>
    <option value="under_100000">Under 100,000 VND</option>
    <option value="100000_200000">100,000 - 200,000 VND</option>
    <option value="over_200000">Over 200,000 VND</option>
  </select>
  <select id="shopSortSelect" class="filter-select">
    <option value="featured">Featured First</option>
    <option value="price_asc">Price: Low to High</option>
    <option value="price_desc">Price: High to Low</option>
    <option value="newest">Newest</option>
    <option value="oldest">Oldest</option>
    <option value="name_asc">Name: A to Z</option>
  </select>
</div>

<div class="catalog-meta">
  <div id="shopCatalogMetaText">Loading products...</div>
  <div class="catalog-request-status" id="shopRequestStatus">Connecting to catalog</div>
</div>

<div class="products-grid" id="shopGrid"></div>
<div class="catalog-empty-state" id="shopCatalogEmpty" hidden></div>

<div class="catalog-pagination" id="shopPagination" hidden>
  <div class="catalog-pagination-info" id="shopPaginationInfo"></div>
  <div class="catalog-pagination-buttons" id="shopPaginationButtons"></div>
</div>
