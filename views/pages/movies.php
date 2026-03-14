<div class="page-header">
  <h1 class="page-title">All Movies</h1>
  <p class="page-subtitle">Discover the latest blockbusters and timeless classics</p>
</div>

<div class="filter-bar">
  <input id="movieCatalogSearchInput" type="text" class="search-input-full" placeholder="Search movies (2+ chars)..." style="max-width:260px">
  <select id="movieCatalogCategoryFilter" class="filter-select">
    <option value="">All Categories</option>
  </select>
  <select id="movieCatalogRatingFilter" class="filter-select">
    <option value="">All Ratings</option>
    <option value="4.5">4.5+ Rating</option>
    <option value="4.0">4.0+ Rating</option>
    <option value="3.5">3.5+ Rating</option>
  </select>
  <select id="movieCatalogSortFilter" class="filter-select">
    <option value="popular">Most Popular</option>
    <option value="newest">Newest</option>
    <option value="rating">Rating</option>
  </select>
  <div id="movieCatalogStatusGroup" style="margin-left:auto;display:flex;gap:6px">
    <button class="filter-chip active" type="button" data-status="now_showing">Now Showing</button>
    <button class="filter-chip" type="button" data-status="coming_soon">Coming Soon</button>
  </div>
</div>

<div class="catalog-meta">
  <span id="movieCatalogCount">0 movies</span>
  <span id="movieCatalogRequestStatus" class="catalog-request-status">Ready</span>
</div>

<div class="movies-grid" id="allMoviesGrid"></div>
<div id="movieCatalogPagination" class="catalog-pagination"></div>

<?php $movieCatalogScriptVersion = @filemtime(__DIR__ . '/../../public/assets/js/movie-catalog.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/js/movie-catalog.js?v=<?php echo urlencode((string) $movieCatalogScriptVersion); ?>"></script>
