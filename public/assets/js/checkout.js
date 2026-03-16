(function () {
  const state = {
    showtimeId: 0,
    slug: '',
    seatIds: [],
    selectedFulfillment: 'e_ticket',
    selectedPayment: 'momo',
    preview: null,
    activeResume: null,
    isSubmitting: false,
    hasCommittedCheckout: false,
  };

  const dom = {};

  function initTicketCheckoutPage() {
    dom.state = document.getElementById('ticketCheckoutState');
    dom.content = document.getElementById('ticketCheckoutContent');
    if (!dom.state || !dom.content) {
      return;
    }

    cacheDom();
    bindEvents();
    hydrateQueryParams();

    if (!state.showtimeId || state.seatIds.length === 0) {
      renderStateCard(
        'Checking your pending checkout...',
        'Looking for any unfinished ticket payment tied to this browser session.'
      );
      void loadActiveCheckout({ showEmptyState: true });
      return;
    }

    renderStateCard('Loading checkout...', 'Confirming your held seats and pricing with the Ticket System.');
    void loadCheckoutPreview();
  }

  function cacheDom() {
    dom.backLink = document.getElementById('ticketCheckoutBackLink');
    dom.name = document.getElementById('ticketCheckoutName');
    dom.email = document.getElementById('ticketCheckoutEmail');
    dom.phone = document.getElementById('ticketCheckoutPhone');
    dom.poster = document.getElementById('ticketCheckoutPoster');
    dom.movieTitle = document.getElementById('ticketCheckoutMovieTitle');
    dom.venue = document.getElementById('ticketCheckoutVenue');
    dom.dateTime = document.getElementById('ticketCheckoutDateTime');
    dom.seatList = document.getElementById('ticketCheckoutSeatList');
    dom.status = document.getElementById('ticketCheckoutStatus');
    dom.hold = document.getElementById('ticketCheckoutHold');
    dom.subtotal = document.getElementById('ticketCheckoutSubtotal');
    dom.surcharge = document.getElementById('ticketCheckoutSurcharge');
    dom.fees = document.getElementById('ticketCheckoutFees');
    dom.total = document.getElementById('ticketCheckoutTotal');
    dom.submit = document.getElementById('ticketCheckoutSubmitBtn');
  }

  function bindEvents() {
    document.querySelectorAll('[data-group="fulfillment"]').forEach(option => {
      option.addEventListener('click', () => selectOption('fulfillment', option));
    });
    document.querySelectorAll('[data-group="payment"]').forEach(option => {
      option.addEventListener('click', () => selectOption('payment', option));
    });
    dom.backLink?.addEventListener('click', handleBackNavigation);
    dom.submit?.addEventListener('click', handleCreateOrder);
  }

  function hydrateQueryParams() {
    const params = new URLSearchParams(window.location.search);
    state.showtimeId = Number(params.get('showtime_id') || 0);
    state.slug = String(params.get('slug') || '').trim();
    state.seatIds = parseNumberList(params.get('seat_ids'));
  }

  async function loadCheckoutPreview() {
    try {
      const payload = await fetchJson('/api/ticket-orders/preview', {
        method: 'POST',
        body: {
          showtime_id: state.showtimeId,
          seat_ids: state.seatIds,
          fulfillment_method: state.selectedFulfillment,
          payment_method: state.selectedPayment,
        },
      });

      state.preview = payload?.data || null;
      state.activeResume = null;
      renderCheckoutPreview();
      void hydrateProfileDefaults();
    } catch (error) {
      const resumed = await loadActiveCheckout({ silent: true });
      if (resumed) {
        if (typeof showToast === 'function') {
          showToast('i', 'Checkout Restored', 'Your unfinished checkout was restored so you can continue payment.');
        }
        return;
      }

      renderStateCard(
        'Checkout unavailable.',
        error.message || 'The selected seat hold could not be confirmed.'
      );

      if (typeof showToast === 'function') {
        showToast('!', 'Checkout', error.message || 'Failed to load checkout preview.');
      }
    }
  }

  function renderCheckoutPreview() {
    if (!state.preview || !state.preview.showtime || !Array.isArray(state.preview.seats) || state.preview.seats.length === 0) {
      renderStateCard('Checkout unavailable.', 'The Ticket System preview did not return any held seats.');
      return;
    }

    renderPoster(state.preview.showtime);
    renderHeader(state.preview.showtime);
    renderOrderSummary();
    syncCheckoutControls();

    dom.state.innerHTML = state.activeResume?.resume_available
      ? buildResumeNotice()
      : '';
    dom.content.hidden = false;
    setSubmitBusy(false);
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

  function renderHeader(showtime) {
    if (dom.backLink) {
      dom.backLink.href = buildSeatSelectionUrl();
    }
    if (dom.movieTitle) {
      dom.movieTitle.textContent = showtime.movie_title || 'Movie Title';
    }
    if (dom.venue) {
      dom.venue.textContent = [showtime.cinema_name || 'Cinema', showtime.room_name || 'Room'].join(' - ');
    }
    if (dom.dateTime) {
      dom.dateTime.textContent = `${formatShowDate(showtime.show_date)} - ${formatTime(showtime.start_time)}`;
    }
  }

  function renderOrderSummary() {
    const order = state.preview?.order || {};
    const seats = Array.isArray(state.preview?.seats) ? state.preview.seats : [];

    if (dom.seatList) {
      dom.seatList.innerHTML = seats.map(seat => `<span class="seat-tag has-seat">${escapeHtml(seat.label)}</span>`).join('');
    }
    if (dom.status) {
      dom.status.textContent = humanizeStatus(order.status || 'paid');
    }
    if (dom.hold) {
      dom.hold.textContent = order.hold_expires_at ? formatDateTime(order.hold_expires_at) : 'Consumed on order creation';
    }
    if (dom.subtotal) {
      dom.subtotal.textContent = formatCurrency(order.subtotal_price);
    }
    if (dom.surcharge) {
      dom.surcharge.textContent = formatCurrency(sumSeats(seats, 'surcharge_amount'));
    }
    if (dom.fees) {
      dom.fees.textContent = formatCurrency(order.fee_amount);
    }
    if (dom.total) {
      dom.total.textContent = formatCurrency(order.total_price);
    }
  }

  function selectOption(group, selectedOption) {
    if (state.activeResume?.resume_available) {
      return;
    }

    document.querySelectorAll(`[data-group="${group}"]`).forEach(option => {
      option.classList.toggle('selected', option === selectedOption);
    });

    if (group === 'fulfillment') {
      state.selectedFulfillment = String(selectedOption.dataset.value || 'e_ticket');
    } else {
      state.selectedPayment = String(selectedOption.dataset.value || 'momo');
    }

    if (state.preview?.order) {
      state.preview.order.fulfillment_method = state.selectedFulfillment;
      state.preview.order.payment_method = state.selectedPayment;
    }

    setSubmitBusy(false);
  }

  async function hydrateProfileDefaults() {
    if (typeof getAuthToken !== 'function') {
      return;
    }

    const token = getAuthToken();
    if (!token) {
      return;
    }

    try {
      const payload = await fetchJson('/api/auth/profile');
      const profile = payload?.data || {};
      if (dom.name && !String(dom.name.value || '').trim()) {
        dom.name.value = String(profile.name || '').trim();
      }
      if (dom.email && !String(dom.email.value || '').trim()) {
        dom.email.value = String(profile.email || '').trim();
      }
      if (dom.phone && !String(dom.phone.value || '').trim()) {
        dom.phone.value = String(profile.phone || '').trim();
      }
    } catch (error) {
      // Optional profile hydration should not block checkout.
    }
  }

  async function handleCreateOrder() {
    if (state.isSubmitting) {
      return;
    }

    if (state.activeResume?.resume_available) {
      return handleResumeCheckout();
    }

    const name = String(dom.name?.value || '').trim();
    const email = String(dom.email?.value || '').trim();
    const phone = String(dom.phone?.value || '').trim();

    if (!name) {
      return showCheckoutError('Contact name is required.');
    }
    if (!email || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) {
      return showCheckoutError('A valid contact email is required.');
    }
    if (!phone || !/^[0-9+\s().-]{9,20}$/.test(phone)) {
      return showCheckoutError('A valid contact phone is required.');
    }

    setSubmitBusy(true);

    try {
      if (state.selectedPayment === 'vnpay') {
        const payload = await fetchJson('/api/payments/ticket-intents', {
          method: 'POST',
          body: {
            showtime_id: state.showtimeId,
            seat_ids: state.seatIds,
            contact_name: name,
            contact_email: email,
            contact_phone: phone,
            fulfillment_method: state.selectedFulfillment,
            payment_method: state.selectedPayment,
          },
        });

        const data = payload?.data || {};
        if (!data.redirect_url) {
          throw new Error('VNPay checkout URL is missing.');
        }

        state.hasCommittedCheckout = true;
        renderRedirectState(data.order || null, data.payment || null, data.redirect_url);
        window.location.href = data.redirect_url;
        return;
      }

      const payload = await fetchJson('/api/ticket-orders', {
        method: 'POST',
        body: {
          showtime_id: state.showtimeId,
          seat_ids: state.seatIds,
          contact_name: name,
          contact_email: email,
          contact_phone: phone,
          fulfillment_method: state.selectedFulfillment,
          payment_method: state.selectedPayment,
        },
      });

      const data = payload?.data || {};
      state.hasCommittedCheckout = true;
      renderSuccessState(data.order || null, Array.isArray(data.tickets) ? data.tickets : []);

      if (typeof showToast === 'function') {
        showToast('✓', 'Order Confirmed', 'Your tickets were created successfully.');
      }
    } catch (error) {
      showCheckoutError(error.message || 'Unable to create the ticket order.');
      void loadCheckoutPreview();
    } finally {
      setSubmitBusy(false);
    }
  }

  function renderSuccessState(order, tickets) {
    const movieTitle = order?.movie_title || 'Movie';
    const orderCode = order?.order_code || 'N/A';
    const seatLabels = Array.isArray(order?.seats) ? order.seats.join(', ') : '';
    const ticketCodes = Array.isArray(tickets) && tickets.length > 0
      ? tickets.map(ticket => escapeHtml(ticket.ticket_code)).join(', ')
      : 'Tickets are ready.';
    const canOpenHistory = Boolean(order?.user_id);

    dom.content.hidden = true;
    dom.state.innerHTML = `
      <div class="detail-state-card">
        <div>
          <strong>Order ${escapeHtml(orderCode)} confirmed.</strong>
          <div>${escapeHtml(movieTitle)}${seatLabels ? ` - Seats ${escapeHtml(seatLabels)}` : ''}</div>
          <div style="margin-top:8px;color:var(--text2);">Ticket codes: ${ticketCodes}</div>
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;">
            ${canOpenHistory ? `<a class="btn btn-primary btn-sm" href="${escapeHtmlAttr(appUrl('/my-tickets'))}">Open My Tickets</a>` : ''}
            <a class="btn btn-ghost btn-sm" href="${escapeHtmlAttr(appUrl('/movies'))}">Continue Browsing</a>
          </div>
        </div>
      </div>
    `;
  }

  function renderRedirectState(order, payment, redirectUrl) {
    const orderCode = order?.order_code || payment?.provider_order_ref || 'N/A';
    const paymentMethod = humanizeStatus(payment?.payment_method || state.selectedPayment);

    dom.content.hidden = true;
    dom.state.innerHTML = `
      <div class="detail-state-card">
        <div>
          <strong>Redirecting to ${escapeHtml(paymentMethod)}...</strong>
          <div>Order ${escapeHtml(orderCode)} was created in pending status and the checkout is being handed off to the payment gateway.</div>
          <div style="margin-top:8px;color:var(--text2);">If the redirect does not start automatically, use the secure link below.</div>
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;">
            <a class="btn btn-primary btn-sm" href="${escapeHtmlAttr(redirectUrl)}">Open VNPay Checkout</a>
            <a class="btn btn-ghost btn-sm" href="${escapeHtmlAttr(appUrl('/my-tickets'))}">My Tickets</a>
          </div>
        </div>
      </div>
    `;
  }

  function showCheckoutError(message) {
    if (typeof showToast === 'function') {
      showToast('!', 'Checkout', message);
    }
  }

  async function loadActiveCheckout(options = {}) {
    try {
      const payload = await fetchJson('/api/ticket-orders/active-checkout');
      const data = payload?.data || {};
      if (!data.resume_available) {
        state.activeResume = null;
        if (options.showEmptyState) {
          renderStateCard(
            'Checkout is missing ticket data.',
            'Select seats from a published showtime before opening the checkout screen.'
          );
        }
        return false;
      }

      applyActiveCheckout(data);
      renderCheckoutPreview();
      return true;
    } catch (error) {
      if (!options.silent) {
        renderStateCard(
          'Checkout unavailable.',
          error.message || 'The active checkout could not be restored right now.'
        );
      }

      return false;
    }
  }

  function applyActiveCheckout(data) {
    state.activeResume = data;
    state.preview = {
      showtime: data.showtime || null,
      seats: Array.isArray(data.seats) ? data.seats : [],
      order: data.order || null,
      payment: data.payment || null,
    };
    state.showtimeId = Number(data.order?.showtime_id || data.showtime?.id || 0);
    state.slug = String(data.order?.movie_slug || data.showtime?.movie_slug || state.slug || '').trim();
    state.seatIds = Array.isArray(data.seats)
      ? data.seats.map(seat => Number(seat.id)).filter(id => Number.isFinite(id) && id > 0)
      : [];
    state.selectedFulfillment = String(data.order?.fulfillment_method || state.selectedFulfillment);
    state.selectedPayment = String(data.payment?.payment_method || data.order?.payment_method || state.selectedPayment);
  }

  function syncCheckoutControls() {
    const order = state.preview?.order || {};
    const isResume = Boolean(state.activeResume?.resume_available);

    if (dom.name) {
      dom.name.value = String(order.contact_name || dom.name.value || '').trim();
      dom.name.readOnly = isResume;
    }
    if (dom.email) {
      dom.email.value = String(order.contact_email || dom.email.value || '').trim();
      dom.email.readOnly = isResume;
    }
    if (dom.phone) {
      dom.phone.value = String(order.contact_phone || dom.phone.value || '').trim();
      dom.phone.readOnly = isResume;
    }

    document.querySelectorAll('[data-group="fulfillment"]').forEach(option => {
      const selected = String(option.dataset.value || '') === state.selectedFulfillment;
      option.classList.toggle('selected', selected);
      option.disabled = isResume;
    });

    document.querySelectorAll('[data-group="payment"]').forEach(option => {
      const selected = String(option.dataset.value || '') === state.selectedPayment;
      option.classList.toggle('selected', selected);
      option.disabled = isResume;
    });
  }

  function buildResumeNotice() {
    const order = state.preview?.order || {};
    const seats = Array.isArray(order.seats) ? order.seats.join(', ') : 'N/A';
    const redirectUrl = String(state.activeResume?.redirect_url || '').trim();
    const expiresAt = String(state.activeResume?.resume_expires_at || order.hold_expires_at || '').trim();
    const expiryLabel = expiresAt ? formatDateTime(expiresAt) : 'Soon';

    return `
      <div class="detail-state-card">
        <div>
          <strong>Unfinished checkout restored.</strong>
          <div>Order ${escapeHtml(order.order_code || 'N/A')} is still waiting for payment for seats ${escapeHtml(seats)}.</div>
          <div style="margin-top:8px;color:var(--text2);">This checkout will remain available until ${escapeHtml(expiryLabel)}.</div>
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:18px;">
            ${redirectUrl ? `<a class="btn btn-primary btn-sm" href="${escapeHtmlAttr(redirectUrl)}">Continue to VNPay</a>` : ''}
            <a class="btn btn-ghost btn-sm" href="${escapeHtmlAttr(buildSeatSelectionUrl())}">Back to Seat Selection</a>
          </div>
        </div>
      </div>
    `;
  }

  function handleResumeCheckout() {
    const redirectUrl = String(state.activeResume?.redirect_url || '').trim();
    if (!redirectUrl) {
      return showCheckoutError('This pending checkout can no longer be resumed. Please return to seat selection.');
    }

    state.hasCommittedCheckout = true;
    renderRedirectState(state.preview?.order || null, state.preview?.payment || null, redirectUrl);
    window.location.href = redirectUrl;
  }

  async function handleBackNavigation(event) {
    if (state.hasCommittedCheckout || state.activeResume?.resume_available || !state.showtimeId || state.seatIds.length === 0) {
      return;
    }

    event.preventDefault();

    try {
      await releaseHeldSeats();
    } catch (error) {
      // Releasing a temporary hold should not trap the user on the page.
    }

    window.location.href = dom.backLink?.href || buildSeatSelectionUrl();
  }

  async function releaseHeldSeats() {
    await fetchJson(`/api/tickets/holds/${encodeURIComponent(state.showtimeId)}`, {
      method: 'DELETE',
    });
  }

  function setSubmitBusy(isBusy) {
    state.isSubmitting = Boolean(isBusy);
    if (!dom.submit) {
      return;
    }

    dom.submit.disabled = state.isSubmitting;
    if (state.isSubmitting) {
      dom.submit.textContent = state.selectedPayment === 'vnpay' ? 'Redirecting to VNPay...' : 'Creating Ticket Order...';
      return;
    }

    if (state.activeResume?.resume_available) {
      dom.submit.textContent = state.activeResume.resume_target === 'payment'
        ? 'Continue to VNPay'
        : 'Open Pending Checkout';
      return;
    }

    dom.submit.textContent = state.selectedPayment === 'vnpay' ? 'Continue to VNPay' : 'Complete Ticket Order';
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

  function renderStateCard(title, message) {
    dom.content.hidden = true;
    dom.state.innerHTML = `
      <div class="detail-state-card">
        <div>
          <strong>${escapeHtml(title)}</strong>
          <div>${escapeHtml(message)}</div>
        </div>
      </div>
    `;
  }

  function buildSeatSelectionUrl() {
    const params = new URLSearchParams();
    params.set('showtime_id', String(state.showtimeId));
    if (state.seatIds.length > 0) {
      params.set('seat_ids', state.seatIds.join(','));
    }
    if (state.slug) {
      params.set('slug', state.slug);
    }

    return `${appUrl('/seat-selection')}?${params.toString()}`;
  }

  function parseNumberList(value) {
    return String(value || '')
      .split(',')
      .map(item => Number(item.trim()))
      .filter(item => Number.isFinite(item) && item > 0);
  }

  function sumSeats(seats, key) {
    return seats.reduce((total, seat) => total + Number(seat?.[key] || 0), 0);
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
    const parsed = new Date(`1970-01-01T${String(value || '').trim()}`);
    if (Number.isNaN(parsed.getTime())) {
      return String(value || 'TBA');
    }

    return new Intl.DateTimeFormat('en-US', {
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    }).format(parsed);
  }

  function formatCurrency(value) {
    return new Intl.NumberFormat('vi-VN', {
      style: 'currency',
      currency: 'VND',
      maximumFractionDigits: 0,
    }).format(Number(value || 0));
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

  function humanizeStatus(value) {
    return String(value || '')
      .trim()
      .replace(/_/g, ' ')
      .replace(/\b\w/g, letter => letter.toUpperCase());
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

  function appUrl(path) {
    const basePath = typeof window.APP_BASE_PATH === 'string' ? window.APP_BASE_PATH : '';
    const normalizedPath = String(path || '').startsWith('/') ? path : `/${path}`;
    return `${basePath}${normalizedPath}`;
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

  document.addEventListener('DOMContentLoaded', initTicketCheckoutPage);
})();
