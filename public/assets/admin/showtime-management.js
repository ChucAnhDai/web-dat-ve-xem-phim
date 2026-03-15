(function () {
  const SHOWTIME_STATUSES = ['draft', 'published', 'cancelled', 'completed', 'archived'];
  const PRESENTATION_TYPES = [
    { value: '2d', label: '2D' },
    { value: '3d', label: '3D' },
    { value: 'imax', label: 'IMAX' },
    { value: '4dx', label: '4DX' },
    { value: 'screenx', label: 'ScreenX' },
    { value: 'dolby_atmos', label: 'Dolby Atmos' },
  ];
  const LANGUAGE_VERSIONS = [
    { value: 'original', label: 'Original' },
    { value: 'subtitled', label: 'Subtitled' },
    { value: 'dubbed', label: 'Dubbed' },
  ];
  const ROOM_TYPE_LOOKUP = {
    standard_2d: { label: 'Standard 2D' },
    premium_3d: { label: 'Premium 3D' },
    vip_recliner: { label: 'VIP Recliner' },
    imax: { label: 'IMAX' },
    '4dx': { label: '4DX' },
    screenx: { label: 'ScreenX' },
    dolby_atmos: { label: 'Dolby Atmos' },
  };

  const state = {
    items: [],
    movies: [],
    cinemas: [],
    rooms: [],
    meta: { total: 0, page: 1, per_page: 20, total_pages: 1 },
    summary: { total: 0, published: 0, today: 0, sold_out: 0 },
    filters: {
      page: 1,
      per_page: 20,
      search: '',
      movie_id: '',
      cinema_id: '',
      status: '',
      show_date: '',
      scope: 'active',
    },
    loading: false,
    optionsLoaded: false,
    searchTimer: null,
    initialized: false,
  };

  const dom = {};

  function initShowtimeManagementPage() {
    if (state.initialized || document.body?.dataset?.activePage !== 'showtimes') {
      return;
    }

    cacheDom();
    if (!dom.body) {
      return;
    }

    state.initialized = true;
    bindEvents();

    renderStats();
    renderLoadingRow('Loading showtime catalog...');
    loadBootstrapData();
  }

  function cacheDom() {
    dom.totalStat = document.getElementById('showtimeTotalStat');
    dom.publishedStat = document.getElementById('showtimePublishedStat');
    dom.todayStat = document.getElementById('showtimeTodayStat');
    dom.soldOutStat = document.getElementById('showtimeSoldOutStat');
    dom.searchInput = document.getElementById('showtimeSearchInput');
    dom.movieFilter = document.getElementById('showtimeMovieFilter');
    dom.cinemaFilter = document.getElementById('showtimeCinemaFilter');
    dom.statusFilter = document.getElementById('showtimeStatusFilter');
    dom.dateFilter = document.getElementById('showtimeDateFilter');
    dom.scopeActiveBtn = document.getElementById('showtimeScopeActiveBtn');
    dom.scopeArchivedBtn = document.getElementById('showtimeScopeArchivedBtn');
    dom.count = document.getElementById('showtimeCount');
    dom.requestStatus = document.getElementById('showtimeRequestStatus');
    dom.createBtn = document.getElementById('showtimeCreateBtn');
    dom.body = document.getElementById('showtimesBody');
    dom.pagination = document.getElementById('showtimesPagination');
  }

  function bindEvents() {
    dom.searchInput?.addEventListener('input', handleSearchInput);
    dom.movieFilter?.addEventListener('change', () => {
      state.filters.movie_id = dom.movieFilter.value;
      state.filters.page = 1;
      loadShowtimes();
    });
    dom.cinemaFilter?.addEventListener('change', () => {
      state.filters.cinema_id = dom.cinemaFilter.value;
      state.filters.page = 1;
      loadShowtimes();
    });
    dom.statusFilter?.addEventListener('change', () => {
      state.filters.status = dom.statusFilter.value;
      state.filters.page = 1;
      loadShowtimes();
    });
    dom.dateFilter?.addEventListener('change', () => {
      state.filters.show_date = dom.dateFilter.value;
      state.filters.page = 1;
      loadShowtimes();
    });
    dom.scopeActiveBtn?.addEventListener('click', () => switchScope('active'));
    dom.scopeArchivedBtn?.addEventListener('click', () => switchScope('archived'));
    dom.createBtn?.addEventListener('click', openCreateShowtimeModal);
    dom.body?.addEventListener('click', handleTableAction);
    dom.pagination?.addEventListener('click', handlePaginationAction);
  }

  async function loadBootstrapData() {
    renderScopeControls();
    syncStatusFilter();
    updateRequestStatus(scopeMessage('Loading movie, cinema, and room options...', 'Loading archive filters and showtime history...'));

    try {
      await Promise.all([loadOptions(), loadShowtimes()]);
      updateRequestStatus(scopeMessage('Showtime data synced', 'Archived showtime data synced'));
    } catch (error) {
      showToast(errorMessageFromException(error, 'Failed to load showtime management data.'), 'error');
    }
  }

  async function loadOptions() {
    const optionScope = state.filters.scope === 'archived' ? 'all' : 'active';
    const [moviesResponse, cinemasResponse, roomsResponse] = await Promise.all([
      adminApiRequest('/api/admin/movies', {
        query: { page: 1, per_page: 200 },
      }),
      adminApiRequest('/api/admin/cinemas', {
        query: { page: 1, per_page: 200, scope: optionScope },
      }),
      adminApiRequest('/api/admin/rooms', {
        query: { page: 1, per_page: 200, scope: optionScope },
      }),
    ]);

    state.movies = (Array.isArray(moviesResponse?.data?.items) ? moviesResponse.data.items : [])
      .filter(movie => state.filters.scope === 'archived' || String(movie?.status || '').toLowerCase() !== 'archived');
    state.cinemas = Array.isArray(cinemasResponse?.data?.items) ? cinemasResponse.data.items : [];
    state.rooms = Array.isArray(roomsResponse?.data?.items) ? roomsResponse.data.items : [];
    state.optionsLoaded = true;

    renderMovieOptions();
    renderCinemaOptions();
  }

  async function loadShowtimes() {
    renderScopeControls();
    syncStatusFilter();
    setLoading(true, scopeMessage('Loading showtime data...', 'Loading archived showtimes...'));

    try {
      const response = await adminApiRequest('/api/admin/showtimes', {
        query: {
          page: state.filters.page,
          per_page: state.filters.per_page,
          search: state.filters.search,
          movie_id: state.filters.movie_id,
          cinema_id: state.filters.cinema_id,
          status: state.filters.status,
          show_date: state.filters.show_date,
          scope: state.filters.scope,
        },
      });

      const payload = response?.data || {};
      state.items = Array.isArray(payload.items) ? payload.items : [];
      state.meta = normalizeMeta(payload.meta);
      state.summary = normalizeSummary(payload.summary);

      renderStats();
      renderScopeControls();
      syncStatusFilter();
      renderTable();
      renderPagination();
      updateRequestStatus(scopeMessage('Showtime data synced', 'Archived showtime data synced'));
    } catch (error) {
      state.items = [];
      state.meta = normalizeMeta();
      state.summary = normalizeSummary();

      renderStats();
      renderScopeControls();
      syncStatusFilter();
      renderErrorRow(errorMessageFromException(error, 'Failed to load showtimes.'));
      renderPagination();
      updateRequestStatus(scopeMessage('Showtime data unavailable', 'Archived showtime data unavailable'));
      showToast(errorMessageFromException(error, 'Failed to load showtimes.'), 'error');
    } finally {
      setLoading(false);
    }
  }

  function renderStats() {
    if (dom.totalStat) dom.totalStat.textContent = String(state.summary.total);
    if (dom.publishedStat) dom.publishedStat.textContent = String(state.summary.published);
    if (dom.todayStat) dom.todayStat.textContent = String(state.summary.today);
    if (dom.soldOutStat) dom.soldOutStat.textContent = String(state.summary.sold_out);
  }

  function renderMovieOptions() {
    if (!dom.movieFilter) {
      return;
    }

    const currentValue = String(state.filters.movie_id || '');
    const options = ['<option value="">All Movies</option>'];

    state.movies.forEach(movie => {
      const value = String(movie.id || '');
      const selected = value === currentValue ? ' selected' : '';
      options.push(`<option value="${escapeHtmlAttr(value)}"${selected}>${escapeHtml(movie.title || 'Movie')}</option>`);
    });

    dom.movieFilter.innerHTML = options.join('');
  }

  function renderCinemaOptions() {
    if (!dom.cinemaFilter) {
      return;
    }

    const currentValue = String(state.filters.cinema_id || '');
    const options = ['<option value="">All Cinemas</option>'];

    state.cinemas.forEach(cinema => {
      const value = String(cinema.id || '');
      const label = cinema.city ? `${cinema.name} - ${cinema.city}` : cinema.name;
      const selected = value === currentValue ? ' selected' : '';
      options.push(`<option value="${escapeHtmlAttr(value)}"${selected}>${escapeHtml(label || 'Cinema')}</option>`);
    });

    dom.cinemaFilter.innerHTML = options.join('');
  }

  function renderTable() {
    if (!dom.body) {
      return;
    }

    const total = Number(state.meta.total || 0);
    if (dom.count) {
      dom.count.textContent = `${total} ${state.filters.scope === 'archived' ? 'archived showtime' : 'showtime'}${total === 1 ? '' : 's'}`;
    }

    if (state.loading && state.items.length === 0) {
      renderLoadingRow(scopeMessage('Loading showtime catalog...', 'Loading archived showtimes...'));
      return;
    }

    if (state.items.length === 0) {
      renderEmptyRow(...emptyStateCopy());
      return;
    }

    dom.body.innerHTML = state.items.map(showtime => `
      <tr>
        <td>
          <div class="td-bold">${escapeHtml(showtime.movie_title || 'Untitled movie')}</div>
          <div class="table-meta-text">${escapeHtml(presentationLabel(showtime.presentation_type))} - ${escapeHtml(languageLabel(showtime.language_version))}</div>
        </td>
        <td class="td-muted">
          ${escapeHtml(showtime.cinema_name || 'Cinema')}
          <div class="table-meta-text">${escapeHtml(showtime.cinema_city || '-')}</div>
        </td>
        <td class="td-muted">
          ${escapeHtml(showtime.room_name || 'Room')}
          <div class="table-meta-text">${escapeHtml(roomTypeLabel(showtime.room_type))}</div>
        </td>
        <td>${escapeHtml(formatDate(showtime.show_date))}</td>
        <td style="font-weight:700;">${escapeHtml(formatTimeRange(showtime.start_time, showtime.end_time))}</td>
        <td style="color:var(--gold);font-weight:700;">${escapeHtml(formatCurrency(showtime.price))}</td>
        <td class="td-muted">
          ${escapeHtml(`${Number(showtime.booked_seats || 0)}/${Number(showtime.total_seats || 0)}`)}
          <div class="table-meta-text">${escapeHtml(`${Number(showtime.available_seats || 0)} available`)}</div>
        </td>
        <td>${statusBadge(showtime.status)}</td>
        <td>${availabilityBadge(showtime.availability_label, showtime.is_sold_out, showtime.status)}</td>
        <td>${buildActionButtons(showtime)}</td>
      </tr>
    `).join('');
  }

  function renderLoadingRow(message) {
    if (!dom.body) {
      return;
    }

    dom.body.innerHTML = `
      <tr class="table-empty-row">
        <td colspan="10">
          <div class="table-empty-state">
            <strong>${escapeHtml(message)}</strong>
            <div class="table-meta-text">${escapeHtml(scopeMessage('Please wait while showtime data is synchronized.', 'Please wait while archived showtime records are loaded.'))}</div>
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
        <td colspan="10">
          <div class="table-empty-state">
            <strong>${escapeHtml(title)}</strong>
            <div class="table-meta-text">${escapeHtml(description)}</div>
          </div>
        </td>
      </tr>
    `;
  }

  function renderErrorRow(message) {
    renderEmptyRow('Showtime data could not be loaded.', message);
    if (dom.count) {
      dom.count.textContent = 'Showtime data unavailable';
    }
  }

  function renderPagination() {
    if (!dom.pagination) {
      return;
    }

    const total = Number(state.meta.total || 0);
    const page = Number(state.meta.page || 1);
    const totalPages = Math.max(1, Number(state.meta.total_pages || 1));
    const perPage = Math.max(1, Number(state.meta.per_page || 20));
    const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const end = total === 0 ? 0 : Math.min(total, start + state.items.length - 1);
    const pages = buildVisiblePages(page, totalPages);

    dom.pagination.innerHTML = `
      <div class="pagination">
        <div class="pagination-info">Showing ${start}-${end} of ${total} showtimes</div>
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

  function handleSearchInput() {
    window.clearTimeout(state.searchTimer);
    state.searchTimer = window.setTimeout(() => {
      state.filters.search = dom.searchInput?.value.trim() || '';
      state.filters.page = 1;
      loadShowtimes();
    }, 250);
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
    loadShowtimes();
  }

  function handleTableAction(event) {
    const button = event.target.closest('button[data-action]');
    if (!button) {
      return;
    }

    const showtimeId = Number(button.dataset.showtimeId || 0);
    if (!showtimeId) {
      showToast('Showtime ID is missing for this action.', 'error');
      return;
    }

    const action = button.dataset.action;
    if (action === 'view') {
      openPreviewShowtimeModal(showtimeId);
      return;
    }
    if (action === 'edit') {
      openEditShowtimeModal(showtimeId);
      return;
    }
    if (action === 'archive') {
      archiveShowtime(showtimeId);
    }
  }

  function openCreateShowtimeModal() {
    if (!state.optionsLoaded) {
      showToast('Please wait for movie, cinema, and room options to finish loading.', 'warning');
      return;
    }

    openShowtimeEditorModal('Add Showtime', {});
  }

  async function openEditShowtimeModal(showtimeId) {
    updateRequestStatus('Loading showtime details...');

    try {
      const showtime = await fetchShowtimeDetail(showtimeId);
      openShowtimeEditorModal('Edit Showtime', showtime);
      updateRequestStatus('Showtime details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load showtime details');
      showToast(errorMessageFromException(error, 'Failed to load showtime details.'), 'error');
    }
  }

  async function openPreviewShowtimeModal(showtimeId) {
    updateRequestStatus('Loading showtime details...');

    try {
      const showtime = await fetchShowtimeDetail(showtimeId);
      openModal('Showtime Details', buildShowtimePreview(showtime), {
        description: 'Review the persisted screening payload, including server-calculated availability fields.',
        note: 'End time is calculated server-side from movie duration and room cleaning buffer.',
        submitLabel: 'Edit Showtime',
        cancelLabel: 'Close',
        busyLabel: 'Opening...',
        onSave: async () => {
          closeModal();
          await openEditShowtimeModal(showtimeId);
        },
      });
      updateRequestStatus('Showtime details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load showtime details');
      showToast(errorMessageFromException(error, 'Failed to load showtime details.'), 'error');
    }
  }

  function openShowtimeEditorModal(title, showtime) {
    openModal(title, buildShowtimeForm(showtime), {
      description: /^Edit/i.test(title)
        ? 'Update screening status, room assignment, date, and ticket price. Overlap validation is enforced on save.'
        : 'Create a new screening. End time is calculated automatically by the backend.',
      note: 'Published showtimes require an active room and an active cinema.',
      submitLabel: /^Edit/i.test(title) ? 'Update Showtime' : 'Create Showtime',
      busyLabel: /^Edit/i.test(title) ? 'Updating...' : 'Creating...',
      onSave: async () => {
        await submitShowtimeForm(showtime?.id || null);
      },
    });

    const form = document.getElementById('showtimeManagementForm');
    form?.querySelector('[name="cinema_id"]')?.addEventListener('change', () => syncRoomOptions(form));
    syncRoomOptions(form, showtime?.room_id || null);
  }

  async function submitShowtimeForm(showtimeId) {
    const form = document.getElementById('showtimeManagementForm');
    if (!form) {
      throw new Error('Showtime form is unavailable.');
    }

    const { payload, errors } = validateShowtimePayload(collectShowtimePayload(form));
    if (Object.keys(errors).length > 0) {
      applyFormErrors(form, errors);
      return;
    }

    clearFormErrors(form);

    try {
      const response = await adminApiRequest(showtimeId ? `/api/admin/showtimes/${showtimeId}` : '/api/admin/showtimes', {
        method: showtimeId ? 'PUT' : 'POST',
        body: payload,
      });

      closeModal();
      if (!showtimeId) {
        state.filters.page = 1;
      }
      await loadShowtimes();
      showToast(response?.message || (showtimeId ? 'Showtime updated successfully.' : 'Showtime created successfully.'), 'success');
    } catch (error) {
      if (error instanceof AdminApiError && [404, 409, 422].includes(error.status)) {
        applyFormErrors(form, error.errors);
        return;
      }

      throw error;
    }
  }

  async function archiveShowtime(showtimeId) {
    const showtime = state.items.find(item => Number(item.id) === Number(showtimeId));
    const movieTitle = showtime?.movie_title || `showtime #${showtimeId}`;

    if (!window.confirm(`Archive the showtime for "${movieTitle}"? The showtime will remain in the database with archived status.`)) {
      return;
    }

    updateRequestStatus('Archiving showtime...');

    try {
      const response = await adminApiRequest(`/api/admin/showtimes/${showtimeId}`, {
        method: 'DELETE',
      });

      if (state.items.length === 1 && state.filters.page > 1) {
        state.filters.page -= 1;
      }

      await loadShowtimes();
      showToast(response?.message || 'Showtime archived successfully.', 'success');
    } catch (error) {
      updateRequestStatus('Showtime archive failed');
      showToast(errorMessageFromException(error, 'Failed to archive showtime.'), 'error');
    }
  }

  async function fetchShowtimeDetail(showtimeId) {
    const response = await adminApiRequest(`/api/admin/showtimes/${showtimeId}`);
    return response?.data || {};
  }

  function buildShowtimeForm(showtime) {
    const value = showtime || {};
    const cinemaId = value.cinema_id || resolveCinemaIdFromRoom(value.room_id) || '';

    return `
      <form id="showtimeManagementForm" class="form-grid" novalidate>
        <div class="form-alert" data-form-alert hidden></div>
        <div class="field">
          <label>Movie</label>
          <select class="select" name="movie_id" data-field-control="movie_id">
            ${buildOptions(state.movies.map(movie => ({ value: movie.id, label: movie.title })), value.movie_id || '')}
          </select>
          <div class="field-error" data-field-error="movie_id" hidden></div>
        </div>
        <div class="field">
          <label>Cinema</label>
          <select class="select" name="cinema_id" data-field-control="cinema_id">
            ${buildOptions(state.cinemas.map(cinema => ({
              value: cinema.id,
              label: cinema.city ? `${cinema.name} - ${cinema.city}` : cinema.name,
            })), cinemaId)}
          </select>
          <div class="field-error" data-field-error="cinema_id" hidden></div>
        </div>
        <div class="field">
          <label>Room</label>
          <select class="select" name="room_id" data-field-control="room_id"></select>
          <div class="field-error" data-field-error="room_id" hidden></div>
        </div>
        <div class="field">
          <label>Status</label>
          <select class="select" name="status" data-field-control="status">
            ${buildOptions([
              { value: 'draft', label: 'Draft' },
              { value: 'published', label: 'Published' },
              { value: 'cancelled', label: 'Cancelled' },
              { value: 'completed', label: 'Completed' },
              { value: 'archived', label: 'Archived' },
            ], value.status || 'draft')}
          </select>
          <div class="field-error" data-field-error="status" hidden></div>
        </div>
        <div class="field">
          <label>Show Date</label>
          <input class="input" name="show_date" data-field-control="show_date" type="date" value="${escapeHtmlAttr(value.show_date || '')}">
          <div class="field-error" data-field-error="show_date" hidden></div>
        </div>
        <div class="field">
          <label>Start Time</label>
          <input class="input" name="start_time" data-field-control="start_time" type="time" value="${escapeHtmlAttr(trimTimeValue(value.start_time))}">
          <div class="field-error" data-field-error="start_time" hidden></div>
        </div>
        <div class="field">
          <label>Ticket Price</label>
          <input class="input" name="price" data-field-control="price" type="number" min="0.01" step="0.01" value="${escapeHtmlAttr(toInputValue(value.price))}">
          <div class="field-error" data-field-error="price" hidden></div>
        </div>
        <div class="field">
          <label>Projected End Time</label>
          <input class="input" type="text" value="${escapeHtmlAttr(trimTimeValue(value.end_time) || 'Calculated on save')}" readonly>
        </div>
        <div class="field">
          <label>Presentation Type</label>
          <select class="select" name="presentation_type" data-field-control="presentation_type">
            ${buildOptions(PRESENTATION_TYPES, value.presentation_type || '2d')}
          </select>
          <div class="field-error" data-field-error="presentation_type" hidden></div>
        </div>
        <div class="field">
          <label>Language Version</label>
          <select class="select" name="language_version" data-field-control="language_version">
            ${buildOptions(LANGUAGE_VERSIONS, value.language_version || 'original')}
          </select>
          <div class="field-error" data-field-error="language_version" hidden></div>
        </div>
      </form>
    `;
  }

  function buildShowtimePreview(showtime) {
    return `
      <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="preview-banner">
          <div class="preview-banner-title">${escapeHtml(showtime.movie_title || 'Untitled movie')}</div>
          <div class="preview-banner-copy">${escapeHtml([showtime.cinema_name, showtime.room_name].filter(Boolean).join(' - '))}</div>
          <div class="meta-pills">
            ${statusBadge(showtime.status)}
            ${availabilityBadge(showtime.availability_label, showtime.is_sold_out, showtime.status)}
            <span class="badge blue">${escapeHtml(formatDate(showtime.show_date))}</span>
            <span class="badge gold">${escapeHtml(formatTimeRange(showtime.start_time, showtime.end_time))}</span>
          </div>
        </div>
        <div class="form-grid">
          <div class="field">
            <label>Presentation</label>
            <input class="input" type="text" value="${escapeHtmlAttr(presentationLabel(showtime.presentation_type))}" readonly>
          </div>
          <div class="field">
            <label>Language</label>
            <input class="input" type="text" value="${escapeHtmlAttr(languageLabel(showtime.language_version))}" readonly>
          </div>
          <div class="field">
            <label>Price</label>
            <input class="input" type="text" value="${escapeHtmlAttr(formatCurrency(showtime.price))}" readonly>
          </div>
          <div class="field">
            <label>Seats</label>
            <input class="input" type="text" value="${escapeHtmlAttr(`${Number(showtime.booked_seats || 0)}/${Number(showtime.total_seats || 0)} booked`)}" readonly>
          </div>
        </div>
      </div>
    `;
  }

  function collectShowtimePayload(form) {
    return {
      movie_id: form.querySelector('[name="movie_id"]')?.value || '',
      cinema_id: form.querySelector('[name="cinema_id"]')?.value || '',
      room_id: form.querySelector('[name="room_id"]')?.value || '',
      show_date: form.querySelector('[name="show_date"]')?.value || '',
      start_time: form.querySelector('[name="start_time"]')?.value || '',
      price: form.querySelector('[name="price"]')?.value || '',
      status: form.querySelector('[name="status"]')?.value || '',
      presentation_type: form.querySelector('[name="presentation_type"]')?.value || '',
      language_version: form.querySelector('[name="language_version"]')?.value || '',
    };
  }

  function validateShowtimePayload(input) {
    const errors = {};
    const payload = {
      movie_id: toPositiveInteger(input.movie_id),
      room_id: toPositiveInteger(input.room_id),
      show_date: String(input.show_date || '').trim(),
      start_time: normalizeTimeInput(input.start_time),
      price: normalizeFloat(input.price),
      status: String(input.status || '').trim().toLowerCase(),
      presentation_type: String(input.presentation_type || '').trim().toLowerCase(),
      language_version: String(input.language_version || '').trim().toLowerCase(),
    };

    if (!payload.movie_id) errors.movie_id = ['Movie must be selected.'];
    if (!toPositiveInteger(input.cinema_id)) errors.cinema_id = ['Cinema must be selected.'];
    if (!payload.room_id) errors.room_id = ['Room must be selected.'];
    if (!payload.show_date || !/^\d{4}-\d{2}-\d{2}$/.test(payload.show_date)) errors.show_date = ['Show date must be a valid YYYY-MM-DD date.'];
    if (!payload.start_time) errors.start_time = ['Start time must use HH:MM or HH:MM:SS.'];
    if (payload.price === null || payload.price <= 0) errors.price = ['Price must be greater than 0.'];
    if (!SHOWTIME_STATUSES.includes(payload.status)) errors.status = ['Showtime status is invalid.'];
    if (!PRESENTATION_TYPES.some(item => item.value === payload.presentation_type)) errors.presentation_type = ['Presentation type is invalid.'];
    if (!LANGUAGE_VERSIONS.some(item => item.value === payload.language_version)) errors.language_version = ['Language version is invalid.'];

    return { payload, errors };
  }

  function syncRoomOptions(form, selectedRoomId) {
    if (!form) {
      return;
    }

    const cinemaId = Number(form.querySelector('[name="cinema_id"]')?.value || 0);
    const roomSelect = form.querySelector('[name="room_id"]');
    if (!roomSelect) {
      return;
    }

    const filteredRooms = state.rooms.filter(room => {
      if (!cinemaId) {
        return true;
      }

      return Number(room.cinema_id || 0) === cinemaId;
    });

    roomSelect.innerHTML = buildOptions(filteredRooms.map(room => ({
      value: room.id,
      label: `${room.room_name} - ${roomTypeLabel(room.room_type)}`,
    })), selectedRoomId || roomSelect.value || '');
  }

  function availabilityBadge(label, isSoldOut, status) {
    const normalizedLabel = String(label || '').trim() || 'Available';
    const tone = status === 'cancelled'
      ? 'red'
      : status === 'draft'
        ? 'gray'
        : isSoldOut
          ? 'red'
          : normalizedLabel.includes('left')
            ? 'orange'
            : 'green';

    return `<span class="badge ${tone}">${escapeHtml(normalizedLabel)}</span>`;
  }

  function normalizeMeta(meta) {
    return {
      total: Number(meta?.total || 0),
      page: Number(meta?.page || 1),
      per_page: Number(meta?.per_page || state.filters.per_page || 20),
      total_pages: Math.max(1, Number(meta?.total_pages || 1)),
    };
  }

  function normalizeSummary(summary) {
    return {
      total: Number(summary?.total || 0),
      published: Number(summary?.published || 0),
      today: Number(summary?.today || 0),
      sold_out: Number(summary?.sold_out || 0),
    };
  }

  function setLoading(isLoading, statusText) {
    state.loading = isLoading;
    if (statusText) updateRequestStatus(statusText);
  }

  function updateRequestStatus(text) {
    if (dom.requestStatus) dom.requestStatus.textContent = text;
  }

  async function switchScope(scope) {
    if (state.filters.scope === scope) {
      return;
    }

    state.filters.scope = scope;
    state.filters.status = '';
    state.filters.page = 1;
    renderScopeControls();
    syncStatusFilter();
    await loadBootstrapData();
  }

  function renderScopeControls() {
    if (dom.scopeActiveBtn) {
      dom.scopeActiveBtn.classList.toggle('btn-primary', state.filters.scope === 'active');
      dom.scopeActiveBtn.classList.toggle('btn-ghost', state.filters.scope !== 'active');
    }
    if (dom.scopeArchivedBtn) {
      dom.scopeArchivedBtn.classList.toggle('btn-primary', state.filters.scope === 'archived');
      dom.scopeArchivedBtn.classList.toggle('btn-ghost', state.filters.scope !== 'archived');
    }
    if (dom.createBtn) {
      dom.createBtn.style.display = state.filters.scope === 'archived' ? 'none' : '';
    }
  }

  function syncStatusFilter() {
    if (!dom.statusFilter) {
      return;
    }

    if (state.filters.scope === 'archived') {
      dom.statusFilter.innerHTML = '<option value="">Archived only</option>';
      dom.statusFilter.value = '';
      dom.statusFilter.disabled = true;
      return;
    }

    dom.statusFilter.disabled = false;
    dom.statusFilter.innerHTML = [
      '<option value="">All Current Status</option>',
      '<option value="draft">Draft</option>',
      '<option value="published">Published</option>',
      '<option value="cancelled">Cancelled</option>',
      '<option value="completed">Completed</option>',
    ].join('');
    dom.statusFilter.value = String(state.filters.status || '');
  }

  function buildActionButtons(showtime) {
    const showtimeId = Number(showtime.id || 0);
    const viewButton = `
      <button class="action-btn view" type="button" title="View" data-action="view" data-showtime-id="${showtimeId}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
    `;

    if (state.filters.scope === 'archived') {
      return `<div class="actions-row">${viewButton}</div>`;
    }

    return `
      <div class="actions-row">
        ${viewButton}
        <button class="action-btn edit" type="button" title="Edit" data-action="edit" data-showtime-id="${showtimeId}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="action-btn del" type="button" title="Archive" data-action="archive" data-showtime-id="${showtimeId}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
        </button>
      </div>
    `;
  }

  function emptyStateCopy() {
    if (state.filters.scope === 'archived') {
      return [
        'No archived showtimes matched the current filters.',
        'Try clearing the search or adjust the movie, cinema, or date filters to review archived screenings.',
      ];
    }

    return [
      'No showtimes matched the current filters.',
      'Try clearing the search or adjust the movie, cinema, status, or date filters.',
    ];
  }

  function scopeMessage(activeMessage, archivedMessage) {
    return state.filters.scope === 'archived' ? archivedMessage : activeMessage;
  }

  function buildVisiblePages(currentPage, totalPages) {
    if (totalPages <= 5) return Array.from({ length: totalPages }, (_, index) => index + 1);

    const pages = [1];
    const start = Math.max(2, currentPage - 1);
    const end = Math.min(totalPages - 1, currentPage + 1);
    if (start > 2) pages.push('ellipsis');
    for (let page = start; page <= end; page += 1) pages.push(page);
    if (end < totalPages - 1) pages.push('ellipsis');
    pages.push(totalPages);
    return pages;
  }

  function resolveCinemaIdFromRoom(roomId) {
    const room = state.rooms.find(item => Number(item.id) === Number(roomId));
    return room ? Number(room.cinema_id || 0) : null;
  }

  function roomTypeLabel(value) {
    const match = ROOM_TYPE_LOOKUP[value];
    return match?.label || humanizeStatus(value);
  }

  function presentationLabel(value) {
    const match = PRESENTATION_TYPES.find(item => item.value === value);
    return match?.label || humanizeStatus(value);
  }

  function languageLabel(value) {
    const match = LANGUAGE_VERSIONS.find(item => item.value === value);
    return match?.label || humanizeStatus(value);
  }

  function normalizeTimeInput(value) {
    const text = String(value || '').trim();
    if (!text) return null;
    if (/^\d{2}:\d{2}$/.test(text)) return `${text}:00`;
    if (/^\d{2}:\d{2}:\d{2}$/.test(text)) return text;
    return null;
  }

  function trimTimeValue(value) {
    const text = String(value || '').trim();
    return text ? text.slice(0, 5) : '';
  }

  function formatTimeRange(startTime, endTime) {
    const start = trimTimeValue(startTime) || 'TBD';
    const end = trimTimeValue(endTime) || 'TBD';
    return `${start} - ${end}`;
  }

  function formatDate(value) {
    if (!value) return 'TBD';
    const parsed = new Date(`${value}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return String(value);

    return new Intl.DateTimeFormat('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(parsed);
  }

  function formatCurrency(value) {
    const amount = Number(value || 0);
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: 'VND',
      maximumFractionDigits: 0,
    }).format(amount);
  }

  function toPositiveInteger(value) {
    const parsed = Number.parseInt(String(value || '').trim(), 10);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
  }

  function normalizeFloat(value) {
    const parsed = Number(String(value || '').trim());
    return Number.isFinite(parsed) ? Number(parsed.toFixed(2)) : null;
  }

  function toInputValue(value) {
    return value === null || value === undefined || value === '' ? '' : String(value);
  }

  document.addEventListener('DOMContentLoaded', initShowtimeManagementPage);
})();
