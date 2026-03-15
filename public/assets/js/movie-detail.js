(function () {
  const state = {
    slug: '',
    movie: null,
    showtimes: [],
    activeShowDate: '',
    gallery: [],
    reviews: [],
    relatedMovies: [],
    initialized: false,
  };

  const dom = {};

  function initMovieDetailPage() {
    if (state.initialized) {
      return;
    }

    cacheDom();
    if (!dom.state || !dom.content) {
      return;
    }

    state.initialized = true;
    bindEvents();
    state.slug = readSlug();

    if (!state.slug) {
      renderStateCard('Movie not selected.', 'Choose a movie from the catalog to open its detail page.');
      return;
    }

    renderStateCard('Loading movie details...', 'Please wait while we load the latest movie profile.');
    void loadMovieDetail();
  }

  function cacheDom() {
    dom.state = document.getElementById('movieDetailState');
    dom.content = document.getElementById('movieDetailContent');
    dom.poster = document.getElementById('movieDetailPoster');
    dom.tags = document.getElementById('movieDetailTags');
    dom.title = document.getElementById('movieDetailTitle');
    dom.meta = document.getElementById('movieDetailMeta');
    dom.summary = document.getElementById('movieDetailSummary');
    dom.credits = document.getElementById('movieDetailCredits');
    dom.trailerBtn = document.getElementById('movieDetailTrailerBtn');
    dom.trailerSection = document.getElementById('movieDetailTrailerSection');
    dom.trailerEmbed = document.getElementById('movieDetailTrailerEmbed');
    dom.showtimesSection = document.getElementById('movieDetailShowtimesSection');
    dom.showtimeDates = document.getElementById('movieDetailShowtimeDates');
    dom.showtimeVenues = document.getElementById('movieDetailShowtimeVenues');
    dom.gallerySection = document.getElementById('movieDetailGallerySection');
    dom.gallery = document.getElementById('movieDetailGallery');
    dom.reviewsSection = document.getElementById('movieDetailReviewsSection');
    dom.reviews = document.getElementById('movieDetailReviews');
    dom.relatedMovies = document.getElementById('relatedMovies');
  }

  function bindEvents() {
    dom.relatedMovies?.addEventListener('click', handleRelatedMovieClick);
    dom.trailerBtn?.addEventListener('click', handleTrailerClick);
    dom.showtimeDates?.addEventListener('click', handleShowtimeDateClick);
    dom.showtimeVenues?.addEventListener('click', handleShowtimeSelectionClick);
  }

  async function loadMovieDetail() {
    try {
      const payload = await fetchJson(`/api/movies/${encodeURIComponent(state.slug)}`);
      const data = payload?.data || {};

      state.movie = data.movie || null;
      state.showtimes = Array.isArray(data.showtimes) ? data.showtimes : [];
      state.activeShowDate = state.showtimes[0]?.date || '';
      state.gallery = Array.isArray(data.gallery) ? data.gallery : [];
      state.reviews = Array.isArray(data.reviews) ? data.reviews : [];
      state.relatedMovies = Array.isArray(data.related_movies) ? data.related_movies : [];

      renderMovieDetail();
    } catch (error) {
      renderStateCard(
        'Movie detail unavailable.',
        error.message || 'The selected movie could not be loaded right now.'
      );

      if (typeof showToast === 'function') {
        showToast('i', 'Movie Detail', error.message || 'Failed to load movie detail.');
      }
    }
  }

  function renderMovieDetail() {
    const movie = state.movie || {};
    document.title = `${movie.title || 'Movie Detail'} - CinemaX`;

    renderPoster(movie);
    renderTags(movie);
    renderMeta(movie);
    renderSummary(movie);
    renderCredits(movie);
    renderTrailer(movie);
    renderShowtimes(movie);
    renderGallery();
    renderReviews();
    renderRelatedMovies();

    if (dom.state) {
      dom.state.innerHTML = '';
    }
    if (dom.content) {
      dom.content.hidden = false;
    }
  }

  function renderPoster(movie) {
    if (!dom.poster) {
      return;
    }

    const mediaUrl = firstNonEmpty(movie.poster_url, movie.banner_url);
    if (!mediaUrl) {
      dom.poster.innerHTML = buildPosterFallbackHtml(movie.title);
      return;
    }

    dom.poster.innerHTML = `
      <div class="detail-media-card detail-media-card-poster">
        <img
          class="detail-media-img"
          src="${escapeHtmlAttr(mediaUrl)}"
          alt="${escapeHtmlAttr(movie.title || 'Movie poster')}"
          loading="lazy"
        >
        <div class="detail-poster-fallback" hidden>${escapeHtml(buildPosterCode(movie.title))}</div>
      </div>
    `;
    wireMediaFallbacks(dom.poster);
  }

  function renderTags(movie) {
    if (!dom.tags) {
      return;
    }

    const tags = [];
    const categories = Array.isArray(movie.category_names) ? movie.category_names : [];

    categories.forEach(category => {
      tags.push(`<span class="tag tag-genre">${escapeHtml(category)}</span>`);
    });

    if (movie.language) {
      tags.push(`<span class="tag tag-genre">${escapeHtml(movie.language)}</span>`);
    }

    if (movie.age_rating) {
      tags.push(`<span class="tag tag-genre">${escapeHtml(movie.age_rating)}</span>`);
    }

    if (movie.status) {
      tags.push(`<span class="tag tag-genre">${escapeHtml(humanizeStatus(movie.status))}</span>`);
    }

    dom.tags.innerHTML = tags.join('');
  }

  function renderMeta(movie) {
    if (!dom.meta) {
      return;
    }

    const parts = [
      `<span class="rating-stars">${buildStarRating(movie.average_rating)}</span>`,
      `<span>${escapeHtml(formatRating(movie.average_rating))}</span>`,
      `<span class="dot"></span>`,
      `<span>${escapeHtml(formatDuration(movie.duration_minutes))}</span>`,
      `<span class="dot"></span>`,
      `<span>${escapeHtml(formatReleaseDate(movie.release_date))}</span>`,
    ];

    if (movie.review_count) {
      parts.push('<span class="dot"></span>');
      parts.push(`<span>${escapeHtml(formatReviewCount(movie.review_count))}</span>`);
    }

    dom.meta.innerHTML = parts.join('');

    if (dom.title) {
      dom.title.textContent = movie.title || 'Untitled Movie';
    }
  }

  function renderSummary(movie) {
    if (!dom.summary) {
      return;
    }

    dom.summary.textContent = movie.summary || 'Movie synopsis is not available for this title yet.';
  }

  function renderCredits(movie) {
    if (!dom.credits) {
      return;
    }

    const creditItems = [
      { label: 'Director', value: movie.director },
      { label: 'Writer', value: movie.writer },
      { label: 'Cast', value: movie.cast_text },
      { label: 'Countries', value: movie.studio },
      { label: 'Release', value: formatReleaseDate(movie.release_date) },
    ].filter(item => item.value);

    if (creditItems.length === 0) {
      dom.credits.innerHTML = '<div class="detail-muted">Additional credits are not available for this movie yet.</div>';
      return;
    }

    dom.credits.innerHTML = creditItems.map(item => `
      <div class="credit-item">
        <label>${escapeHtml(item.label)}</label>
        <p>${escapeHtml(item.value)}</p>
      </div>
    `).join('');
  }

  function renderTrailer(movie) {
    if (!dom.trailerSection || !dom.trailerEmbed) {
      return;
    }

    const trailerSource = resolveTrailerSource(movie?.trailer_url || '');

    if (dom.trailerBtn) {
      dom.trailerBtn.disabled = !trailerSource;
      dom.trailerBtn.textContent = trailerSource ? 'Watch Trailer' : 'Trailer Unavailable';
      dom.trailerBtn.dataset.url = trailerSource?.externalUrl || '';
      dom.trailerBtn.dataset.mode = trailerSource?.mode || '';
    }

    if (!trailerSource || trailerSource.mode === 'link') {
      dom.trailerSection.hidden = true;
      dom.trailerEmbed.innerHTML = '';
      return;
    }

    dom.trailerSection.hidden = false;
    if (trailerSource.mode === 'iframe') {
      dom.trailerEmbed.innerHTML = `
        <div class="detail-player-shell detail-player-shell-featured">
          <iframe
            class="detail-player-frame"
            src="${escapeHtmlAttr(trailerSource.playerUrl)}"
            title="${escapeHtmlAttr((movie.title || 'Movie') + ' trailer')}"
            loading="lazy"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
            allowfullscreen
            referrerpolicy="strict-origin-when-cross-origin"
          ></iframe>
        </div>
      `;
      return;
    }

    if (trailerSource.mode === 'video') {
      dom.trailerEmbed.innerHTML = `
        <div class="detail-player-shell detail-player-shell-featured">
          <video class="detail-player-video" controls preload="metadata" playsinline>
            <source src="${escapeHtmlAttr(trailerSource.playerUrl)}">
          </video>
        </div>
      `;
      return;
    }
  }

  function renderGallery() {
    if (!dom.gallerySection || !dom.gallery) {
      return;
    }

    if (state.gallery.length === 0) {
      dom.gallerySection.hidden = true;
      dom.gallery.innerHTML = '';
      return;
    }

    dom.gallerySection.hidden = false;
    dom.gallery.innerHTML = state.gallery.map((asset, index) => `
      <div class="detail-gallery-card">
        <img
          class="detail-media-img"
          src="${escapeHtmlAttr(asset.image_url || '')}"
          alt="${escapeHtmlAttr(asset.alt_text || `${state.movie?.title || 'Movie'} image ${index + 1}`)}"
          loading="lazy"
        >
        <div class="detail-media-fallback" hidden>${escapeHtml(buildPosterCode(state.movie?.title))}</div>
        <div class="detail-gallery-meta">
          <span>${escapeHtml(humanizeAssetType(asset.asset_type))}</span>
        </div>
      </div>
    `).join('');
    wireMediaFallbacks(dom.gallery);
  }

  function renderShowtimes(movie) {
    if (!dom.showtimesSection || !dom.showtimeDates || !dom.showtimeVenues) {
      return;
    }

    const showtimes = Array.isArray(state.showtimes) ? state.showtimes : [];
    const shouldShowSection = showtimes.length > 0 || movie.status === 'now_showing';
    if (!shouldShowSection) {
      dom.showtimesSection.hidden = true;
      dom.showtimeDates.innerHTML = '';
      dom.showtimeVenues.innerHTML = '';
      return;
    }

    dom.showtimesSection.hidden = false;

    if (showtimes.length === 0) {
      dom.showtimeDates.innerHTML = '';
      dom.showtimeVenues.innerHTML = `
        <div class="detail-state-card detail-state-card-compact">
          <div>
            <strong>Showtimes for this movie are opening soon.</strong>
            <div>This movie is available in the catalog, but its screening times have not been published yet.</div>
          </div>
        </div>
      `;
      return;
    }

    const activeDate = resolveActiveShowDate(showtimes, state.activeShowDate);
    state.activeShowDate = activeDate;
    const activeGroup = showtimes.find(group => group?.date === activeDate) || showtimes[0];

    dom.showtimeDates.innerHTML = showtimes.map(group => renderShowtimeDateTab(group, group.date === activeDate)).join('');
    dom.showtimeVenues.innerHTML = renderShowtimeVenues(activeGroup);
  }

  function renderReviews() {
    if (!dom.reviewsSection || !dom.reviews) {
      return;
    }

    if (state.reviews.length === 0) {
      dom.reviewsSection.hidden = true;
      dom.reviews.innerHTML = '';
      return;
    }

    dom.reviewsSection.hidden = false;
    dom.reviews.innerHTML = state.reviews.map(review => `
      <article class="detail-review-card">
        <div class="detail-review-header">
          <div>
            <strong>${escapeHtml(review.user_name || 'Anonymous')}</strong>
            <div class="detail-muted">${escapeHtml(formatReviewDate(review.created_at))}</div>
          </div>
          <div class="rating-stars">${buildStarRating(review.rating)}</div>
        </div>
        <p>${escapeHtml(review.comment || 'No written review was provided.')}</p>
      </article>
    `).join('');
  }

  function renderRelatedMovies() {
    if (!dom.relatedMovies) {
      return;
    }

    if (state.relatedMovies.length === 0) {
      dom.relatedMovies.innerHTML = `
        <div class="detail-state-card detail-state-card-compact" style="grid-column:1/-1;">
          <div>
            <strong>No related movies available.</strong>
            <div>There are no related titles available for this movie yet.</div>
          </div>
        </div>
      `;
      return;
    }

    dom.relatedMovies.innerHTML = state.relatedMovies.map(movie => renderRelatedMovieCard(movie)).join('');
    wireMediaFallbacks(dom.relatedMovies);
  }

  function renderRelatedMovieCard(movie) {
    const category = movie.primary_category_name || 'Uncategorized';
    const detailUrl = buildMovieDetailUrl(movie.slug);
    const posterMarkup = movie.poster_url
      ? `
        <div class="detail-media-card">
          <img class="detail-media-img" src="${escapeHtmlAttr(movie.poster_url)}" alt="${escapeHtmlAttr(movie.title || 'Movie poster')}" loading="lazy">
          <div class="detail-media-fallback" hidden>${escapeHtml(buildPosterCode(movie.title))}</div>
        </div>
      `
      : buildPosterFallbackHtml(movie.title);

    return `
      <div class="card movie-card" data-url="${escapeHtmlAttr(detailUrl)}">
        <div class="movie-poster">
          ${posterMarkup}
          <div class="genre-badge">${escapeHtml(category)}</div>
          <div class="rating-badge">${escapeHtml(formatRating(movie.average_rating))}</div>
          <div class="movie-poster-overlay">
            <button class="btn btn-primary btn-sm" type="button" data-url="${escapeHtmlAttr(detailUrl)}">Details</button>
          </div>
        </div>
        <div class="movie-info">
          <div class="movie-title">${escapeHtml(movie.title || 'Untitled Movie')}</div>
          <div class="movie-meta">
            <span>${escapeHtml(formatRating(movie.average_rating))}</span>
            <span class="dot"></span>
            <span>${escapeHtml(formatDuration(movie.duration_minutes))}</span>
          </div>
        </div>
        <div class="movie-actions">
          <button class="btn btn-primary btn-sm" style="flex:1" type="button" data-url="${escapeHtmlAttr(detailUrl)}">Open</button>
          <a class="btn btn-secondary btn-sm" style="flex:1;text-align:center" href="${escapeHtmlAttr(detailUrl)}#movieDetailTrailerSection">Watch</a>
        </div>
      </div>
    `;
  }

  function handleRelatedMovieClick(event) {
    const button = event.target.closest('[data-url]');
    if (button?.dataset?.url) {
      window.location.href = button.dataset.url;
    }
  }

  function handleTrailerClick(event) {
    const trailerSource = resolveTrailerSource(state.movie?.trailer_url || '');
    if (!trailerSource) {
      event.preventDefault();
      if (typeof showToast === 'function') {
        showToast('i', 'Trailer', 'Trailer has not been published for this movie yet.');
      }
      return;
    }

    if (trailerSource.mode === 'link') {
      window.open(trailerSource.externalUrl, '_blank', 'noopener,noreferrer');
      return;
    }

    event.preventDefault();
    dom.trailerSection?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function handleShowtimeDateClick(event) {
    const button = event.target.closest('[data-show-date]');
    if (!button) {
      return;
    }

    const nextDate = String(button.dataset.showDate || '').trim();
    if (!nextDate || nextDate === state.activeShowDate) {
      return;
    }

    state.activeShowDate = nextDate;
    renderShowtimes(state.movie || {});
  }

  function handleShowtimeSelectionClick(event) {
    const button = event.target.closest('[data-showtime-url]');
    if (!button || button.disabled) {
      return;
    }

    const targetUrl = String(button.dataset.showtimeUrl || '').trim();
    if (!targetUrl) {
      return;
    }

    window.location.href = targetUrl;
  }

  function resolveTrailerSource(url) {
    const cleanUrl = String(url || '').trim();
    if (!cleanUrl) {
      return null;
    }

    const youtubeId = extractYouTubeId(cleanUrl);
    if (youtubeId) {
      return {
        mode: 'iframe',
        playerUrl: `https://www.youtube.com/embed/${youtubeId}?rel=0`,
        externalUrl: cleanUrl,
      };
    }

    const vimeoId = extractVimeoId(cleanUrl);
    if (vimeoId) {
      return {
        mode: 'iframe',
        playerUrl: `https://player.vimeo.com/video/${vimeoId}`,
        externalUrl: cleanUrl,
      };
    }

    if (isDirectVideoUrl(cleanUrl)) {
      return {
        mode: 'video',
        playerUrl: cleanUrl,
        externalUrl: cleanUrl,
      };
    }

    return {
      mode: 'link',
      externalUrl: cleanUrl,
    };
  }

  function resolveActiveShowDate(showtimes, currentDate) {
    if (showtimes.some(group => group?.date === currentDate)) {
      return currentDate;
    }

    return showtimes[0]?.date || '';
  }

  function renderShowtimeDateTab(group, isActive) {
    const parts = formatShowDateParts(group?.date);
    const dayLabel = group?.is_today ? 'Today' : parts.weekday;

    return `
      <button class="date-tab${isActive ? ' active' : ''}" type="button" data-show-date="${escapeHtmlAttr(group?.date || '')}">
        <div class="day">${escapeHtml(dayLabel)}</div>
        <div class="date">${escapeHtml(parts.day)}</div>
      </button>
    `;
  }

  function renderShowtimeVenues(group) {
    const venues = Array.isArray(group?.venues) ? group.venues : [];
    if (venues.length === 0) {
      return `
        <div class="detail-state-card detail-state-card-compact">
          <div>
            <strong>No screenings for this movie on this date.</strong>
            <div>Please try another day to continue with ticket booking.</div>
          </div>
        </div>
      `;
    }

    return venues.map(venue => `
      <div class="cinema-row detail-showtime-row">
        <div class="cinema-name">${escapeHtml(buildVenueLabel(venue))}</div>
        <div class="time-chips">
          ${(venue.times || []).map(time => {
            const isUnavailable = Boolean(time.is_sold_out) || String(time.status || '').trim().toLowerCase() !== 'published';

            return `
            <button
              class="time-chip showtime-chip${isUnavailable ? ' unavailable full' : ''}"
              type="button"
              data-showtime-url="${escapeHtmlAttr(buildSeatSelectionUrl(time.id, state.movie?.slug))}"
              ${isUnavailable ? 'disabled' : ''}
            >
              <span class="showtime-chip-time">${escapeHtml(time.start_time_label || formatTime(time.start_time))}</span>
              <span class="time-chip-note">${escapeHtml(time.availability_label || 'Available')}</span>
            </button>
          `;
          }).join('')}
        </div>
      </div>
    `).join('');
  }

  function wireMediaFallbacks(container) {
    if (!container) {
      return;
    }

    container.querySelectorAll('.detail-media-card, .detail-gallery-card').forEach(card => {
      const image = card.querySelector('.detail-media-img');
      const fallback = card.querySelector('.detail-media-fallback, .detail-poster-fallback');

      if (!image || !fallback || image.dataset.fallbackBound === '1') {
        return;
      }

      image.dataset.fallbackBound = '1';

      image.addEventListener('error', () => {
        image.hidden = true;
        fallback.hidden = false;
      }, { once: true });

      image.addEventListener('load', () => {
        image.hidden = false;
        fallback.hidden = true;
      }, { once: true });

      if (image.complete && image.naturalWidth === 0) {
        image.hidden = true;
        fallback.hidden = false;
      }
    });
  }

  function readSlug() {
    const params = new URLSearchParams(window.location.search);
    return String(params.get('slug') || '').trim();
  }

  async function fetchJson(path) {
    const response = await fetch(appUrl(path), {
      headers: {
        Accept: 'application/json',
      },
      cache: 'no-store',
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(firstErrorMessage(payload?.errors, payload?.message || 'Request failed.'));
    }

    return payload || {};
  }

  function renderStateCard(title, message) {
    if (dom.content) {
      dom.content.hidden = true;
    }

    if (dom.state) {
      dom.state.innerHTML = `
        <div class="detail-state-card">
          <div>
            <strong>${escapeHtml(title)}</strong>
            <div>${escapeHtml(message)}</div>
          </div>
        </div>
      `;
    }
  }

  function buildMovieDetailUrl(slug) {
    const query = slug ? `?slug=${encodeURIComponent(slug)}` : '';
    return `${appUrl('/movie-detail')}${query}`;
  }

  function buildSeatSelectionUrl(showtimeId, slug) {
    const params = new URLSearchParams();
    if (showtimeId) {
      params.set('showtime_id', String(showtimeId));
    }
    if (slug) {
      params.set('slug', String(slug));
    }

    const query = params.toString();
    return `${appUrl('/seat-selection')}${query ? `?${query}` : ''}`;
  }

  function appUrl(path) {
    const basePath = typeof window.APP_BASE_PATH === 'string' ? window.APP_BASE_PATH : '';
    const normalizedPath = String(path || '').startsWith('/') ? path : `/${path}`;
    return `${basePath}${normalizedPath}`;
  }

  function formatRating(value) {
    const rating = Number(value || 0);
    return Number.isFinite(rating) ? `${rating.toFixed(1)} / 5` : '0.0 / 5';
  }

  function formatDuration(minutes) {
    const totalMinutes = Number(minutes || 0);
    if (!Number.isFinite(totalMinutes) || totalMinutes <= 0) {
      return 'N/A';
    }

    const hours = Math.floor(totalMinutes / 60);
    const mins = totalMinutes % 60;
    if (!hours) {
      return `${mins}m`;
    }

    return mins ? `${hours}h ${mins}m` : `${hours}h`;
  }

  function formatReleaseDate(value) {
    if (!value) {
      return 'TBA';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
      return String(value);
    }

    return new Intl.DateTimeFormat('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(parsed);
  }

  function formatShowDateParts(value) {
    if (!value) {
      return { weekday: 'Day', day: '--' };
    }

    const parsed = new Date(`${value}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) {
      return {
        weekday: String(value).slice(0, 3).toUpperCase(),
        day: String(value).slice(-2),
      };
    }

    return {
      weekday: new Intl.DateTimeFormat('en-US', { weekday: 'short' }).format(parsed).toUpperCase(),
      day: new Intl.DateTimeFormat('en-US', { day: '2-digit' }).format(parsed),
    };
  }

  function formatTime(value) {
    const time = String(value || '').trim();
    if (!time) {
      return 'TBA';
    }

    const parsed = new Date(`1970-01-01T${time}`);
    if (Number.isNaN(parsed.getTime())) {
      return time;
    }

    return new Intl.DateTimeFormat('en-US', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    }).format(parsed);
  }

  function formatReviewCount(count) {
    const total = Number(count || 0);
    return `${total} vote${total === 1 ? '' : 's'}`;
  }

  function formatReviewDate(value) {
    if (!value) {
      return 'Recently';
    }

    const parsed = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) {
      return String(value);
    }

    return new Intl.DateTimeFormat('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(parsed);
  }

  function buildStarRating(value) {
    const rating = Math.max(0, Math.min(5, Number(value || 0)));
    const rounded = Math.round(rating);
    const filled = '&#9733;'.repeat(rounded);
    const empty = '&#9734;'.repeat(Math.max(0, 5 - rounded));
    return `${filled}${empty}`;
  }

  function buildPosterFallbackHtml(title) {
    return `<div class="detail-poster-fallback">${escapeHtml(buildPosterCode(title))}</div>`;
  }

  function buildPosterCode(title) {
    return String(title || 'Movie')
      .trim()
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map(part => part.charAt(0).toUpperCase())
      .join('') || 'MV';
  }

  function extractYouTubeId(url) {
    const patterns = [
      /youtube\.com\/watch\?v=([\w-]{6,})/i,
      /youtube\.com\/embed\/([\w-]{6,})/i,
      /youtu\.be\/([\w-]{6,})/i,
      /youtube\.com\/shorts\/([\w-]{6,})/i,
    ];

    for (const pattern of patterns) {
      const match = String(url).match(pattern);
      if (match?.[1]) {
        return match[1];
      }
    }

    return null;
  }

  function extractVimeoId(url) {
    const match = String(url).match(/vimeo\.com\/(\d+)/i);
    return match?.[1] || null;
  }

  function isDirectVideoUrl(url) {
    return /\.(mp4|webm|ogg|m3u8)(\?.*)?$/i.test(String(url || ''));
  }

  function firstNonEmpty() {
    for (let index = 0; index < arguments.length; index += 1) {
      const value = String(arguments[index] || '').trim();
      if (value) {
        return value;
      }
    }

    return '';
  }

  function humanizeStatus(value) {
    return String(value || '')
      .split('_')
      .filter(Boolean)
      .map(part => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ') || 'Unknown';
  }

  function buildVenueLabel(venue) {
    return [venue?.cinema_name || 'Cinema', venue?.room_name || 'Room']
      .filter(Boolean)
      .join(' - ');
  }

  function humanizeAssetType(value) {
    const clean = String(value || '').trim();
    if (!clean) {
      return 'Image';
    }

    return clean.charAt(0).toUpperCase() + clean.slice(1);
  }

  function firstErrorMessage(errors, fallback) {
    if (!errors || typeof errors !== 'object') {
      return fallback || 'Request failed.';
    }

    for (const messages of Object.values(errors)) {
      if (Array.isArray(messages) && messages.length > 0) {
        return String(messages[0]);
      }
    }

    return fallback || 'Request failed.';
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function escapeHtmlAttr(value) {
    return escapeHtml(value).replace(/`/g, '&#96;');
  }

  document.addEventListener('DOMContentLoaded', initMovieDetailPage);
})();
