(function () {
  const state = {
    cinemas: [],
    rooms: [],
    room: null,
    seats: [],
    summary: null,
    cinemaId: '',
    roomId: '',
    selectedSeatId: null,
    loading: false,
    initialized: false,
  };

  const dom = {};

  function initSeatManagementPage() {
    if (state.initialized || document.body?.dataset?.activePage !== 'seats') {
      return;
    }

    cacheDom();
    if (!dom.cinemaFilter || !dom.roomFilter) {
      return;
    }

    const params = new URLSearchParams(window.location.search);
    state.cinemaId = params.get('cinema_id') || '';
    state.roomId = params.get('room_id') || '';
    state.initialized = true;

    bindEvents();
    loadBootstrapData();
  }

  function cacheDom() {
    dom.cinemaFilter = document.getElementById('seatCinemaFilter');
    dom.roomFilter = document.getElementById('seatRoomFilter');
    dom.saveBtn = document.getElementById('saveSeatLayoutBtn');
    dom.requestStatus = document.getElementById('seatRequestStatus');
    dom.roomTitle = document.getElementById('seatRoomTitle');
    dom.roomMeta = document.getElementById('seatRoomMeta');
    dom.layoutState = document.getElementById('seatLayoutState');
    dom.map = document.getElementById('seatMap');
    dom.totalStat = document.getElementById('seatTotalStat');
    dom.vipStat = document.getElementById('seatVipStat');
    dom.coupleStat = document.getElementById('seatCoupleStat');
    dom.blockedStat = document.getElementById('seatBlockedStat');
    dom.totalProgress = document.getElementById('seatTotalProgress');
    dom.vipProgress = document.getElementById('seatVipProgress');
    dom.coupleProgress = document.getElementById('seatCoupleProgress');
    dom.blockedProgress = document.getElementById('seatBlockedProgress');
    dom.selectedSeatLabel = document.getElementById('selectedSeatLabel');
    dom.selectedSeatType = document.getElementById('selectedSeatType');
    dom.selectedSeatStatus = document.getElementById('selectedSeatStatus');
    dom.updateSelectedBtn = document.getElementById('updateSelectedSeatBtn');
    dom.removeSelectedBtn = document.getElementById('removeSelectedSeatBtn');
    dom.presetRows = document.getElementById('seatPresetRows');
    dom.presetCount = document.getElementById('seatPresetCount');
    dom.presetType = document.getElementById('seatPresetType');
    dom.presetStatus = document.getElementById('seatPresetStatus');
    dom.generateBtn = document.getElementById('generateSeatLayoutBtn');
    dom.appendRowBtn = document.getElementById('appendSeatRowBtn');
    dom.clearBtn = document.getElementById('clearSeatLayoutBtn');
  }

  function bindEvents() {
    dom.cinemaFilter?.addEventListener('change', handleCinemaChange);
    dom.roomFilter?.addEventListener('change', handleRoomChange);
    dom.map?.addEventListener('click', handleSeatSelection);
    dom.saveBtn?.addEventListener('click', saveSeatLayout);
    dom.updateSelectedBtn?.addEventListener('click', updateSelectedSeat);
    dom.removeSelectedBtn?.addEventListener('click', removeSelectedSeat);
    dom.generateBtn?.addEventListener('click', generateLayout);
    dom.appendRowBtn?.addEventListener('click', appendRow);
    dom.clearBtn?.addEventListener('click', clearLayout);
  }

  async function loadBootstrapData() {
    updateRequestStatus('Loading cinemas and rooms...');

    try {
      await loadCinemas();

      if (state.roomId && !state.cinemaId) {
        await loadRooms();

        const roomMatch = state.rooms.find(room => Number(room.id) === Number(state.roomId));
        if (roomMatch) {
          state.cinemaId = String(roomMatch.cinema_id || '');
        }
      }

      if (!state.cinemaId) {
        state.cinemaId = resolveDefaultCinemaId();
      }

      renderCinemaOptions();
      await loadRooms();
      if (!state.roomId) {
        state.roomId = resolveDefaultRoomId();
      }

      if (state.roomId && !state.cinemaId) {
        const roomMatch = state.rooms.find(room => Number(room.id) === Number(state.roomId));
        if (roomMatch) {
          state.cinemaId = String(roomMatch.cinema_id || '');
          renderCinemaOptions();
        }
      }

      renderRoomOptions();
      syncSelectionParams();

      if (state.roomId) {
        await loadSeatLayout(state.roomId);
      } else {
        renderSeatState('Select a room to load its seat map.', 'The live layout, seat status, and room capacity will appear here.');
      }
    } catch (error) {
      renderSeatState('Seat management is unavailable.', errorMessageFromException(error, 'Failed to load cinema seat management data.'));
      showToast(errorMessageFromException(error, 'Failed to load cinema seat management data.'), 'error');
    }
  }

  async function loadCinemas() {
    const response = await adminApiRequest('/api/admin/cinemas', {
      query: { page: 1, per_page: 200 },
    });

    state.cinemas = Array.isArray(response?.data?.items) ? response.data.items : [];
    renderCinemaOptions();

    if (!state.cinemaId && state.roomId) {
      const roomMatch = state.rooms.find(room => Number(room.id) === Number(state.roomId));
      if (roomMatch) {
        state.cinemaId = String(roomMatch.cinema_id || '');
      }
    }
  }

  async function loadRooms() {
    const response = await adminApiRequest('/api/admin/rooms', {
      query: {
        page: 1,
        per_page: 200,
        cinema_id: state.cinemaId || '',
      },
    });

    state.rooms = Array.isArray(response?.data?.items) ? response.data.items : [];
    renderRoomOptions();

    if (state.roomId && !state.rooms.some(room => Number(room.id) === Number(state.roomId))) {
      state.roomId = '';
    }
  }

  async function loadSeatLayout(roomId) {
    if (!roomId) {
      renderSeatState('Select a room to load its seat map.', 'The live layout, seat status, and room capacity will appear here.');
      return;
    }

    setLoading(true, 'Loading seat layout...');

    try {
      const response = await adminApiRequest(`/api/admin/rooms/${roomId}/seats`);
      const payload = response?.data || {};

      state.room = payload.room || null;
      state.seats = Array.isArray(payload.seats) ? payload.seats.map(normalizeSeat) : [];
      state.summary = payload.summary || buildSummary(state.seats);
      state.selectedSeatId = null;

      renderRoomHeader();
      renderSeatMap();
      renderSeatSummary();
      renderSelectedSeat();
      updateRequestStatus('Seat layout loaded');
    } catch (error) {
      state.room = null;
      state.seats = [];
      state.summary = null;
      state.selectedSeatId = null;
      renderSeatState('Seat layout could not be loaded.', errorMessageFromException(error, 'Failed to load room seat layout.'));
      renderSeatSummary();
      renderSelectedSeat();
      updateRequestStatus('Seat layout unavailable');
      showToast(errorMessageFromException(error, 'Failed to load room seat layout.'), 'error');
    } finally {
      setLoading(false);
    }
  }

  function renderCinemaOptions() {
    if (!dom.cinemaFilter) {
      return;
    }

    dom.cinemaFilter.innerHTML = [
      '<option value="">Select Cinema</option>',
      ...state.cinemas.map(cinema => {
        const selected = String(cinema.id || '') === String(state.cinemaId || '') ? ' selected' : '';
        const label = cinema.city ? `${cinema.name} - ${cinema.city}` : cinema.name;
        return `<option value="${escapeHtmlAttr(cinema.id)}"${selected}>${escapeHtml(label || 'Cinema')}</option>`;
      }),
    ].join('');
  }

  function renderRoomOptions() {
    if (!dom.roomFilter) {
      return;
    }

    dom.roomFilter.innerHTML = [
      '<option value="">Select Room</option>',
      ...state.rooms.map(room => {
        const selected = String(room.id || '') === String(state.roomId || '') ? ' selected' : '';
        return `<option value="${escapeHtmlAttr(room.id)}"${selected}>${escapeHtml(room.room_name || 'Room')}</option>`;
      }),
    ].join('');
  }

  function renderRoomHeader() {
    if (!state.room) {
      renderSeatState('Select a room to load its seat map.', 'The live layout, seat status, and room capacity will appear here.');
      return;
    }

    if (dom.roomTitle) {
      dom.roomTitle.textContent = `${state.room.room_name || 'Room'} - ${state.room.cinema_name || 'Cinema'}`;
    }
    if (dom.roomMeta) {
      dom.roomMeta.textContent = `${roomTypeLabel(state.room.room_type)} - ${state.room.screen_label || 'Screen not set'} - ${Number(state.room.total_seats || 0)} seats`;
    }
    if (dom.layoutState) {
      dom.layoutState.hidden = true;
    }
    if (dom.map) {
      dom.map.hidden = false;
    }
  }

  function renderSeatState(title, message) {
    if (dom.layoutState) {
      dom.layoutState.hidden = false;
      dom.layoutState.innerHTML = `
        <strong>${escapeHtml(title)}</strong>
        <div class="table-meta-text">${escapeHtml(message)}</div>
      `;
    }
    if (dom.map) {
      dom.map.hidden = true;
      dom.map.innerHTML = '';
    }
    if (dom.roomTitle) {
      dom.roomTitle.textContent = 'Select a room';
    }
    if (dom.roomMeta) {
      dom.roomMeta.textContent = 'Choose a cinema and room to manage the persisted layout.';
    }
  }

  function renderSeatMap() {
    if (!dom.map || !state.room) {
      return;
    }

    if (state.seats.length === 0) {
      renderSeatState('This room has no seats yet.', 'Use the bulk layout tools to generate or append seats, then save the layout.');
      return;
    }

    const rows = groupSeatsByRow(state.seats);
    dom.map.hidden = false;
    dom.map.innerHTML = [
      '<div class="screen-bar"></div>',
      ...rows.map(row => `
        <div class="seat-row">
          <div class="seat-label">${escapeHtml(row.label)}</div>
          ${row.seats.map(seat => `
            <button
              class="seat ${seatClassName(seat)}"
              type="button"
              data-seat-id="${escapeHtmlAttr(seat.id)}"
              title="${escapeHtmlAttr(`${seat.label} - ${humanizeStatus(seat.seat_type)} - ${humanizeStatus(seat.status)}`)}"
            >${escapeHtml(seat.seat_number)}</button>
          `).join('')}
          <div class="seat-label">${escapeHtml(row.label)}</div>
        </div>
      `),
      `
        <div class="seat-legend">
          <div class="legend-item"><div class="legend-box normal"></div>Normal</div>
          <div class="legend-item"><div class="legend-box vip"></div>VIP</div>
          <div class="legend-item"><div class="legend-box couple"></div>Couple</div>
          <div class="legend-item"><div class="legend-box maintenance"></div>Maintenance</div>
          <div class="legend-item"><div class="legend-box disabled"></div>Disabled</div>
          <div class="legend-item"><div class="legend-box selected"></div>Selected</div>
        </div>
      `,
    ].join('');
  }

  function renderSeatSummary() {
    const summary = state.summary || buildSummary(state.seats);
    const total = Math.max(1, Number(summary.total || 0));
    const blocked = Number(summary.maintenance || 0) + Number(summary.disabled || 0);

    if (dom.totalStat) dom.totalStat.textContent = String(summary.total || 0);
    if (dom.vipStat) dom.vipStat.textContent = String(summary.vip || 0);
    if (dom.coupleStat) dom.coupleStat.textContent = String(summary.couple || 0);
    if (dom.blockedStat) dom.blockedStat.textContent = String(blocked);

    if (dom.totalProgress) dom.totalProgress.style.width = `${Math.min(100, Number(summary.available || 0) / total * 100)}%`;
    if (dom.vipProgress) dom.vipProgress.style.width = `${Math.min(100, Number(summary.vip || 0) / total * 100)}%`;
    if (dom.coupleProgress) dom.coupleProgress.style.width = `${Math.min(100, Number(summary.couple || 0) / total * 100)}%`;
    if (dom.blockedProgress) dom.blockedProgress.style.width = `${Math.min(100, blocked / total * 100)}%`;
  }

  function renderSelectedSeat() {
    const seat = findSelectedSeat();
    if (!seat) {
      if (dom.selectedSeatLabel) dom.selectedSeatLabel.value = 'Click a seat to select';
      if (dom.selectedSeatType) dom.selectedSeatType.value = 'normal';
      if (dom.selectedSeatStatus) dom.selectedSeatStatus.value = 'available';
      return;
    }

    if (dom.selectedSeatLabel) dom.selectedSeatLabel.value = `${seat.label} - ${humanizeStatus(seat.seat_type)} - ${humanizeStatus(seat.status)}`;
    if (dom.selectedSeatType) dom.selectedSeatType.value = seat.seat_type;
    if (dom.selectedSeatStatus) dom.selectedSeatStatus.value = seat.status;
  }

  function handleCinemaChange() {
    state.cinemaId = dom.cinemaFilter?.value || '';
    state.roomId = '';
    state.room = null;
    state.seats = [];
    state.summary = null;
    state.selectedSeatId = null;
    renderRoomOptions();
    renderSeatState('Loading rooms...', 'Please wait while we refresh the room list for this cinema.');
    loadRooms()
      .then(() => {
        state.roomId = resolveDefaultRoomId();
        renderRoomOptions();
        syncSelectionParams();

        if (state.roomId) {
          return loadSeatLayout(state.roomId);
        }

        renderSeatState('Select a room to load its seat map.', 'The live layout, seat status, and room capacity will appear here.');
        return null;
      })
      .catch(error => {
        renderSeatState('Rooms could not be loaded.', errorMessageFromException(error, 'Failed to load rooms for this cinema.'));
        showToast(errorMessageFromException(error, 'Failed to load rooms for this cinema.'), 'error');
      });
  }

  function handleRoomChange() {
    state.roomId = dom.roomFilter?.value || '';
    syncSelectionParams();
    if (!state.roomId) {
      state.room = null;
      state.seats = [];
      state.summary = null;
      state.selectedSeatId = null;
      renderSeatState('Select a room to load its seat map.', 'The live layout, seat status, and room capacity will appear here.');
      renderSeatSummary();
      renderSelectedSeat();
      return;
    }

    loadSeatLayout(state.roomId);
  }

  function resolveDefaultCinemaId() {
    const preferredCinema = state.cinemas.find(cinema => cinema.status === 'active' && Number(cinema.total_seats || 0) > 0)
      || state.cinemas.find(cinema => cinema.status === 'active' && Number(cinema.room_count || 0) > 0)
      || state.cinemas.find(cinema => cinema.status === 'active')
      || state.cinemas.find(cinema => cinema.status !== 'archived')
      || state.cinemas[0];

    return preferredCinema ? String(preferredCinema.id || '') : '';
  }

  function resolveDefaultRoomId() {
    const preferredRoom = state.rooms.find(room => room.status === 'active' && Number(room.total_seats || 0) > 0)
      || state.rooms.find(room => room.status === 'active')
      || state.rooms.find(room => room.status !== 'archived')
      || state.rooms[0];

    return preferredRoom ? String(preferredRoom.id || '') : '';
  }

  function syncSelectionParams() {
    const params = new URLSearchParams(window.location.search);

    if (state.cinemaId) {
      params.set('cinema_id', String(state.cinemaId));
    } else {
      params.delete('cinema_id');
    }

    if (state.roomId) {
      params.set('room_id', String(state.roomId));
    } else {
      params.delete('room_id');
    }

    const nextUrl = `${window.location.pathname}${params.toString() ? `?${params.toString()}` : ''}`;
    window.history.replaceState({}, '', nextUrl);
  }

  function handleSeatSelection(event) {
    const button = event.target.closest('[data-seat-id]');
    if (!button) {
      return;
    }

    state.selectedSeatId = String(button.dataset.seatId || '');
    renderSeatMap();
    renderSelectedSeat();
  }

  function updateSelectedSeat() {
    const seat = findSelectedSeat();
    if (!seat) {
      showToast('Select a seat before updating it.', 'warning');
      return;
    }

    seat.seat_type = dom.selectedSeatType?.value || seat.seat_type;
    seat.status = dom.selectedSeatStatus?.value || seat.status;
    syncDerivedState();
    showToast(`Seat ${seat.label} updated in the draft layout.`, 'success');
  }

  function removeSelectedSeat() {
    const seat = findSelectedSeat();
    if (!seat) {
      showToast('Select a seat before removing it.', 'warning');
      return;
    }

    state.seats = state.seats.filter(item => String(item.id) !== String(seat.id));
    state.selectedSeatId = null;
    syncDerivedState();
    showToast(`Seat ${seat.label} removed from the draft layout.`, 'success');
  }

  function generateLayout() {
    const rows = String(dom.presetRows?.value || '')
      .split(',')
      .map(value => value.trim().toUpperCase())
      .filter(Boolean);
    const seatsPerRow = Number.parseInt(String(dom.presetCount?.value || ''), 10);
    const seatType = dom.presetType?.value || 'normal';
    const seatStatus = dom.presetStatus?.value || 'available';

    if (rows.length === 0) {
      showToast('Enter at least one row label, for example A,B,C,D.', 'warning');
      return;
    }
    if (!Number.isInteger(seatsPerRow) || seatsPerRow < 1 || seatsPerRow > 40) {
      showToast('Seats per row must be between 1 and 40.', 'warning');
      return;
    }

    state.seats = [];
    rows.forEach((rowLabel, rowIndex) => {
      for (let seatNumber = 1; seatNumber <= seatsPerRow; seatNumber += 1) {
        state.seats.push(normalizeSeat({
          id: `draft-${rowIndex + 1}-${seatNumber}`,
          room_id: state.roomId || 0,
          seat_row: rowLabel,
          seat_number: seatNumber,
          seat_type: seatType,
          status: seatStatus,
        }));
      }
    });

    state.selectedSeatId = null;
    syncDerivedState();
    showToast('Draft seat layout regenerated.', 'success');
  }

  function appendRow() {
    const lastRow = getLastRowLabel(state.seats);
    const nextRow = incrementRowLabel(lastRow || 'A');
    const seatsPerRow = Number.parseInt(String(dom.presetCount?.value || ''), 10);
    const seatType = dom.presetType?.value || 'normal';
    const seatStatus = dom.presetStatus?.value || 'available';

    if (!Number.isInteger(seatsPerRow) || seatsPerRow < 1 || seatsPerRow > 40) {
      showToast('Seats per row must be between 1 and 40.', 'warning');
      return;
    }

    for (let seatNumber = 1; seatNumber <= seatsPerRow; seatNumber += 1) {
      state.seats.push(normalizeSeat({
        id: `draft-${nextRow}-${seatNumber}-${Date.now()}`,
        room_id: state.roomId || 0,
        seat_row: nextRow,
        seat_number: seatNumber,
        seat_type: seatType,
        status: seatStatus,
      }));
    }

    syncDerivedState();
    showToast(`Row ${nextRow} appended to the draft layout.`, 'success');
  }

  function clearLayout() {
    state.seats = [];
    state.summary = buildSummary([]);
    state.selectedSeatId = null;
    renderSeatState('This room has no seats yet.', 'Use the bulk layout tools to generate or append seats, then save the layout.');
    renderSeatSummary();
    renderSelectedSeat();
    showToast('Draft layout cleared.', 'info');
  }

  async function saveSeatLayout() {
    if (!state.roomId) {
      showToast('Select a room before saving the layout.', 'warning');
      return;
    }

    const payload = {
      seats: state.seats.map(seat => ({
        seat_row: seat.seat_row,
        seat_number: seat.seat_number,
        seat_type: seat.seat_type,
        status: seat.status,
      })),
    };

    setButtonBusy(dom.saveBtn, true, 'Saving...');
    updateRequestStatus('Saving seat layout...');

    try {
      const response = await adminApiRequest(`/api/admin/rooms/${state.roomId}/seats`, {
        method: 'PUT',
        body: payload,
      });

      await loadSeatLayout(state.roomId);
      showToast(response?.message || 'Seat layout updated successfully.', 'success');
    } catch (error) {
      updateRequestStatus('Seat layout save failed');
      showToast(errorMessageFromException(error, 'Failed to save seat layout.'), 'error');
    } finally {
      setButtonBusy(dom.saveBtn, false);
    }
  }

  function syncDerivedState() {
    state.summary = buildSummary(state.seats);
    renderRoomHeader();
    renderSeatMap();
    renderSeatSummary();
    renderSelectedSeat();
  }

  function buildSummary(seats) {
    return {
      total: seats.length,
      normal: seats.filter(seat => seat.seat_type === 'normal').length,
      vip: seats.filter(seat => seat.seat_type === 'vip').length,
      couple: seats.filter(seat => seat.seat_type === 'couple').length,
      available: seats.filter(seat => seat.status === 'available').length,
      maintenance: seats.filter(seat => seat.status === 'maintenance').length,
      disabled: seats.filter(seat => seat.status === 'disabled').length,
    };
  }

  function groupSeatsByRow(seats) {
    const grouped = new Map();

    seats
      .slice()
      .sort((left, right) => {
        const rowComparison = compareRowLabels(left.seat_row, right.seat_row);
        if (rowComparison !== 0) {
          return rowComparison;
        }

        return Number(left.seat_number || 0) - Number(right.seat_number || 0);
      })
      .forEach(seat => {
        const rowLabel = String(seat.seat_row || '').trim().toUpperCase() || 'A';
        if (!grouped.has(rowLabel)) {
          grouped.set(rowLabel, []);
        }
        grouped.get(rowLabel).push(seat);
      });

    return Array.from(grouped.entries()).map(([label, rowSeats]) => ({
      label,
      seats: rowSeats,
    }));
  }

  function seatClassName(seat) {
    const classes = [];
    if (String(state.selectedSeatId || '') === String(seat.id)) {
      classes.push('selected');
    } else if (seat.status === 'maintenance') {
      classes.push('maintenance');
    } else if (seat.status === 'disabled') {
      classes.push('disabled');
    } else {
      classes.push(seat.seat_type === 'vip' ? 'vip' : seat.seat_type === 'couple' ? 'couple' : 'normal');
    }

    return classes.join(' ');
  }

  function findSelectedSeat() {
    return state.seats.find(seat => String(seat.id) === String(state.selectedSeatId || '')) || null;
  }

  function normalizeSeat(seat) {
    const seatRow = String(seat.seat_row || '').trim().toUpperCase();
    const seatNumber = Number.parseInt(String(seat.seat_number || 0), 10) || 0;

    return {
      id: seat.id,
      room_id: seat.room_id,
      seat_row: seatRow,
      seat_number: seatNumber,
      label: `${seatRow}${seatNumber}`,
      seat_type: String(seat.seat_type || 'normal').trim().toLowerCase(),
      status: String(seat.status || 'available').trim().toLowerCase(),
    };
  }

  function roomTypeLabel(value) {
    const labels = {
      standard_2d: 'Standard 2D',
      premium_3d: 'Premium 3D',
      vip_recliner: 'VIP Recliner',
      imax: 'IMAX',
      '4dx': '4DX',
      screenx: 'ScreenX',
      dolby_atmos: 'Dolby Atmos',
    };

    return labels[value] || humanizeStatus(value);
  }

  function getLastRowLabel(seats) {
    if (!seats.length) {
      return '';
    }

    return seats
      .map(seat => String(seat.seat_row || '').trim().toUpperCase())
      .sort()
      .slice(-1)[0];
  }

  function incrementRowLabel(label) {
    const cleanLabel = String(label || 'A').trim().toUpperCase();
    if (!cleanLabel) {
      return 'A';
    }

    const characters = cleanLabel.split('');
    for (let index = characters.length - 1; index >= 0; index -= 1) {
      const code = characters[index].charCodeAt(0);
      if (code < 65 || code > 90) {
        return `${cleanLabel}A`;
      }
      if (code < 90) {
        characters[index] = String.fromCharCode(code + 1);
        for (let tailIndex = index + 1; tailIndex < characters.length; tailIndex += 1) {
          characters[tailIndex] = 'A';
        }

        return characters.join('');
      }

      characters[index] = 'A';
    }

    return `A${characters.join('')}`;
  }

  function compareRowLabels(left, right) {
    return rowLabelToIndex(left) - rowLabelToIndex(right);
  }

  function rowLabelToIndex(value) {
    const cleanValue = String(value || '')
      .trim()
      .toUpperCase()
      .replace(/[^A-Z]/g, '');

    if (!cleanValue) {
      return 0;
    }

    return cleanValue.split('').reduce((accumulator, character) => {
      return (accumulator * 26) + (character.charCodeAt(0) - 64);
    }, 0);
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

  document.addEventListener('DOMContentLoaded', initSeatManagementPage);
})();
