(function () {
  const DEFAULT_META = {
    total: 0,
    page: 1,
    per_page: 12,
    total_pages: 1,
  };

  const state = {
    items: [],
    categories: [],
    meta: { ...DEFAULT_META },
    filters: {
      page: 1,
      per_page: 12,
      search: '',
      category_id: '',
      min_rating: '',
      sort: 'popular',
      status: 'now_showing',
    },
    loading: false,
    searchTimer: null,
    initialized: false,
  };

  const dom = {};

  function initMovieCatalogPage() {
    if (state.initialized || document.body?.dataset?.activePage !== 'movies') {
      return;
    }

    cacheDom();
    if (!dom.grid) {
      return;
    }

    state.initialized = true;
    bindEvents();
    renderStatusButtons();
    renderLoadingState('Loading movie catalog...');
    void loadMovieCatalog();
  }

  function cacheDom() {
    dom.search = document.getElementById('movieCatalogSearchInput');
    dom.category = document.getElementById('movieCatalogCategoryFilter');
    dom.rating = document.getElementById('movieCatalogRatingFilter');
    dom.sort = document.getElementById('movieCatalogSortFilter');
    dom.statusGroup = document.getElementById('movieCatalogStatusGroup');
    dom.count = document.getElementById('movieCatalogCount');
    dom.requestStatus = document.getElementById('movieCatalogRequestStatus');
    dom.grid = document.getElementById('allMoviesGrid');
    dom.pagination = document.getElementById('movieCatalogPagination');
  }

  function bindEvents() {
    dom.search?.addEventListener('input', handleSearchInput);
    dom.category?.addEventListener('change', () => {
      state.filters.category_id = dom.category.value;
      state.filters.page = 1;
      void loadMovieCatalog();
    });
    dom.rating?.addEventListener('change', () => {
      state.filters.min_rating = dom.rating.value;
      state.filters.page = 1;
      void loadMovieCatalog();
    });
    dom.sort?.addEventListener('change', () => {
      state.filters.sort = dom.sort.value || 'popular';
      state.filters.page = 1;
      void loadMovieCatalog();
    });
    dom.statusGroup?.addEventListener('click', handleStatusClick);
    dom.grid?.addEventListener('click', handleGridClick);
    dom.pagination?.addEventListener('click', handlePaginationClick);
  }

  async function loadMovieCatalog() {
    setLoading(true, 'Loading movie catalog...');

    try {
      const response = await fetchJson('/api/movies', buildQuery());
      const payload = response?.data || {};

      state.items = Array.isArray(payload.items) ? payload.items : [];
      state.categories = Array.isArray(payload.categories) ? payload.categories : [];
      state.meta = normalizeMeta(payload.meta);

      renderCategoryOptions();
      renderStatusButtons();
      renderGrid();
      renderPagination();
      updateRequestStatus('Movie catalog synced');
    } catch (error) {
      state.items = [];
      state.meta = { ...DEFAULT_META };
      renderGridError(error.message || 'Failed to load movie catalog.');
      renderPagination();
      updateRequestStatus('Movie catalog unavailable');

      if (typeof showToast === 'function') {
        showToast('i', 'Movie Catalog', error.message || 'Failed to load movie catalog.');
      }
    } finally {
      setLoading(false);
    }
  }

  function buildQuery() {
    return {
      page: state.filters.page,
      per_page: state.filters.per_page,
      search: state.filters.search,
      category_id: state.filters.category_id,
      min_rating: state.filters.min_rating,
      sort: state.filters.sort,
      status: state.filters.status,
    };
  }

  function normalizeMeta(meta = {}) {
    return {
      total: Number(meta?.total || 0),
      page: Number(meta?.page || state.filters.page || 1),
      per_page: Number(meta?.per_page || state.filters.per_page || 12),
      total_pages: Math.max(1, Number(meta?.total_pages || 1)),
    };
  }

  function setLoading(isLoading, statusText) {
    state.loading = isLoading;
    if (statusText) {
      updateRequestStatus(statusText);
    }
  }

  function updateRequestStatus(text) {
    if (dom.requestStatus) {
      dom.requestStatus.textContent = text;
    }
  }

  function renderCategoryOptions() {
    if (!dom.category) {
      return;
    }

    const selectedCategoryId = String(state.filters.category_id || '');
    const options = [
      '<option value="">All Categories</option>',
      ...state.categories.map(category => {
        const value = String(category.id || '');
        const selected = value === selectedCategoryId ? ' selected' : '';
        return `<option value="${escapeHtmlAttr(value)}"${selected}>${escapeHtml(category.name || 'Category')}</option>`;
      }),
    ];

    dom.category.innerHTML = options.join('');
  }

  function renderStatusButtons() {
    if (!dom.statusGroup) {
      return;
    }

    dom.statusGroup.querySelectorAll('[data-status]').forEach(button => {
      button.classList.toggle('active', button.dataset.status === state.filters.status);
    });
  }

  function renderGrid() {
    if (!dom.grid) {
      return;
    }

    const total = Number(state.meta.total || 0);
    if (dom.count) {
      dom.count.textContent = `${total} movie${total === 1 ? '' : 's'}`;
    }

    if (state.loading && state.items.length === 0) {
      renderLoadingState('Loading movie catalog...');
      return;
    }

    if (state.items.length === 0) {
      dom.grid.innerHTML = `
        <div class="catalog-empty-state" style="grid-column:1/-1;">
          <div>
            <strong>No public movies matched this view.</strong>
            <div>Only movies marked Now Showing or Coming Soon are visible here. Movies still saved as Draft in admin stay hidden until they are published.</div>
          </div>
        </div>
      `;
      return;
    }

    dom.grid.innerHTML = state.items.map(renderCatalogMovieCard).join('');
  }

  function renderLoadingState(message) {
    if (!dom.grid) {
      return;
    }

    if (dom.count) {
      dom.count.textContent = 'Loading movies...';
    }

    dom.grid.innerHTML = `
      <div class="catalog-empty-state" style="grid-column:1/-1;">
        <div>
          <strong>${escapeHtml(message)}</strong>
          <div>Please wait while the catalog is being synchronized.</div>
        </div>
      </div>
    `;
  }

  function renderGridError(message) {
    if (!dom.grid) {
      return;
    }

    if (dom.count) {
      dom.count.textContent = 'Movie catalog unavailable';
    }

    dom.grid.innerHTML = `
      <div class="catalog-empty-state" style="grid-column:1/-1;">
        <div>
          <strong>Movie catalog unavailable.</strong>
          <div>${escapeHtml(message)}</div>
        </div>
      </div>
    `;
  }

  function renderCatalogMovieCard(movie) {
    const category = movie.primary_category_name || 'Uncategorized';
    const rating = formatRating(movie.average_rating);
    const duration = formatDuration(movie.duration_minutes);
    const releaseYear = extractReleaseYear(movie.release_date);
    const poster = movie.poster_url
      ? `<img src="${escapeHtmlAttr(movie.poster_url)}" alt="${escapeHtmlAttr(movie.title || 'Movie poster')}" loading="lazy" onerror="this.parentNode.style.background='var(--bg4)'">`
      : '';
    const statusLabel = humanizeStatus(movie.status || 'now_showing');
    const detailsUrl = movieDetailUrl(movie.slug);
    const bookDisabled = movie.status !== 'now_showing';

    return `
      <div class="card movie-card" data-slug="${escapeHtmlAttr(movie.slug || '')}">
        <div class="movie-poster">
          ${poster}
          <div class="genre-badge">${escapeHtml(category)}</div>
          <div class="rating-badge">${escapeHtml(rating)}</div>
          <div class="movie-poster-overlay">
            <button class="btn btn-primary btn-sm" type="button" data-action="details" data-url="${escapeHtmlAttr(detailsUrl)}">Details</button>
            <button class="btn btn-secondary btn-sm" type="button" data-action="showtimes" ${bookDisabled ? 'disabled' : ''}>${bookDisabled ? 'Coming Soon' : 'Book'}</button>
          </div>
        </div>
        <div class="movie-info">
          <div class="movie-title">${escapeHtml(movie.title || 'Untitled Movie')}</div>
          <div class="movie-meta">
            <span>${escapeHtml(rating)}</span>
            <span class="dot"></span>
            <span>${escapeHtml(duration)}</span>
            <span class="dot"></span>
            <span>${escapeHtml(releaseYear)}</span>
          </div>
          <div class="movie-meta" style="margin-top:8px;">
            <span>${escapeHtml(statusLabel)}</span>
            <span class="dot"></span>
            <span>${escapeHtml(`${Number(movie.review_count || 0)} reviews`)}</span>
          </div>
        </div>
        <div class="movie-actions">
          <button class="btn btn-primary btn-sm" style="flex:1" type="button" data-action="details" data-url="${escapeHtmlAttr(detailsUrl)}">Details</button>
          <button class="btn btn-secondary btn-sm" style="flex:1" type="button" data-action="showtimes" ${bookDisabled ? 'disabled' : ''}>${bookDisabled ? 'Preview' : 'Book'}</button>
        </div>
      </div>
    `;
  }

  function renderPagination() {
    if (!dom.pagination) {
      return;
    }

    const total = Number(state.meta.total || 0);
    const page = Number(state.meta.page || 1);
    const totalPages = Math.max(1, Number(state.meta.total_pages || 1));
    const perPage = Math.max(1, Number(state.meta.per_page || 12));
    const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const end = total === 0 ? 0 : Math.min(total, start + state.items.length - 1);
    const pages = buildVisiblePages(page, totalPages);

    dom.pagination.innerHTML = `
      <div class="catalog-pagination-info">Showing ${start}-${end} of ${total} movies</div>
      <div class="catalog-pagination-buttons">
        <button class="catalog-page-btn" type="button" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>Prev</button>
        ${pages.map(item => {
          if (item === 'ellipsis') {
            return '<button class="catalog-page-btn" type="button" disabled>...</button>';
          }

          return `<button class="catalog-page-btn${item === page ? ' active' : ''}" type="button" data-page="${item}">${item}</button>`;
        }).join('')}
        <button class="catalog-page-btn" type="button" data-page="${page + 1}" ${page >= totalPages ? 'disabled' : ''}>Next</button>
      </div>
    `;
  }

  function buildVisiblePages(currentPage, totalPages) {
    if (totalPages <= 5) {
      return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const pages = [1];
    const start = Math.max(2, currentPage - 1);
    const end = Math.min(totalPages - 1, currentPage + 1);

    if (start > 2) {
      pages.push('ellipsis');
    }

    for (let page = start; page <= end; page += 1) {
      pages.push(page);
    }

    if (end < totalPages - 1) {
      pages.push('ellipsis');
    }

    pages.push(totalPages);
    return pages;
  }

  function handleSearchInput() {
    window.clearTimeout(state.searchTimer);
    state.searchTimer = window.setTimeout(() => {
      state.filters.search = dom.search?.value.trim() || '';
      state.filters.page = 1;
      void loadMovieCatalog();
    }, 300);
  }

  function handleStatusClick(event) {
    const button = event.target.closest('[data-status]');
    if (!button) {
      return;
    }

    const status = button.dataset.status || 'now_showing';
    if (status === state.filters.status) {
      return;
    }

    state.filters.status = status;
    state.filters.page = 1;
    renderStatusButtons();
    void loadMovieCatalog();
  }

  function handleGridClick(event) {
    const actionButton = event.target.closest('[data-action]');
    if (actionButton) {
      const action = actionButton.dataset.action;
      if (action === 'details' && actionButton.dataset.url) {
        window.location.href = actionButton.dataset.url;
      }
      if (action === 'showtimes' && !actionButton.disabled) {
        window.location.href = catalogAppUrl('/showtimes');
      }
      return;
    }

    const card = event.target.closest('[data-slug]');
    if (card?.dataset?.slug) {
      window.location.href = movieDetailUrl(card.dataset.slug);
    }
  }

  function handlePaginationClick(event) {
    const button = event.target.closest('[data-page]');
    if (!button || button.disabled) {
      return;
    }

    const targetPage = Number(button.dataset.page || 1);
    if (!Number.isFinite(targetPage) || targetPage < 1 || targetPage === state.filters.page) {
      return;
    }

    state.filters.page = targetPage;
    void loadMovieCatalog();
  }

  async function fetchJson(path, query) {
    const response = await fetch(`${catalogAppUrl(path)}${buildQueryString(query)}`, {
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

  function catalogAppUrl(path) {
    const basePath = typeof window.APP_BASE_PATH === 'string' ? window.APP_BASE_PATH : '';
    const normalizedPath = String(path || '').startsWith('/') ? path : `/${path}`;
    return `${basePath}${normalizedPath}`;
  }

  function movieDetailUrl(slug) {
    const query = slug ? `?slug=${encodeURIComponent(slug)}` : '';
    return `${catalogAppUrl('/movie-detail')}${query}`;
  }

  function buildQueryString(params) {
    const searchParams = new URLSearchParams();

    Object.entries(params || {}).forEach(([key, value]) => {
      if (value === null || value === undefined || value === '') {
        return;
      }

      searchParams.append(key, String(value));
    });

    const query = searchParams.toString();
    return query ? `?${query}` : '';
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

  function extractReleaseYear(releaseDate) {
    if (!releaseDate) {
      return 'TBA';
    }

    const match = String(releaseDate).match(/^(\d{4})/);
    return match ? match[1] : String(releaseDate);
  }

  function humanizeStatus(value) {
    return String(value || '')
      .split('_')
      .filter(Boolean)
      .map(part => part.charAt(0).toUpperCase() + part.slice(1))
      .join(' ') || 'Unknown';
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

  document.addEventListener('DOMContentLoaded', initMovieCatalogPage);
})();
