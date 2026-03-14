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
        <a id="movieDetailBookBtn" href="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/seat-selection" class="btn btn-primary btn-lg">Book Ticket</a>
        <button id="movieDetailTrailerBtn" class="btn btn-secondary btn-lg" type="button">Watch Trailer</button>
        <button class="btn btn-secondary btn-lg" type="button" onclick="showToast('i', 'Watchlist', 'Watchlist flow has not been connected yet.')">Watchlist</button>
      </div>
    </div>
  </div>

  <div class="trailer-section" id="movieDetailTrailerSection" hidden>
    <a id="movieDetailTrailerLink" class="trailer-placeholder" href="#" target="_blank" rel="noopener noreferrer">
      <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(229,9,20,0.1),rgba(0,0,0,0.5))"></div>
      <div style="text-align:center;position:relative;z-index:2">
        <div class="play-btn-big">Play</div>
        <p style="font-size:13px;color:var(--text2);margin-top:10px">Watch Official Trailer</p>
      </div>
    </a>
  </div>

  <div class="showtime-section" id="movieDetailGallerySection" hidden>
    <h3 style="font-family:'Bebas Neue',cursive;font-size:22px;letter-spacing:0.5px;margin-bottom:16px">Gallery</h3>
    <div class="detail-gallery" id="movieDetailGallery"></div>
  </div>

  <div class="showtime-section">
    <h3 style="font-family:'Bebas Neue',cursive;font-size:22px;letter-spacing:0.5px;margin-bottom:16px">Available Sources</h3>
    <div class="detail-muted" style="margin-bottom:16px">Streaming/embed sources are synchronized from your OPhim provider.</div>
    <div id="movieDetailPlaybackGroups"></div>
  </div>

  <div class="showtime-section" id="movieDetailReviewsSection" hidden>
    <h3 style="font-family:'Bebas Neue',cursive;font-size:22px;letter-spacing:0.5px;margin-bottom:16px">Audience Reviews</h3>
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

<script src="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/assets/js/movie-detail.js"></script>
