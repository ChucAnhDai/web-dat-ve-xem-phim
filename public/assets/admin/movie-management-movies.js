(function () {
  const MOVIE_STATUSES = ['draft', 'coming_soon', 'now_showing', 'ended', 'archived'];
  const AGE_RATINGS = ['P', 'K', 'T13', 'T16', 'T18', 'PG-13', 'R'];
  const OPHIM_LIST_OPTIONS = [
    { value: 'phim-chieu-rap', label: 'Phim Chieu Rap' },
    { value: 'phim-sap-chieu', label: 'Phim Sap Chieu' },
    { value: 'phim-moi', label: 'Phim Moi' },
    { value: 'phim-bo', label: 'Phim Bo' },
    { value: 'phim-le', label: 'Phim Le' },
    { value: 'tv-shows', label: 'TV Shows' },
    { value: 'hoat-hinh', label: 'Hoat Hinh' },
    { value: 'phim-vietsub', label: 'Phim Vietsub' },
    { value: 'phim-thuyet-minh', label: 'Phim Thuyet Minh' },
    { value: 'phim-long-tien', label: 'Phim Long Tien' },
    { value: 'phim-bo-dang-chieu', label: 'Phim Bo Dang Chieu' },
    { value: 'phim-bo-hoan-thanh', label: 'Phim Bo Hoan Thanh' },
    { value: 'subteam', label: 'Subteam' },
  ];
  const DEFAULT_SUMMARY = {
    total: 0,
    draft: 0,
    coming_soon: 0,
    now_showing: 0,
    ended: 0,
    archived: 0,
  };

  const state = {
    items: [],
    categories: [],
    meta: {
      page: 1,
      per_page: 10,
      total: 0,
      total_pages: 1,
    },
    summary: { ...DEFAULT_SUMMARY },
    filters: {
      page: 1,
      per_page: 10,
      search: '',
      primary_category_id: '',
      status: '',
    },
    loading: false,
    categoriesLoaded: false,
    searchTimer: null,
    initialized: false,
  };

  const dom = {};

  function initMovieManagementMovies() {
    if (state.initialized || document.body?.dataset?.activePage !== 'movies') {
      return;
    }

    cacheDom();
    if (!dom.moviesBody) {
      return;
    }

    state.initialized = true;
    bindEvents();
    window.handleMovieSectionAction = openCreateMovieModal;

    renderStats();
    renderLoadingRow('Loading movie catalog...');
    refreshMovieData();
  }

  function cacheDom() {
    dom.movieTotalStat = document.getElementById('movieTotalStat');
    dom.movieNowShowingStat = document.getElementById('movieNowShowingStat');
    dom.movieComingSoonStat = document.getElementById('movieComingSoonStat');
    dom.movieDraftStat = document.getElementById('movieDraftStat');
    dom.movieSearchInput = document.getElementById('movieSearchInput');
    dom.movieCategoryFilter = document.getElementById('movieCategoryFilter');
    dom.movieStatusFilter = document.getElementById('movieStatusFilter');
    dom.movieCount = document.getElementById('movieCount');
    dom.movieRequestStatus = document.getElementById('movieRequestStatus');
    dom.movieImportOphimBtn = document.getElementById('movieImportOphimBtn');
    dom.movieBatchImportOphimBtn = document.getElementById('movieBatchImportOphimBtn');
    dom.movieExportBtn = document.getElementById('movieExportBtn');
    dom.moviesBody = document.getElementById('moviesBody');
    dom.moviesPagination = document.getElementById('moviesPagination');
  }

  function bindEvents() {
    dom.movieSearchInput?.addEventListener('input', handleSearchInput);
    dom.movieCategoryFilter?.addEventListener('change', () => {
      state.filters.primary_category_id = dom.movieCategoryFilter.value;
      state.filters.page = 1;
      loadMovies();
    });
    dom.movieStatusFilter?.addEventListener('change', () => {
      state.filters.status = dom.movieStatusFilter.value;
      state.filters.page = 1;
      loadMovies();
    });
    dom.movieImportOphimBtn?.addEventListener('click', openImportOphimModal);
    dom.movieBatchImportOphimBtn?.addEventListener('click', openBatchImportOphimModal);
    dom.movieExportBtn?.addEventListener('click', exportCurrentMovieView);
    dom.moviesBody?.addEventListener('click', handleTableAction);
    dom.moviesPagination?.addEventListener('click', handlePaginationAction);
  }

  async function refreshMovieData() {
    try {
      await Promise.allSettled([loadCategoryOptions(), loadMovies()]);
    } catch (error) {
      showToast(errorMessageFromException(error, 'Failed to load movie management data.'), 'error');
    }
  }

  async function loadCategoryOptions() {
    updateRequestStatus('Loading categories...');

    try {
      const response = await adminApiRequest('/api/admin/movie-categories', {
        query: {
          page: 1,
          per_page: 100,
        },
      });
      state.categories = Array.isArray(response?.data?.items) ? response.data.items : [];
      state.categoriesLoaded = true;
      renderCategoryOptions();
      updateRequestStatus('Categories synced');
      return state.categories;
    } catch (error) {
      state.categories = [];
      state.categoriesLoaded = false;
      renderCategoryOptions();
      updateRequestStatus('Category sync failed');
      throw error;
    }
  }

  async function loadMovies() {
    setLoading(true, 'Loading movie catalog...');

    try {
      const response = await adminApiRequest('/api/admin/movies', {
        query: buildMovieQuery(),
      });

      const payload = response?.data || {};
      state.items = Array.isArray(payload.items) ? payload.items : [];
      state.meta = normalizeMeta(payload.meta);
      state.summary = normalizeSummary(payload.summary);

      if (state.items.length === 0 && state.meta.total > 0 && state.filters.page > 1) {
        state.filters.page = Math.max(1, state.meta.total_pages);
        return loadMovies();
      }

      renderStats();
      renderMoviesTable();
      renderPagination();
      updateRequestStatus('Movie catalog synced');
    } catch (error) {
      state.items = [];
      state.meta = normalizeMeta();
      state.summary = { ...DEFAULT_SUMMARY };

      renderStats();
      renderErrorRow(errorMessageFromException(error, 'Failed to load movie catalog.'));
      renderPagination();
      updateRequestStatus('Movie catalog unavailable');
      showToast(errorMessageFromException(error, 'Failed to load movie catalog.'), 'error');
    } finally {
      setLoading(false);
    }
  }

  function buildMovieQuery() {
    return {
      page: state.filters.page,
      per_page: state.filters.per_page,
      search: state.filters.search,
      primary_category_id: state.filters.primary_category_id,
      status: state.filters.status,
    };
  }

  function normalizeMeta(meta = {}) {
    return {
      total: Number(meta?.total || 0),
      page: Number(meta?.page || state.filters.page || 1),
      per_page: Number(meta?.per_page || state.filters.per_page || 10),
      total_pages: Math.max(1, Number(meta?.total_pages || 1)),
    };
  }

  function normalizeSummary(summary = {}) {
    return {
      total: Number(summary?.total || 0),
      draft: Number(summary?.draft || 0),
      coming_soon: Number(summary?.coming_soon || 0),
      now_showing: Number(summary?.now_showing || 0),
      ended: Number(summary?.ended || 0),
      archived: Number(summary?.archived || 0),
    };
  }

  function setLoading(isLoading, statusText) {
    state.loading = isLoading;
    if (statusText) {
      updateRequestStatus(statusText);
    }
  }

  function updateRequestStatus(text) {
    if (dom.movieRequestStatus) {
      dom.movieRequestStatus.textContent = text;
    }
  }

  function renderStats() {
    if (dom.movieTotalStat) dom.movieTotalStat.textContent = String(state.summary.total);
    if (dom.movieNowShowingStat) dom.movieNowShowingStat.textContent = String(state.summary.now_showing);
    if (dom.movieComingSoonStat) dom.movieComingSoonStat.textContent = String(state.summary.coming_soon);
    if (dom.movieDraftStat) dom.movieDraftStat.textContent = String(state.summary.draft);
  }

  function renderCategoryOptions() {
    if (!dom.movieCategoryFilter) {
      return;
    }

    const activeValue = String(state.filters.primary_category_id || '');
    const options = [
      '<option value="">All Categories</option>',
      ...state.categories.map(category => {
        const value = String(category.id);
        const selected = value === activeValue ? ' selected' : '';
        const suffix = Number(category.is_active) === 0 ? ' (Inactive)' : '';
        return `<option value="${escapeHtmlAttr(value)}"${selected}>${escapeHtml(category.name || 'Untitled Category')}${escapeHtml(suffix)}</option>`;
      }),
    ];

    dom.movieCategoryFilter.innerHTML = options.join('');
  }

  function renderMoviesTable() {
    if (!dom.moviesBody) {
      return;
    }

    const total = Number(state.meta.total || 0);
    dom.movieCount.textContent = `${total} movie${total === 1 ? '' : 's'}`;

    if (state.loading && state.items.length === 0) {
      renderLoadingRow('Loading movie catalog...');
      return;
    }

    if (state.items.length === 0) {
      renderEmptyRow(
        'No movies matched the current filters.',
        'Try clearing the search or filters, or add a new movie to the catalog.'
      );
      return;
    }

    dom.moviesBody.innerHTML = state.items.map(movie => buildMovieRow(movie)).join('');
  }

  function buildMovieRow(movie) {
    const posterMarkup = movie.poster_url
      ? `<img class="poster-thumb" src="${escapeHtmlAttr(movie.poster_url)}" alt="${escapeHtmlAttr(movie.title || 'Movie poster')}" loading="lazy">`
      : `<div class="poster-img-placeholder">${escapeHtml(getPosterCode(movie.title))}</div>`;
    const categoryLabel = movie.primary_category_name || getCategoryNameById(movie.primary_category_id) || 'Unassigned';
    const reviewCount = Number(movie.review_count || 0);

    return `
      <tr>
        <td>${posterMarkup}</td>
        <td>
          <div class="td-bold">${escapeHtml(movie.title || 'Untitled Movie')}</div>
          <div class="td-muted">${escapeHtml(movie.slug || '-')}</div>
        </td>
        <td><span class="badge gray">${escapeHtml(categoryLabel)}</span></td>
        <td class="td-muted">${escapeHtml(formatMovieDuration(movie.duration_minutes))}</td>
        <td class="td-muted">${escapeHtml(formatMovieDate(movie.release_date))}</td>
        <td>
          ${stars(movie.average_rating)}
          <div class="table-meta-text" style="margin-top:4px;">${escapeHtml(`${reviewCount} review${reviewCount === 1 ? '' : 's'}`)}</div>
        </td>
        <td>${statusBadge(movie.status)}</td>
        <td>
          <div class="actions-row">
            <button class="action-btn view" type="button" title="View" data-action="view" data-movie-id="${Number(movie.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="action-btn edit" type="button" title="Edit" data-action="edit" data-movie-id="${Number(movie.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="action-btn del" type="button" title="Archive" data-action="archive" data-movie-id="${Number(movie.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            </button>
            <button class="action-btn gold" type="button" title="Add Showtime" data-action="showtimes" data-movie-id="${Number(movie.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `;
  }

  function renderLoadingRow(message) {
    if (!dom.moviesBody) {
      return;
    }

    dom.movieCount.textContent = 'Loading movies...';
    dom.moviesBody.innerHTML = `
      <tr class="table-empty-row">
        <td colspan="8">
          <div class="table-empty-state">
            <strong>${escapeHtml(message)}</strong>
            <div class="table-meta-text">Please wait while we sync the latest movie catalog.</div>
          </div>
        </td>
      </tr>
    `;
  }

  function renderEmptyRow(title, description) {
    if (!dom.moviesBody) {
      return;
    }

    dom.moviesBody.innerHTML = `
      <tr class="table-empty-row">
        <td colspan="8">
          <div class="table-empty-state">
            <strong>${escapeHtml(title)}</strong>
            <div class="table-meta-text">${escapeHtml(description)}</div>
          </div>
        </td>
      </tr>
    `;
  }

  function renderErrorRow(message) {
    renderEmptyRow('Movie data could not be loaded.', message);
    dom.movieCount.textContent = 'Movie data unavailable';
  }

  function renderPagination() {
    if (!dom.moviesPagination) {
      return;
    }

    const total = Number(state.meta.total || 0);
    const page = Number(state.meta.page || 1);
    const totalPages = Math.max(1, Number(state.meta.total_pages || 1));
    const perPage = Math.max(1, Number(state.meta.per_page || 10));
    const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const end = total === 0 ? 0 : Math.min(total, start + state.items.length - 1);
    const pages = buildVisiblePages(page, totalPages);

    dom.moviesPagination.innerHTML = `
      <div class="pagination">
        <div class="pagination-info">Showing ${start}-${end} of ${total} movies</div>
        <div class="pagination-btns">
          <button class="pg-btn" type="button" data-page="${page - 1}" ${page <= 1 ? 'disabled' : ''}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
          </button>
          ${pages.map(item => {
            if (item === 'ellipsis') {
              return '<button class="pg-btn" type="button" disabled>...</button>';
            }

            return `<button class="pg-btn${item === page ? ' active' : ''}" type="button" data-page="${item}">${item}</button>`;
          }).join('')}
          <button class="pg-btn" type="button" data-page="${page + 1}" ${page >= totalPages ? 'disabled' : ''}>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
          </button>
        </div>
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
      state.filters.search = dom.movieSearchInput?.value.trim() || '';
      state.filters.page = 1;
      loadMovies();
    }, 300);
  }

  function handlePaginationAction(event) {
    const button = event.target.closest('button[data-page]');
    if (!button || button.disabled) {
      return;
    }

    const targetPage = Number(button.dataset.page || 1);
    if (!Number.isFinite(targetPage) || targetPage < 1 || targetPage === state.filters.page) {
      return;
    }

    state.filters.page = targetPage;
    loadMovies();
  }

  function handleTableAction(event) {
    const button = event.target.closest('button[data-action]');
    if (!button) {
      return;
    }

    const movieId = Number(button.dataset.movieId || 0);
    if (!movieId) {
      showToast('Movie ID is missing for this action.', 'error');
      return;
    }

    const action = button.dataset.action;

    if (action === 'showtimes') {
      window.location.href = adminAppUrl('/admin/showtimes');
      return;
    }

    if (action === 'view') {
      openPreviewMovieModal(movieId);
      return;
    }

    if (action === 'edit') {
      openEditMovieModal(movieId);
      return;
    }

    if (action === 'archive') {
      archiveMovie(movieId);
    }
  }

  async function openCreateMovieModal() {
    if (!(await ensureCategoriesReady())) {
      return;
    }

    openMovieEditorModal('Add Movie', {});
  }

  function openImportOphimModal() {
    openModal('Import OPhim Movie', buildOphimImportBody(), {
      description: 'Fetch a movie from OPhim by slug, map it into the local movie schema, and keep admin data editable afterward.',
      note: 'The import creates missing categories automatically and can also sync poster, banner, and gallery assets.',
      submitLabel: 'Import Movie',
      busyLabel: 'Importing...',
      onSave: async () => {
        await submitOphimImportForm();
      },
    });
  }

  function openBatchImportOphimModal() {
    openModal('Batch Sync OPhim Movies', buildOphimBatchImportBody(), {
      description: 'Load a page from an OPhim movie list and sync each movie into the local admin catalog.',
      note: 'Batch sync keeps local movies editable. Image sync is optional and disabled by default for faster imports.',
      submitLabel: 'Sync Movie List',
      busyLabel: 'Syncing...',
      onSave: async () => {
        await submitOphimBatchImportForm();
      },
    });
  }

  async function openEditMovieModal(movieId) {
    if (!(await ensureCategoriesReady())) {
      return;
    }

    updateRequestStatus('Loading movie details...');

    try {
      const movie = await fetchMovieDetail(movieId);
      openMovieEditorModal('Edit Movie', movie);
      updateRequestStatus('Movie details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load movie details');
      showToast(errorMessageFromException(error, 'Failed to load movie details.'), 'error');
    }
  }

  async function openPreviewMovieModal(movieId) {
    updateRequestStatus('Loading movie details...');

    try {
      const movie = await fetchMovieDetail(movieId);
      openModal('Movie Details', buildMoviePreview(movie), {
        description: 'Read the full schema-aligned metadata stored for this movie.',
        note: 'This preview comes directly from the admin movie API.',
        submitLabel: 'Edit Movie',
        cancelLabel: 'Close',
        busyLabel: 'Opening...',
        onSave: async () => {
          closeModal();
          await openEditMovieModal(movie.id);
        },
      });
      updateRequestStatus('Movie details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load movie details');
      showToast(errorMessageFromException(error, 'Failed to load movie details.'), 'error');
    }
  }

  async function archiveMovie(movieId) {
    const movie = state.items.find(item => Number(item.id) === Number(movieId));
    const movieTitle = movie?.title || `movie #${movieId}`;

    if (!window.confirm(`Archive "${movieTitle}"? The movie will stay in the database with archived status.`)) {
      return;
    }

    updateRequestStatus('Archiving movie...');

    try {
      const response = await adminApiRequest(`/api/admin/movies/${movieId}`, {
        method: 'DELETE',
      });

      if (state.items.length === 1 && state.filters.page > 1) {
        state.filters.page -= 1;
      }

      await loadMovies();
      showToast(response?.message || 'Movie archived successfully.', 'success');
    } catch (error) {
      updateRequestStatus('Archive failed');
      showToast(errorMessageFromException(error, 'Failed to archive movie.'), 'error');
    }
  }

  async function fetchMovieDetail(movieId) {
    const response = await adminApiRequest(`/api/admin/movies/${movieId}`);
    return response?.data || {};
  }

  async function ensureCategoriesReady() {
    if (state.categoriesLoaded && state.categories.length > 0) {
      return true;
    }

    try {
      await loadCategoryOptions();
      return true;
    } catch (error) {
      showToast(errorMessageFromException(error, 'Movie categories are required before you can edit movies.'), 'error');
      return false;
    }
  }

  function openMovieEditorModal(title, movie) {
    const isEdit = Number(movie?.id || 0) > 0;

    openModal(title, buildMovieEditorBody(movie), {
      description: isEdit
        ? 'Update metadata that maps directly to the movies and movie_category_assignments tables.'
        : 'Create a movie record using the same fields defined in the SQL schema.',
      note: 'Required fields are validated in both the browser and the backend service.',
      submitLabel: isEdit ? 'Update Movie' : 'Create Movie',
      busyLabel: isEdit ? 'Updating...' : 'Creating...',
      onSave: async () => {
        await submitMovieForm(movie?.id || null);
      },
    });

    attachMovieFormInteractions(movie);
  }

  function buildMovieEditorBody(movie = {}) {
    const primaryCategoryId = Number(movie.primary_category_id || 0);
    const selectedCategoryIds = Array.isArray(movie.category_ids) && movie.category_ids.length > 0
      ? movie.category_ids.map(value => Number(value))
      : (primaryCategoryId ? [primaryCategoryId] : []);

    if (primaryCategoryId && !selectedCategoryIds.includes(primaryCategoryId)) {
      selectedCategoryIds.push(primaryCategoryId);
    }

    return `
      <form id="movieAdminForm" class="form-grid" novalidate>
        <div class="form-alert" data-form-alert hidden></div>

        <div class="field">
          <label for="movieTitleInput">Movie Title</label>
          <input class="input" id="movieTitleInput" data-field-control="title" name="title" type="text" placeholder="Enter title" value="${escapeHtmlAttr(movie.title || '')}">
          <div class="field-error" data-field-error="title" hidden></div>
        </div>

        <div class="field">
          <label for="movieSlugInput">Slug</label>
          <input class="input" id="movieSlugInput" data-field-control="slug" name="slug" type="text" placeholder="movie-slug" value="${escapeHtmlAttr(movie.slug || '')}">
          <div class="field-error" data-field-error="slug" hidden></div>
        </div>

        <div class="field">
          <label for="moviePrimaryCategoryInput">Primary Category</label>
          <select class="select" id="moviePrimaryCategoryInput" data-field-control="primary_category_id" name="primary_category_id">
            <option value="">Select primary category</option>
            ${state.categories.map(category => {
              const selected = Number(category.id) === primaryCategoryId ? ' selected' : '';
              const suffix = Number(category.is_active) === 0 ? ' (Inactive)' : '';
              return `<option value="${escapeHtmlAttr(category.id)}"${selected}>${escapeHtml(category.name || 'Untitled Category')}${escapeHtml(suffix)}</option>`;
            }).join('')}
          </select>
          <div class="field-error" data-field-error="primary_category_id" hidden></div>
        </div>

        <div class="field">
          <label for="movieStatusInput">Status</label>
          <select class="select" id="movieStatusInput" data-field-control="status" name="status">
            ${buildOptions(MOVIE_STATUSES.map(status => ({ value: status, label: humanizeStatus(status) })), movie.status || 'draft')}
          </select>
          <div class="field-error" data-field-error="status" hidden></div>
        </div>

        <div class="field">
          <label for="movieDurationInput">Duration (minutes)</label>
          <input class="input" id="movieDurationInput" data-field-control="duration_minutes" name="duration_minutes" type="number" min="1" max="500" placeholder="120" value="${escapeHtmlAttr(movie.duration_minutes || '')}">
          <div class="field-error" data-field-error="duration_minutes" hidden></div>
        </div>

        <div class="field">
          <label for="movieReleaseDateInput">Release Date</label>
          <input class="input" id="movieReleaseDateInput" data-field-control="release_date" name="release_date" type="date" value="${escapeHtmlAttr(movie.release_date || '')}">
          <div class="field-error" data-field-error="release_date" hidden></div>
        </div>

        <div class="field">
          <label for="movieRatingInput">Average Rating (0-5)</label>
          <input class="input" id="movieRatingInput" data-field-control="average_rating" name="average_rating" type="number" min="0" max="5" step="0.1" placeholder="4.5" value="${escapeHtmlAttr(movie.average_rating ?? '')}">
          <div class="field-error" data-field-error="average_rating" hidden></div>
        </div>

        <div class="field">
          <label for="movieAgeRatingInput">Age Rating</label>
          <select class="select" id="movieAgeRatingInput" data-field-control="age_rating" name="age_rating">
            <option value="">Optional</option>
            ${buildOptions(AGE_RATINGS, movie.age_rating || '')}
          </select>
          <div class="field-error" data-field-error="age_rating" hidden></div>
        </div>

        <div class="field">
          <label for="movieLanguageInput">Language</label>
          <input class="input" id="movieLanguageInput" data-field-control="language" name="language" type="text" placeholder="English, Vietnamese" value="${escapeHtmlAttr(movie.language || '')}">
          <div class="field-error" data-field-error="language" hidden></div>
        </div>

        <div class="field">
          <label for="movieDirectorInput">Director</label>
          <input class="input" id="movieDirectorInput" data-field-control="director" name="director" type="text" placeholder="Director name" value="${escapeHtmlAttr(movie.director || '')}">
          <div class="field-error" data-field-error="director" hidden></div>
        </div>

        <div class="field">
          <label for="movieWriterInput">Writer</label>
          <input class="input" id="movieWriterInput" data-field-control="writer" name="writer" type="text" placeholder="Writer name" value="${escapeHtmlAttr(movie.writer || '')}">
          <div class="field-error" data-field-error="writer" hidden></div>
        </div>

        <div class="field">
          <label for="movieStudioInput">Studio</label>
          <input class="input" id="movieStudioInput" data-field-control="studio" name="studio" type="text" placeholder="Studio name" value="${escapeHtmlAttr(movie.studio || '')}">
          <div class="field-error" data-field-error="studio" hidden></div>
        </div>

        <div class="field form-full">
          <label for="movieCastInput">Cast Summary</label>
          <input class="input" id="movieCastInput" data-field-control="cast_text" name="cast_text" type="text" placeholder="Lead cast, comma separated" value="${escapeHtmlAttr(movie.cast_text || '')}">
          <div class="field-error" data-field-error="cast_text" hidden></div>
        </div>

        <div class="field">
          <label for="moviePosterInput">Poster URL</label>
          <input class="input" id="moviePosterInput" data-field-control="poster_url" name="poster_url" type="url" placeholder="https://cdn.example.com/poster.jpg" value="${escapeHtmlAttr(movie.poster_url || '')}">
          <div class="field-error" data-field-error="poster_url" hidden></div>
        </div>

        <div class="field">
          <label for="movieTrailerInput">Trailer URL</label>
          <input class="input" id="movieTrailerInput" data-field-control="trailer_url" name="trailer_url" type="url" placeholder="https://youtube.com/watch?v=..." value="${escapeHtmlAttr(movie.trailer_url || '')}">
          <div class="field-error" data-field-error="trailer_url" hidden></div>
        </div>

        <div class="field form-full">
          <label>Assigned Categories</label>
          <div class="checkbox-grid" data-field-control="category_ids">
            ${buildCategoryChecklist(selectedCategoryIds)}
          </div>
          <div class="helper-text">The primary category is always included in the assignment set.</div>
          <div class="field-error" data-field-error="category_ids" hidden></div>
        </div>

        <div class="field form-full">
          <label for="movieSummaryInput">Summary</label>
          <textarea class="textarea" id="movieSummaryInput" data-field-control="summary" name="summary" placeholder="Movie summary...">${escapeHtml(movie.summary || '')}</textarea>
          <div class="helper-text">Posters, banners, and gallery art remain managed in the Movie Images section.</div>
          <div class="field-error" data-field-error="summary" hidden></div>
        </div>
      </form>
    `;
  }

  function buildCategoryChecklist(selectedCategoryIds) {
    if (state.categories.length === 0) {
      return '<div class="surface-card form-full"><div class="surface-card-title">No categories available</div><div class="surface-card-copy">Create at least one movie category before adding movies.</div></div>';
    }

    return state.categories.map(category => {
      const isChecked = selectedCategoryIds.includes(Number(category.id));
      const description = category.description || 'No description available for this category yet.';
      const suffix = Number(category.is_active) === 0 ? 'Inactive' : 'Active';

      return `
        <label class="checkbox-option">
          <input type="checkbox" name="category_ids[]" value="${escapeHtmlAttr(category.id)}"${isChecked ? ' checked' : ''}>
          <span>
            <strong>${escapeHtml(category.name || 'Untitled Category')}</strong>
            <small>${escapeHtml(description)}</small>
            <small>${escapeHtml(suffix)}</small>
          </span>
        </label>
      `;
    }).join('');
  }

  function buildOphimImportBody(values = {}) {
    const statusOverride = String(values.status_override || '');

    return `
      <form id="movieOphimImportForm" class="form-grid" novalidate>
        <div class="form-alert" data-form-alert hidden></div>

        <div class="field form-full">
          <label for="movieOphimSlugInput">OPhim Slug</label>
          <input class="input" id="movieOphimSlugInput" data-field-control="slug" name="slug" type="text" placeholder="tro-choi-con-muc" value="${escapeHtmlAttr(values.slug || '')}">
          <div class="helper-text">Enter the movie slug from the OPhim detail endpoint.</div>
          <div class="field-error" data-field-error="slug" hidden></div>
        </div>

        <div class="field">
          <label for="movieOphimStatusOverrideInput">Status Override</label>
          <select class="select" id="movieOphimStatusOverrideInput" data-field-control="status_override" name="status_override">
            <option value="">Infer from OPhim source</option>
            ${buildOptions(MOVIE_STATUSES.map(status => ({ value: status, label: humanizeStatus(status) })), statusOverride)}
          </select>
          <div class="helper-text">Leave empty to map trailer titles to Coming Soon and the rest to Now Showing.</div>
          <div class="field-error" data-field-error="status_override" hidden></div>
        </div>

        <div class="field">
          <label>Sync Options</label>
          <label class="checkbox-option">
            <input id="movieOphimSyncImagesInput" name="sync_images" type="checkbox" value="1"${values.sync_images === 0 ? '' : ' checked'}>
            <span>
              <strong>Sync images</strong>
              <small>Fetch poster, banner, and gallery assets from OPhim/TMDB and archive previous synced assets.</small>
            </span>
          </label>
          <div class="field-error" data-field-error="sync_images" hidden></div>
        </div>

        <div class="field">
          <label>Conflict Handling</label>
          <label class="checkbox-option">
            <input id="movieOphimOverwriteInput" name="overwrite_existing" type="checkbox" value="1"${values.overwrite_existing === 0 ? '' : ' checked'}>
            <span>
              <strong>Overwrite existing movie</strong>
              <small>Update the local record when a movie with the same slug already exists.</small>
            </span>
          </label>
          <div class="field-error" data-field-error="overwrite_existing" hidden></div>
        </div>
      </form>
    `;
  }

  function buildOphimBatchImportBody(values = {}) {
    const listSlug = String(values.list_slug || 'phim-chieu-rap');
    const statusOverride = String(values.status_override || '');
    const page = Number(values.page || 1);
    const limit = Number(values.limit || 12);

    return `
      <form id="movieOphimBatchImportForm" class="form-grid" novalidate>
        <div class="form-alert" data-form-alert hidden></div>

        <div class="field">
          <label for="movieOphimListSlugInput">OPhim List</label>
          <select class="select" id="movieOphimListSlugInput" data-field-control="list_slug" name="list_slug">
            ${buildOptions(OPHIM_LIST_OPTIONS, listSlug)}
          </select>
          <div class="helper-text">Choose the OPhim catalog list you want to sync into the admin database.</div>
          <div class="field-error" data-field-error="list_slug" hidden></div>
        </div>

        <div class="field">
          <label for="movieOphimBatchStatusOverrideInput">Status Override</label>
          <select class="select" id="movieOphimBatchStatusOverrideInput" data-field-control="status_override" name="status_override">
            <option value="">Infer from selected list</option>
            ${buildOptions(MOVIE_STATUSES.map(status => ({ value: status, label: humanizeStatus(status) })), statusOverride)}
          </select>
          <div class="helper-text">Cinema lists default to Now Showing or Coming Soon when you leave this empty.</div>
          <div class="field-error" data-field-error="status_override" hidden></div>
        </div>

        <div class="field">
          <label for="movieOphimBatchPageInput">Page</label>
          <input class="input" id="movieOphimBatchPageInput" data-field-control="page" name="page" type="number" min="1" max="100" value="${escapeHtmlAttr(page)}">
          <div class="helper-text">OPhim page index to import.</div>
          <div class="field-error" data-field-error="page" hidden></div>
        </div>

        <div class="field">
          <label for="movieOphimBatchLimitInput">Limit</label>
          <input class="input" id="movieOphimBatchLimitInput" data-field-control="limit" name="limit" type="number" min="1" max="24" value="${escapeHtmlAttr(limit)}">
          <div class="helper-text">Import between 1 and 24 movies in one batch.</div>
          <div class="field-error" data-field-error="limit" hidden></div>
        </div>

        <div class="field">
          <label>Sync Options</label>
          <label class="checkbox-option">
            <input name="sync_images" type="checkbox" value="1"${values.sync_images === 1 ? ' checked' : ''}>
            <span>
              <strong>Sync images for each movie</strong>
              <small>Fetch poster, banner, and gallery assets per movie. This makes the batch slower but richer.</small>
            </span>
          </label>
          <div class="field-error" data-field-error="sync_images" hidden></div>
        </div>

        <div class="field">
          <label>Conflict Handling</label>
          <label class="checkbox-option">
            <input name="overwrite_existing" type="checkbox" value="1"${values.overwrite_existing === 0 ? '' : ' checked'}>
            <span>
              <strong>Overwrite existing local movies</strong>
              <small>Update already-imported slugs instead of skipping them.</small>
            </span>
          </label>
          <div class="field-error" data-field-error="overwrite_existing" hidden></div>
        </div>
      </form>
    `;
  }

  function attachMovieFormInteractions(movie = {}) {
    const form = document.getElementById('movieAdminForm');
    if (!form) {
      return;
    }

    const titleInput = form.querySelector('[name="title"]');
    const slugInput = form.querySelector('[name="slug"]');
    const primaryCategorySelect = form.querySelector('[name="primary_category_id"]');
    const categoryCheckboxes = Array.from(form.querySelectorAll('input[name="category_ids[]"]'));

    if (slugInput) {
      slugInput.dataset.manual = movie.slug ? '1' : '0';
      slugInput.addEventListener('input', () => {
        slugInput.dataset.manual = slugInput.value.trim() === '' ? '0' : '1';
      });
    }

    titleInput?.addEventListener('input', () => {
      if (slugInput && slugInput.dataset.manual !== '1') {
        slugInput.value = slugifyValue(titleInput.value);
      }
    });

    primaryCategorySelect?.addEventListener('change', () => {
      const selectedValue = String(primaryCategorySelect.value || '');
      if (!selectedValue) {
        return;
      }

      const matchingCheckbox = categoryCheckboxes.find(checkbox => checkbox.value === selectedValue);
      if (matchingCheckbox) {
        matchingCheckbox.checked = true;
      }
    });

    categoryCheckboxes.forEach(checkbox => {
      checkbox.addEventListener('change', () => {
        const selectedPrimary = String(primaryCategorySelect?.value || '');
        if (checkbox.checked || checkbox.value !== selectedPrimary) {
          return;
        }

        checkbox.checked = true;
        showToast('Primary category is always included in the category assignments.', 'info');
      });
    });
  }

  async function submitMovieForm(movieId) {
    const form = document.getElementById('movieAdminForm');
    if (!form) {
      throw new Error('Movie form is unavailable.');
    }

    const { payload, errors } = validateMoviePayload(collectMovieFormPayload(form));
    if (Object.keys(errors).length > 0) {
      applyFormErrors(form, errors);
      return;
    }

    clearFormErrors(form);

    try {
      const response = await adminApiRequest(movieId ? `/api/admin/movies/${movieId}` : '/api/admin/movies', {
        method: movieId ? 'PUT' : 'POST',
        body: payload,
      });

      closeModal();
      if (!movieId) {
        state.filters.page = 1;
      }

      await loadMovies();
      showToast(response?.message || (movieId ? 'Movie updated successfully.' : 'Movie created successfully.'), 'success');
    } catch (error) {
      if (error instanceof AdminApiError && (error.status === 409 || error.status === 422)) {
        applyFormErrors(form, error.errors);
        return;
      }

      throw error;
    }
  }

  async function submitOphimImportForm() {
    const form = document.getElementById('movieOphimImportForm');
    if (!form) {
      throw new Error('OPhim import form is unavailable.');
    }

    const { payload, errors } = validateOphimImportPayload(collectOphimImportPayload(form));
    if (Object.keys(errors).length > 0) {
      applyFormErrors(form, errors);
      return;
    }

    clearFormErrors(form);
    updateRequestStatus('Importing movie from OPhim...');

    try {
      const response = await adminApiRequest('/api/admin/movies/import-ophim', {
        method: 'POST',
        body: payload,
      });

      closeModal();
      state.filters.page = 1;
      const reloadResults = await Promise.allSettled([loadCategoryOptions(), loadMovies()]);
      const failedReload = reloadResults.find(result => result.status === 'rejected');
      if (failedReload && failedReload.reason) {
        throw failedReload.reason;
      }

      updateRequestStatus('OPhim movie synced');

      const movieTitle = response?.data?.movie?.title || payload.slug;
      const fallbackMessage = response?.data?.sync?.created
        ? `Imported "${movieTitle}" from OPhim.`
        : `Synced "${movieTitle}" from OPhim.`;

      showToast(response?.message || fallbackMessage, 'success');
    } catch (error) {
      updateRequestStatus('OPhim import failed');

      if (error instanceof AdminApiError && [404, 409, 422].includes(error.status)) {
        applyFormErrors(form, error.errors);
        return;
      }

      throw error;
    }
  }

  async function submitOphimBatchImportForm() {
    const form = document.getElementById('movieOphimBatchImportForm');
    if (!form) {
      throw new Error('OPhim batch import form is unavailable.');
    }

    const { payload, errors } = validateOphimBatchImportPayload(collectOphimBatchImportPayload(form));
    if (Object.keys(errors).length > 0) {
      applyFormErrors(form, errors);
      return;
    }

    clearFormErrors(form);
    updateRequestStatus('Syncing OPhim movie list...');

    try {
      const response = await adminApiRequest('/api/admin/movies/import-ophim-list', {
        method: 'POST',
        body: payload,
      });

      closeModal();
      state.filters.page = 1;
      const reloadResults = await Promise.allSettled([loadCategoryOptions(), loadMovies()]);
      const failedReload = reloadResults.find(result => result.status === 'rejected');
      if (failedReload && failedReload.reason) {
        throw failedReload.reason;
      }

      updateRequestStatus('OPhim movie list synced');

      const summary = response?.data || {};
      const toastType = Number(summary.failed_count || 0) > 0 ? 'warning' : 'success';
      showToast(buildOphimBatchSyncMessage(summary), toastType);
    } catch (error) {
      updateRequestStatus('OPhim batch sync failed');

      if (error instanceof AdminApiError && [404, 422].includes(error.status)) {
        applyFormErrors(form, error.errors);
        return;
      }

      throw error;
    }
  }

  function collectMovieFormPayload(form) {
    return {
      title: form.querySelector('[name="title"]')?.value || '',
      slug: form.querySelector('[name="slug"]')?.value || '',
      primary_category_id: form.querySelector('[name="primary_category_id"]')?.value || '',
      status: form.querySelector('[name="status"]')?.value || '',
      duration_minutes: form.querySelector('[name="duration_minutes"]')?.value || '',
      release_date: form.querySelector('[name="release_date"]')?.value || '',
      average_rating: form.querySelector('[name="average_rating"]')?.value || '',
      age_rating: form.querySelector('[name="age_rating"]')?.value || '',
      language: form.querySelector('[name="language"]')?.value || '',
      director: form.querySelector('[name="director"]')?.value || '',
      writer: form.querySelector('[name="writer"]')?.value || '',
      studio: form.querySelector('[name="studio"]')?.value || '',
      cast_text: form.querySelector('[name="cast_text"]')?.value || '',
      poster_url: form.querySelector('[name="poster_url"]')?.value || '',
      trailer_url: form.querySelector('[name="trailer_url"]')?.value || '',
      summary: form.querySelector('[name="summary"]')?.value || '',
      category_ids: Array.from(form.querySelectorAll('input[name="category_ids[]"]:checked')).map(checkbox => checkbox.value),
    };
  }

  function collectOphimImportPayload(form) {
    return {
      slug: form.querySelector('[name="slug"]')?.value || '',
      status_override: form.querySelector('[name="status_override"]')?.value || '',
      sync_images: form.querySelector('[name="sync_images"]')?.checked ? '1' : '0',
      overwrite_existing: form.querySelector('[name="overwrite_existing"]')?.checked ? '1' : '0',
    };
  }

  function collectOphimBatchImportPayload(form) {
    return {
      list_slug: form.querySelector('[name="list_slug"]')?.value || '',
      page: form.querySelector('[name="page"]')?.value || '1',
      limit: form.querySelector('[name="limit"]')?.value || '12',
      status_override: form.querySelector('[name="status_override"]')?.value || '',
      sync_images: form.querySelector('[name="sync_images"]')?.checked ? '1' : '0',
      overwrite_existing: form.querySelector('[name="overwrite_existing"]')?.checked ? '1' : '0',
    };
  }

  function validateMoviePayload(input) {
    const errors = {};
    const payload = {
      title: String(input.title || '').trim(),
      slug: slugifyValue(input.slug || input.title || ''),
      primary_category_id: toPositiveInteger(input.primary_category_id),
      status: String(input.status || '').trim().toLowerCase(),
      duration_minutes: toInteger(input.duration_minutes),
      release_date: String(input.release_date || '').trim(),
      average_rating: String(input.average_rating || '').trim(),
      age_rating: String(input.age_rating || '').trim(),
      language: String(input.language || '').trim(),
      director: String(input.director || '').trim(),
      writer: String(input.writer || '').trim(),
      studio: String(input.studio || '').trim(),
      cast_text: String(input.cast_text || '').trim(),
      poster_url: String(input.poster_url || '').trim(),
      trailer_url: String(input.trailer_url || '').trim(),
      summary: String(input.summary || '').trim(),
      category_ids: Array.isArray(input.category_ids)
        ? input.category_ids.map(toPositiveInteger).filter(Boolean)
        : [],
    };

    if (payload.title === '') {
      errors.title = ['Field is required.'];
    }
    if (payload.slug === '') {
      errors.slug = ['Slug is required.'];
    }
    if (!payload.primary_category_id) {
      errors.primary_category_id = ['Primary category must be selected.'];
    }
    if (!MOVIE_STATUSES.includes(payload.status)) {
      errors.status = ['Movie status is invalid.'];
    }
    if (!Number.isInteger(payload.duration_minutes) || payload.duration_minutes < 1 || payload.duration_minutes > 500) {
      errors.duration_minutes = ['Duration must be between 1 and 500 minutes.'];
    }
    if (payload.release_date !== '' && !isValidDateString(payload.release_date)) {
      errors.release_date = ['Release date must be a valid YYYY-MM-DD date.'];
    }
    if (payload.poster_url !== '' && !isValidUrl(payload.poster_url)) {
      errors.poster_url = ['Poster URL must be a valid URL.'];
    }
    if (payload.trailer_url !== '' && !isValidUrl(payload.trailer_url)) {
      errors.trailer_url = ['Trailer URL must be a valid URL.'];
    }

    if (payload.average_rating !== '') {
      const averageRating = Number(payload.average_rating);
      if (!Number.isFinite(averageRating)) {
        errors.average_rating = ['Average rating must be numeric.'];
      } else if (averageRating < 0 || averageRating > 5) {
        errors.average_rating = ['Average rating must be between 0 and 5.'];
      } else {
        payload.average_rating = Number(averageRating.toFixed(2));
      }
    } else {
      payload.average_rating = 0;
    }

    if (payload.primary_category_id && !payload.category_ids.includes(payload.primary_category_id)) {
      payload.category_ids.push(payload.primary_category_id);
    }

    payload.category_ids = Array.from(new Set(payload.category_ids));
    if (payload.category_ids.length === 0) {
      errors.category_ids = ['At least one category must be assigned.'];
    }

    payload.release_date = payload.release_date || null;
    payload.poster_url = payload.poster_url || null;
    payload.trailer_url = payload.trailer_url || null;
    payload.age_rating = payload.age_rating || null;
    payload.language = payload.language || null;
    payload.director = payload.director || null;
    payload.writer = payload.writer || null;
    payload.studio = payload.studio || null;
    payload.cast_text = payload.cast_text || null;
    payload.summary = payload.summary || null;

    return { payload, errors };
  }

  function validateOphimImportPayload(input) {
    const errors = {};
    const payload = {
      slug: String(input.slug || '').trim().toLowerCase(),
      status_override: String(input.status_override || '').trim().toLowerCase(),
      sync_images: input.sync_images === '0' ? 0 : 1,
      overwrite_existing: input.overwrite_existing === '0' ? 0 : 1,
    };

    if (payload.slug === '') {
      errors.slug = ['Field is required.'];
    } else if (!/^[a-z0-9-]+$/.test(payload.slug)) {
      errors.slug = ['OPhim slug may only contain lowercase letters, numbers, and hyphens.'];
    }

    if (payload.status_override !== '' && !MOVIE_STATUSES.includes(payload.status_override)) {
      errors.status_override = ['Status override is invalid.'];
    }

    payload.status_override = payload.status_override || null;

    return { payload, errors };
  }

  function validateOphimBatchImportPayload(input) {
    const errors = {};
    const payload = {
      list_slug: String(input.list_slug || '').trim().toLowerCase(),
      page: toPositiveInteger(input.page),
      limit: toPositiveInteger(input.limit),
      status_override: String(input.status_override || '').trim().toLowerCase(),
      sync_images: input.sync_images === '1' ? 1 : 0,
      overwrite_existing: input.overwrite_existing === '0' ? 0 : 1,
    };

    const allowedListSlugs = OPHIM_LIST_OPTIONS.map(option => option.value);

    if (payload.list_slug === '') {
      errors.list_slug = ['Field is required.'];
    } else if (!allowedListSlugs.includes(payload.list_slug)) {
      errors.list_slug = ['OPhim list slug is invalid.'];
    }

    if (!payload.page || payload.page < 1 || payload.page > 100) {
      errors.page = ['Page must be between 1 and 100.'];
    }

    if (!payload.limit || payload.limit < 1 || payload.limit > 24) {
      errors.limit = ['Limit must be between 1 and 24.'];
    }

    if (payload.status_override !== '' && !MOVIE_STATUSES.includes(payload.status_override)) {
      errors.status_override = ['Status override is invalid.'];
    }

    payload.status_override = payload.status_override || null;

    return { payload, errors };
  }

  function buildMoviePreview(movie) {
    const primaryCategory = movie.primary_category_name || getCategoryNameById(movie.primary_category_id) || 'Unassigned';
    const assignedCategories = Array.isArray(movie.category_ids) && movie.category_ids.length > 0
      ? movie.category_ids.map(getCategoryNameById).filter(Boolean)
      : (primaryCategory ? [primaryCategory] : []);

    return `
      <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="preview-banner">
          <div class="preview-banner-title">${escapeHtml(movie.title || 'Untitled Movie')}</div>
          <div class="preview-banner-copy">${escapeHtml(movie.summary || 'No summary is stored for this movie yet.')}</div>
          <div class="meta-pills">
            <span class="badge blue">${escapeHtml(primaryCategory)}</span>
            <span class="badge gray">${escapeHtml(formatMovieDuration(movie.duration_minutes))}</span>
            <span class="badge gold">${escapeHtml(formatMovieDate(movie.release_date))}</span>
            ${statusBadge(movie.status)}
          </div>
        </div>

        <div class="form-grid">
          <div class="field">
            <label>Slug</label>
            <input class="input" type="text" value="${escapeHtmlAttr(movie.slug || '')}" readonly>
          </div>
          <div class="field">
            <label>Average Rating</label>
            <input class="input" type="text" value="${escapeHtmlAttr(String(movie.average_rating ?? 0))}" readonly>
          </div>
          <div class="field">
            <label>Language</label>
            <input class="input" type="text" value="${escapeHtmlAttr(movie.language || 'N/A')}" readonly>
          </div>
          <div class="field">
            <label>Age Rating</label>
            <input class="input" type="text" value="${escapeHtmlAttr(movie.age_rating || 'N/A')}" readonly>
          </div>
          <div class="field">
            <label>Director</label>
            <input class="input" type="text" value="${escapeHtmlAttr(movie.director || 'N/A')}" readonly>
          </div>
          <div class="field">
            <label>Writer</label>
            <input class="input" type="text" value="${escapeHtmlAttr(movie.writer || 'N/A')}" readonly>
          </div>
          <div class="field">
            <label>Studio</label>
            <input class="input" type="text" value="${escapeHtmlAttr(movie.studio || 'N/A')}" readonly>
          </div>
          <div class="field">
            <label>Review Count</label>
            <input class="input" type="text" value="${escapeHtmlAttr(String(movie.review_count || 0))}" readonly>
          </div>
          <div class="field form-full">
            <label>Assigned Categories</label>
            <div class="meta-pills">
              ${assignedCategories.length > 0
                ? assignedCategories.map(category => `<span class="badge gray">${escapeHtml(category)}</span>`).join('')
                : '<span class="table-meta-text">No categories assigned.</span>'}
            </div>
          </div>
          <div class="field form-full">
            <label>Cast Summary</label>
            <textarea class="textarea" readonly>${escapeHtml(movie.cast_text || 'N/A')}</textarea>
          </div>
          <div class="field form-full">
            <label>Poster URL</label>
            <input class="input" type="text" value="${escapeHtmlAttr(movie.poster_url || 'N/A')}" readonly>
          </div>
          <div class="field form-full">
            <label>Trailer URL</label>
            <input class="input" type="text" value="${escapeHtmlAttr(movie.trailer_url || 'N/A')}" readonly>
          </div>
        </div>
      </div>
    `;
  }

  function exportCurrentMovieView() {
    if (state.items.length === 0) {
      showToast('There are no movie rows to export from the current view.', 'info');
      return;
    }

    const headers = [
      'ID',
      'Title',
      'Slug',
      'Primary Category',
      'Duration Minutes',
      'Release Date',
      'Average Rating',
      'Review Count',
      'Status',
      'Director',
      'Studio',
    ];

    const rows = state.items.map(movie => [
      movie.id,
      movie.title,
      movie.slug,
      movie.primary_category_name || getCategoryNameById(movie.primary_category_id) || '',
      movie.duration_minutes,
      movie.release_date,
      movie.average_rating,
      movie.review_count,
      movie.status,
      movie.director,
      movie.studio,
    ]);

    const csvContent = [headers, ...rows]
      .map(columns => columns.map(toCsvCell).join(','))
      .join('\r\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const exportUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const dateStamp = new Date().toISOString().slice(0, 10);

    link.href = exportUrl;
    link.download = `movie-management-page-${state.meta.page}-${dateStamp}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(exportUrl);

    showToast('Current movie page exported to CSV.', 'success');
  }

  function toCsvCell(value) {
    const normalized = String(value ?? '');
    return `"${normalized.replace(/"/g, '""')}"`;
  }

  function getCategoryNameById(categoryId) {
    const category = state.categories.find(item => Number(item.id) === Number(categoryId));
    return category?.name || '';
  }

  function getPosterCode(title) {
    return String(title || '')
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map(part => part.charAt(0))
      .join('')
      .toUpperCase() || 'MV';
  }

  function buildOphimBatchSyncMessage(summary = {}) {
    const created = Number(summary.created_count || 0);
    const updated = Number(summary.updated_count || 0);
    const skipped = Number(summary.skipped_count || 0);
    const failed = Number(summary.failed_count || 0);
    const processed = Number(summary.processed_count || 0);
    const listLabel = getOphimListLabel(summary.list_slug);

    return `${listLabel}: processed ${processed}, created ${created}, updated ${updated}, skipped ${skipped}, failed ${failed}.`;
  }

  function getOphimListLabel(value) {
    const option = OPHIM_LIST_OPTIONS.find(item => item.value === value);
    return option?.label || 'OPhim list';
  }

  function formatMovieDate(value) {
    if (!value) {
      return 'TBD';
    }

    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
      return String(value);
    }

    return new Intl.DateTimeFormat('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(date);
  }

  function toPositiveInteger(value) {
    const parsed = Number.parseInt(String(value ?? '').trim(), 10);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
  }

  function toInteger(value) {
    const parsed = Number.parseInt(String(value ?? '').trim(), 10);
    return Number.isInteger(parsed) ? parsed : null;
  }

  function isValidUrl(value) {
    try {
      const url = new URL(value);
      return url.protocol === 'http:' || url.protocol === 'https:';
    } catch (error) {
      return false;
    }
  }

  function isValidDateString(value) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
      return false;
    }

    const date = new Date(`${value}T00:00:00Z`);
    return !Number.isNaN(date.getTime()) && date.toISOString().slice(0, 10) === value;
  }

  document.addEventListener('DOMContentLoaded', initMovieManagementMovies);
})();
