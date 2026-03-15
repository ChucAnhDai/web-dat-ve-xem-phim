(function () {
  const ROOM_TYPES = [
    { value: 'standard_2d', label: 'Standard 2D' },
    { value: 'premium_3d', label: 'Premium 3D' },
    { value: 'vip_recliner', label: 'VIP Recliner' },
    { value: 'imax', label: 'IMAX' },
    { value: '4dx', label: '4DX' },
    { value: 'screenx', label: 'ScreenX' },
    { value: 'dolby_atmos', label: 'Dolby Atmos' },
  ];
  const ROOM_STATUSES = ['active', 'maintenance', 'closed', 'archived'];
  const PROJECTION_TYPES = [
    { value: 'digital_4k', label: 'Digital 4K' },
    { value: 'laser', label: 'Laser' },
    { value: 'imax_dual', label: 'IMAX Dual' },
    { value: 'motion_rig', label: 'Motion Rig' },
  ];
  const SOUND_PROFILES = [
    { value: 'stereo', label: 'Stereo' },
    { value: 'dolby_7_1', label: 'Dolby 7.1' },
    { value: 'dolby_atmos', label: 'Dolby Atmos' },
    { value: 'immersive_360', label: 'Immersive 360' },
  ];

  const state = {
    items: [],
    cinemas: [],
    meta: { total: 0, page: 1, per_page: 20, total_pages: 1 },
    summary: { total: 0, type_count: 0, active: 0, total_seats: 0 },
    filters: {
      page: 1,
      per_page: 20,
      search: '',
      cinema_id: '',
      room_type: '',
      status: '',
      scope: 'active',
    },
    loading: false,
    searchTimer: null,
    initialized: false,
  };

  const dom = {};

  function initRoomManagementPage() {
    if (state.initialized || document.body?.dataset?.activePage !== 'rooms') {
      return;
    }

    cacheDom();
    if (!dom.body) {
      return;
    }

    const query = new URLSearchParams(window.location.search);
    state.filters.cinema_id = query.get('cinema_id') || '';

    state.initialized = true;
    bindEvents();
    window.handleCinemaSectionAction = openCreateRoomModal;

    renderStats();
    renderLoadingRow('Loading room catalog...');
    loadRooms();
  }

  function cacheDom() {
    dom.totalStat = document.getElementById('roomTotalStat');
    dom.typeStat = document.getElementById('roomTypeStat');
    dom.activeStat = document.getElementById('roomActiveStat');
    dom.seatStat = document.getElementById('roomSeatStat');
    dom.searchInput = document.getElementById('roomSearchInput');
    dom.cinemaFilter = document.getElementById('roomCinemaFilter');
    dom.typeFilter = document.getElementById('roomTypeFilter');
    dom.statusFilter = document.getElementById('roomStatusFilter');
    dom.scopeActiveBtn = document.getElementById('roomScopeActiveBtn');
    dom.scopeArchivedBtn = document.getElementById('roomScopeArchivedBtn');
    dom.sectionActionBtn = document.getElementById('cinemaSectionActionBtn');
    dom.count = document.getElementById('roomCount');
    dom.requestStatus = document.getElementById('roomRequestStatus');
    dom.body = document.getElementById('roomsBody');
    dom.pagination = document.getElementById('roomsPagination');
  }

  function bindEvents() {
    dom.searchInput?.addEventListener('input', handleSearchInput);
    dom.cinemaFilter?.addEventListener('change', () => {
      state.filters.cinema_id = dom.cinemaFilter.value;
      state.filters.page = 1;
      loadRooms();
    });
    dom.typeFilter?.addEventListener('change', () => {
      state.filters.room_type = dom.typeFilter.value;
      state.filters.page = 1;
      loadRooms();
    });
    dom.statusFilter?.addEventListener('change', () => {
      state.filters.status = dom.statusFilter.value;
      state.filters.page = 1;
      loadRooms();
    });
    dom.scopeActiveBtn?.addEventListener('click', () => switchScope('active'));
    dom.scopeArchivedBtn?.addEventListener('click', () => switchScope('archived'));
    dom.body?.addEventListener('click', handleTableAction);
    dom.pagination?.addEventListener('click', handlePaginationAction);
  }

  async function loadRooms() {
    renderScopeControls();
    syncStatusFilter();
    setLoading(true, scopeMessage('Loading room data...', 'Loading archived rooms...'));

    try {
      const response = await adminApiRequest('/api/admin/rooms', {
        query: {
          page: state.filters.page,
          per_page: state.filters.per_page,
          search: state.filters.search,
          cinema_id: state.filters.cinema_id,
          room_type: state.filters.room_type,
          status: state.filters.status,
          scope: state.filters.scope,
        },
      });

      const payload = response?.data || {};
      state.items = Array.isArray(payload.items) ? payload.items : [];
      state.meta = normalizeMeta(payload.meta);
      state.summary = normalizeSummary(payload.summary);
      state.cinemas = Array.isArray(payload.options?.cinemas) ? payload.options.cinemas : [];

      renderStats();
      renderCinemaOptions();
      renderScopeControls();
      syncStatusFilter();
      renderTable();
      renderPagination();
      updateRequestStatus(scopeMessage('Room data synced', 'Archived room data synced'));
    } catch (error) {
      state.items = [];
      state.meta = normalizeMeta();
      state.summary = normalizeSummary();

      renderStats();
      renderCinemaOptions();
      renderScopeControls();
      syncStatusFilter();
      renderErrorRow(errorMessageFromException(error, 'Failed to load rooms.'));
      renderPagination();
      updateRequestStatus(scopeMessage('Room data unavailable', 'Archived room data unavailable'));
      showToast(errorMessageFromException(error, 'Failed to load rooms.'), 'error');
    } finally {
      setLoading(false);
    }
  }

  function renderStats() {
    if (dom.totalStat) dom.totalStat.textContent = String(state.summary.total);
    if (dom.typeStat) dom.typeStat.textContent = String(state.summary.type_count);
    if (dom.activeStat) dom.activeStat.textContent = String(state.summary.active);
    if (dom.seatStat) dom.seatStat.textContent = formatNumber(state.summary.total_seats);
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
      dom.count.textContent = `${total} ${state.filters.scope === 'archived' ? 'archived room' : 'room'}${total === 1 ? '' : 's'}`;
    }

    if (state.loading && state.items.length === 0) {
      renderLoadingRow(scopeMessage('Loading room catalog...', 'Loading archived rooms...'));
      return;
    }

    if (state.items.length === 0) {
      renderEmptyRow(...emptyStateCopy());
      return;
    }

    dom.body.innerHTML = state.items.map(room => `
      <tr>
        <td>
          <div class="td-bold">${escapeHtml(room.room_name || 'Untitled room')}</div>
          <div class="table-meta-text">${escapeHtml(room.screen_label || '-')}</div>
        </td>
        <td class="td-muted">
          ${escapeHtml(room.cinema_name || 'Cinema')}
          <div class="table-meta-text">${escapeHtml(room.cinema_city || '-')}</div>
        </td>
        <td><span class="badge blue">${escapeHtml(roomTypeLabel(room.room_type))}</span></td>
        <td class="td-muted">
          ${escapeHtml(room.screen_label || '-')}
          <div class="table-meta-text">${escapeHtml(projectionTypeLabel(room.projection_type))}</div>
        </td>
        <td class="td-muted">${escapeHtml(`${Number(room.cleaning_buffer_minutes || 0)} min`)}</td>
        <td style="font-weight:700;">${formatNumber(room.total_seats)}</td>
        <td>${statusBadge(room.status)}</td>
        <td>
          ${buildActionButtons(room)}
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
            <div class="table-meta-text">${escapeHtml(scopeMessage('Please wait while the room catalog is synchronized.', 'Please wait while archived room records are loaded.'))}</div>
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
    renderEmptyRow('Room data could not be loaded.', message);
    if (dom.count) {
      dom.count.textContent = 'Room data unavailable';
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
        <div class="pagination-info">Showing ${start}-${end} of ${total} rooms</div>
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
      loadRooms();
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
    loadRooms();
  }

  function handleTableAction(event) {
    const button = event.target.closest('button[data-action]');
    if (!button) {
      return;
    }

    const roomId = Number(button.dataset.roomId || 0);
    if (!roomId) {
      showToast('Room ID is missing for this action.', 'error');
      return;
    }

    const action = button.dataset.action;
    if (action === 'view') {
      openPreviewRoomModal(roomId);
      return;
    }
    if (action === 'edit') {
      openEditRoomModal(roomId);
      return;
    }
    if (action === 'seats') {
      window.location.href = adminAppUrl(`/admin/seats?room_id=${encodeURIComponent(String(roomId))}`);
      return;
    }
    if (action === 'archive') {
      archiveRoom(roomId);
    }
  }

  function openCreateRoomModal() {
    if (state.cinemas.length === 0) {
      showToast('Create at least one cinema before adding rooms.', 'warning');
      return;
    }

    openRoomEditorModal('Add Room', {});
  }

  async function openEditRoomModal(roomId) {
    updateRequestStatus('Loading room details...');

    try {
      const room = await fetchRoomDetail(roomId);
      openRoomEditorModal('Edit Room', room);
      updateRequestStatus('Room details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load room details');
      showToast(errorMessageFromException(error, 'Failed to load room details.'), 'error');
    }
  }

  async function openPreviewRoomModal(roomId) {
    updateRequestStatus('Loading room details...');

    try {
      const room = await fetchRoomDetail(roomId);
      openModal('Room Details', buildRoomPreview(room), {
        description: 'Review the persisted operational room fields currently used by seats and showtimes.',
        note: 'This preview comes directly from the admin room API.',
        submitLabel: 'Edit Room',
        cancelLabel: 'Close',
        busyLabel: 'Opening...',
        onSave: async () => {
          closeModal();
          await openEditRoomModal(roomId);
        },
      });
      updateRequestStatus('Room details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load room details');
      showToast(errorMessageFromException(error, 'Failed to load room details.'), 'error');
    }
  }

  function openRoomEditorModal(title, room) {
    openModal(title, buildRoomForm(room), {
      description: /^Edit/i.test(title)
        ? 'Update the room format, screen profile, and operating status stored for this auditorium.'
        : 'Create a room profile that seats and showtimes can attach to.',
      note: 'Capacity is recomputed from the persisted seat layout, not from the room form.',
      submitLabel: /^Edit/i.test(title) ? 'Update Room' : 'Create Room',
      busyLabel: /^Edit/i.test(title) ? 'Updating...' : 'Creating...',
      onSave: async () => {
        await submitRoomForm(room?.id || null);
      },
    });
  }

  async function submitRoomForm(roomId) {
    const form = document.getElementById('roomManagementForm');
    if (!form) {
      throw new Error('Room form is unavailable.');
    }

    const { payload, errors } = validateRoomPayload(collectRoomPayload(form));
    if (Object.keys(errors).length > 0) {
      applyFormErrors(form, errors);
      return;
    }

    clearFormErrors(form);

    try {
      const response = await adminApiRequest(roomId ? `/api/admin/rooms/${roomId}` : '/api/admin/rooms', {
        method: roomId ? 'PUT' : 'POST',
        body: payload,
      });

      closeModal();
      if (!roomId) {
        state.filters.page = 1;
      }
      await loadRooms();
      showToast(response?.message || (roomId ? 'Room updated successfully.' : 'Room created successfully.'), 'success');
    } catch (error) {
      if (error instanceof AdminApiError && [404, 409, 422].includes(error.status)) {
        applyFormErrors(form, error.errors);
        return;
      }

      throw error;
    }
  }

  async function archiveRoom(roomId) {
    const room = state.items.find(item => Number(item.id) === Number(roomId));
    const roomName = room?.room_name || `room #${roomId}`;

    if (!window.confirm(`Archive "${roomName}"? The room will remain in the database with archived status.`)) {
      return;
    }

    updateRequestStatus('Archiving room...');

    try {
      const response = await adminApiRequest(`/api/admin/rooms/${roomId}`, {
        method: 'DELETE',
      });

      if (state.items.length === 1 && state.filters.page > 1) {
        state.filters.page -= 1;
      }

      await loadRooms();
      showToast(response?.message || 'Room archived successfully.', 'success');
    } catch (error) {
      updateRequestStatus('Room archive failed');
      showToast(errorMessageFromException(error, 'Failed to archive room.'), 'error');
    }
  }

  async function fetchRoomDetail(roomId) {
    const response = await adminApiRequest(`/api/admin/rooms/${roomId}`);
    return response?.data || {};
  }

  function buildRoomForm(room) {
    const value = room || {};

    return `
      <form id="roomManagementForm" class="form-grid" novalidate>
        <div class="form-alert" data-form-alert hidden></div>
        <div class="field">
          <label>Cinema</label>
          <select class="select" name="cinema_id" data-field-control="cinema_id">
            ${buildOptions(state.cinemas.map(cinema => ({
              value: cinema.id,
              label: cinema.city ? `${cinema.name} - ${cinema.city}` : cinema.name,
            })), value.cinema_id || state.filters.cinema_id || '')}
          </select>
          <div class="field-error" data-field-error="cinema_id" hidden></div>
        </div>
        <div class="field">
          <label>Room Name</label>
          <input class="input" name="room_name" data-field-control="room_name" value="${escapeHtmlAttr(value.room_name || '')}" placeholder="Room 1 - IMAX">
          <div class="field-error" data-field-error="room_name" hidden></div>
        </div>
        <div class="field">
          <label>Room Type</label>
          <select class="select" name="room_type" data-field-control="room_type">
            ${buildOptions(ROOM_TYPES, value.room_type || 'standard_2d')}
          </select>
          <div class="field-error" data-field-error="room_type" hidden></div>
        </div>
        <div class="field">
          <label>Status</label>
          <select class="select" name="status" data-field-control="status">
            ${buildOptions([
              { value: 'active', label: 'Active' },
              { value: 'maintenance', label: 'Maintenance' },
              { value: 'closed', label: 'Closed' },
              { value: 'archived', label: 'Archived' },
            ], value.status || 'active')}
          </select>
          <div class="field-error" data-field-error="status" hidden></div>
        </div>
        <div class="field">
          <label>Screen Label</label>
          <input class="input" name="screen_label" data-field-control="screen_label" value="${escapeHtmlAttr(value.screen_label || '')}" placeholder="22m IMAX screen">
          <div class="field-error" data-field-error="screen_label" hidden></div>
        </div>
        <div class="field">
          <label>Projection Type</label>
          <select class="select" name="projection_type" data-field-control="projection_type">
            ${buildOptions(PROJECTION_TYPES, value.projection_type || 'laser')}
          </select>
          <div class="field-error" data-field-error="projection_type" hidden></div>
        </div>
        <div class="field">
          <label>Sound Profile</label>
          <select class="select" name="sound_profile" data-field-control="sound_profile">
            ${buildOptions(SOUND_PROFILES, value.sound_profile || 'dolby_atmos')}
          </select>
          <div class="field-error" data-field-error="sound_profile" hidden></div>
        </div>
        <div class="field">
          <label>Cleaning Buffer (minutes)</label>
          <input class="input" name="cleaning_buffer_minutes" data-field-control="cleaning_buffer_minutes" type="number" min="0" max="180" value="${escapeHtmlAttr(String(value.cleaning_buffer_minutes ?? 15))}">
          <div class="field-error" data-field-error="cleaning_buffer_minutes" hidden></div>
        </div>
      </form>
    `;
  }

  function buildRoomPreview(room) {
    return `
      <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="preview-banner">
          <div class="preview-banner-title">${escapeHtml(room.room_name || 'Untitled room')}</div>
          <div class="preview-banner-copy">${escapeHtml([room.cinema_name, room.screen_label].filter(Boolean).join(' - ') || 'No screen metadata stored.')}</div>
          <div class="meta-pills">
            ${statusBadge(room.status)}
            <span class="badge blue">${escapeHtml(roomTypeLabel(room.room_type))}</span>
            <span class="badge gold">${escapeHtml(`${formatNumber(room.total_seats)} seats`)}</span>
          </div>
        </div>
        <div class="form-grid">
          <div class="field">
            <label>Cinema</label>
            <input class="input" type="text" value="${escapeHtmlAttr(room.cinema_name || 'Cinema')}" readonly>
          </div>
          <div class="field">
            <label>City</label>
            <input class="input" type="text" value="${escapeHtmlAttr(room.cinema_city || 'N/A')}" readonly>
          </div>
          <div class="field">
            <label>Projection Type</label>
            <input class="input" type="text" value="${escapeHtmlAttr(projectionTypeLabel(room.projection_type))}" readonly>
          </div>
          <div class="field">
            <label>Sound Profile</label>
            <input class="input" type="text" value="${escapeHtmlAttr(soundProfileLabel(room.sound_profile))}" readonly>
          </div>
          <div class="field">
            <label>Cleaning Buffer</label>
            <input class="input" type="text" value="${escapeHtmlAttr(`${Number(room.cleaning_buffer_minutes || 0)} min`)}" readonly>
          </div>
          <div class="field">
            <label>Tracked Capacity</label>
            <input class="input" type="text" value="${escapeHtmlAttr(`${formatNumber(room.total_seats)} seats`)}" readonly>
          </div>
        </div>
      </div>
    `;
  }

  function collectRoomPayload(form) {
    return {
      cinema_id: form.querySelector('[name="cinema_id"]')?.value || '',
      room_name: form.querySelector('[name="room_name"]')?.value || '',
      room_type: form.querySelector('[name="room_type"]')?.value || '',
      screen_label: form.querySelector('[name="screen_label"]')?.value || '',
      projection_type: form.querySelector('[name="projection_type"]')?.value || '',
      sound_profile: form.querySelector('[name="sound_profile"]')?.value || '',
      cleaning_buffer_minutes: form.querySelector('[name="cleaning_buffer_minutes"]')?.value || '',
      status: form.querySelector('[name="status"]')?.value || '',
    };
  }

  function validateRoomPayload(input) {
    const errors = {};
    const payload = {
      cinema_id: toPositiveInteger(input.cinema_id),
      room_name: String(input.room_name || '').trim(),
      room_type: String(input.room_type || '').trim().toLowerCase(),
      screen_label: String(input.screen_label || '').trim(),
      projection_type: String(input.projection_type || '').trim().toLowerCase(),
      sound_profile: String(input.sound_profile || '').trim().toLowerCase(),
      cleaning_buffer_minutes: toInteger(input.cleaning_buffer_minutes),
      status: String(input.status || '').trim().toLowerCase(),
    };

    if (!payload.cinema_id) errors.cinema_id = ['Cinema must be selected.'];
    if (payload.room_name === '') errors.room_name = ['Field is required.'];
    if (!ROOM_TYPES.some(item => item.value === payload.room_type)) errors.room_type = ['Room type is invalid.'];
    if (payload.screen_label === '') errors.screen_label = ['Field is required.'];
    if (!PROJECTION_TYPES.some(item => item.value === payload.projection_type)) errors.projection_type = ['Projection type is invalid.'];
    if (!SOUND_PROFILES.some(item => item.value === payload.sound_profile)) errors.sound_profile = ['Sound profile is invalid.'];
    if (!Number.isInteger(payload.cleaning_buffer_minutes) || payload.cleaning_buffer_minutes < 0 || payload.cleaning_buffer_minutes > 180) {
      errors.cleaning_buffer_minutes = ['Cleaning buffer must be between 0 and 180 minutes.'];
    }
    if (!ROOM_STATUSES.includes(payload.status)) errors.status = ['Room status is invalid.'];

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
      type_count: Number(summary?.type_count || 0),
      active: Number(summary?.active || 0),
      total_seats: Number(summary?.total_seats || 0),
    };
  }

  function setLoading(isLoading, statusText) {
    state.loading = isLoading;
    if (statusText) updateRequestStatus(statusText);
  }

  function updateRequestStatus(text) {
    if (dom.requestStatus) dom.requestStatus.textContent = text;
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
    loadRooms();
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
      '<option value="maintenance">Maintenance</option>',
      '<option value="closed">Closed</option>',
    ].join('');
    dom.statusFilter.value = String(state.filters.status || '');
  }

  function buildActionButtons(room) {
    const roomId = Number(room.id || 0);
    const viewButton = `
      <button class="action-btn view" type="button" title="View" data-action="view" data-room-id="${roomId}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
      </button>
    `;

    if (state.filters.scope === 'archived') {
      return `<div class="actions-row">${viewButton}</div>`;
    }

    return `
      <div class="actions-row">
        ${viewButton}
        <button class="action-btn edit" type="button" title="Edit" data-action="edit" data-room-id="${roomId}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="action-btn gold" type="button" title="Seats" data-action="seats" data-room-id="${roomId}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13V7a2 2 0 012-2h10a2 2 0 012 2v6M5 13H3v5h18v-5h-2M5 13h14"/><path d="M8 21v-3M16 21v-3"/></svg>
        </button>
        <button class="action-btn del" type="button" title="Archive" data-action="archive" data-room-id="${roomId}">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
        </button>
      </div>
    `;
  }

  function emptyStateCopy() {
    if (state.filters.scope === 'archived') {
      return [
        'No archived rooms matched the current filters.',
        'Try clearing the search or adjust the cinema and type filters to review archived rooms.',
      ];
    }

    return [
      'No rooms matched the current filters.',
      'Try clearing the search or select another cinema or room type.',
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

  function roomTypeLabel(value) {
    const match = ROOM_TYPES.find(item => item.value === value);
    return match?.label || humanizeStatus(value);
  }

  function projectionTypeLabel(value) {
    const match = PROJECTION_TYPES.find(item => item.value === value);
    return match?.label || humanizeStatus(value);
  }

  function soundProfileLabel(value) {
    const match = SOUND_PROFILES.find(item => item.value === value);
    return match?.label || humanizeStatus(value);
  }

  function toPositiveInteger(value) {
    const parsed = Number.parseInt(String(value || '').trim(), 10);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
  }

  function toInteger(value) {
    const parsed = Number.parseInt(String(value || '').trim(), 10);
    return Number.isInteger(parsed) ? parsed : null;
  }

  function formatNumber(value) {
    return Number(value || 0).toLocaleString('en-US');
  }

  document.addEventListener('DOMContentLoaded', initRoomManagementPage);
})();
