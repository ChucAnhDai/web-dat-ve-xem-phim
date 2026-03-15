(function () {
  const state = {
    tickets: [],
    currentFilter: 'all',
    isLoading: false,
  };

  function initMyTicketsPage() {
    const grid = document.getElementById('ticketsGrid');
    if (!grid) {
      return;
    }

    bindFilters();
    void loadTickets();
  }

  function bindFilters() {
    document.querySelectorAll('[data-filter]').forEach(chip => {
      chip.addEventListener('click', () => {
        document.querySelectorAll('[data-filter]').forEach(item => item.classList.remove('active'));
        chip.classList.add('active');
        state.currentFilter = chip.dataset.filter || 'all';
        renderTickets();
      });
    });
  }

  async function loadTickets() {
    const grid = document.getElementById('ticketsGrid');
    if (!grid) {
      return;
    }

    const token = typeof getAuthToken === 'function' ? getAuthToken() : '';
    if (!token) {
      grid.innerHTML = `
        <div class="detail-state-card" style="grid-column:1/-1">
          <div>
            <strong>Sign in to view your tickets.</strong>
            <div>Your purchased tickets and QR payloads appear here after login.</div>
          </div>
        </div>
      `;
      return;
    }

    state.isLoading = true;
    grid.innerHTML = '<p style="color:var(--text2);padding:40px 0;text-align:center;grid-column:1/-1">Loading your tickets...</p>';

    try {
      const payload = await fetchJson('/api/me/tickets?per_page=100');
      state.tickets = Array.isArray(payload?.data?.items) ? payload.data.items : [];
      renderTickets();
    } catch (error) {
      if (error.status === 401 && typeof clearAuthToken === 'function') {
        clearAuthToken();
      }

      grid.innerHTML = `
        <div class="detail-state-card" style="grid-column:1/-1">
          <div>
            <strong>Ticket history unavailable.</strong>
            <div>${escapeHtml(error.message || 'Failed to load your tickets.')}</div>
          </div>
        </div>
      `;
    } finally {
      state.isLoading = false;
    }
  }

  function renderTickets() {
    const grid = document.getElementById('ticketsGrid');
    if (!grid) {
      return;
    }

    const list = state.tickets.filter(ticket => {
      if (state.currentFilter === 'all') {
        return true;
      }
      if (state.currentFilter === 'issue') {
        return ['cancelled', 'refunded', 'expired'].includes(String(ticket.status || '').toLowerCase());
      }

      return String(ticket.status || '').toLowerCase() === state.currentFilter;
    });

    if (list.length === 0) {
      grid.innerHTML = '<p style="color:var(--text2);padding:40px 0;text-align:center;grid-column:1/-1">No tickets match this filter.</p>';
      return;
    }

    grid.innerHTML = list.map(ticket => `
      <div class="ticket-card">
        <span class="ticket-status-badge" style="${escapeHtmlAttr(ticketBadgeStyle(ticket.status))}">${escapeHtml(humanizeStatus(ticket.status))}</span>
        <div class="ticket-top">
          <div class="ticket-poster"><img src="${escapeHtmlAttr(ticket.poster_url || '')}" alt="${escapeHtmlAttr(ticket.movie_title || 'Ticket poster')}" onerror="this.parentNode.style.background='var(--bg4)'"></div>
          <div>
            <div class="ticket-movie">${escapeHtml(ticket.movie_title || 'Movie')}</div>
            <div class="ticket-meta">
              <p>${escapeHtml(ticket.cinema_name || 'Cinema')} - ${escapeHtml(ticket.room_name || 'Room')}</p>
              <p>${escapeHtml(formatShowDate(ticket.show_date))}</p>
              <p>${escapeHtml(formatTime(ticket.start_time))}</p>
            </div>
          </div>
        </div>
        <div class="ticket-bottom">
          <div style="display:flex;flex-direction:column;gap:8px;">
            <div>
              <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">Ticket</div>
              <div class="ticket-seats">
                <span class="ticket-seat-tag">${escapeHtml(ticket.ticket_code || '')}</span>
                <span class="ticket-seat-tag">${escapeHtml(ticket.seat_label || '')}</span>
              </div>
            </div>
            <div class="td-muted">${escapeHtml(ticket.order_code || '')} - ${escapeHtml(humanizeStatus(ticket.fulfillment_method))} - ${escapeHtml(formatCurrency(ticket.price))}</div>
          </div>
          <button class="qr-code" type="button" onclick='copyQrPayload("${escapeJs(ticket.qr_payload || "")}")'>QR</button>
        </div>
      </div>
    `).join('');
  }

  async function fetchJson(path) {
    const token = typeof getAuthToken === 'function' ? getAuthToken() : '';
    const response = await fetch(appUrl(path), {
      headers: {
        Accept: 'application/json',
        Authorization: token ? `Bearer ${token}` : '',
      },
      cache: 'no-store',
      credentials: 'same-origin',
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      const error = new Error(firstErrorMessage(payload?.errors, payload?.message || 'Request failed.'));
      error.status = response.status;
      throw error;
    }

    return payload || {};
  }

  function copyQrPayload(payload) {
    if (!payload) {
      return;
    }

    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(payload).catch(() => {});
    }

    if (typeof showToast === 'function') {
      showToast('i', 'Ticket Payload', 'QR payload copied to clipboard.');
    }
  }

  function formatShowDate(value) {
    const parsed = new Date(`${value}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) {
      return String(value || '');
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
      return String(value || '');
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

  function humanizeStatus(value) {
    const normalized = String(value || '').trim().toLowerCase();
    if (normalized === 'e_ticket') {
      return 'E Ticket';
    }
    if (normalized === 'counter_pickup') {
      return 'Counter Pickup';
    }

    return normalized
      .replace(/_/g, ' ')
      .replace(/\b\w/g, letter => letter.toUpperCase());
  }

  function ticketBadgeStyle(status) {
    const normalized = String(status || '').trim().toLowerCase();
    if (normalized === 'cancelled' || normalized === 'refunded' || normalized === 'expired') {
      return 'background:rgba(239,68,68,0.12);color:#fca5a5;border:1px solid rgba(239,68,68,0.25);';
    }
    if (normalized === 'pending') {
      return 'background:rgba(245,158,11,0.12);color:#fcd34d;border:1px solid rgba(245,158,11,0.25);';
    }

    return 'background:rgba(34,197,94,0.12);color:#86efac;border:1px solid rgba(34,197,94,0.25);';
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

  function escapeJs(value) {
    return String(value ?? '')
      .replace(/\\/g, '\\\\')
      .replace(/"/g, '\\"')
      .replace(/'/g, "\\'");
  }

  window.copyQrPayload = copyQrPayload;

  document.addEventListener('DOMContentLoaded', initMyTicketsPage);
})();
