(function () {
  const MAX_SEATS_PER_HOLD = 6;
  const SURCHARGES = {
    normal: 0,
    vip: 15000,
    couple: 30000,
  };

  const state = {
    showtimeId: 0,
    slug: '',
    showtime: null,
    seats: [],
    requestedSeatIds: [],
    selectedSeatIds: [],
    holdExpiresAt: null,
    isSubmittingHold: false,
    initialized: false,
  };

  const dom = {};

  function initSeatSelectionPage() {
    if (state.initialized || document.body?.dataset?.activePage !== 'movies') {
      return;
    }

    cacheDom();
    if (!dom.state || !dom.content || !dom.grid) {
      return;
    }

    state.initialized = true;
    bindEvents();

    const params = new URLSearchParams(window.location.search);
    state.showtimeId = Number(params.get('showtime_id') || 0);
    state.slug = String(params.get('slug') || '').trim();
    state.requestedSeatIds = parseNumberList(params.get('seat_ids'));

    if (!Number.isFinite(state.showtimeId) || state.showtimeId <= 0) {
      renderStateCard('Showtime not selected.', 'Choose a screening from the movie detail page to continue with seat selection.');
      return;
    }

    renderStateCard('Loading seat map...', 'Please wait while we load the latest seat availability for this screening.');
    void loadSeatMap();
  }

  function cacheDom() {
    dom.state = document.getElementById('seatSelectionState');
    dom.content = document.getElementById('seatSelectionContent');
    dom.backLink = document.getElementById('seatSelectionBackLink');
    dom.subtitle = document.getElementById('seatSelectionSubtitle');
    dom.grid = document.getElementById('seatGrid');
    dom.poster = document.getElementById('seatSelectionPoster');
    dom.movieTitle = document.getElementById('seatSelectionMovieTitle');
    dom.venue = document.getElementById('seatSelectionVenue');
    dom.dateTime = document.getElementById('seatSelectionDateTime');
    dom.basePrice = document.getElementById('seatBasePrice');
    dom.surcharge = document.getElementById('seatSurcharge');
    dom.seatCount = document.getElementById('seatCount');
    dom.total = document.getElementById('seatTotal');
    dom.selectedSeats = document.getElementById('selectedSeatsDisplay');
    dom.checkoutBtn = document.getElementById('seatSelectionCheckoutBtn');
  }

  function bindEvents() {
    dom.grid?.addEventListener('click', handleSeatClick);
    dom.checkoutBtn?.addEventListener('click', proceedCheckout);
  }

  async function loadSeatMap() {
    const preferredSeatIds = state.selectedSeatIds.length > 0
      ? [...state.selectedSeatIds]
      : [...state.requestedSeatIds];

    try {
      const payload = await fetchJson(`/api/showtimes/${encodeURIComponent(state.showtimeId)}/seat-map`);
      const data = payload?.data || {};

      state.showtime = data.showtime || null;
      state.seats = Array.isArray(data.seats) ? data.seats : [];
      state.holdExpiresAt = currentSessionHoldExpiry(state.seats);
      state.selectedSeatIds = restoreSeatSelection(preferredSeatIds);

      renderSeatSelection();
    } catch (error) {
      renderStateCard(
        'Seat map unavailable.',
        error.message || 'This screening could not be loaded right now.'
      );

      if (typeof showToast === 'function') {
        showToast('i', 'Seat Selection', error.message || 'Failed to load seat map.');
      }
    }
  }

  function renderSeatSelection() {
    const showtime = state.showtime || {};
    document.title = `${showtime.movie_title || 'Seat Selection'} - CinemaX`;

    renderHeader(showtime);
    renderPoster(showtime);
    renderSeatGrid();
    updateSummary();

    if (dom.state) {
      dom.state.innerHTML = '';
    }
    if (dom.content) {
      dom.content.hidden = false;
    }
  }

  function renderHeader(showtime) {
    const backUrl = buildBackToMovieUrl(showtime.movie_slug || state.slug);
    if (dom.backLink) {
      dom.backLink.href = backUrl;
    }

    const scheduleLabel = `${formatShowDate(showtime.show_date)} - ${formatTime(showtime.start_time)}`;
    const holdSuffix = state.holdExpiresAt
      ? ` - Hold active until ${formatDateTime(state.holdExpiresAt)}`
      : '';
    const subtitle = `${showtime.movie_title || 'Movie'} - ${buildVenueLabel(showtime)} - ${scheduleLabel}${holdSuffix}`;

    if (dom.subtitle) {
      dom.subtitle.textContent = subtitle;
    }

    if (dom.movieTitle) {
      dom.movieTitle.textContent = showtime.movie_title || 'Movie Title';
    }
    if (dom.venue) {
      dom.venue.textContent = buildVenueLabel(showtime);
    }
    if (dom.dateTime) {
      dom.dateTime.textContent = scheduleLabel;
    }
  }

  function renderPoster(showtime) {
    if (!dom.poster) {
      return;
    }

    const posterUrl = String(showtime.poster_url || '').trim();
    if (!posterUrl) {
      dom.poster.innerHTML = '<div class="product-img-fallback" style="font-size:24px;height:100%">MV</div>';
      return;
    }

    dom.poster.innerHTML = `<img src="${escapeHtmlAttr(posterUrl)}" alt="${escapeHtmlAttr(showtime.movie_title || 'Movie poster')}" loading="lazy">`;
  }

  function renderSeatGrid() {
    if (!dom.grid) {
      return;
    }

    if (state.seats.length === 0) {
      dom.grid.innerHTML = `
        <div class="detail-state-card detail-state-card-compact" style="width:100%;">
          <div>
            <strong>No seat map published.</strong>
            <div>This screening does not have a seat layout available yet.</div>
          </div>
        </div>
      `;
      return;
    }

    const rows = groupSeatsByRow(state.seats);
    dom.grid.innerHTML = rows.map(renderSeatRow).join('');
  }

  function renderSeatRow(row) {
    const midpoint = Math.ceil(row.seats.length / 2);

    return `
      <div class="seat-row">
        <div class="row-label">${escapeHtml(row.label)}</div>
        ${row.seats.map((seat, index) => `
          ${index === midpoint ? '<div class="seat-aisle"></div>' : ''}
          <button
            class="seat ${seatClassName(seat)}"
            type="button"
            data-seat-id="${escapeHtmlAttr(seat.id)}"
            ${seat.is_selectable ? '' : 'disabled'}
            title="${escapeHtmlAttr(`${seat.label} - ${humanizeSeatType(seat.type)} - ${humanizeSeatStatus(seat)}`)}"
          >
            ${escapeHtml(seat.number)}
          </button>
        `).join('')}
      </div>
    `;
  }

  function handleSeatClick(event) {
    const button = event.target.closest('[data-seat-id]');
    if (!button || button.disabled) {
      return;
    }

    const seatId = Number(button.dataset.seatId || 0);
    if (!Number.isFinite(seatId) || seatId <= 0) {
      return;
    }

    const index = state.selectedSeatIds.indexOf(seatId);
    if (index === -1) {
      if (state.selectedSeatIds.length >= MAX_SEATS_PER_HOLD) {
        if (typeof showToast === 'function') {
          showToast('!', 'Seat Limit', `A single hold can include up to ${MAX_SEATS_PER_HOLD} seats.`);
        }
        return;
      }
      state.selectedSeatIds.push(seatId);
    } else {
      state.selectedSeatIds.splice(index, 1);
    }

    renderSeatGrid();
    updateSummary();
  }

  function updateSummary() {
    const basePrice = Number(state.showtime?.price || 0);
    const selectedSeats = state.seats.filter(seat => state.selectedSeatIds.includes(Number(seat.id)));
    const surcharge = selectedSeats.reduce((total, seat) => total + surchargeForSeat(seat.type), 0);
    const total = selectedSeats.length * basePrice + surcharge;

    if (dom.basePrice) {
      dom.basePrice.textContent = formatCurrency(basePrice);
    }
    if (dom.surcharge) {
      dom.surcharge.textContent = formatCurrency(surcharge);
    }
    if (dom.seatCount) {
      dom.seatCount.textContent = String(selectedSeats.length);
    }
    if (dom.total) {
      dom.total.textContent = formatCurrency(total);
    }
    if (dom.selectedSeats) {
      dom.selectedSeats.innerHTML = selectedSeats.length
        ? selectedSeats.map(seat => `<span class="seat-tag has-seat">${escapeHtml(seat.label)}</span>`).join('')
        : '<span class="seat-tag">None selected</span>';
    }
  }

  async function proceedCheckout() {
    if (state.isSubmittingHold) {
      return;
    }

    const selectedSeats = state.seats.filter(seat => state.selectedSeatIds.includes(Number(seat.id)));
    if (selectedSeats.length === 0) {
      if (typeof showToast === 'function') {
        showToast('i', 'No Seats', 'Please select at least one seat.');
      }
      return;
    }
    if (selectedSeats.length > MAX_SEATS_PER_HOLD) {
      if (typeof showToast === 'function') {
        showToast('!', 'Seat Limit', `A single hold can include up to ${MAX_SEATS_PER_HOLD} seats.`);
      }
      return;
    }

    setCheckoutBusy(true);

    try {
      const payload = await fetchJson('/api/tickets/holds', {
        method: 'POST',
        body: {
          showtime_id: state.showtimeId,
          seat_ids: selectedSeats.map(seat => Number(seat.id)),
        },
      });
      const hold = payload?.data || {};
      const seatIds = Array.isArray(hold.seat_ids) && hold.seat_ids.length > 0
        ? hold.seat_ids
        : selectedSeats.map(seat => Number(seat.id));
      const seatLabels = Array.isArray(hold.seat_labels) && hold.seat_labels.length > 0
        ? hold.seat_labels
        : selectedSeats.map(seat => seat.label);

      state.requestedSeatIds = seatIds.map(value => Number(value)).filter(value => Number.isFinite(value) && value > 0);
      state.selectedSeatIds = [...state.requestedSeatIds];
      state.holdExpiresAt = String(hold.hold_expires_at || '').trim() || null;

      if (typeof showToast === 'function') {
        const expiryLabel = state.holdExpiresAt ? ` Held until ${formatDateTime(state.holdExpiresAt)}.` : '';
        showToast('i', 'Seats Added to Cart', `${seatLabels.length} seat(s) secured and added to your cart.${expiryLabel}`);
      }

      window.setTimeout(() => {
        window.location.href = appUrl('/cart');
      }, 250);
    } catch (error) {
      if (/checkout waiting for payment/i.test(String(error.message || ''))) {
        const resumed = await redirectToActiveCheckout();
        if (resumed) {
          return;
        }
      }

      if (typeof showToast === 'function') {
        showToast('!', 'Seat Hold Failed', error.message || 'Unable to hold the selected seats.');
      }
      await loadSeatMap();
    } finally {
      setCheckoutBusy(false);
    }
  }

  function groupSeatsByRow(seats) {
    const grouped = new Map();

    seats.forEach(seat => {
      const rowLabel = String(seat.row || '').trim().toUpperCase() || 'A';
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
    if (state.selectedSeatIds.includes(Number(seat.id))) {
      return 'selected';
    }
    if (seat.is_booked) {
      return 'booked';
    }
    if (seat.is_payment_pending && seat.pending_by_current_session) {
      return 'payment-pending-current';
    }
    if (seat.is_payment_pending) {
      return 'payment-pending';
    }
    if (seat.is_held && !seat.held_by_current_session) {
      return 'held';
    }
    if (seat.is_held && seat.held_by_current_session) {
      return 'held-current';
    }
    if (seat.status === 'maintenance') {
      return 'maintenance';
    }
    if (seat.status === 'disabled') {
      return 'disabled';
    }
    if (seat.type === 'vip') {
      return 'vip';
    }
    if (seat.type === 'couple') {
      return 'couple';
    }

    return 'available';
  }

  function restoreSeatSelection(preferredSeatIds) {
    if (!Array.isArray(preferredSeatIds) || preferredSeatIds.length === 0) {
      return [];
    }

    const selectableSeatIds = new Set(
      state.seats
        .filter(seat => seat.is_selectable)
        .map(seat => Number(seat.id))
    );

    return preferredSeatIds.filter(seatId => selectableSeatIds.has(Number(seatId)));
  }

  function currentSessionHoldExpiry(seats) {
    const heldSeats = Array.isArray(seats)
      ? seats.filter(seat => (
        (seat.held_by_current_session && seat.hold_expires_at)
        || (seat.pending_by_current_session && seat.pending_expires_at)
      ))
      : [];

    if (heldSeats.length === 0) {
      return null;
    }

    heldSeats.sort((left, right) => {
      const leftExpiry = String(left.pending_expires_at || left.hold_expires_at || '');
      const rightExpiry = String(right.pending_expires_at || right.hold_expires_at || '');
      return leftExpiry.localeCompare(rightExpiry);
    });

    return String(heldSeats[0].pending_expires_at || heldSeats[0].hold_expires_at || '').trim() || null;
  }

  function setCheckoutBusy(isBusy) {
    state.isSubmittingHold = Boolean(isBusy);
    if (!dom.checkoutBtn) {
      return;
    }

    dom.checkoutBtn.disabled = state.isSubmittingHold;
    dom.checkoutBtn.textContent = state.isSubmittingHold ? 'Holding Seats...' : 'Add to Cart';
  }

  function surchargeForSeat(type) {
    return Number(SURCHARGES[String(type || 'normal')] || 0);
  }

  function buildBackToMovieUrl(slug) {
    const cleanSlug = String(slug || '').trim();
    if (!cleanSlug) {
      return appUrl('/movies');
    }

    return `${appUrl('/movie-detail')}?slug=${encodeURIComponent(cleanSlug)}#movieDetailShowtimesSection`;
  }

  async function fetchJson(path, options = {}) {
    const headers = Object.assign({
      Accept: 'application/json',
    }, options.headers || {});
    const token = typeof getAuthToken === 'function' ? getAuthToken() : '';
    if (token && !headers.Authorization) {
      headers.Authorization = `Bearer ${token}`;
    }
    const init = {
      method: String(options.method || 'GET').toUpperCase(),
      headers,
      cache: 'no-store',
      credentials: 'same-origin',
    };

    if (Object.prototype.hasOwnProperty.call(options, 'body')) {
      if (!Object.prototype.hasOwnProperty.call(headers, 'Content-Type')) {
        headers['Content-Type'] = 'application/json';
      }
      init.body = typeof options.body === 'string' ? options.body : JSON.stringify(options.body);
    }

    const response = await fetch(appUrl(path), init);

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      throw new Error(firstErrorMessage(payload?.errors, payload?.message || 'Request failed.'));
    }

    return payload || {};
  }

  async function redirectToActiveCheckout() {
    try {
      const payload = await fetchJson('/api/ticket-orders/active-checkout');
      if (!payload?.data?.resume_available) {
        return false;
      }

      if (typeof showToast === 'function') {
        showToast('i', 'Resume Checkout', 'Your previous checkout is still active. Restoring it now.');
      }

      window.location.href = appUrl('/shop/checkout');
      return true;
    } catch (error) {
      return false;
    }
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

  function appUrl(path) {
    const basePath = typeof window.APP_BASE_PATH === 'string' ? window.APP_BASE_PATH : '';
    const normalizedPath = String(path || '').startsWith('/') ? path : `/${path}`;
    return `${basePath}${normalizedPath}`;
  }

  function buildVenueLabel(showtime) {
    return [showtime?.cinema_name || 'Cinema', showtime?.room_name || 'Room']
      .filter(Boolean)
      .join(' - ');
  }

  function formatShowDate(value) {
    if (!value) {
      return 'TBA';
    }

    const parsed = new Date(`${value}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) {
      return String(value);
    }

    return new Intl.DateTimeFormat('en-GB', {
      weekday: 'short',
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(parsed);
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

  function formatCurrency(value) {
    const amount = Number(value || 0);
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: 'VND',
      maximumFractionDigits: 0,
    }).format(amount);
  }

  function humanizeSeatType(value) {
    const type = String(value || 'normal').trim().toLowerCase();
    if (type === 'vip') {
      return 'VIP';
    }
    if (type === 'couple') {
      return 'Couple';
    }

    return 'Standard';
  }

  function humanizeSeatStatus(seat) {
    if (seat?.is_booked) {
      return 'Paid';
    }
    if (seat?.is_payment_pending && seat?.pending_by_current_session) {
      return 'Pending payment by you';
    }
    if (seat?.is_payment_pending) {
      return 'Pending payment';
    }
    if (seat?.is_held && seat?.held_by_current_session) {
      return 'Held by you';
    }
    if (seat?.is_held) {
      return 'Held by another customer';
    }

    const value = String(seat?.status || 'available').trim().toLowerCase();
    if (value === 'maintenance') {
      return 'Maintenance';
    }
    if (value === 'disabled') {
      return 'Disabled';
    }

    return 'Available';
  }

  function parseNumberList(value) {
    return String(value || '')
      .split(',')
      .map(item => Number(item.trim()))
      .filter(item => Number.isFinite(item) && item > 0);
  }

  function formatDateTime(value) {
    const normalized = String(value || '').trim().replace(' ', 'T');
    if (!normalized) {
      return 'TBA';
    }

    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) {
      return String(value);
    }

    return new Intl.DateTimeFormat('vi-VN', {
      hour: '2-digit',
      minute: '2-digit',
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    }).format(parsed);
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

  document.addEventListener('DOMContentLoaded', initSeatSelectionPage);
})();
