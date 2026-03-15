<div style="margin-bottom:16px">
  <a href="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/movies" class="btn btn-ghost btn-sm">Back to Movies</a>
</div>

<div id="movieDetailState"></div>

<div id="movieDetailContent" hidden>
  <div class="detail-hero">
    <div>
      <div class="detail-poster" id="movieDetailPoster"></div>
    </div>
    <div class="detail-info">
      <div class="detail-tags" id="movieDetailTags"></div>
      <h1 class="detail-title" id="movieDetailTitle">Movie Title</h1>
      <div class="detail-meta-row" id="movieDetailMeta"></div>
      <p class="detail-desc" id="movieDetailSummary"></p>
      <div class="detail-credits" id="movieDetailCredits"></div>
      <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button id="movieDetailTrailerBtn" class="btn btn-secondary btn-lg" type="button">Watch Trailer</button>
        <button class="btn btn-secondary btn-lg" type="button" onclick="showToast('i', 'Watchlist', 'Watchlist flow has not been connected yet.')">Watchlist</button>
      </div>
    </div>
  </div>

  <div class="trailer-section" id="movieDetailTrailerSection" hidden>
    <div id="movieDetailTrailerEmbed"></div>
  </div>

  <div class="showtime-section" id="movieDetailShowtimesSection" hidden>
    <div class="detail-section-head">
      <div>
        <h3 class="detail-section-title">Showtimes For This Movie</h3>
        <p class="detail-section-copy">Only screenings for the movie you are viewing appear here. Choose a screening to continue to seat selection.</p>
      </div>
    </div>
    <div class="date-tabs" id="movieDetailShowtimeDates"></div>
    <div id="movieDetailShowtimeVenues"></div>
  </div>

  <div class="showtime-section" id="movieDetailGallerySection" hidden>
    <div class="detail-section-head">
      <div>
        <h3 class="detail-section-title">Gallery</h3>
        <p class="detail-section-copy">Poster, banner, and still images published for this title.</p>
      </div>
    </div>
    <div class="detail-gallery" id="movieDetailGallery"></div>
  </div>

  <div class="showtime-section" id="movieDetailReviewsSection" hidden>
    <div class="detail-section-head">
      <div>
        <h3 class="detail-section-title">Audience Reviews</h3>
        <p class="detail-section-copy">Approved reviews from your movie catalog will appear here.</p>
      </div>
    </div>
    <div class="detail-review-list" id="movieDetailReviews"></div>
  </div>

  <div style="margin-top:36px">
    <div class="section-header">
      <h2 class="section-title">You May Also <span>Like</span></h2>
      <a href="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/movies" class="btn btn-ghost btn-sm">View all</a>
    </div>
    <div class="movies-grid" id="relatedMovies"></div>
  </div>
</div>

<?php $movieDetailScriptVersion = @filemtime(__DIR__ . '/../../../public/assets/js/movie-detail.js') ?: time(); ?>
<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/js/movie-detail.js?v=<?php echo urlencode((string) $movieDetailScriptVersion); ?>"></script>
