(function () {
  const STATUSES = ['active', 'renovation', 'closed', 'archived'];

  const state = {
    items: [],
    meta: { total: 0, page: 1, per_page: 20, total_pages: 1 },
    summary: { total: 0, city_count: 0, room_count: 0, total_seats: 0 },
    filters: {
      page: 1,
      per_page: 20,
      search: '',
      city: '',
      status: '',
      scope: 'active',
    },
    cities: [],
    loading: false,
    searchTimer: null,
    initialized: false,
  };

  const dom = {};

  function initCinemaManagementPage() {
    if (state.initialized || document.body?.dataset?.activePage !== 'cinemas') {
      return;
    }

    cacheDom();
    if (!dom.body) {
      return;
    }

    state.initialized = true;
    bindEvents();
    window.handleCinemaSectionAction = openCreateCinemaModal;

    renderStats();
    renderLoadingRow('Loading cinema locations...');
    loadCinemas();
  }

  function cacheDom() {
    dom.totalStat = document.getElementById('cinemaTotalStat');
    dom.cityStat = document.getElementById('cinemaCityStat');
    dom.roomStat = document.getElementById('cinemaRoomStat');
    dom.seatStat = document.getElementById('cinemaSeatStat');
    dom.searchInput = document.getElementById('cinemaSearchInput');
    dom.cityFilter = document.getElementById('cinemaCityFilter');
    dom.statusFilter = document.getElementById('cinemaStatusFilter');
    dom.scopeActiveBtn = document.getElementById('cinemaScopeActiveBtn');
    dom.scopeArchivedBtn = document.getElementById('cinemaScopeArchivedBtn');
    dom.sectionActionBtn = document.getElementById('cinemaSectionActionBtn');
    dom.count = document.getElementById('cinemaCount');
    dom.requestStatus = document.getElementById('cinemaRequestStatus');
    dom.body = document.getElementById('cinemasBody');
    dom.pagination = document.getElementById('cinemasPagination');
  }

  function bindEvents() {
    dom.searchInput?.addEventListener('input', handleSearchInput);
    dom.cityFilter?.addEventListener('change', () => {
      state.filters.city = dom.cityFilter.value;
      state.filters.page = 1;
      loadCinemas();
    });
    dom.statusFilter?.addEventListener('change', () => {
      state.filters.status = dom.statusFilter.value;
      state.filters.page = 1;
      loadCinemas();
    });
    dom.scopeActiveBtn?.addEventListener('click', () => switchScope('active'));
    dom.scopeArchivedBtn?.addEventListener('click', () => switchScope('archived'));
    dom.body?.addEventListener('click', handleTableAction);
    dom.pagination?.addEventListener('click', handlePaginationAction);
  }

  async function loadCinemas() {
    renderScopeControls();
    syncStatusFilter();
    setLoading(true, scopeMessage('Loading cinema data...', 'Loading archived cinemas...'));

    try {
      const response = await adminApiRequest('/api/admin/cinemas', {
        query: {
          page: state.filters.page,
          per_page: state.filters.per_page,
          search: state.filters.search,
          city: state.filters.city,
          status: state.filters.status,
          scope: state.filters.scope,
        },
      });

      const payload = response?.data || {};
      state.items = Array.isArray(payload.items) ? payload.items : [];
      state.meta = normalizeMeta(payload.meta);
      state.summary = normalizeSummary(payload.summary);
      state.cities = Array.isArray(payload.options?.cities) ? payload.options.cities : [];

      renderStats();
      renderCityOptions();
      renderScopeControls();
      syncStatusFilter();
      renderTable();
      renderPagination();
      updateRequestStatus(scopeMessage('Cinema data synced', 'Archived cinema data synced'));
    } catch (error) {
      state.items = [];
      state.meta = normalizeMeta();
      state.summary = normalizeSummary();

      renderStats();
      renderCityOptions();
      renderScopeControls();
      syncStatusFilter();
      renderErrorRow(errorMessageFromException(error, 'Failed to load cinema locations.'));
      renderPagination();
      updateRequestStatus(scopeMessage('Cinema data unavailable', 'Archived cinema data unavailable'));
      showToast(errorMessageFromException(error, 'Failed to load cinema locations.'), 'error');
    } finally {
      setLoading(false);
    }
  }

  function renderStats() {
    if (dom.totalStat) dom.totalStat.textContent = String(state.summary.total);
    if (dom.cityStat) dom.cityStat.textContent = String(state.summary.city_count);
    if (dom.roomStat) dom.roomStat.textContent = String(state.summary.room_count);
    if (dom.seatStat) dom.seatStat.textContent = formatNumber(state.summary.total_seats);
  }

  function renderCityOptions() {
    if (!dom.cityFilter) {
      return;
    }

    const currentValue = String(state.filters.city || '');
    const options = ['<option value="">All Cities</option>'];

    state.cities.forEach(city => {
      const value = String(city || '').trim();
      if (!value) {
        return;
      }

      const selected = value === currentValue ? ' selected' : '';
      options.push(`<option value="${escapeHtmlAttr(value)}"${selected}>${escapeHtml(value)}</option>`);
    });

    dom.cityFilter.innerHTML = options.join('');
  }

  function renderTable() {
    if (!dom.body) {
      return;
    }

    const total = Number(state.meta.total || 0);
    if (dom.count) {
      dom.count.textContent = `${total} ${state.filters.scope === 'archived' ? 'archived cinema' : 'cinema'}${total === 1 ? '' : 's'}`;
    }

    if (state.loading && state.items.length === 0) {
      renderLoadingRow(scopeMessage('Loading cinema locations...', 'Loading archived cinemas...'));
      return;
    }

    if (state.items.length === 0) {
      renderEmptyRow(...emptyStateCopy());
      return;
    }

    dom.body.innerHTML = state.items.map(cinema => `
      <tr>
        <td>
          <div class="td-bold">${escapeHtml(cinema.name || 'Untitled cinema')}</div>
          <div class="table-meta-text">${escapeHtml(cinema.slug || '-')}</div>
        </td>
        <td class="td-muted">${escapeHtml(cinema.city || '-')}</td>
        <td class="td-muted" style="font-size:12px;">${escapeHtml(cinema.address || '-')}</td>
        <td style="font-weight:700;">${formatNumber(cinema.room_count)}</td>
        <td style="font-weight:700;">${formatNumber(cinema.total_seats)}</td>
        <td class="td-muted">
          ${escapeHtml(cinema.manager_name || 'Unassigned')}
          <div class="table-meta-text">${escapeHtml(cinema.support_phone || 'No phone')}</div>
        </td>
        <td>${statusBadge(cinema.status)}</td>
        <td>
          ${buildActionButtons(cinema)}
        </td>
      </tr>
    `).join('');
  }

  function renderLoadingRow(message) {
    if (!dom.body) {
      return;
    }

    dom.body.innerHTML = `
      <tr class="table-empty-row">
        <td colspan="8">
          <div class="table-empty-state">
            <strong>${escapeHtml(message)}</strong>
            <div class="table-meta-text">${escapeHtml(scopeMessage('Please wait while we sync the cinema network.', 'Please wait while we load archived cinema records.'))}</div>
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
    renderEmptyRow('Cinema data could not be loaded.', message);
    if (dom.count) {
      dom.count.textContent = 'Cinema data unavailable';
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
        <div class="pagination-info">Showing ${start}-${end} of ${total} cinemas</div>
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
      loadCinemas();
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
    loadCinemas();
  }

  function handleTableAction(event) {
    const button = event.target.closest('button[data-action]');
    if (!button) {
      return;
    }

    const cinemaId = Number(button.dataset.cinemaId || 0);
    if (!cinemaId) {
      showToast('Cinema ID is missing for this action.', 'error');
      return;
    }

    const action = button.dataset.action;
    if (action === 'view') {
      openPreviewCinemaModal(cinemaId);
      return;
    }
    if (action === 'edit') {
      openEditCinemaModal(cinemaId);
      return;
    }
    if (action === 'rooms') {
      window.location.href = adminAppUrl(`/admin/cinemas?section=rooms&cinema_id=${encodeURIComponent(String(cinemaId))}`);
      return;
    }
    if (action === 'archive') {
      archiveCinema(cinemaId);
    }
  }

  function openCreateCinemaModal() {
    openCinemaEditorModal('Add Cinema', {});
  }

  async function openEditCinemaModal(cinemaId) {
    updateRequestStatus('Loading cinema details...');

    try {
      const cinema = await fetchCinemaDetail(cinemaId);
      openCinemaEditorModal('Edit Cinema', cinema);
      updateRequestStatus('Cinema details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load cinema details');
      showToast(errorMessageFromException(error, 'Failed to load cinema details.'), 'error');
    }
  }

  async function openPreviewCinemaModal(cinemaId) {
    updateRequestStatus('Loading cinema details...');

    try {
      const cinema = await fetchCinemaDetail(cinemaId);
      openModal('Cinema Details', buildCinemaPreview(cinema), {
        description: 'Review the persisted cinema fields currently used by rooms, seats, and showtimes.',
        note: 'This preview comes directly from the admin cinema API.',
        submitLabel: 'Edit Cinema',
        cancelLabel: 'Close',
        busyLabel: 'Opening...',
        onSave: async () => {
          closeModal();
          await openEditCinemaModal(cinemaId);
        },
      });
      updateRequestStatus('Cinema details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load cinema details');
      showToast(errorMessageFromException(error, 'Failed to load cinema details.'), 'error');
    }
  }

  function openCinemaEditorModal(title, cinema) {
    openModal(title, buildCinemaForm(cinema), {
      description: /^Edit/i.test(title)
        ? 'Update the operational fields stored for this cinema location.'
        : 'Create a cinema profile that rooms, seats, and showtimes can attach to.',
      note: 'Closing or archiving a cinema is blocked when active rooms or future published showtimes still exist.',
      submitLabel: /^Edit/i.test(title) ? 'Update Cinema' : 'Create Cinema',
      busyLabel: /^Edit/i.test(title) ? 'Updating...' : 'Creating...',
      onSave: async () => {
        await submitCinemaForm(cinema?.id || null);
      },
    });

    const form = document.getElementById('cinemaManagementForm');
    const nameInput = form?.querySelector('[name="name"]');
    const slugInput = form?.querySelector('[name="slug"]');
    nameInput?.addEventListener('input', () => {
      if (!slugInput || slugInput.dataset.touched === '1') {
        return;
      }
      slugInput.value = slugifyValue(nameInput.value);
    });
    slugInput?.addEventListener('input', () => {
      slugInput.dataset.touched = slugInput.value.trim() !== '' ? '1' : '';
    });
  }

  async function submitCinemaForm(cinemaId) {
    const form = document.getElementById('cinemaManagementForm');
    if (!form) {
      throw new Error('Cinema form is unavailable.');
    }

    const { payload, errors } = validateCinemaPayload(collectCinemaPayload(form));
    if (Object.keys(errors).length > 0) {
      applyFormErrors(form, errors);
      return;
    }

    clearFormErrors(form);

    try {
      const response = await adminApiRequest(cinemaId ? `/api/admin/cinemas/${cinemaId}` : '/api/admin/cinemas', {
        method: cinemaId ? 'PUT' : 'POST',
        body: payload,
      });

      closeModal();
      if (!cinemaId) {
        state.filters.page = 1;
      }
      await loadCinemas();
      showToast(response?.message || (cinemaId ? 'Cinema updated successfully.' : 'Cinema created successfully.'), 'success');
    } catch (error) {
      if (error instanceof AdminApiError && [409, 422].includes(error.status)) {
        applyFormErrors(form, error.errors);
        return;
      }

      throw error;
    }
  }

  async function archiveCinema(cinemaId) {
    const cinema = state.items.find(item => Number(item.id) === Number(cinemaId));
    const cinemaName = cinema?.name || `cinema #${cinemaId}`;

    if (!window.confirm(`Archive "${cinemaName}"? The cinema will remain in the database with archived status.`)) {
      return;
    }

    updateRequestStatus('Archiving cinema...');

    try {
      const response = await adminApiRequest(`/api/admin/cinemas/${cinemaId}`, {
        method: 'DELETE',
      });

      if (state.items.length === 1 && state.filters.page > 1) {
        state.filters.page -= 1;
      }

      await loadCinemas();
      showToast(response?.message || 'Cinema archived successfully.', 'success');
    } catch (error) {
      updateRequestStatus('Cinema archive failed');
      showToast(errorMessageFromException(error, 'Failed to archive cinema.'), 'error');
    }
  }

  async function fetchCinemaDetail(cinemaId) {
    const response = await adminApiRequest(`/api/admin/cinemas/${cinemaId}`);
    return response?.data || {};
  }

  function buildCinemaForm(cinema) {
    const value = cinema || {};

    return `
      <form id="cinemaManagementForm" class="form-grid" novalidate>
        <div class="form-alert" data-form-alert hidden></div>
        <div class="field">
          <label>Cinema Name</label>
          <input class="input" name="name" data-field-control="name" value="${escapeHtmlAttr(value.name || '')}" placeholder="CineShop Galaxy">
          <div class="field-error" data-field-error="name" hidden></div>
        </div>
        <div class="field">
          <label>Slug</label>
          <input class="input" name="slug" data-field-control="slug" value="${escapeHtmlAttr(value.slug || '')}" placeholder="cineshop-galaxy">
          <div class="field-error" data-field-error="slug" hidden></div>
        </div>
        <div class="field">
          <label>City</label>
          <input class="input" name="city" data-field-control="city" value="${escapeHtmlAttr(value.city || '')}" placeholder="Ho Chi Minh City">
          <div class="field-error" data-field-error="city" hidden></div>
        </div>
        <div class="field">
          <label>Status</label>
          <select class="select" name="status" data-field-control="status">
            ${buildOptions([
              { value: 'active', label: 'Active' },
              { value: 'renovation', label: 'Renovation' },
              { value: 'closed', label: 'Closed' },
              { value: 'archived', label: 'Archived' },
            ], value.status || 'active')}
          </select>
          <div class="field-error" data-field-error="status" hidden></div>
        </div>
        <div class="field form-full">
          <label>Address</label>
          <input class="input" name="address" data-field-control="address" value="${escapeHtmlAttr(value.address || '')}" placeholder="Full cinema address">
          <div class="field-error" data-field-error="address" hidden></div>
        </div>
        <div class="field">
          <label>Manager Name</label>
          <input class="input" name="manager_name" data-field-control="manager_name" value="${escapeHtmlAttr(value.manager_name || '')}" placeholder="Operations manager">
          <div class="field-error" data-field-error="manager_name" hidden></div>
        </div>
        <div class="field">
          <label>Support Phone</label>
          <input class="input" name="support_phone" data-field-control="support_phone" value="${escapeHtmlAttr(value.support_phone || '')}" placeholder="+84 901 234 567">
          <div class="field-error" data-field-error="support_phone" hidden></div>
        </div>
        <div class="field">
          <label>Opening Time</label>
          <input class="input" name="opening_time" data-field-control="opening_time" value="${escapeHtmlAttr(trimTimeValue(value.opening_time))}" placeholder="09:00">
          <div class="field-error" data-field-error="opening_time" hidden></div>
        </div>
        <div class="field">
          <label>Closing Time</label>
          <input class="input" name="closing_time" data-field-control="closing_time" value="${escapeHtmlAttr(trimTimeValue(value.closing_time))}" placeholder="23:30">
          <div class="field-error" data-field-error="closing_time" hidden></div>
        </div>
        <div class="field">
          <label>Latitude</label>
          <input class="input" name="latitude" data-field-control="latitude" value="${escapeHtmlAttr(toInputValue(value.latitude))}" placeholder="10.7769">
          <div class="field-error" data-field-error="latitude" hidden></div>
        </div>
        <div class="field">
          <label>Longitude</label>
          <input class="input" name="longitude" data-field-control="longitude" value="${escapeHtmlAttr(toInputValue(value.longitude))}" placeholder="106.7009">
          <div class="field-error" data-field-error="longitude" hidden></div>
        </div>
        <div class="field form-full">
          <label>Description</label>
          <textarea class="textarea" name="description" data-field-control="description" placeholder="Guest-facing or operations note for this cinema.">${escapeHtml(value.description || '')}</textarea>
          <div class="field-error" data-field-error="description" hidden></div>
        </div>
      </form>
    `;
  }

  function buildCinemaPreview(cinema) {
    return `
      <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="preview-banner">
          <div class="preview-banner-title">${escapeHtml(cinema.name || 'Untitled cinema')}</div>
          <div class="preview-banner-copy">${escapeHtml(cinema.address || 'No address stored.')}</div>
          <div class="meta-pills">
            ${statusBadge(cinema.status)}
            <span class="badge blue">${escapeHtml(cinema.city || 'Unknown city')}</span>
            <span class="badge gold">${escapeHtml(`${formatNumber(cinema.room_count)} rooms`)}</span>
            <span class="badge gray">${escapeHtml(`${formatNumber(cinema.total_seats)} seats`)}</span>
          </div>
        </div>
        <div class="form-grid">
          <div class="field">
            <label>Slug</label>
            <input class="input" type="text" value="${escapeHtmlAttr(cinema.slug || '')}" readonly>
          </div>
          <div class="field">
            <label>Manager</label>
            <input class="input" type="text" value="${escapeHtmlAttr(cinema.manager_name || 'Unassigned')}" readonly>
          </div>
          <div class="field">
            <label>Support Phone</label>
            <input class="input" type="text" value="${escapeHtmlAttr(cinema.support_phone || 'N/A')}" readonly>
          </div>
          <div class="field">
            <label>Hours</label>
            <input class="input" type="text" value="${escapeHtmlAttr(formatTimeRange(cinema.opening_time, cinema.closing_time))}" readonly>
          </div>
          <div class="field">
            <label>Latitude</label>
            <input class="input" type="text" value="${escapeHtmlAttr(toInputValue(cinema.latitude) || 'N/A')}" readonly>
          </div>
          <div class="field">
            <label>Longitude</label>
            <input class="input" type="text" value="${escapeHtmlAttr(toInputValue(cinema.longitude) || 'N/A')}" readonly>
          </div>
          <div class="field form-full">
            <label>Description</label>
            <textarea class="textarea" readonly>${escapeHtml(cinema.description || 'No description stored.')}</textarea>
          </div>
        </div>
      </div>
    `;
  }

  function collectCinemaPayload(form) {
    return {
      name: form.querySelector('[name="name"]')?.value || '',
      slug: form.querySelector('[name="slug"]')?.value || '',
      city: form.querySelector('[name="city"]')?.value || '',
      address: form.querySelector('[name="address"]')?.value || '',
      manager_name: form.querySelector('[name="manager_name"]')?.value || '',
      support_phone: form.querySelector('[name="support_phone"]')?.value || '',
      status: form.querySelector('[name="status"]')?.value || '',
      opening_time: form.querySelector('[name="opening_time"]')?.value || '',
      closing_time: form.querySelector('[name="closing_time"]')?.value || '',
      latitude: form.querySelector('[name="latitude"]')?.value || '',
      longitude: form.querySelector('[name="longitude"]')?.value || '',
      description: form.querySelector('[name="description"]')?.value || '',
    };
  }

  function validateCinemaPayload(input) {
    const errors = {};
    const payload = {
      name: String(input.name || '').trim(),
      slug: slugifyValue(input.slug || input.name || ''),
      city: String(input.city || '').trim(),
      address: String(input.address || '').trim(),
      manager_name: String(input.manager_name || '').trim() || null,
      support_phone: String(input.support_phone || '').trim() || null,
      status: String(input.status || '').trim().toLowerCase(),
      opening_time: normalizeTimeInput(input.opening_time),
      closing_time: normalizeTimeInput(input.closing_time),
      latitude: normalizeNumberInput(input.latitude),
      longitude: normalizeNumberInput(input.longitude),
      description: String(input.description || '').trim() || null,
    };

    if (payload.name === '') errors.name = ['Field is required.'];
    if (payload.slug === '') errors.slug = ['Slug is required.'];
    if (payload.city === '') errors.city = ['Field is required.'];
    if (payload.address === '') errors.address = ['Field is required.'];
    if (!STATUSES.includes(payload.status)) errors.status = ['Cinema status is invalid.'];
    if (payload.support_phone && !/^[0-9+\s().-]{9,20}$/.test(payload.support_phone)) {
      errors.support_phone = ['Support phone must be a valid phone number.'];
    }
    if (input.opening_time && payload.opening_time === null) errors.opening_time = ['Opening time must use HH:MM or HH:MM:SS.'];
    if (input.closing_time && payload.closing_time === null) errors.closing_time = ['Closing time must use HH:MM or HH:MM:SS.'];
    if (input.latitude !== '' && payload.latitude === null) {
      errors.latitude = ['Latitude must be numeric.'];
    } else if (payload.latitude !== null && (payload.latitude < -90 || payload.latitude > 90)) {
      errors.latitude = ['Latitude must be between -90 and 90.'];
    }
    if (input.longitude !== '' && payload.longitude === null) {
      errors.longitude = ['Longitude must be numeric.'];
    } else if (payload.longitude !== null && (payload.longitude < -180 || payload.longitude > 180)) {
      errors.longitude = ['Longitude must be between -180 and 180.'];
    }

    return { payload, errors };
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
      city_count: Number(summary?.city_count || 0),
      room_count: Number(summary?.room_count || 0),
      total_seats: Number(summary?.total_seats || 0),
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

  function switchScope(scope) {
    if (state.filters.scope === scope) {
      return;
    }

    state.filters.scope = scope;
    state.filters.status = '';
    state.filters.page = 1;
    renderScopeControls();
    syncStatusFilter();
    loadCinemas();
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
    if (dom.sectionActionBtn) {
      dom.sectionActionBtn.style.display = state.filters.scope === 'archived' ? 'none' : '';
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
      '<option value="active">Active</option>',
      '<option value="renovation">Renovation</option>',
      '<option value="closed">Closed</option>',
    ].join('');
    dom.statusFilter.value = String(state.filters.status || '');
  }

  function buildActionButtons(cinema) {
    const cinemaId = Number(cinema.id || 0);
    const viewButton = `
      <button class="action-btn view" type="button" title="View" data-action="view" data-cinema-id="${cinemaId}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
    `;

    if (state.filters.scope === 'archived') {
      return `<div class="actions-row">${viewButton}</div>`;
    }

    return `
      <div class="actions-row">
        ${viewButton}
        <button class="action-btn edit" type="button" title="Edit" data-action="edit" data-cinema-id="${cinemaId}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="action-btn gold" type="button" title="Rooms" data-action="rooms" data-cinema-id="${cinemaId}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        </button>
        <button class="action-btn del" type="button" title="Archive" data-action="archive" data-cinema-id="${cinemaId}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
        </button>
      </div>
    `;
  }

  function emptyStateCopy() {
    if (state.filters.scope === 'archived') {
      return [
        'No archived cinemas matched the current filters.',
        'Try clearing the search or adjust the city filter to review archived cinema records.',
      ];
    }

    return [
      'No cinema locations matched the current filters.',
      'Try clearing the search or adjust the status and city filters.',
    ];
  }

  function scopeMessage(activeMessage, archivedMessage) {
    return state.filters.scope === 'archived' ? archivedMessage : activeMessage;
  }

  function buildVisiblePages(currentPage, totalPages) {
    if (totalPages <= 5) {
      return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const pages = [1];
    const start = Math.max(2, currentPage - 1);
    const end = Math.min(totalPages - 1, currentPage + 1);

    if (start > 2) pages.push('ellipsis');
    for (let page = start; page <= end; page += 1) pages.push(page);
    if (end < totalPages - 1) pages.push('ellipsis');
    pages.push(totalPages);

    return pages;
  }

  function normalizeTimeInput(value) {
    const text = String(value || '').trim();
    if (!text) return null;
    if (/^\d{2}:\d{2}$/.test(text)) return `${text}:00`;
    if (/^\d{2}:\d{2}:\d{2}$/.test(text)) return text;
    return null;
  }

  function normalizeNumberInput(value) {
    const text = String(value || '').trim();
    if (!text) return null;
    const parsed = Number(text);
    return Number.isFinite(parsed) ? parsed : null;
  }

  function trimTimeValue(value) {
    const text = String(value || '').trim();
    return text ? text.slice(0, 5) : '';
  }

  function formatTimeRange(openingTime, closingTime) {
    const opening = trimTimeValue(openingTime);
    const closing = trimTimeValue(closingTime);
    if (!opening && !closing) return 'Not set';
    return [opening || 'N/A', closing || 'N/A'].join(' - ');
  }

  function toInputValue(value) {
    return value === null || value === undefined || value === '' ? '' : String(value);
  }

  function formatNumber(value) {
    return Number(value || 0).toLocaleString('en-US');
  }

  document.addEventListener('DOMContentLoaded', initCinemaManagementPage);
})();
