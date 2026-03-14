(function () {
  const state = {
    slug: '',
    movie: null,
    gallery: [],
    playbackGroups: [],
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
        'Choose a movie from the catalog to open its detail page.'
      );
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
    dom.bookBtn = document.getElementById('movieDetailBookBtn');
    dom.trailerBtn = document.getElementById('movieDetailTrailerBtn');
    dom.trailerSection = document.getElementById('movieDetailTrailerSection');
    dom.trailerLink = document.getElementById('movieDetailTrailerLink');
    dom.gallerySection = document.getElementById('movieDetailGallerySection');
    dom.gallery = document.getElementById('movieDetailGallery');
    dom.playbackGroups = document.getElementById('movieDetailPlaybackGroups');
    dom.reviewsSection = document.getElementById('movieDetailReviewsSection');
    dom.relatedMovies = document.getElementById('relatedMovies');
  }

  function bindEvents() {
    dom.relatedMovies?.addEventListener('click', handleRelatedMovieClick);
    dom.trailerBtn?.addEventListener('click', handleTrailerClick);
    dom.bookBtn?.addEventListener('click', handlePrimaryActionClick);
  }

  async function loadMovieDetail() {
    try {
      const payload = await fetchJson(`/api/movies/${encodeURIComponent(state.slug)}`);
      const data = payload?.data || {};

      state.movie = data.movie || null;
      state.gallery = Array.isArray(data.gallery) ? data.gallery : [];
      state.playbackGroups = Array.isArray(data.playback_groups) ? data.playback_groups : [];
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
    renderGallery();
    renderPlaybackGroups();
    renderReviewsPlaceholder();
    renderRelatedMovies();
    renderPrimaryAction();

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

    dom.summary.textContent = movie.summary || 'Movie synopsis is being updated from the source provider.';
  }

  function renderCredits(movie) {
    if (!dom.credits) {
      return;
    }

    const creditItems = [
      { label: 'Director', value: movie.director },
      { label: 'Cast', value: movie.cast_text },
      { label: 'Countries', value: movie.studio },
      { label: 'Release', value: formatReleaseDate(movie.release_date) },
    ].filter(item => item.value);

    if (creditItems.length === 0) {
      dom.credits.innerHTML = '<div class="detail-muted">Additional credits are not available from the source provider.</div>';
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

  function renderPlaybackGroups() {
    if (!dom.playbackGroups) {
      return;
    }

    if (state.playbackGroups.length === 0) {
      dom.playbackGroups.innerHTML = `
        <div class="detail-state-card">
          <div>
            <strong>No playback source published yet.</strong>
            <div>No playback source has been published for this movie yet.</div>
          </div>
        </div>
      `;
      return;
    }

    dom.playbackGroups.innerHTML = state.playbackGroups.map(group => `
      <div class="cinema-row">
        <div class="cinema-name">${escapeHtml(group.server_name || 'Playback Server')}</div>
        <div class="time-chips">
          ${(group.items || []).map(item => {
            const targetUrl = item.embed_url || item.stream_url || '';
            const helperLabel = item.stream_url ? 'm3u8' : 'embed';

            return `
              <a
                class="time-chip detail-time-link"
                href="${escapeHtmlAttr(targetUrl || '#')}"
                ${targetUrl ? 'target="_blank" rel="noopener noreferrer"' : 'aria-disabled="true"'}
              >
                <span>${escapeHtml(item.label || 'Open')}</span>
                <span class="detail-muted">${escapeHtml(helperLabel)}</span>
              </a>
            `;
          }).join('')}
        </div>
      </div>
    `).join('');
  }

  function renderReviewsPlaceholder() {
    if (dom.reviewsSection) {
      dom.reviewsSection.hidden = true;
    }
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
            <div>There are no related titles available for this movie yet.</div>
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
          <a class="btn btn-secondary btn-sm" style="flex:1;text-align:center" href="${escapeHtmlAttr(detailUrl)}#movieDetailPlaybackGroups">Sources</a>
        </div>
      </div>
    `;
  }

  function renderPrimaryAction() {
    if (!dom.bookBtn) {
      return;
    }

    if (state.playbackGroups.length > 0) {
      dom.bookBtn.textContent = 'Watch Options';
      dom.bookBtn.href = '#movieDetailPlaybackGroups';
      dom.bookBtn.dataset.mode = 'playback';
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

  function handleRelatedMovieClick(event) {
    const button = event.target.closest('[data-url]');
    if (button?.dataset?.url) {
      window.location.href = button.dataset.url;
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

  function handlePrimaryActionClick(event) {
    const mode = dom.bookBtn?.dataset?.mode || 'list';
    if (mode === 'playback') {
      event.preventDefault();
      document.getElementById('movieDetailPlaybackGroups')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }

    if (mode === 'disabled') {
      event.preventDefault();
      if (typeof showToast === 'function') {
        showToast('i', 'Movie Status', 'This movie is currently marked as coming soon.');
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

  function buildMovieListUrl() {
    return appUrl('/movies');
  }

  function buildMovieDetailUrl(slug) {
    const query = slug ? `?slug=${encodeURIComponent(slug)}` : '';
    return `${appUrl('/movie-detail')}${query}`;
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

  function formatReviewCount(count) {
    const total = Number(count || 0);
    return `${total} vote${total === 1 ? '' : 's'}`;
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
