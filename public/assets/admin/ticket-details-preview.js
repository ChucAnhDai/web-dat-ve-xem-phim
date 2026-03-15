(function () {
  const state = {
    tickets: [],
    meta: { page: 1, per_page: 20, total: 0, total_pages: 1 },
    filters: {
      search: '',
      status: '',
    },
    holds: [],
    isLoading: false,
  };

  let searchTimer = null;

  function initTicketDetailsPage() {
    const tableBody = document.getElementById('ticketDetailsBody');
    if (!tableBody) {
      return;
    }

    bindFilters();
    window.handleTicketSectionAction = () => loadTicketData(true);
    window.openTicketDetail = openTicketDetail;
    window.loadTicketDetailsPage = loadTicketDetailsPage;

    void loadTicketData(true);
  }

  function bindFilters() {
    const searchInput = document.getElementById('ticketDetailSearch');
    const statusFilter = document.getElementById('ticketDetailStatus');

    searchInput?.addEventListener('input', event => {
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(() => {
        state.filters.search = String(event.target.value || '').trim();
        state.meta.page = 1;
        void loadTicketData();
      }, 180);
    });

    statusFilter?.addEventListener('change', event => {
      state.filters.status = String(event.target.value || '').trim();
      state.meta.page = 1;
      void loadTicketData();
    });
  }

  async function loadTicketData(showRefreshToast = false) {
    if (state.isLoading) {
      return;
    }

    state.isLoading = true;
    renderTicketLoading();
    renderHoldLoading();

    try {
      const [ticketPayload, holdPayload] = await Promise.all([
        adminApiRequest('/api/admin/ticket-details', {
          query: {
            search: state.filters.search,
            status: state.filters.status,
            page: state.meta.page,
            per_page: state.meta.per_page,
          },
          useStoredToken: true,
        }),
        adminApiRequest('/api/admin/ticket-holds', {
          query: { limit: 20 },
          useStoredToken: true,
        }),
      ]);

      const ticketData = ticketPayload?.data || {};
      state.tickets = Array.isArray(ticketData.items) ? ticketData.items : [];
      state.meta = ticketData.meta || state.meta;
      state.holds = Array.isArray(holdPayload?.data?.items) ? holdPayload.data.items : [];

      renderSeatHoldQueue(state.holds);
      renderTicketDetails(state.tickets);
      renderTicketSummary(ticketData.summary || {});
      renderPagination(state.meta);

      if (showRefreshToast) {
        showToast('Ticket details synced.', 'success');
      }
    } catch (error) {
      renderSeatHoldQueue([]);
      renderTicketError(errorMessageFromException(error, 'Failed to load ticket details.'));
      renderTicketSummary({});
      renderPagination({ page: 1, total_pages: 1, total: 0 });
    } finally {
      state.isLoading = false;
    }
  }

  function renderHoldLoading() {
    const container = document.getElementById('ticketQueue');
    if (container) {
      container.innerHTML = '<div class="td-muted" style="padding:12px 0;">Loading seat hold queue...</div>';
    }
  }

  function renderTicketLoading() {
    const tableBody = document.getElementById('ticketDetailsBody');
    const countLabel = document.getElementById('ticketDetailCount');

    if (tableBody) {
      tableBody.innerHTML = '<tr><td colspan="10" class="td-muted" style="text-align:center;padding:28px 16px;">Loading ticket details...</td></tr>';
    }
    if (countLabel) {
      countLabel.textContent = 'Loading...';
    }
  }

  function renderTicketError(message) {
    const tableBody = document.getElementById('ticketDetailsBody');
    const countLabel = document.getElementById('ticketDetailCount');

    if (tableBody) {
      tableBody.innerHTML = `<tr><td colspan="10" class="td-muted" style="text-align:center;padding:28px 16px;">${escapeHtml(message)}</td></tr>`;
    }
    if (countLabel) {
      countLabel.textContent = '0 tickets';
    }
  }

  function renderSeatHoldQueue(items) {
    const container = document.getElementById('ticketQueue');
    if (!container) {
      return;
    }

    if (!Array.isArray(items) || items.length === 0) {
      container.innerHTML = '<div class="td-muted">No active seat holds at the moment.</div>';
      return;
    }

    container.innerHTML = items.map(item => `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
        <div>
          <div class="td-bold">${escapeHtml(item.seat_label || '')} - ${escapeHtml(item.movie_title || '')}</div>
          <div class="td-muted">${escapeHtml(item.customer_ref || '')} - ${escapeHtml(item.cinema_name || '')} / ${escapeHtml(item.room_name || '')}</div>
        </div>
        <div class="td-muted">${escapeHtml(formatDateTime(item.hold_expires_at))}</div>
      </div>
    `).join('');
  }

  function renderTicketDetails(data) {
    const tableBody = document.getElementById('ticketDetailsBody');
    const countLabel = document.getElementById('ticketDetailCount');

    if (!tableBody) {
      return;
    }

    if (!Array.isArray(data) || data.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="10" class="td-muted" style="text-align:center;padding:28px 16px;">No matching ticket details found.</td></tr>';
      if (countLabel) {
        countLabel.textContent = '0 tickets';
      }
      return;
    }

    tableBody.innerHTML = data.map(row => `
      <tr>
        <td class="td-id">${escapeHtml(row.ticket_code || '')}</td>
        <td class="td-id">${escapeHtml(row.order_code || '')}</td>
        <td>
          <div class="td-bold">${escapeHtml(row.movie_title || 'Movie')}</div>
          <div class="td-muted">${escapeHtml(row.room_name || 'Room')}</div>
        </td>
        <td class="td-muted">${escapeHtml(formatDateTime(`${row.show_date || ''} ${row.start_time || ''}`))}</td>
        <td class="td-mono">${escapeHtml(row.seat_label || '')}</td>
        <td class="td-muted">${escapeHtml(row.contact_name || 'Guest')}</td>
        <td>${statusBadge(row.status)}</td>
        <td class="td-bold">${escapeHtml(formatCurrency(row.price))}</td>
        <td class="td-mono">${escapeHtml(row.qr_payload || '')}</td>
        <td>
          <div class="actions-row">
            <button class="action-btn view" title="View" onclick="openTicketDetail(${Number(row.id || 0)})">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `).join('');

    if (countLabel) {
      countLabel.textContent = `${state.meta.total || data.length} tickets`;
    }
  }

  function renderTicketSummary(summary) {
    const total = Number(summary.total_tickets || 0);
    const paid = Number(summary.paid_tickets || 0);
    const pending = Number(summary.pending_tickets || 0);
    const issues = Number(summary.issue_tickets || 0);

    const totalStat = document.getElementById('ticketOrdersTotalStat');
    const paidStat = document.getElementById('ticketOrdersPaidStat');
    const pendingStat = document.getElementById('ticketOrdersPendingStat');
    const riskStat = document.getElementById('ticketOrdersRiskStat');

    if (totalStat) totalStat.textContent = String(total);
    if (paidStat) paidStat.textContent = String(paid);
    if (pendingStat) pendingStat.textContent = String(pending);
    if (riskStat) riskStat.textContent = String(issues);
  }

  function renderPagination(meta) {
    const container = document.getElementById('ticketPagination');
    if (!container) {
      return;
    }

    const total = Number(meta?.total || 0);
    const page = Number(meta?.page || 1);
    const perPage = Number(meta?.per_page || 20);
    const totalPages = Number(meta?.total_pages || 1);
    const from = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const to = Math.min(total, page * perPage);

    const buttons = [];
    buttons.push(`<button class="pg-btn${page <= 1 ? ' disabled' : ''}" ${page <= 1 ? 'disabled' : `onclick="loadTicketDetailsPage(${page - 1})"`}>‹</button>`);
    for (let current = 1; current <= totalPages; current += 1) {
      if (current === 1 || current === totalPages || Math.abs(current - page) <= 1) {
        buttons.push(`<button class="pg-btn${current === page ? ' active' : ''}" onclick="loadTicketDetailsPage(${current})">${current}</button>`);
      } else if (buttons[buttons.length - 1] !== '<button class="pg-btn" disabled>…</button>') {
        buttons.push('<button class="pg-btn" disabled>…</button>');
      }
    }
    buttons.push(`<button class="pg-btn${page >= totalPages ? ' disabled' : ''}" ${page >= totalPages ? 'disabled' : `onclick="loadTicketDetailsPage(${page + 1})"`}>›</button>`);

    container.innerHTML = `
      <div class="pagination">
        <span class="pagination-info">${total === 0 ? 'No ticket details found' : `Showing ${from}-${to} of ${total} ticket details`}</span>
        <div class="pagination-actions">${buttons.join('')}</div>
      </div>
    `;
  }

  function loadTicketDetailsPage(page) {
    state.meta.page = Number(page || 1);
    void loadTicketData();
  }

  async function openTicketDetail(ticketId) {
    if (!ticketId) {
      return;
    }

    try {
      const payload = await adminApiRequest(`/api/admin/ticket-details/${encodeURIComponent(ticketId)}`, {
        useStoredToken: true,
      });
      const ticket = payload?.data || {};
      openModal('Ticket Detail', ticketDetailBody(ticket));
    } catch (error) {
      showToast(errorMessageFromException(error, 'Failed to load ticket detail.'), 'error');
    }
  }

  function ticketDetailBody(ticket) {
    return `<div class="form-grid">
      <div class="field"><label>Ticket Code</label><input class="input" value="${escapeHtmlAttr(ticket.ticket_code || '')}" readonly></div>
      <div class="field"><label>Order Code</label><input class="input" value="${escapeHtmlAttr(ticket.order_code || '')}" readonly></div>
      <div class="field"><label>Status</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(ticket.status))}" readonly></div>
      <div class="field"><label>Seat</label><input class="input" value="${escapeHtmlAttr(ticket.seat_label || '')}" readonly></div>
      <div class="field form-full"><label>Movie</label><input class="input" value="${escapeHtmlAttr(ticket.movie_title || '')}" readonly></div>
      <div class="field"><label>Room</label><input class="input" value="${escapeHtmlAttr(ticket.room_name || '')}" readonly></div>
      <div class="field"><label>Showtime</label><input class="input" value="${escapeHtmlAttr(formatDateTime(`${ticket.show_date || ''} ${ticket.start_time || ''}`))}" readonly></div>
      <div class="field"><label>Customer</label><input class="input" value="${escapeHtmlAttr(ticket.contact_name || 'Guest')}" readonly></div>
      <div class="field"><label>Line Price</label><input class="input" value="${escapeHtmlAttr(formatCurrency(ticket.price))}" readonly></div>
      <div class="field"><label>Payment Status</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(ticket.payment_status))}" readonly></div>
      <div class="field"><label>Payment Method</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(ticket.payment_method))}" readonly></div>
      <div class="field form-full"><label>QR Payload</label><input class="input" value="${escapeHtmlAttr(ticket.qr_payload || '')}" readonly></div>
    </div>`;
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
      return 'N/A';
    }

    const parsed = new Date(normalized);
    if (Number.isNaN(parsed.getTime())) {
      return String(value || '');
    }

    return new Intl.DateTimeFormat('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
    }).format(parsed);
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

  document.addEventListener('DOMContentLoaded', initTicketDetailsPage);
})();
