<div class="page-header">
  <h1 class="page-title">Showtimes</h1>
  <p class="page-subtitle">Find live screenings by movie, cinema, city, and date.</p>
</div>

<div class="filter-bar" style="flex-wrap:wrap;align-items:center;">
  <input id="publicShowtimeSearchInput" class="filter-select" type="text" placeholder="Search movie or cinema..." style="min-width:220px;">
  <select id="publicShowtimeMovieFilter" class="filter-select">
    <option value="">All Movies</option>
  </select>
  <select id="publicShowtimeCinemaFilter" class="filter-select">
    <option value="">All Cinemas</option>
  </select>
  <select id="publicShowtimeCityFilter" class="filter-select">
    <option value="">All Cities</option>
  </select>
  <input id="publicShowtimeDateFilter" type="date" class="filter-select">
  <span id="publicShowtimeStatus" style="font-size:12px;color:var(--text2);margin-left:auto;">Ready</span>
</div>

<div id="publicShowtimeState"></div>
<div id="publicShowtimesGrid"></div>
<div id="publicShowtimesPagination"></div>

<?php $showtimesScriptVersion = @filemtime(__DIR__ . '/../../public/assets/js/showtimes.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/js/showtimes.js?v=<?php echo urlencode((string) $showtimesScriptVersion); ?>"></script>
