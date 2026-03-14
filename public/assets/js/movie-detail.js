(function () {
  const state = {
    slug: '',
    loading: false,
    activeDateIndex: 0,
    movie: null,
    gallery: [],
    showtimeGroups: [],
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
      renderStateCard(
        'Movie not selected.',
        'Choose a published movie from the catalog to open its detail page.'
      );
      return;
    }

    renderStateCard('Loading movie details...', 'Please wait while we load the movie profile.');
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
    dom.bookBtn = document.getElementById('movieDetailBookBtn');
    dom.trailerBtn = document.getElementById('movieDetailTrailerBtn');
    dom.trailerSection = document.getElementById('movieDetailTrailerSection');
    dom.trailerLink = document.getElementById('movieDetailTrailerLink');
    dom.gallerySection = document.getElementById('movieDetailGallerySection');
    dom.gallery = document.getElementById('movieDetailGallery');
    dom.dateTabs = document.getElementById('movieDetailDateTabs');
    dom.showtimes = document.getElementById('movieDetailShowtimes');
    dom.reviewsSection = document.getElementById('movieDetailReviewsSection');
    dom.reviews = document.getElementById('movieDetailReviews');
    dom.relatedMovies = document.getElementById('relatedMovies');
  }

  function bindEvents() {
    dom.dateTabs?.addEventListener('click', handleDateTabClick);
    dom.relatedMovies?.addEventListener('click', handleRelatedMovieClick);
    dom.trailerBtn?.addEventListener('click', handleTrailerClick);
    dom.bookBtn?.addEventListener('click', handleBookClick);
  }

  async function loadMovieDetail() {
    state.loading = true;

    try {
      const payload = await fetchJson(`/api/movies/${encodeURIComponent(state.slug)}`);
      const data = payload?.data || {};

      state.movie = data.movie || null;
      state.gallery = Array.isArray(data.gallery) ? data.gallery : [];
      state.showtimeGroups = Array.isArray(data.showtime_groups) ? data.showtime_groups : [];
      state.reviews = Array.isArray(data.reviews) ? data.reviews : [];
      state.relatedMovies = Array.isArray(data.related_movies) ? data.related_movies : [];
      state.activeDateIndex = 0;

      renderMovieDetail();
    } catch (error) {
      renderStateCard(
        'Movie detail unavailable.',
        error.message || 'The selected movie could not be loaded right now.'
      );

      if (typeof showToast === 'function') {
        showToast('i', 'Movie Detail', error.message || 'Failed to load movie detail.');
      }
    } finally {
      state.loading = false;
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
    renderGallery();
    renderShowtimes();
    renderReviews();
    renderRelatedMovies();
    renderBookButton();

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

    if (movie.poster_url) {
      dom.poster.innerHTML = `
        <img
          src="${escapeHtmlAttr(movie.poster_url)}"
          alt="${escapeHtmlAttr(movie.title || 'Movie poster')}"
          loading="lazy"
          onerror="this.remove();this.parentNode.innerHTML='${escapeJsHtml(buildPosterFallbackHtml(movie.title))}'"
        >
      `;
      return;
    }

    dom.poster.innerHTML = buildPosterFallbackHtml(movie.title);
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

    if (movie.language) {
      parts.push('<span class="dot"></span>');
      parts.push(`<span>${escapeHtml(movie.language)}</span>`);
    }

    parts.push('<span class="dot"></span>');
    parts.push(`<span>${escapeHtml(formatReviewCount(movie.review_count))}</span>`);

    dom.meta.innerHTML = parts.join('');

    if (dom.title) {
      dom.title.textContent = movie.title || 'Untitled Movie';
    }
  }

  function renderSummary(movie) {
    if (dom.summary) {
      dom.summary.textContent = movie.summary || 'Plot summary is being updated for this title.';
    }
  }

  function renderCredits(movie) {
    if (!dom.credits) {
      return;
    }

    const creditItems = [
      { label: 'Director', value: movie.director },
      { label: 'Writer', value: movie.writer },
      { label: 'Cast', value: movie.cast_text },
      { label: 'Studio', value: movie.studio },
      { label: 'Language', value: movie.language },
      { label: 'Release', value: formatReleaseDate(movie.release_date) },
    ].filter(item => item.value);

    if (creditItems.length === 0) {
      dom.credits.innerHTML = '<div class="detail-muted">Production credits will be published soon.</div>';
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
    const trailerUrl = String(movie.trailer_url || '').trim();

    if (dom.trailerBtn) {
      dom.trailerBtn.disabled = trailerUrl === '';
      dom.trailerBtn.textContent = trailerUrl ? 'Watch Trailer' : 'Trailer Unavailable';
      dom.trailerBtn.dataset.url = trailerUrl;
    }

    if (!dom.trailerSection || !dom.trailerLink) {
      return;
    }

    if (!trailerUrl) {
      dom.trailerSection.hidden = true;
      dom.trailerLink.removeAttribute('href');
      return;
    }

    dom.trailerSection.hidden = false;
    dom.trailerLink.href = trailerUrl;
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
    dom.gallery.innerHTML = state.gallery.map(asset => `
      <img
        src="${escapeHtmlAttr(asset.image_url || '')}"
        alt="${escapeHtmlAttr(asset.alt_text || state.movie?.title || 'Movie gallery image')}"
        loading="lazy"
      >
    `).join('');
  }

  function renderShowtimes() {
    if (!dom.dateTabs || !dom.showtimes) {
      return;
    }

    if (state.showtimeGroups.length === 0) {
      dom.dateTabs.innerHTML = '';
      dom.showtimes.innerHTML = `
        <div class="detail-state-card">
          <div>
            <strong>No showtimes published yet.</strong>
            <div>Sessions will appear here once this movie is scheduled for booking.</div>
          </div>
        </div>
      `;
      return;
    }

    if (state.activeDateIndex >= state.showtimeGroups.length) {
      state.activeDateIndex = 0;
    }

    dom.dateTabs.innerHTML = state.showtimeGroups.map((group, index) => `
      <button class="date-tab${index === state.activeDateIndex ? ' active' : ''}" type="button" data-date-index="${index}">
        <div class="day">${escapeHtml(formatDayLabel(group.date))}</div>
        <div class="date">${escapeHtml(formatDayNumber(group.date))}</div>
      </button>
    `).join('');

    renderShowtimeVenues(state.showtimeGroups[state.activeDateIndex]);
  }

  function renderShowtimeVenues(group) {
    if (!dom.showtimes) {
      return;
    }

    const venues = Array.isArray(group?.venues) ? group.venues : [];
    if (venues.length === 0) {
      dom.showtimes.innerHTML = `
        <div class="detail-state-card">
          <div>
            <strong>No sessions for this day.</strong>
            <div>Please choose another date or come back later.</div>
          </div>
        </div>
      `;
      return;
    }

    dom.showtimes.innerHTML = venues.map(venue => `
      <div class="cinema-row">
        <div class="cinema-name">${escapeHtml(venue.cinema_name || 'Cinema')} · ${escapeHtml(venue.room_name || 'Room')}</div>
        <div class="time-chips">
          ${(venue.times || []).map(time => `
            <a class="time-chip detail-time-link" href="${escapeHtmlAttr(buildSeatSelectionUrl(state.slug, time.id))}">
              <span>${escapeHtml(formatTimeLabel(time.start_time))}</span>
              <span class="detail-muted">${escapeHtml(formatCurrency(time.price))}</span>
            </a>
          `).join('')}
        </div>
      </div>
    `).join('');
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
            <strong>${escapeHtml(review.user_name || 'Guest')}</strong>
            <div class="detail-muted">${escapeHtml(formatReleaseDate(review.created_at))}</div>
          </div>
          <div class="rating-stars">${buildStarRating(review.rating)}</div>
        </div>
        <p>${escapeHtml(review.comment || 'No review comment provided.')}</p>
      </article>
    `).join('');
  }

  function renderRelatedMovies() {
    if (!dom.relatedMovies) {
      return;
    }

    if (state.relatedMovies.length === 0) {
      dom.relatedMovies.innerHTML = `
        <div class="detail-state-card" style="grid-column:1/-1;">
          <div>
            <strong>No related movies available.</strong>
            <div>Publish more movies in the same category to enrich this section.</div>
          </div>
        </div>
      `;
      return;
    }

    dom.relatedMovies.innerHTML = state.relatedMovies.map(movie => renderRelatedMovieCard(movie)).join('');
  }

  function renderRelatedMovieCard(movie) {
    const category = movie.primary_category_name || 'Uncategorized';
    const detailUrl = buildMovieDetailUrl(movie.slug);
    const poster = movie.poster_url
      ? `<img src="${escapeHtmlAttr(movie.poster_url)}" alt="${escapeHtmlAttr(movie.title || 'Movie poster')}" loading="lazy">`
      : buildPosterFallbackHtml(movie.title);

    return `
      <div class="card movie-card" data-url="${escapeHtmlAttr(detailUrl)}">
        <div class="movie-poster">
          ${poster}
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
          <a class="btn btn-secondary btn-sm" style="flex:1;text-align:center" href="${escapeHtmlAttr(detailUrl)}#movieDetailShowtimes">Showtimes</a>
        </div>
      </div>
    `;
  }

  function renderBookButton() {
    if (!dom.bookBtn) {
      return;
    }

    const isNowShowing = state.movie?.status === 'now_showing';
    const hasShowtimes = state.showtimeGroups.length > 0;

    if (isNowShowing && hasShowtimes) {
      dom.bookBtn.textContent = 'Check Showtimes';
      dom.bookBtn.href = '#movieDetailShowtimes';
      dom.bookBtn.dataset.mode = 'showtimes';
      return;
    }

    if (state.movie?.status === 'coming_soon') {
      dom.bookBtn.textContent = 'Coming Soon';
      dom.bookBtn.href = buildMovieListUrl();
      dom.bookBtn.dataset.mode = 'disabled';
      return;
    }

    dom.bookBtn.textContent = 'Browse Movies';
    dom.bookBtn.href = buildMovieListUrl();
    dom.bookBtn.dataset.mode = 'list';
  }

  function handleDateTabClick(event) {
    const button = event.target.closest('[data-date-index]');
    if (!button) {
      return;
    }

    const nextIndex = Number(button.dataset.dateIndex || 0);
    if (!Number.isFinite(nextIndex) || nextIndex < 0 || nextIndex === state.activeDateIndex) {
      return;
    }

    state.activeDateIndex = nextIndex;
    renderShowtimes();
  }

  function handleRelatedMovieClick(event) {
    const button = event.target.closest('[data-url]');
    if (button?.dataset?.url) {
      window.location.href = button.dataset.url;
      return;
    }

    const card = event.target.closest('[data-url]');
    if (card?.dataset?.url) {
      window.location.href = card.dataset.url;
    }
  }

  function handleTrailerClick(event) {
    const trailerUrl = dom.trailerBtn?.dataset?.url || '';
    if (!trailerUrl) {
      event.preventDefault();
      if (typeof showToast === 'function') {
        showToast('i', 'Trailer', 'Trailer has not been published for this movie yet.');
      }
      return;
    }

    window.open(trailerUrl, '_blank', 'noopener,noreferrer');
  }

  function handleBookClick(event) {
    const mode = dom.bookBtn?.dataset?.mode || 'list';
    if (mode === 'showtimes') {
      event.preventDefault();
      document.getElementById('movieDetailShowtimes')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }

    if (mode === 'disabled') {
      event.preventDefault();
      if (typeof showToast === 'function') {
        showToast('i', 'Booking', 'This movie is not open for booking yet.');
      }
    }
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

  function buildMovieListUrl() {
    return appUrl('/movies');
  }

  function buildMovieDetailUrl(slug) {
    const query = slug ? `?slug=${encodeURIComponent(slug)}` : '';
    return `${appUrl('/movie-detail')}${query}`;
  }

  function buildSeatSelectionUrl(slug, showtimeId) {
    const params = new URLSearchParams();
    if (slug) {
      params.set('slug', slug);
    }
    if (showtimeId) {
      params.set('showtime_id', String(showtimeId));
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

  function formatDayLabel(value) {
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
      return 'Date';
    }

    return new Intl.DateTimeFormat('en-US', { weekday: 'short' }).format(parsed);
  }

  function formatDayNumber(value) {
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
      return '--';
    }

    return new Intl.DateTimeFormat('en-US', { day: '2-digit' }).format(parsed);
  }

  function formatTimeLabel(value) {
    const source = String(value || '').trim();
    if (!source) {
      return 'TBA';
    }

    const parsed = new Date(`1970-01-01T${source}`);
    if (Number.isNaN(parsed.getTime())) {
      return source.slice(0, 5);
    }

    return new Intl.DateTimeFormat('en-US', {
      hour: 'numeric',
      minute: '2-digit',
    }).format(parsed);
  }

  function formatReviewCount(count) {
    const total = Number(count || 0);
    return `${total} review${total === 1 ? '' : 's'}`;
  }

  function formatCurrency(value) {
    const amount = Number(value || 0);
    if (!Number.isFinite(amount) || amount <= 0) {
      return 'TBA';
    }

    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(amount);
  }

  function buildStarRating(value) {
    const rating = Math.max(0, Math.min(5, Number(value || 0)));
    const rounded = Math.round(rating);
    const filled = '&#9733;'.repeat(rounded);
    const empty = '&#9734;'.repeat(Math.max(0, 5 - rounded));
    return `${filled}${empty}`;
  }

  function buildPosterFallbackHtml(title) {
    const initials = String(title || 'Movie')
      .trim()
      .split(/\s+/)
      .filter(Boolean)
      .slice(0, 2)
      .map(part => part.charAt(0).toUpperCase())
      .join('') || 'MV';

    return `<div class="detail-poster-fallback">${escapeHtml(initials)}</div>`;
  }

  function humanizeStatus(value) {
    return String(value || '')
      .split('_')
      .filter(Boolean)
      .map(part => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ') || 'Unknown';
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

  function escapeJsHtml(value) {
    return String(value ?? '')
      .replace(/\\/g, '\\\\')
      .replace(/'/g, "\\'")
      .replace(/\r/g, '')
      .replace(/\n/g, '');
  }

  document.addEventListener('DOMContentLoaded', initMovieDetailPage);
})();
