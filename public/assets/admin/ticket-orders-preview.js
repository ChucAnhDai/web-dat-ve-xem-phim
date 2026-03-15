(function () {
  const state = {
    items: [],
    meta: { page: 1, per_page: 20, total: 0, total_pages: 1 },
    filters: {
      search: '',
      status: '',
      payment_method: '',
    },
    isLoading: false,
  };

  let searchTimer = null;

  function initTicketOrdersPage() {
    const tableBody = document.getElementById('ticketOrdersBody');
    if (!tableBody) {
      return;
    }

    bindFilters();
    window.handleTicketSectionAction = () => loadTicketOrders(true);
    window.openTicketOrderDetail = openTicketOrderDetail;
    window.loadTicketOrdersPage = loadTicketOrdersPage;

    void loadTicketOrders(true);
  }

  function bindFilters() {
    const searchInput = document.getElementById('ticketSearch');
    const statusFilter = document.getElementById('ticketStatusFilter');
    const paymentFilter = document.getElementById('ticketPaymentFilter');

    searchInput?.addEventListener('input', event => {
      window.clearTimeout(searchTimer);
      searchTimer = window.setTimeout(() => {
        state.filters.search = String(event.target.value || '').trim();
        state.meta.page = 1;
        void loadTicketOrders();
      }, 180);
    });

    statusFilter?.addEventListener('change', event => {
      state.filters.status = String(event.target.value || '').trim();
      state.meta.page = 1;
      void loadTicketOrders();
    });

    paymentFilter?.addEventListener('change', event => {
      state.filters.payment_method = String(event.target.value || '').trim();
      state.meta.page = 1;
      void loadTicketOrders();
    });
  }

  async function loadTicketOrders(showRefreshToast = false) {
    if (state.isLoading) {
      return;
    }

    state.isLoading = true;
    renderLoadingState();

    try {
      const payload = await adminApiRequest('/api/admin/ticket-orders', {
        query: {
          search: state.filters.search,
          status: state.filters.status,
          payment_method: state.filters.payment_method,
          page: state.meta.page,
          per_page: state.meta.per_page,
        },
        useStoredToken: true,
      });

      const data = payload?.data || {};
      state.items = Array.isArray(data.items) ? data.items : [];
      state.meta = data.meta || state.meta;

      renderTicketOrders(state.items);
      renderSummary(data.summary || {});
      renderPagination(state.meta);

      if (showRefreshToast) {
        showToast('Ticket orders synced.', 'success');
      }
    } catch (error) {
      renderErrorState(errorMessageFromException(error, 'Failed to load ticket orders.'));
      renderSummary({});
      renderPagination({ page: 1, total_pages: 1, total: 0 });
    } finally {
      state.isLoading = false;
    }
  }

  function renderLoadingState() {
    const tableBody = document.getElementById('ticketOrdersBody');
    const countLabel = document.getElementById('ticketOrderCount');

    if (tableBody) {
      tableBody.innerHTML = '<tr><td colspan="11" class="td-muted" style="text-align:center;padding:28px 16px;">Loading ticket orders...</td></tr>';
    }
    if (countLabel) {
      countLabel.textContent = 'Loading...';
    }
  }

  function renderErrorState(message) {
    const tableBody = document.getElementById('ticketOrdersBody');
    const countLabel = document.getElementById('ticketOrderCount');

    if (tableBody) {
      tableBody.innerHTML = `<tr><td colspan="11" class="td-muted" style="text-align:center;padding:28px 16px;">${escapeHtml(message)}</td></tr>`;
    }
    if (countLabel) {
      countLabel.textContent = '0 orders';
    }
  }

  function renderTicketOrders(data) {
    const tableBody = document.getElementById('ticketOrdersBody');
    const countLabel = document.getElementById('ticketOrderCount');

    if (!tableBody) {
      return;
    }

    if (!Array.isArray(data) || data.length === 0) {
      tableBody.innerHTML = '<tr><td colspan="11" class="td-muted" style="text-align:center;padding:28px 16px;">No matching ticket orders found.</td></tr>';
      if (countLabel) {
        countLabel.textContent = '0 orders';
      }
      return;
    }

    tableBody.innerHTML = data.map(order => `
      <tr>
        <td class="td-id">${escapeHtml(order.order_code || '')}</td>
        <td>
          <div class="td-bold">${escapeHtml(order.contact_name || 'Guest')}</div>
          <div class="td-muted">${escapeHtml(order.contact_email || 'N/A')}</div>
          <div class="td-muted">${escapeHtml(order.contact_phone || 'N/A')}</div>
        </td>
        <td>
          <div class="td-bold">${escapeHtml(order.movie_title || 'Movie')}</div>
          <div class="td-muted">${escapeHtml(order.cinema_name || 'Cinema')}</div>
          <div class="td-muted">${escapeHtml(order.room_name || 'Room')}</div>
        </td>
        <td>
          <div class="td-bold">${escapeHtml(String(order.seat_count || 0))} seats</div>
          <div class="td-mono">${escapeHtml(Array.isArray(order.seats) ? order.seats.join(', ') : '')}</div>
        </td>
        <td><span class="badge gray">${escapeHtml(humanizeStatus(order.fulfillment_method))}</span></td>
        <td class="td-bold">${escapeHtml(formatCurrency(order.total_price))}</td>
        <td>
          <div>${statusBadge(order.payment_status || 'pending')}</div>
          <div class="td-muted" style="margin-top:4px;">${escapeHtml(humanizeStatus(order.payment_method || ''))}</div>
        </td>
        <td>${statusBadge(order.status)}</td>
        <td class="td-muted">${escapeHtml(order.hold_expires_at ? formatDateTime(order.hold_expires_at) : 'N/A')}</td>
        <td class="td-muted">${escapeHtml(formatDateTime(order.order_date))}</td>
        <td>
          <div class="actions-row">
            <button class="action-btn view" title="View" onclick="openTicketOrderDetail(${Number(order.id || 0)})">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `).join('');

    if (countLabel) {
      countLabel.textContent = `${state.meta.total || data.length} orders`;
    }
  }

  function renderSummary(summary) {
    setText('ticketOrdersTotalStat', String(summary.total_orders || 0));
    setText('ticketOrdersPaidStat', String(summary.paid_orders || 0));
    setText('ticketOrdersPendingStat', String(summary.pending_orders || 0));
    setText('ticketOrdersRiskStat', String(summary.risk_orders || 0));
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
    buttons.push(`<button class="pg-btn${page <= 1 ? ' disabled' : ''}" ${page <= 1 ? 'disabled' : `onclick="loadTicketOrdersPage(${page - 1})"`}>‹</button>`);
    for (let current = 1; current <= totalPages; current += 1) {
      if (current === 1 || current === totalPages || Math.abs(current - page) <= 1) {
        buttons.push(`<button class="pg-btn${current === page ? ' active' : ''}" onclick="loadTicketOrdersPage(${current})">${current}</button>`);
      } else if (buttons[buttons.length - 1] !== '<button class="pg-btn" disabled>…</button>') {
        buttons.push('<button class="pg-btn" disabled>…</button>');
      }
    }
    buttons.push(`<button class="pg-btn${page >= totalPages ? ' disabled' : ''}" ${page >= totalPages ? 'disabled' : `onclick="loadTicketOrdersPage(${page + 1})"`}>›</button>`);

    container.innerHTML = `
      <div class="pagination">
        <span class="pagination-info">${total === 0 ? 'No ticket orders found' : `Showing ${from}-${to} of ${total} ticket orders`}</span>
        <div class="pagination-actions">${buttons.join('')}</div>
      </div>
    `;
  }

  function loadTicketOrdersPage(page) {
    state.meta.page = Number(page || 1);
    void loadTicketOrders();
  }

  async function openTicketOrderDetail(orderId) {
    if (!orderId) {
      return;
    }

    try {
      const payload = await adminApiRequest(`/api/admin/ticket-orders/${encodeURIComponent(orderId)}`, {
        useStoredToken: true,
      });
      const order = payload?.data || {};

      openModal('Ticket Order Detail', orderDetailBody(order));
    } catch (error) {
      showToast(errorMessageFromException(error, 'Failed to load ticket order detail.'), 'error');
    }
  }

  function orderDetailBody(order) {
    const tickets = Array.isArray(order.tickets) ? order.tickets : [];

    return `<div style="display:flex;flex-direction:column;gap:16px;">
      <div class="form-grid">
        <div class="field"><label>Order Code</label><input class="input" value="${escapeHtmlAttr(order.order_code || '')}" readonly></div>
        <div class="field"><label>Status</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(order.status))}" readonly></div>
        <div class="field"><label>Customer</label><input class="input" value="${escapeHtmlAttr(order.contact_name || 'Guest')}" readonly></div>
        <div class="field"><label>Contact Email</label><input class="input" value="${escapeHtmlAttr(order.contact_email || '')}" readonly></div>
        <div class="field"><label>Contact Phone</label><input class="input" value="${escapeHtmlAttr(order.contact_phone || '')}" readonly></div>
        <div class="field"><label>Fulfillment</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(order.fulfillment_method))}" readonly></div>
        <div class="field"><label>Movie</label><input class="input" value="${escapeHtmlAttr(order.movie_title || '')}" readonly></div>
        <div class="field"><label>Cinema / Room</label><input class="input" value="${escapeHtmlAttr(`${order.cinema_name || ''} - ${order.room_name || ''}`)}" readonly></div>
        <div class="field"><label>Seats</label><input class="input" value="${escapeHtmlAttr(Array.isArray(order.seats) ? order.seats.join(', ') : '')}" readonly></div>
        <div class="field"><label>Seat Count</label><input class="input" value="${escapeHtmlAttr(String(order.seat_count || 0))}" readonly></div>
        <div class="field"><label>Subtotal</label><input class="input" value="${escapeHtmlAttr(formatCurrency(order.subtotal_price))}" readonly></div>
        <div class="field"><label>Total</label><input class="input" value="${escapeHtmlAttr(formatCurrency(order.total_price))}" readonly></div>
        <div class="field"><label>Payment Method</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(order.payment_method))}" readonly></div>
        <div class="field"><label>Payment Status</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(order.payment_status))}" readonly></div>
        <div class="field"><label>Transaction Code</label><input class="input" value="${escapeHtmlAttr(order.transaction_code || '')}" readonly></div>
        <div class="field"><label>Created</label><input class="input" value="${escapeHtmlAttr(formatDateTime(order.order_date))}" readonly></div>
      </div>
      <div>
        <div class="card-title" style="margin-bottom:12px;">Tickets</div>
        ${tickets.length === 0 ? '<div class="td-muted">No ticket lines found.</div>' : `
          <div style="display:flex;flex-direction:column;gap:10px;">
            ${tickets.map(ticket => `
              <div style="padding:12px;border:1px solid rgba(255,255,255,0.08);border-radius:12px;">
                <div class="td-bold">${escapeHtml(ticket.ticket_code || '')} - ${escapeHtml(ticket.seat_label || '')}</div>
                <div class="td-muted">${escapeHtml(formatDateTime(`${ticket.show_date || ''} ${ticket.start_time || ''}`))}</div>
                <div class="td-muted">${escapeHtml(formatCurrency(ticket.price))} - ${escapeHtml(humanizeStatus(ticket.status))}</div>
              </div>
            `).join('')}
          </div>
        `}
      </div>
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

  function setText(id, value) {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = value;
    }
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

  document.addEventListener('DOMContentLoaded', initTicketOrdersPage);
})();
