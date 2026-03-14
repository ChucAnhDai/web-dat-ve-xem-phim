(function () {
  const ASSET_TYPES = ['poster', 'banner', 'gallery'];
  const ASSET_STATUSES = ['draft', 'active', 'archived'];
  const DEFAULT_SUMMARY = {
    total: 0,
    poster: 0,
    banner: 0,
    gallery: 0,
    draft: 0,
    active: 0,
    archived: 0,
  };

  const state = {
    items: [],
    movies: [],
    meta: {
      total: 0,
      page: 1,
      per_page: 10,
      total_pages: 1,
    },
    summary: { ...DEFAULT_SUMMARY },
    filters: {
      page: 1,
      per_page: 10,
      search: '',
      movie_id: '',
      asset_type: '',
      status: '',
    },
    loading: false,
    moviesLoaded: false,
    searchTimer: null,
    initialized: false,
  };

  const dom = {};

  function initMovieManagementMovieImages() {
    if (state.initialized || document.body?.dataset?.activePage !== 'movie-images') {
      return;
    }

    cacheDom();
    if (!dom.body) {
      return;
    }

    state.initialized = true;
    bindEvents();
    window.handleMovieSectionAction = () => {
      void openCreateAssetModal();
    };

    renderSummary();
    renderLoadingRow('Loading movie assets...');
    void refreshAssetData();
  }

  function cacheDom() {
    dom.total = document.getElementById('movieAssetTotalStat');
    dom.poster = document.getElementById('movieAssetPosterStat');
    dom.gallery = document.getElementById('movieAssetGalleryStat');
    dom.draft = document.getElementById('movieAssetDraftStat');
    dom.search = document.getElementById('movieAssetSearchInput');
    dom.movieFilter = document.getElementById('movieAssetMovieFilter');
    dom.typeFilter = document.getElementById('movieAssetTypeFilter');
    dom.statusFilter = document.getElementById('movieAssetStatusFilter');
    dom.count = document.getElementById('movieAssetCount');
    dom.requestStatus = document.getElementById('movieAssetRequestStatus');
    dom.exportBtn = document.getElementById('movieAssetExportBtn');
    dom.body = document.getElementById('movieAssetsBody');
    dom.pagination = document.getElementById('movieAssetsPagination');
  }

  function bindEvents() {
    dom.search?.addEventListener('input', handleSearchInput);
    dom.movieFilter?.addEventListener('change', () => {
      state.filters.movie_id = dom.movieFilter.value;
      state.filters.page = 1;
      void loadAssets();
    });
    dom.typeFilter?.addEventListener('change', () => {
      state.filters.asset_type = dom.typeFilter.value;
      state.filters.page = 1;
      void loadAssets();
    });
    dom.statusFilter?.addEventListener('change', () => {
      state.filters.status = dom.statusFilter.value;
      state.filters.page = 1;
      void loadAssets();
    });
    dom.exportBtn?.addEventListener('click', exportCurrentAssetsView);
    dom.body?.addEventListener('click', handleTableAction);
    dom.pagination?.addEventListener('click', handlePaginationAction);
  }

  async function refreshAssetData() {
    await Promise.allSettled([loadMovieOptions(), loadAssets()]);
  }

  async function loadMovieOptions() {
    updateRequestStatus('Loading movie options...');

    try {
      const movies = [];
      let page = 1;
      let totalPages = 1;

      while (page <= totalPages) {
        const response = await adminApiRequest('/api/admin/movies', {
          query: {
            page,
            per_page: 100,
          },
        });
        const payload = response?.data || {};
        const items = Array.isArray(payload.items) ? payload.items : [];
        const meta = normalizeMeta(payload.meta, { page, per_page: 100 });

        movies.push(...items.map(movie => ({
          id: Number(movie.id || 0),
          title: movie.title || 'Untitled Movie',
          slug: movie.slug || '',
          status: movie.status || '',
        })));

        totalPages = meta.total_pages;
        page += 1;
      }

      state.movies = dedupeMovies(movies);
      state.moviesLoaded = true;
      renderMovieOptions();
      updateRequestStatus('Movie options synced');
      return state.movies;
    } catch (error) {
      state.movies = [];
      state.moviesLoaded = false;
      renderMovieOptions();
      updateRequestStatus('Movie option sync failed');
      showToast(errorMessageFromException(error, 'Failed to load movie options.'), 'error');
      throw error;
    }
  }

  async function ensureMovieOptionsLoaded() {
    if (state.moviesLoaded) {
      return state.movies;
    }

    return loadMovieOptions();
  }

  async function loadAssets() {
    setLoading(true, 'Loading movie assets...');

    try {
      const response = await adminApiRequest('/api/admin/movie-assets', {
        query: buildAssetQuery(),
      });

      const payload = response?.data || {};
      state.items = Array.isArray(payload.items) ? payload.items : [];
      state.meta = normalizeMeta(payload.meta);
      state.summary = normalizeSummary(payload.summary);

      if (state.items.length === 0 && state.meta.total > 0 && state.filters.page > 1) {
        state.filters.page = Math.max(1, state.meta.total_pages);
        return loadAssets();
      }

      renderSummary();
      renderAssetTable();
      renderPagination();
      updateRequestStatus('Movie assets synced');
    } catch (error) {
      state.items = [];
      state.meta = normalizeMeta();
      state.summary = { ...DEFAULT_SUMMARY };

      renderSummary();
      renderErrorRow(errorMessageFromException(error, 'Failed to load movie assets.'));
      renderPagination();
      updateRequestStatus('Movie assets unavailable');
      showToast(errorMessageFromException(error, 'Failed to load movie assets.'), 'error');
    } finally {
      setLoading(false);
    }
  }

  function buildAssetQuery() {
    return {
      page: state.filters.page,
      per_page: state.filters.per_page,
      search: state.filters.search,
      movie_id: state.filters.movie_id,
      asset_type: state.filters.asset_type,
      status: state.filters.status,
    };
  }

  function normalizeMeta(meta = {}, fallback = {}) {
    return {
      total: Number(meta?.total || 0),
      page: Number(meta?.page || fallback.page || state.filters.page || 1),
      per_page: Number(meta?.per_page || fallback.per_page || state.filters.per_page || 10),
      total_pages: Math.max(1, Number(meta?.total_pages || 1)),
    };
  }

  function normalizeSummary(summary = {}) {
    return {
      total: Number(summary?.total || 0),
      poster: Number(summary?.poster || 0),
      banner: Number(summary?.banner || 0),
      gallery: Number(summary?.gallery || 0),
      draft: Number(summary?.draft || 0),
      active: Number(summary?.active || 0),
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
    if (dom.requestStatus) {
      dom.requestStatus.textContent = text;
    }
  }

  function renderSummary() {
    if (dom.total) dom.total.textContent = String(state.summary.total);
    if (dom.poster) dom.poster.textContent = String(state.summary.poster);
    if (dom.gallery) dom.gallery.textContent = String(state.summary.gallery);
    if (dom.draft) dom.draft.textContent = String(state.summary.draft);
  }

  function renderMovieOptions() {
    renderMovieSelect(dom.movieFilter, state.filters.movie_id, true);
  }

  function renderMovieSelect(select, selectedValue, includeAllOption) {
    if (!select) {
      return;
    }

    const options = [];
    if (includeAllOption) {
      options.push('<option value="">All Movies</option>');
    } else if (state.movies.length === 0) {
      options.push('<option value="">No movies available</option>');
    } else {
      options.push('<option value="">Select movie</option>');
    }

    options.push(...state.movies.map(movie => {
      const value = String(movie.id);
      const selected = String(selectedValue || '') === value ? ' selected' : '';
      const suffix = movie.status ? ` (${humanizeStatus(movie.status)})` : '';

      return `<option value="${escapeHtmlAttr(value)}"${selected}>${escapeHtml(movie.title || 'Untitled Movie')}${escapeHtml(suffix)}</option>`;
    }));

    select.innerHTML = options.join('');
  }

  function renderAssetTable() {
    if (!dom.body) {
      return;
    }

    const total = Number(state.meta.total || 0);
    if (dom.count) {
      dom.count.textContent = `${total} asset${total === 1 ? '' : 's'}`;
    }

    if (state.loading && state.items.length === 0) {
      renderLoadingRow('Loading movie assets...');
      return;
    }

    if (state.items.length === 0) {
      renderEmptyRow(
        'No movie assets matched the current filters.',
        'Try clearing the search, changing the filters, or add a new asset record.'
      );
      return;
    }

    dom.body.innerHTML = state.items.map(asset => buildAssetRow(asset)).join('');
  }

  function buildAssetRow(asset) {
    const preview = buildAssetThumb(asset.image_url, asset.alt_text || asset.movie_title || asset.asset_type, asset.asset_type);
    const updatedAt = formatAssetDate(asset.updated_at || asset.created_at);
    const movieLabel = asset.movie_title || getMovieNameById(asset.movie_id) || `Movie #${asset.movie_id}`;
    const movieMeta = asset.movie_slug ? `<div class="td-muted">${escapeHtml(asset.movie_slug)}</div>` : '';

    return `
      <tr>
        <td>${preview}</td>
        <td>
          <div class="td-bold">${escapeHtml(movieLabel)}</div>
          ${movieMeta}
        </td>
        <td><span class="badge gray">${escapeHtml(humanizeStatus(asset.asset_type || 'gallery'))}</span></td>
        <td class="td-muted">${escapeHtml(asset.alt_text || 'No alt text')}</td>
        <td class="td-muted">${escapeHtml(String(asset.sort_order ?? 0))}</td>
        <td><span class="badge ${Number(asset.is_primary) === 1 ? 'gold' : 'gray'}">${Number(asset.is_primary) === 1 ? 'Primary' : 'Secondary'}</span></td>
        <td>${statusBadge(asset.status || 'draft')}</td>
        <td class="td-muted">${escapeHtml(updatedAt)}</td>
        <td>
          <div class="actions-row">
            <button class="action-btn view" type="button" title="View" data-action="view" data-asset-id="${Number(asset.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="action-btn edit" type="button" title="Edit" data-action="edit" data-asset-id="${Number(asset.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="action-btn del" type="button" title="Archive" data-action="archive" data-asset-id="${Number(asset.id || 0)}" ${asset.status === 'archived' ? 'disabled' : ''}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `;
  }

  function renderLoadingRow(message) {
    if (!dom.body) {
      return;
    }

    if (dom.count) {
      dom.count.textContent = 'Loading assets...';
    }

    dom.body.innerHTML = `
      <tr class="table-empty-row">
        <td colspan="9">
          <div class="table-empty-state">
            <strong>${escapeHtml(message)}</strong>
            <div class="table-meta-text">Please wait while movie asset metadata is being synchronized.</div>
          </div>
        </td>
      </tr>
    `;
  }

  function renderEmptyRow(title, description) {
    if (!dom.body) {
      return;
    }

    dom.body.innerHTML = `
      <tr class="table-empty-row">
        <td colspan="9">
          <div class="table-empty-state">
            <strong>${escapeHtml(title)}</strong>
            <div class="table-meta-text">${escapeHtml(description)}</div>
          </div>
        </td>
      </tr>
    `;
  }

  function renderErrorRow(message) {
    renderEmptyRow('Movie asset data could not be loaded.', message);
    if (dom.count) {
      dom.count.textContent = 'Movie asset data unavailable';
    }
  }

  function renderPagination() {
    if (!dom.pagination) {
      return;
    }

    const total = Number(state.meta.total || 0);
    const page = Number(state.meta.page || 1);
    const totalPages = Math.max(1, Number(state.meta.total_pages || 1));
    const perPage = Math.max(1, Number(state.meta.per_page || 10));
    const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const end = total === 0 ? 0 : Math.min(total, start + state.items.length - 1);
    const pages = buildVisiblePages(page, totalPages);

    dom.pagination.innerHTML = `
      <div class="pagination">
        <div class="pagination-info">Showing ${start}-${end} of ${total} assets</div>
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
      state.filters.search = dom.search?.value.trim() || '';
      state.filters.page = 1;
      void loadAssets();
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
    void loadAssets();
  }

  function handleTableAction(event) {
    const button = event.target.closest('button[data-action]');
    if (!button || button.disabled) {
      return;
    }

    const assetId = Number(button.dataset.assetId || 0);
    if (!assetId) {
      showToast('Movie asset ID is missing for this action.', 'error');
      return;
    }

    const action = button.dataset.action;
    if (action === 'view') {
      void openPreviewAssetModal(assetId);
      return;
    }

    if (action === 'edit') {
      void openEditAssetModal(assetId);
      return;
    }

    if (action === 'archive') {
      void archiveAsset(assetId);
    }
  }

  async function openCreateAssetModal() {
    await ensureMovieOptionsLoaded();
    if (state.movies.length === 0) {
      showToast('Create a movie before adding image assets.', 'info');
      return;
    }

    openAssetEditorModal('Add Movie Asset', {});
  }

  async function openEditAssetModal(assetId) {
    updateRequestStatus('Loading asset details...');

    try {
      const [asset] = await Promise.all([
        fetchAssetDetail(assetId),
        ensureMovieOptionsLoaded().catch(() => []),
      ]);

      ensureMovieInOptions(asset);
      openAssetEditorModal('Edit Movie Asset', asset);
      updateRequestStatus('Asset details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load asset details');
      showToast(errorMessageFromException(error, 'Failed to load movie asset details.'), 'error');
    }
  }

  async function openPreviewAssetModal(assetId) {
    updateRequestStatus('Loading asset details...');

    try {
      const asset = await fetchAssetDetail(assetId);
      ensureMovieInOptions(asset);

      openModal('Movie Asset Details', buildAssetPreview(asset), {
        description: 'Read the schema-aligned metadata stored for this movie image asset.',
        note: 'Assets are currently managed through URL records in movie_images.',
        submitLabel: 'Edit Asset',
        cancelLabel: 'Close',
        busyLabel: 'Opening...',
        onSave: async () => {
          closeModal();
          await openEditAssetModal(asset.id);
        },
      });

      updateRequestStatus('Asset details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load asset details');
      showToast(errorMessageFromException(error, 'Failed to load movie asset details.'), 'error');
    }
  }

  async function fetchAssetDetail(assetId) {
    const response = await adminApiRequest(`/api/admin/movie-assets/${assetId}`);
    return response?.data || {};
  }

  async function archiveAsset(assetId) {
    const asset = state.items.find(item => Number(item.id) === Number(assetId));
    const assetName = asset?.movie_title
      ? `${asset.movie_title} / ${humanizeStatus(asset.asset_type || 'gallery')}`
      : `asset #${assetId}`;

    if (!window.confirm(`Archive "${assetName}"? Archived assets are removed from active rotations and lose primary status.`)) {
      return;
    }

    updateRequestStatus('Archiving asset...');

    try {
      const response = await adminApiRequest(`/api/admin/movie-assets/${assetId}`, {
        method: 'DELETE',
      });

      if (state.items.length === 1 && state.filters.page > 1) {
        state.filters.page -= 1;
      }

      await loadAssets();
      showToast(response?.message || 'Movie asset archived successfully.', 'success');
    } catch (error) {
      updateRequestStatus('Asset archive failed');
      showToast(errorMessageFromException(error, 'Failed to archive movie asset.'), 'error');
    }
  }

  function openAssetEditorModal(title, asset) {
    const isEdit = Number(asset?.id || 0) > 0;

    openModal(title, buildAssetEditorBody(asset), {
      description: isEdit
        ? 'Update metadata that maps directly to the movie_images table.'
        : 'Create a new movie image asset record linked to an existing movie.',
      note: 'This admin flow stores image URLs. File upload storage can be added later without changing the schema.',
      submitLabel: isEdit ? 'Update Asset' : 'Create Asset',
      busyLabel: isEdit ? 'Updating...' : 'Creating...',
      onSave: async () => {
        await submitAssetForm(asset?.id || null);
      },
    });

    attachAssetFormInteractions(asset);
  }

  function buildAssetEditorBody(asset = {}) {
    return `
      <form id="movieAssetForm" class="form-grid" novalidate>
        <div class="form-alert" data-form-alert hidden></div>

        <div class="field form-full">
          <label for="movieAssetMovieInput">Movie</label>
          <select class="select" id="movieAssetMovieInput" data-field-control="movie_id" name="movie_id">
            ${buildMovieOptions(asset.movie_id)}
          </select>
          <div class="helper-text">Choose the movie that owns this poster, banner, or gallery image.</div>
          <div class="field-error" data-field-error="movie_id" hidden></div>
        </div>

        <div class="field">
          <label for="movieAssetTypeInput">Asset Type</label>
          <select class="select" id="movieAssetTypeInput" data-field-control="asset_type" name="asset_type">
            ${buildOptions([
              { value: 'poster', label: 'Poster' },
              { value: 'banner', label: 'Banner' },
              { value: 'gallery', label: 'Gallery' },
            ], asset.asset_type || 'poster')}
          </select>
          <div class="field-error" data-field-error="asset_type" hidden></div>
        </div>

        <div class="field">
          <label for="movieAssetStatusInput">Status</label>
          <select class="select" id="movieAssetStatusInput" data-field-control="status" name="status">
            ${buildOptions([
              { value: 'draft', label: 'Draft' },
              { value: 'active', label: 'Active' },
              { value: 'archived', label: 'Archived' },
            ], asset.status || 'draft')}
          </select>
          <div class="field-error" data-field-error="status" hidden></div>
        </div>

        <div class="field">
          <label for="movieAssetPrimaryInput">Primary Asset</label>
          <select class="select" id="movieAssetPrimaryInput" data-field-control="is_primary" name="is_primary">
            ${buildOptions([
              { value: '1', label: 'Yes' },
              { value: '0', label: 'No' },
            ], Number(asset.is_primary) === 1 ? '1' : '0')}
          </select>
          <div class="helper-text">Only one asset per movie and asset type can remain primary.</div>
          <div class="field-error" data-field-error="is_primary" hidden></div>
        </div>

        <div class="field">
          <label for="movieAssetSortOrderInput">Sort Order</label>
          <input class="input" id="movieAssetSortOrderInput" data-field-control="sort_order" name="sort_order" type="number" min="0" placeholder="0" value="${escapeHtmlAttr(asset.sort_order ?? 0)}">
          <div class="field-error" data-field-error="sort_order" hidden></div>
        </div>

        <div class="field form-full">
          <label for="movieAssetImageUrlInput">Image URL</label>
          <input class="input" id="movieAssetImageUrlInput" data-field-control="image_url" name="image_url" type="url" placeholder="https://cdn.example.com/movies/demo/poster.jpg" value="${escapeHtmlAttr(asset.image_url || '')}">
          <div class="helper-text">Use a secure HTTP(S) URL that points to the stored image asset.</div>
          <div class="field-error" data-field-error="image_url" hidden></div>
        </div>

        <div class="field form-full">
          <label for="movieAssetAltTextInput">Alt Text</label>
          <input class="input" id="movieAssetAltTextInput" data-field-control="alt_text" name="alt_text" type="text" placeholder="Describe the artwork for accessibility" value="${escapeHtmlAttr(asset.alt_text || '')}">
          <div class="helper-text">Alt text improves accessibility and helps editors recognize the asset later.</div>
          <div class="field-error" data-field-error="alt_text" hidden></div>
        </div>
      </form>
    `;
  }

  function attachAssetFormInteractions(asset) {
    const form = document.getElementById('movieAssetForm');
    if (!form) {
      return;
    }

    const statusInput = form.querySelector('[name="status"]');
    const primaryInput = form.querySelector('[name="is_primary"]');

    function syncPrimaryState() {
      if (!statusInput || !primaryInput) {
        return;
      }

      const isArchived = statusInput.value === 'archived';
      if (isArchived) {
        primaryInput.value = '0';
      }
      primaryInput.disabled = isArchived;
      primaryInput.title = isArchived ? 'Archived assets cannot be primary.' : '';
    }

    statusInput?.addEventListener('change', syncPrimaryState);
    syncPrimaryState();

    if (!asset?.movie_id) {
      const movieSelect = form.querySelector('[name="movie_id"]');
      if (movieSelect) {
        renderMovieSelect(movieSelect, '', false);
      }
    }
  }

  async function submitAssetForm(assetId) {
    const form = document.getElementById('movieAssetForm');
    if (!form) {
      throw new Error('Movie asset form is unavailable.');
    }

    const { payload, errors } = validateAssetPayload(collectAssetFormPayload(form));
    if (Object.keys(errors).length > 0) {
      applyFormErrors(form, errors);
      return;
    }

    clearFormErrors(form);

    try {
      const response = await adminApiRequest(assetId ? `/api/admin/movie-assets/${assetId}` : '/api/admin/movie-assets', {
        method: assetId ? 'PUT' : 'POST',
        body: payload,
      });

      closeModal();
      if (!assetId) {
        state.filters.page = 1;
      }

      await loadAssets();
      showToast(response?.message || (assetId ? 'Movie asset updated successfully.' : 'Movie asset created successfully.'), 'success');
    } catch (error) {
      if (error instanceof AdminApiError && (error.status === 404 || error.status === 409 || error.status === 422)) {
        applyFormErrors(form, error.errors);
        return;
      }

      throw error;
    }
  }

  function collectAssetFormPayload(form) {
    return {
      movie_id: form.querySelector('[name="movie_id"]')?.value || '',
      asset_type: form.querySelector('[name="asset_type"]')?.value || '',
      image_url: form.querySelector('[name="image_url"]')?.value || '',
      alt_text: form.querySelector('[name="alt_text"]')?.value || '',
      sort_order: form.querySelector('[name="sort_order"]')?.value || '',
      is_primary: form.querySelector('[name="is_primary"]')?.value || '0',
      status: form.querySelector('[name="status"]')?.value || 'draft',
    };
  }

  function validateAssetPayload(input) {
    const errors = {};
    const payload = {
      movie_id: toPositiveInteger(input.movie_id),
      asset_type: String(input.asset_type || '').trim().toLowerCase(),
      image_url: String(input.image_url || '').trim(),
      alt_text: String(input.alt_text || '').trim(),
      sort_order: toNonNegativeInteger(input.sort_order),
      is_primary: toBoolInteger(input.is_primary),
      status: String(input.status || '').trim().toLowerCase(),
    };

    if (payload.movie_id === null) {
      errors.movie_id = ['Movie is required.'];
    }
    if (!ASSET_TYPES.includes(payload.asset_type)) {
      errors.asset_type = ['Asset type is invalid.'];
    }
    if (payload.image_url === '') {
      errors.image_url = ['Image URL is required.'];
    } else if (!isValidHttpUrl(payload.image_url)) {
      errors.image_url = ['Image URL must be a valid HTTP(S) URL.'];
    }
    if (payload.sort_order === null) {
      errors.sort_order = ['Sort order must be a non-negative integer.'];
    }
    if (payload.is_primary === null) {
      errors.is_primary = ['Primary flag is invalid.'];
    }
    if (!ASSET_STATUSES.includes(payload.status)) {
      errors.status = ['Asset status is invalid.'];
    }

    if (payload.status === 'archived') {
      payload.is_primary = 0;
    }

    payload.alt_text = payload.alt_text || null;

    return { payload, errors };
  }

  function buildAssetPreview(asset) {
    const updatedAt = formatAssetDate(asset.updated_at || asset.created_at);
    const movieLabel = asset.movie_title || getMovieNameById(asset.movie_id) || `Movie #${asset.movie_id}`;
    const previewImage = asset.image_url
      ? `<img src="${escapeHtmlAttr(asset.image_url)}" alt="${escapeHtmlAttr(asset.alt_text || movieLabel)}" style="width:100%;max-width:280px;border-radius:12px;border:1px solid var(--border);background:var(--bg3);object-fit:cover;">`
      : '<div class="poster-img-placeholder" style="width:100%;max-width:280px;height:180px;">IMG</div>';

    return `
      <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="preview-banner">
          <div class="preview-banner-title">${escapeHtml(movieLabel)}</div>
          <div class="preview-banner-copy">${escapeHtml(asset.alt_text || 'No alt text has been stored for this asset yet.')}</div>
          <div class="meta-pills">
            <span class="badge blue">${escapeHtml(humanizeStatus(asset.asset_type || 'gallery'))}</span>
            <span class="badge ${Number(asset.is_primary) === 1 ? 'gold' : 'gray'}">${Number(asset.is_primary) === 1 ? 'Primary' : 'Secondary'}</span>
            ${statusBadge(asset.status || 'draft')}
          </div>
        </div>

        <div style="display:flex;justify-content:center;">${previewImage}</div>

        <div class="form-grid">
          <div class="field">
            <label>Image URL</label>
            <input class="input" type="text" value="${escapeHtmlAttr(asset.image_url || 'N/A')}" readonly>
          </div>
          <div class="field">
            <label>Updated</label>
            <input class="input" type="text" value="${escapeHtmlAttr(updatedAt)}" readonly>
          </div>
          <div class="field">
            <label>Sort Order</label>
            <input class="input" type="text" value="${escapeHtmlAttr(String(asset.sort_order ?? 0))}" readonly>
          </div>
          <div class="field">
            <label>Movie Slug</label>
            <input class="input" type="text" value="${escapeHtmlAttr(asset.movie_slug || 'N/A')}" readonly>
          </div>
          <div class="field form-full">
            <label>Alt Text</label>
            <textarea class="textarea" readonly>${escapeHtml(asset.alt_text || 'N/A')}</textarea>
          </div>
        </div>
      </div>
    `;
  }

  function exportCurrentAssetsView() {
    if (state.items.length === 0) {
      showToast('There are no movie assets to export from the current view.', 'info');
      return;
    }

    const headers = [
      'ID',
      'Movie ID',
      'Movie Title',
      'Movie Slug',
      'Asset Type',
      'Image URL',
      'Alt Text',
      'Sort Order',
      'Is Primary',
      'Status',
      'Updated At',
    ];

    const rows = state.items.map(asset => [
      asset.id,
      asset.movie_id,
      asset.movie_title || '',
      asset.movie_slug || '',
      asset.asset_type || '',
      asset.image_url || '',
      asset.alt_text || '',
      asset.sort_order,
      Number(asset.is_primary) === 1 ? 'yes' : 'no',
      asset.status || '',
      asset.updated_at || asset.created_at || '',
    ]);

    const csvContent = [headers, ...rows]
      .map(columns => columns.map(toCsvCell).join(','))
      .join('\r\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const exportUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const dateStamp = new Date().toISOString().slice(0, 10);

    link.href = exportUrl;
    link.download = `movie-assets-page-${state.meta.page}-${dateStamp}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(exportUrl);

    showToast('Current movie asset page exported to CSV.', 'success');
  }

  function dedupeMovies(movies) {
    const unique = new Map();

    movies.forEach(movie => {
      const id = Number(movie.id || 0);
      if (!id) {
        return;
      }

      unique.set(id, {
        id,
        title: movie.title || 'Untitled Movie',
        slug: movie.slug || '',
        status: movie.status || '',
      });
    });

    return Array.from(unique.values()).sort((left, right) => {
      const leftTitle = String(left.title || '').toLowerCase();
      const rightTitle = String(right.title || '').toLowerCase();
      return leftTitle.localeCompare(rightTitle);
    });
  }

  function ensureMovieInOptions(asset) {
    const movieId = Number(asset?.movie_id || 0);
    if (!movieId) {
      return;
    }

    if (state.movies.some(movie => Number(movie.id) === movieId)) {
      return;
    }

    state.movies = dedupeMovies([
      ...state.movies,
      {
        id: movieId,
        title: asset.movie_title || `Movie #${movieId}`,
        slug: asset.movie_slug || '',
        status: '',
      },
    ]);
    renderMovieOptions();
  }

  function buildMovieOptions(selectedMovieId) {
    const select = document.createElement('select');
    renderMovieSelect(select, selectedMovieId, false);
    return select.innerHTML;
  }

  function buildAssetThumb(imageUrl, altText, assetType) {
    if (imageUrl) {
      return `<img class="poster-thumb" src="${escapeHtmlAttr(imageUrl)}" alt="${escapeHtmlAttr(altText || assetType || 'Movie asset')}" loading="lazy">`;
    }

    return `<div class="poster-img-placeholder">${escapeHtml(getAssetCode(assetType))}</div>`;
  }

  function getAssetCode(assetType) {
    if (assetType === 'poster') return 'PS';
    if (assetType === 'banner') return 'BN';
    return 'GL';
  }

  function getMovieNameById(movieId) {
    const movie = state.movies.find(item => Number(item.id) === Number(movieId));
    return movie?.title || '';
  }

  function formatAssetDate(value) {
    if (!value) {
      return 'N/A';
    }

    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
      return String(value);
    }

    return new Intl.DateTimeFormat('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(date);
  }

  function toCsvCell(value) {
    const normalized = String(value ?? '');
    return `"${normalized.replace(/"/g, '""')}"`;
  }

  function toPositiveInteger(value) {
    const parsed = Number.parseInt(String(value ?? '').trim(), 10);
    if (!Number.isInteger(parsed) || parsed < 1) {
      return null;
    }

    return parsed;
  }

  function toNonNegativeInteger(value) {
    const parsed = Number.parseInt(String(value ?? '').trim(), 10);
    if (!Number.isInteger(parsed) || parsed < 0) {
      return null;
    }

    return parsed;
  }

  function toBoolInteger(value) {
    const normalized = String(value ?? '').trim().toLowerCase();
    if (['1', 'true', 'yes', 'on'].includes(normalized)) {
      return 1;
    }
    if (['0', 'false', 'no', 'off'].includes(normalized)) {
      return 0;
    }

    return null;
  }

  function isValidHttpUrl(value) {
    try {
      const url = new URL(String(value || '').trim());
      return ['http:', 'https:'].includes(url.protocol);
    } catch (error) {
      return false;
    }
  }

  document.addEventListener('DOMContentLoaded', initMovieManagementMovieImages);
})();
