(function () {
  const paymentState = {
    items: [],
    meta: { page: 1, per_page: 20, total: 0, total_pages: 1 },
    summary: { captured_value: 0, total_records: 0, vnpay_month_value: 0, issue_count: 0 },
    filters: { search: '', payment_method: '', payment_status: '', scope: '' },
    methodOptions: [],
    loading: false,
  };

  const methodState = {
    items: [],
    meta: { page: 1, per_page: 20, total: 0, total_pages: 1 },
    summary: { total_methods: 0, active_methods: 0, maintenance_methods: 0, disabled_methods: 0 },
    overview: [],
    filters: { search: '', status: '', channel_type: '' },
    loading: false,
  };

  let paymentSearchTimer = null;
  let methodSearchTimer = null;

  function initPaymentsAdmin() {
    const hasPayments = Boolean(document.getElementById('paymentsBody'));
    const hasMethods = Boolean(document.getElementById('paymentMethodsBody'));

    if (!hasPayments && !hasMethods) {
      return;
    }

    window.openAdminPaymentDetail = openAdminPaymentDetail;
    window.loadPaymentsPage = loadPaymentsPage;
    window.openPaymentMethodDetail = openPaymentMethodDetail;
    window.loadPaymentMethodsPage = loadPaymentMethodsPage;
    window.editPaymentMethod = editPaymentMethod;
    window.archivePaymentMethod = archivePaymentMethod;

    if (hasPayments) {
      bindPaymentFilters();
      window.handlePaymentsSectionAction = exportPaymentsCsv;
      void loadPayments(true);
    }

    if (hasMethods) {
      bindMethodFilters();
      window.handlePaymentsSectionAction = () => openPaymentMethodEditor('create');
      void loadPaymentMethods(true);
    }
  }

  function bindPaymentFilters() {
    document.getElementById('paymentSearch')?.addEventListener('input', event => {
      window.clearTimeout(paymentSearchTimer);
      paymentSearchTimer = window.setTimeout(() => {
        paymentState.filters.search = String(event.target.value || '').trim();
        paymentState.meta.page = 1;
        void loadPayments();
      }, 180);
    });

    document.getElementById('paymentMethodFilter')?.addEventListener('change', event => {
      paymentState.filters.payment_method = normalizeFilterValue(event.target.value);
      paymentState.meta.page = 1;
      void loadPayments();
    });

    document.getElementById('paymentStatusFilter')?.addEventListener('change', event => {
      paymentState.filters.payment_status = normalizeFilterValue(event.target.value);
      paymentState.meta.page = 1;
      void loadPayments();
    });

    document.getElementById('paymentScopeFilter')?.addEventListener('change', event => {
      paymentState.filters.scope = normalizeFilterValue(event.target.value);
      paymentState.meta.page = 1;
      void loadPayments();
    });

    document.getElementById('paymentExportBtn')?.addEventListener('click', exportPaymentsCsv);
  }

  function bindMethodFilters() {
    document.getElementById('methodSearch')?.addEventListener('input', event => {
      window.clearTimeout(methodSearchTimer);
      methodSearchTimer = window.setTimeout(() => {
        methodState.filters.search = String(event.target.value || '').trim();
        methodState.meta.page = 1;
        void loadPaymentMethods();
      }, 180);
    });

    document.getElementById('methodStatusFilter')?.addEventListener('change', event => {
      methodState.filters.status = normalizeFilterValue(event.target.value);
      methodState.meta.page = 1;
      void loadPaymentMethods();
    });

    document.getElementById('methodTypeFilter')?.addEventListener('change', event => {
      methodState.filters.channel_type = normalizeFilterValue(event.target.value);
      methodState.meta.page = 1;
      void loadPaymentMethods();
    });
  }

  async function loadPayments(showRefreshToast = false) {
    if (paymentState.loading) {
      return;
    }

    paymentState.loading = true;
    renderPaymentsLoadingState();

    try {
      const payload = await adminApiRequest('/api/admin/payments', {
        query: {
          search: paymentState.filters.search,
          payment_method: paymentState.filters.payment_method,
          payment_status: paymentState.filters.payment_status,
          scope: paymentState.filters.scope,
          page: paymentState.meta.page,
          per_page: paymentState.meta.per_page,
        },
        useStoredToken: true,
      });

      const data = payload?.data || {};
      paymentState.items = Array.isArray(data.items) ? data.items : [];
      paymentState.meta = normalizeMeta(data.meta, paymentState.meta);
      paymentState.summary = {
        captured_value: Number(data.summary?.captured_value || 0),
        total_records: Number(data.summary?.total_records || 0),
        vnpay_month_value: Number(data.summary?.vnpay_month_value || 0),
        issue_count: Number(data.summary?.issue_count || 0),
      };
      paymentState.methodOptions = normalizeMethodOptions(data.method_options, paymentState.items);

      renderPaymentMethodFilter(paymentState.methodOptions);
      renderPaymentsTable(paymentState.items);
      renderPaymentSummary(paymentState.summary);
      renderPaymentsPagination(paymentState.meta);

      if (showRefreshToast) {
        showToast('Payment records synced.', 'success');
      }
    } catch (error) {
      paymentState.items = [];
      paymentState.meta = normalizeMeta();
      paymentState.summary = { captured_value: 0, total_records: 0, vnpay_month_value: 0, issue_count: 0 };
      paymentState.methodOptions = [];
      renderPaymentMethodFilter([]);
      renderPaymentsErrorState(errorMessageFromException(error, 'Failed to load payment records.'));
      renderPaymentSummary(paymentState.summary);
      renderPaymentsPagination(paymentState.meta);
    } finally {
      paymentState.loading = false;
    }
  }

  function renderPaymentsLoadingState() {
    const body = document.getElementById('paymentsBody');
    setText('paymentCount', 'Loading...');
    if (body) {
      body.innerHTML = '<tr><td colspan="9" class="td-muted" style="text-align:center;padding:28px 16px;">Loading payment records...</td></tr>';
    }
  }

  function renderPaymentsErrorState(message) {
    const body = document.getElementById('paymentsBody');
    setText('paymentCount', '0 payments');
    if (body) {
      body.innerHTML = `<tr><td colspan="9" class="td-muted" style="text-align:center;padding:28px 16px;">${escapeHtml(message)}</td></tr>`;
    }
  }

  function renderPaymentMethodFilter(options) {
    const select = document.getElementById('paymentMethodFilter');
    if (!select) {
      return;
    }

    const current = paymentState.filters.payment_method || normalizeFilterValue(select.value);
    const normalizedOptions = normalizeMethodOptions(options, paymentState.items);
    const dynamicOptions = normalizedOptions.map(option => ({
      value: option.code,
      label: option.name || humanizeStatus(option.code || ''),
    }));

    select.innerHTML = [
      '<option value="">All Methods</option>',
      buildOptions(dynamicOptions, current),
    ].join('');
    select.value = current;
  }

  function renderPaymentsTable(items) {
    const body = document.getElementById('paymentsBody');
    if (!body) {
      return;
    }

    if (!Array.isArray(items) || items.length === 0) {
      body.innerHTML = '<tr><td colspan="9" class="td-muted" style="text-align:center;padding:28px 16px;">No matching payment records found.</td></tr>';
      setText('paymentCount', '0 payments');
      return;
    }

    body.innerHTML = items.map(payment => `
      <tr>
        <td class="td-id">${escapeHtml(payment.transaction_code || 'N/A')}</td>
        <td>
          <div>${statusBadge(payment.payment_method || 'unknown')}</div>
          <div class="td-muted" style="margin-top:4px;">${escapeHtml(payment.method_name || humanizeStatus(payment.payment_method || 'unknown'))}</div>
        </td>
        <td class="td-mono">${escapeHtml(payment.provider_transaction_code || payment.provider_order_ref || 'Pending callback')}</td>
        <td class="td-bold">${escapeHtml(formatCurrency(payment.amount, payment.currency))}</td>
        <td>
          <div class="td-id">${escapeHtml(payment.order_code || 'N/A')}</div>
          <div class="td-muted">${escapeHtml(payment.customer_name || 'Guest')}</div>
        </td>
        <td><span class="badge ${payment.order_scope === 'ticket' ? 'red' : 'blue'}">${escapeHtml(humanizeStatus(payment.order_scope || 'shop'))}</span></td>
        <td>${statusBadge(payment.payment_status || 'pending')}</td>
        <td class="td-muted">${escapeHtml(formatDateTime(payment.updated_at || payment.payment_date || payment.initiated_at))}</td>
        <td>
          <div class="actions-row">
            <button class="action-btn view" title="View" onclick="openAdminPaymentDetail(${Number(payment.id || 0)})">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `).join('');

    setText('paymentCount', `${paymentState.meta.total || items.length} payments`);
  }

  function renderPaymentSummary(summary) {
    setText('paymentCapturedStat', formatCurrency(summary.captured_value, 'VND'));
    setText('paymentTotalStat', String(summary.total_records || 0));
    setText('paymentVnpayMonthStat', formatCurrency(summary.vnpay_month_value, 'VND'));
    setText('paymentIssueStat', String(summary.issue_count || 0));
  }

  function renderPaymentsPagination(meta) {
    const container = document.getElementById('paymentsPagination');
    if (container) {
      container.innerHTML = paginationTemplate(meta, 'loadPaymentsPage', 'payments');
    }
  }

  function loadPaymentsPage(page) {
    paymentState.meta.page = Number(page || 1);
    void loadPayments();
  }

  async function openAdminPaymentDetail(paymentId) {
    if (!paymentId) {
      return;
    }

    try {
      const payload = await adminApiRequest(`/api/admin/payments/${encodeURIComponent(paymentId)}`, {
        useStoredToken: true,
      });
      const payment = payload?.data || {};

      openModal('Payment Detail', paymentDetailBody(payment), {
        submitLabel: 'Close',
        cancelLabel: 'Close',
        description: 'Read-only payment snapshot synchronized from the live SQL payment ledger.',
        note: 'Lifecycle updates stay inside ticket and shop order management. Payment detail here is read-only for audit.',
        onSave: () => closeModal(),
      });
    } catch (error) {
      showToast(errorMessageFromException(error, 'Failed to load payment detail.'), 'error');
    }
  }

  async function loadPaymentMethods(showRefreshToast = false) {
    if (methodState.loading) {
      return;
    }

    methodState.loading = true;
    renderMethodsLoadingState();
    renderMethodOverview([]);
    renderMethodSettlementSummary([]);

    try {
      const payload = await adminApiRequest('/api/admin/payment-methods', {
        query: {
          search: methodState.filters.search,
          status: methodState.filters.status,
          channel_type: methodState.filters.channel_type,
          page: methodState.meta.page,
          per_page: methodState.meta.per_page,
        },
        useStoredToken: true,
      });

      const data = payload?.data || {};
      methodState.items = Array.isArray(data.items) ? data.items : [];
      methodState.meta = normalizeMeta(data.meta, methodState.meta);
      methodState.summary = {
        total_methods: Number(data.summary?.total_methods || 0),
        active_methods: Number(data.summary?.active_methods || 0),
        maintenance_methods: Number(data.summary?.maintenance_methods || 0),
        disabled_methods: Number(data.summary?.disabled_methods || 0),
      };
      methodState.overview = Array.isArray(data.overview) ? data.overview : [];

      renderMethodsTable(methodState.items);
      renderMethodSummary(methodState.summary);
      renderMethodsPagination(methodState.meta);
      renderMethodOverview(methodState.overview);
      renderMethodSettlementSummary(methodState.overview);

      if (showRefreshToast) {
        showToast('Payment methods synced.', 'success');
      }
    } catch (error) {
      methodState.items = [];
      methodState.meta = normalizeMeta();
      methodState.summary = { total_methods: 0, active_methods: 0, maintenance_methods: 0, disabled_methods: 0 };
      methodState.overview = [];
      renderMethodsErrorState(errorMessageFromException(error, 'Failed to load payment methods.'));
      renderMethodSummary(methodState.summary);
      renderMethodsPagination(methodState.meta);
      renderMethodOverview([]);
      renderMethodSettlementSummary([]);
    } finally {
      methodState.loading = false;
    }
  }

  function renderMethodsLoadingState() {
    const body = document.getElementById('paymentMethodsBody');
    setText('methodCount', 'Loading...');
    if (body) {
      body.innerHTML = '<tr><td colspan="9" class="td-muted" style="text-align:center;padding:28px 16px;">Loading payment methods...</td></tr>';
    }
  }

  function renderMethodsErrorState(message) {
    const body = document.getElementById('paymentMethodsBody');
    setText('methodCount', '0 methods');
    if (body) {
      body.innerHTML = `<tr><td colspan="9" class="td-muted" style="text-align:center;padding:28px 16px;">${escapeHtml(message)}</td></tr>`;
    }
  }

  function renderMethodsTable(items) {
    const body = document.getElementById('paymentMethodsBody');
    if (!body) {
      return;
    }

    if (!Array.isArray(items) || items.length === 0) {
      body.innerHTML = '<tr><td colspan="9" class="td-muted" style="text-align:center;padding:28px 16px;">No matching payment methods found.</td></tr>';
      setText('methodCount', '0 methods');
      return;
    }

    body.innerHTML = items.map(method => `
      <tr>
        <td class="td-mono">${escapeHtml(method.code || '')}</td>
        <td>
          <div class="td-bold">${escapeHtml(method.name || '')}</div>
          <div class="td-muted">${escapeHtml(method.provider || '')}</div>
        </td>
        <td class="td-muted">${escapeHtml(humanizeStatus(method.channel_type || 'gateway'))}</td>
        <td class="td-bold">${escapeHtml(String(method.transaction_count || 0))}</td>
        <td class="td-bold" style="color:var(--green);">${escapeHtml(formatCurrency(method.captured_value, 'VND'))}</td>
        <td class="td-muted">${escapeHtml(formatPercent(method.fee_rate_percent))} + ${escapeHtml(formatCurrency(method.fixed_fee_amount, 'VND'))}</td>
        <td class="td-muted">${escapeHtml(method.settlement_cycle || 'instant')}</td>
        <td>${statusBadge(method.status || 'inactive')}</td>
        <td>
          <div class="actions-row">
            <button class="action-btn view" title="View" onclick="openPaymentMethodDetail(${Number(method.id || 0)})">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="action-btn edit" title="Edit" onclick="editPaymentMethod(${Number(method.id || 0)})">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="action-btn delete" title="Archive" onclick="archivePaymentMethod(${Number(method.id || 0)}, '${escapeHtmlAttr(method.name || method.code || 'method')}')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `).join('');

    setText('methodCount', `${methodState.meta.total || items.length} methods`);
  }

  function renderMethodSummary(summary) {
    setText('paymentMethodTotalStat', String(summary.total_methods || 0));
    setText('paymentMethodActiveStat', String(summary.active_methods || 0));
    setText('paymentMethodMaintenanceStat', String(summary.maintenance_methods || 0));
    setText('paymentMethodDisabledStat', String(summary.disabled_methods || 0));
  }

  function renderMethodOverview(items) {
    const container = document.getElementById('paymentMethodOverview');
    if (!container) {
      return;
    }

    if (!Array.isArray(items) || items.length === 0) {
      container.innerHTML = '<div class="td-muted">No payment method overview is available right now.</div>';
      return;
    }

    container.innerHTML = items.map(method => `
      <div class="payment-method" style="cursor:default;">
        <div class="pm-icon" style="background:${escapeHtmlAttr(methodAccent(method.code, method.status))};color:#fff;font-size:10px;font-weight:700;">${escapeHtml(methodIcon(method.code))}</div>
        <div>
          <div class="pm-name">${escapeHtml(method.name || method.code || 'Method')}</div>
          <div class="pm-sub">${escapeHtml(humanizeStatus(method.channel_type || 'gateway'))} / ${escapeHtml(String(method.transaction_count || 0))} transactions</div>
        </div>
        <div class="pm-amount" style="color:var(--green);">${escapeHtml(formatCurrency(method.captured_value, 'VND'))}</div>
      </div>
    `).join('');
  }

  function renderMethodSettlementSummary(items) {
    const container = document.getElementById('paymentMethodSettlementSummary');
    if (!container) {
      return;
    }

    if (!Array.isArray(items) || items.length === 0) {
      container.innerHTML = '<div class="td-muted">Settlement data will appear once payment methods are loaded.</div>';
      return;
    }

    const totalRevenue = items.reduce((sum, method) => sum + Number(method.captured_value || 0), 0);
    container.innerHTML = `<div style="display:flex;flex-direction:column;gap:16px;">${items.map(method => {
      const revenue = Number(method.captured_value || 0);
      const share = totalRevenue > 0 ? Math.round((revenue / totalRevenue) * 1000) / 10 : 0;
      return `
        <div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;gap:12px;">
            <span style="font-size:13px;font-weight:600;">${escapeHtml(method.name || method.code || 'Method')}</span>
            <span style="font-size:12px;color:var(--text-dim);">${escapeHtml(formatPercent(method.fee_rate_percent))} / ${escapeHtml(method.settlement_cycle || 'instant')} / ${share}% revenue share</span>
          </div>
          <div class="progress-bar" style="height:8px;">
            <div class="progress-fill" style="width:${Math.min(100, share)}%;background:${escapeHtmlAttr(methodAccent(method.code, method.status))};"></div>
          </div>
        </div>
      `;
    }).join('')}</div>`;
  }

  function renderMethodsPagination(meta) {
    const container = document.getElementById('paymentMethodsPagination');
    if (container) {
      container.innerHTML = paginationTemplate(meta, 'loadPaymentMethodsPage', 'payment methods');
    }
  }

  function loadPaymentMethodsPage(page) {
    methodState.meta.page = Number(page || 1);
    void loadPaymentMethods();
  }

  async function openPaymentMethodDetail(methodId) {
    if (!methodId) {
      return;
    }

    try {
      const method = await fetchPaymentMethod(methodId);
      openModal('Payment Method Detail', paymentMethodDetailBody(method), {
        submitLabel: 'Close',
        cancelLabel: 'Close',
        description: 'Read-only metadata and aggregated usage synced from payment_methods and payments.',
        note: 'Use Edit to update fee metadata, operational status, redirect support, or settlement posture.',
        onSave: () => closeModal(),
      });
    } catch (error) {
      showToast(errorMessageFromException(error, 'Failed to load payment method detail.'), 'error');
    }
  }

  async function editPaymentMethod(methodId) {
    if (!methodId) {
      return;
    }

    try {
      const method = await fetchPaymentMethod(methodId);
      openPaymentMethodEditor('edit', method);
    } catch (error) {
      showToast(errorMessageFromException(error, 'Failed to load payment method detail.'), 'error');
    }
  }

  async function archivePaymentMethod(methodId, methodName) {
    if (!methodId) {
      return;
    }

    const confirmed = typeof window.confirm === 'function'
      ? window.confirm(`Archive ${methodName}? This will set the method status to disabled.`)
      : true;
    if (!confirmed) {
      return;
    }

    try {
      await adminApiRequest(`/api/admin/payment-methods/${encodeURIComponent(methodId)}`, {
        method: 'DELETE',
        useStoredToken: true,
      });
      showToast('Payment method archived.', 'success');
      await loadPaymentMethods();
    } catch (error) {
      showToast(errorMessageFromException(error, 'Failed to archive payment method.'), 'error');
    }
  }

  function openPaymentMethodEditor(mode, method = null) {
    const normalizedMode = mode === 'edit' ? 'edit' : 'create';
    const snapshot = normalizedMode === 'edit' ? method : createEmptyMethod();
    const title = normalizedMode === 'edit' ? 'Edit Payment Method' : 'Add Payment Method';

    openModal(title, paymentMethodFormBody(normalizedMode, snapshot), {
      submitLabel: normalizedMode === 'edit' ? 'Update Method' : 'Create Method',
      cancelLabel: 'Cancel',
      description: 'Operational payment metadata is stored in SQL and used across checkout, reporting, and admin observability.',
      note: normalizedMode === 'edit'
        ? 'The payment code stays immutable once created to keep ledger references stable.'
        : 'Create a new method catalog entry with fee, redirect, settlement, and support metadata.',
      busyLabel: normalizedMode === 'edit' ? 'Updating...' : 'Creating...',
      onSave: async () => {
        const form = document.getElementById('paymentMethodForm');
        if (!form) {
          return;
        }

        clearFormErrors(form);
        const payload = readPaymentMethodForm(form, normalizedMode);
        const clientErrors = validatePaymentMethodForm(payload, normalizedMode);
        if (Object.keys(clientErrors).length > 0) {
          applyFormErrors(form, clientErrors);
          throw new Error(firstApiErrorMessage(clientErrors, 'Please fix the highlighted fields.'));
        }

        try {
          const endpoint = normalizedMode === 'edit'
            ? `/api/admin/payment-methods/${encodeURIComponent(snapshot.id)}`
            : '/api/admin/payment-methods';
          await adminApiRequest(endpoint, {
            method: normalizedMode === 'edit' ? 'PUT' : 'POST',
            body: payload,
            useStoredToken: true,
          });
        } catch (error) {
          if (error instanceof AdminApiError) {
            applyFormErrors(form, error.errors || {});
          }
          throw error;
        }

        closeModal();
        showToast(normalizedMode === 'edit' ? 'Payment method updated.' : 'Payment method created.', 'success');
        await loadPaymentMethods();
      },
    });
  }

  function paymentDetailBody(payment) {
    return `
      <div style="display:grid;gap:20px;">
        <div class="grid-2">
          <div class="field"><label>Transaction Code</label><input class="input" value="${escapeHtmlAttr(payment.transaction_code || 'N/A')}" readonly></div>
          <div class="field"><label>Payment Status</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(payment.payment_status || 'pending'))}" readonly></div>
          <div class="field"><label>Payment Method</label><input class="input" value="${escapeHtmlAttr(payment.method_name || humanizeStatus(payment.payment_method || 'unknown'))}" readonly></div>
          <div class="field"><label>Gateway Ref</label><input class="input" value="${escapeHtmlAttr(payment.provider_transaction_code || payment.provider_order_ref || 'Pending callback')}" readonly></div>
          <div class="field"><label>Order Scope</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(payment.order_scope || 'shop'))}" readonly></div>
          <div class="field"><label>Order Code</label><input class="input" value="${escapeHtmlAttr(payment.order_code || 'N/A')}" readonly></div>
          <div class="field"><label>Order Status</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(payment.order_status || 'unknown'))}" readonly></div>
          <div class="field"><label>Amount</label><input class="input" value="${escapeHtmlAttr(formatCurrency(payment.amount, payment.currency))}" readonly></div>
          <div class="field"><label>Customer</label><input class="input" value="${escapeHtmlAttr(payment.customer_name || 'Guest')}" readonly></div>
          <div class="field"><label>Checkout Email</label><input class="input" value="${escapeHtmlAttr(payment.contact_email || 'N/A')}" readonly></div>
          <div class="field"><label>Checkout Phone</label><input class="input" value="${escapeHtmlAttr(payment.contact_phone || 'N/A')}" readonly></div>
          <div class="field"><label>Provider Response</label><input class="input" value="${escapeHtmlAttr(payment.provider_response_code || 'N/A')}" readonly></div>
          <div class="field"><label>Initiated At</label><input class="input" value="${escapeHtmlAttr(formatDateTime(payment.initiated_at))}" readonly></div>
          <div class="field"><label>Completed At</label><input class="input" value="${escapeHtmlAttr(formatDateTime(payment.completed_at))}" readonly></div>
          <div class="field"><label>Failed At</label><input class="input" value="${escapeHtmlAttr(formatDateTime(payment.failed_at))}" readonly></div>
          <div class="field"><label>Refunded At</label><input class="input" value="${escapeHtmlAttr(formatDateTime(payment.refunded_at))}" readonly></div>
        </div>
        <div class="field">
          <label>Provider Message</label>
          <textarea class="textarea" rows="3" readonly>${escapeHtml(payment.provider_message || 'N/A')}</textarea>
        </div>
        <div class="grid-2">
          <div class="field">
            <label>Request Payload</label>
            <textarea class="textarea" rows="10" readonly>${escapeHtml(prettyPayload(payment.request_payload))}</textarea>
          </div>
          <div class="field">
            <label>Callback Payload</label>
            <textarea class="textarea" rows="10" readonly>${escapeHtml(prettyPayload(payment.callback_payload))}</textarea>
          </div>
        </div>
      </div>
    `;
  }

  function paymentMethodDetailBody(method) {
    return `
      <div style="display:grid;gap:20px;">
        <div class="grid-2">
          <div class="field"><label>Code</label><input class="input" value="${escapeHtmlAttr(method.code || '')}" readonly></div>
          <div class="field"><label>Name</label><input class="input" value="${escapeHtmlAttr(method.name || '')}" readonly></div>
          <div class="field"><label>Provider</label><input class="input" value="${escapeHtmlAttr(method.provider || '')}" readonly></div>
          <div class="field"><label>Channel Type</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(method.channel_type || 'gateway'))}" readonly></div>
          <div class="field"><label>Status</label><input class="input" value="${escapeHtmlAttr(humanizeStatus(method.status || 'inactive'))}" readonly></div>
          <div class="field"><label>Settlement Cycle</label><input class="input" value="${escapeHtmlAttr(method.settlement_cycle || 'instant')}" readonly></div>
          <div class="field"><label>Fee Rate</label><input class="input" value="${escapeHtmlAttr(formatPercent(method.fee_rate_percent))}" readonly></div>
          <div class="field"><label>Fixed Fee</label><input class="input" value="${escapeHtmlAttr(formatCurrency(method.fixed_fee_amount, 'VND'))}" readonly></div>
          <div class="field"><label>Transaction Count</label><input class="input" value="${escapeHtmlAttr(String(method.transaction_count || 0))}" readonly></div>
          <div class="field"><label>Captured Value</label><input class="input" value="${escapeHtmlAttr(formatCurrency(method.captured_value, 'VND'))}" readonly></div>
          <div class="field"><label>Issue Count</label><input class="input" value="${escapeHtmlAttr(String(method.issue_count || 0))}" readonly></div>
          <div class="field"><label>Last Payment</label><input class="input" value="${escapeHtmlAttr(formatDateTime(method.last_payment_at))}" readonly></div>
        </div>
        <div class="grid-2">
          <div class="field"><label>Supports Refund</label><input class="input" value="${escapeHtmlAttr(method.supports_refund ? 'Yes' : 'No')}" readonly></div>
          <div class="field"><label>Supports Webhook</label><input class="input" value="${escapeHtmlAttr(method.supports_webhook ? 'Yes' : 'No')}" readonly></div>
          <div class="field"><label>Supports Redirect</label><input class="input" value="${escapeHtmlAttr(method.supports_redirect ? 'Yes' : 'No')}" readonly></div>
          <div class="field"><label>Display Order</label><input class="input" value="${escapeHtmlAttr(String(method.display_order || 0))}" readonly></div>
        </div>
        <div class="field">
          <label>Description</label>
          <textarea class="textarea" rows="4" readonly>${escapeHtml(method.description || 'N/A')}</textarea>
        </div>
      </div>
    `;
  }

  function paymentMethodFormBody(mode, method) {
    const isEdit = mode === 'edit';
    return `
      <form id="paymentMethodForm" style="display:grid;gap:18px;">
        <div class="form-alert" data-form-alert hidden></div>
        <div class="grid-2">
          <div class="field">
            <label for="methodCode">Code</label>
            <input id="methodCode" name="code" class="input" data-field-control="code" value="${escapeHtmlAttr(method.code || '')}" ${isEdit ? 'readonly' : ''} placeholder="vnpay">
            <div class="field-error" data-field-error="code" hidden></div>
          </div>
          <div class="field">
            <label for="methodName">Name</label>
            <input id="methodName" name="name" class="input" data-field-control="name" value="${escapeHtmlAttr(method.name || '')}" placeholder="VNPay">
            <div class="field-error" data-field-error="name" hidden></div>
          </div>
          <div class="field">
            <label for="methodProvider">Provider</label>
            <input id="methodProvider" name="provider" class="input" data-field-control="provider" value="${escapeHtmlAttr(method.provider || '')}" placeholder="vnpay">
            <div class="field-error" data-field-error="provider" hidden></div>
          </div>
          <div class="field">
            <label for="methodChannelType">Channel Type</label>
            <select id="methodChannelType" name="channel_type" class="select" data-field-control="channel_type">
              ${buildOptions([
                { value: 'e_wallet', label: 'E-wallet' },
                { value: 'gateway', label: 'Gateway' },
                { value: 'international', label: 'International' },
                { value: 'counter', label: 'Counter' },
              ], method.channel_type || 'gateway')}
            </select>
            <div class="field-error" data-field-error="channel_type" hidden></div>
          </div>
          <div class="field">
            <label for="methodStatus">Status</label>
            <select id="methodStatus" name="status" class="select" data-field-control="status">
              ${buildOptions([
                { value: 'active', label: 'Active' },
                { value: 'maintenance', label: 'Maintenance' },
                { value: 'disabled', label: 'Disabled' },
              ], method.status || 'active')}
            </select>
            <div class="field-error" data-field-error="status" hidden></div>
          </div>
          <div class="field">
            <label for="methodSettlementCycle">Settlement Cycle</label>
            <input id="methodSettlementCycle" name="settlement_cycle" class="input" data-field-control="settlement_cycle" value="${escapeHtmlAttr(method.settlement_cycle || 'instant')}" placeholder="T+1">
            <div class="field-error" data-field-error="settlement_cycle" hidden></div>
          </div>
          <div class="field">
            <label for="methodFeeRate">Fee Rate Percent</label>
            <input id="methodFeeRate" name="fee_rate_percent" type="number" step="0.01" min="0" max="100" class="input" data-field-control="fee_rate_percent" value="${escapeHtmlAttr(formatNumberInput(method.fee_rate_percent))}">
            <div class="field-error" data-field-error="fee_rate_percent" hidden></div>
          </div>
          <div class="field">
            <label for="methodFixedFee">Fixed Fee Amount</label>
            <input id="methodFixedFee" name="fixed_fee_amount" type="number" step="0.01" min="0" class="input" data-field-control="fixed_fee_amount" value="${escapeHtmlAttr(formatNumberInput(method.fixed_fee_amount))}">
            <div class="field-error" data-field-error="fixed_fee_amount" hidden></div>
          </div>
          <div class="field">
            <label for="methodDisplayOrder">Display Order</label>
            <input id="methodDisplayOrder" name="display_order" type="number" step="1" min="0" class="input" data-field-control="display_order" value="${escapeHtmlAttr(method.display_order ?? '')}">
            <div class="field-error" data-field-error="display_order" hidden></div>
          </div>
        </div>
        <div class="grid-2" style="gap:12px;">
          <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid rgba(255,255,255,0.08);border-radius:12px;">
            <input type="checkbox" name="supports_refund" ${method.supports_refund ? 'checked' : ''}>
            <span>Supports refund</span>
          </label>
          <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid rgba(255,255,255,0.08);border-radius:12px;">
            <input type="checkbox" name="supports_webhook" ${method.supports_webhook ? 'checked' : ''}>
            <span>Supports webhook</span>
          </label>
          <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1px solid rgba(255,255,255,0.08);border-radius:12px;">
            <input type="checkbox" name="supports_redirect" ${method.supports_redirect ? 'checked' : ''}>
            <span>Supports redirect</span>
          </label>
        </div>
        <div class="field">
          <label for="methodDescription">Description</label>
          <textarea id="methodDescription" name="description" class="textarea" data-field-control="description" rows="4" placeholder="Operational note for admins and checkout observability.">${escapeHtml(method.description || '')}</textarea>
          <div class="field-error" data-field-error="description" hidden></div>
        </div>
      </form>
    `;
  }

  function readPaymentMethodForm(form) {
    return {
      code: String(form.querySelector('[name="code"]')?.value || '').trim().toLowerCase(),
      name: String(form.querySelector('[name="name"]')?.value || '').trim(),
      provider: String(form.querySelector('[name="provider"]')?.value || '').trim().toLowerCase(),
      channel_type: normalizeFilterValue(form.querySelector('[name="channel_type"]')?.value),
      status: normalizeFilterValue(form.querySelector('[name="status"]')?.value),
      fee_rate_percent: String(form.querySelector('[name="fee_rate_percent"]')?.value || '').trim(),
      fixed_fee_amount: String(form.querySelector('[name="fixed_fee_amount"]')?.value || '').trim(),
      settlement_cycle: String(form.querySelector('[name="settlement_cycle"]')?.value || '').trim(),
      display_order: String(form.querySelector('[name="display_order"]')?.value || '').trim(),
      supports_refund: Boolean(form.querySelector('[name="supports_refund"]')?.checked),
      supports_webhook: Boolean(form.querySelector('[name="supports_webhook"]')?.checked),
      supports_redirect: Boolean(form.querySelector('[name="supports_redirect"]')?.checked),
      description: String(form.querySelector('[name="description"]')?.value || '').trim(),
    };
  }

  function validatePaymentMethodForm(payload, mode) {
    const errors = {};

    if (mode !== 'edit' && !/^[a-z0-9_-]{1,30}$/.test(String(payload.code || ''))) {
      errors.code = ['Code must use lowercase letters, numbers, underscores, or hyphens only.'];
    }

    if (!String(payload.name || '').trim()) {
      errors.name = ['Payment method name is required.'];
    }

    if (!/^[a-z0-9._-]{1,50}$/.test(String(payload.provider || ''))) {
      errors.provider = ['Provider must use lowercase letters, numbers, dot, underscore, or hyphen.'];
    }

    if (!['e_wallet', 'gateway', 'international', 'counter'].includes(String(payload.channel_type || ''))) {
      errors.channel_type = ['Payment channel type is invalid.'];
    }

    if (!['active', 'maintenance', 'disabled'].includes(String(payload.status || ''))) {
      errors.status = ['Payment method status is invalid.'];
    }

    if (!String(payload.settlement_cycle || '').trim()) {
      errors.settlement_cycle = ['Settlement cycle is required.'];
    }

    const feeRate = Number(payload.fee_rate_percent);
    if (!isFiniteNumber(feeRate) || feeRate < 0 || feeRate > 100) {
      errors.fee_rate_percent = ['Fee rate percent must be between 0 and 100.'];
    }

    const fixedFee = Number(payload.fixed_fee_amount);
    if (!isFiniteNumber(fixedFee) || fixedFee < 0) {
      errors.fixed_fee_amount = ['Fixed fee amount must be zero or greater.'];
    }

    if (String(payload.display_order || '').trim() !== '') {
      const displayOrder = Number(payload.display_order);
      if (!Number.isInteger(displayOrder) || displayOrder < 0 || displayOrder > 9999) {
        errors.display_order = ['Display order must be an integer between 0 and 9999.'];
      }
    }

    return errors;
  }

  async function fetchPaymentMethod(methodId) {
    const payload = await adminApiRequest(`/api/admin/payment-methods/${encodeURIComponent(methodId)}`, {
      useStoredToken: true,
    });

    return payload?.data || {};
  }

  function normalizeMeta(meta = null, fallback = null) {
    const source = meta && typeof meta === 'object' ? meta : (fallback && typeof fallback === 'object' ? fallback : {});
    const perPage = Math.max(1, Number(source.per_page || 20));
    const total = Math.max(0, Number(source.total || 0));
    const totalPages = Math.max(1, Number(source.total_pages || Math.ceil(total / perPage) || 1));

    return {
      page: Math.max(1, Number(source.page || 1)),
      per_page: perPage,
      total,
      total_pages: totalPages,
    };
  }

  function normalizeFilterValue(value) {
    const normalized = String(value || '').trim().toLowerCase();
    return normalized === '' || normalized === 'all' ? '' : normalized;
  }

  function paginationTemplate(meta, handlerName, label) {
    const total = Number(meta?.total || 0);
    const page = Number(meta?.page || 1);
    const perPage = Number(meta?.per_page || 20);
    const totalPages = Math.max(1, Number(meta?.total_pages || 1));
    const from = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const to = Math.min(total, page * perPage);

    const buttons = [];
    buttons.push(`<button class="pg-btn${page <= 1 ? ' disabled' : ''}" ${page <= 1 ? 'disabled' : `onclick="${handlerName}(${page - 1})"`}>Prev</button>`);
    for (let current = 1; current <= totalPages; current += 1) {
      if (current === 1 || current === totalPages || Math.abs(current - page) <= 1) {
        buttons.push(`<button class="pg-btn${current === page ? ' active' : ''}" onclick="${handlerName}(${current})">${current}</button>`);
      } else if (buttons[buttons.length - 1] !== '<button class="pg-btn" disabled>...</button>') {
        buttons.push('<button class="pg-btn" disabled>...</button>');
      }
    }
    buttons.push(`<button class="pg-btn${page >= totalPages ? ' disabled' : ''}" ${page >= totalPages ? 'disabled' : `onclick="${handlerName}(${page + 1})"`}>Next</button>`);

    return `
      <div class="pagination">
        <span class="pagination-info">${total === 0 ? `No ${label} found` : `Showing ${from}-${to} of ${total} ${label}`}</span>
        <div class="pagination-actions">${buttons.join('')}</div>
      </div>
    `;
  }

  function formatCurrency(value, currency = 'VND') {
    const normalizedCurrency = String(currency || 'VND').toUpperCase();
    return new Intl.NumberFormat(normalizedCurrency === 'VND' ? 'vi-VN' : 'en-US', {
      style: 'currency',
      currency: normalizedCurrency,
      maximumFractionDigits: normalizedCurrency === 'VND' ? 0 : 2,
    }).format(Number(value || 0));
  }

  function formatPercent(value) {
    const numeric = Number(value || 0);
    if (!isFiniteNumber(numeric)) {
      return '0%';
    }

    return `${numeric.toFixed(2).replace(/\.00$/, '')}%`;
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

  function prettyPayload(value) {
    if (value === null || value === undefined || value === '') {
      return 'N/A';
    }

    if (typeof value === 'object') {
      return JSON.stringify(value, null, 2);
    }

    const raw = String(value);
    try {
      return JSON.stringify(JSON.parse(raw), null, 2);
    } catch (error) {
      return raw;
    }
  }

  function methodAccent(code, status) {
    if (String(status || '').toLowerCase() === 'disabled') {
      return '#6b7280';
    }
    if (String(status || '').toLowerCase() === 'maintenance') {
      return '#f59e0b';
    }

    const normalized = String(code || '').toLowerCase();
    if (normalized === 'momo') return '#9b2c9b';
    if (normalized === 'vnpay') return '#2563eb';
    if (normalized === 'paypal') return '#0ea5e9';
    if (normalized === 'cash') return '#16a34a';

    return '#ef4444';
  }

  function methodIcon(code) {
    const normalized = String(code || '').toLowerCase();
    if (normalized === 'momo') return 'MO';
    if (normalized === 'vnpay') return 'VN';
    if (normalized === 'paypal') return 'PP';
    if (normalized === 'cash') return 'CA';

    return normalized.slice(0, 2).toUpperCase() || 'PM';
  }

  function setText(id, value) {
    const element = document.getElementById(id);
    if (element) {
      element.textContent = value;
    }
  }

  function createEmptyMethod() {
    return {
      code: '',
      name: '',
      provider: '',
      channel_type: 'gateway',
      status: 'active',
      fee_rate_percent: 0,
      fixed_fee_amount: 0,
      settlement_cycle: 'instant',
      supports_refund: false,
      supports_webhook: false,
      supports_redirect: false,
      display_order: '',
      description: '',
    };
  }

  function isFiniteNumber(value) {
    return Number.isFinite(Number(value));
  }

  function formatNumberInput(value) {
    if (value === null || value === undefined || value === '') {
      return '';
    }

    const numeric = Number(value);
    return Number.isFinite(numeric) ? String(numeric) : '';
  }

  function normalizeMethodOptions(options, payments) {
    const source = Array.isArray(options) ? options : [];
    const map = new Map();

    source.forEach(option => {
      const code = String(option?.code || option?.payment_method || '').trim().toLowerCase();
      if (!code) {
        return;
      }

      map.set(code, {
        code,
        name: String(option?.name || option?.method_name || humanizeStatus(code)).trim(),
      });
    });

    if (Array.isArray(payments)) {
      payments.forEach(payment => {
        const code = String(payment?.payment_method || '').trim().toLowerCase();
        if (!code || map.has(code)) {
          return;
        }

        map.set(code, {
          code,
          name: String(payment?.method_name || humanizeStatus(code)).trim(),
        });
      });
    }

    return Array.from(map.values()).sort((left, right) => left.name.localeCompare(right.name));
  }

  async function exportPaymentsCsv() {
    const button = document.getElementById('paymentExportBtn');
    if (button) {
      setButtonBusy(button, true, 'Exporting...');
    }

    try {
      const rows = await collectPaymentsForExport();
      const headers = [
        'Transaction Code',
        'Method Code',
        'Method Name',
        'Gateway Ref',
        'Provider Order Ref',
        'Amount',
        'Currency',
        'Order Scope',
        'Order Code',
        'Order Status',
        'Customer Name',
        'Checkout Email',
        'Checkout Phone',
        'Payment Status',
        'Provider Response Code',
        'Provider Message',
        'Initiated At',
        'Completed At',
        'Updated At',
      ];
      const lines = [headers.join(',')];

      rows.forEach(payment => {
        lines.push([
          payment.transaction_code || '',
          payment.payment_method || '',
          payment.method_name || '',
          payment.provider_transaction_code || '',
          payment.provider_order_ref || '',
          Number(payment.amount || 0).toFixed(2),
          payment.currency || 'VND',
          payment.order_scope || '',
          payment.order_code || '',
          payment.order_status || '',
          payment.customer_name || '',
          payment.contact_email || '',
          payment.contact_phone || '',
          payment.payment_status || '',
          payment.provider_response_code || '',
          payment.provider_message || '',
          payment.initiated_at || '',
          payment.completed_at || '',
          payment.updated_at || '',
        ].map(escapeCsvValue).join(','));
      });

      downloadCsv(`payments-${new Date().toISOString().slice(0, 10)}.csv`, lines.join('\n'));
      showToast(`Exported ${rows.length} payment records.`, 'success');
    } catch (error) {
      showToast(errorMessageFromException(error, 'Failed to export payments.'), 'error');
    } finally {
      if (button) {
        setButtonBusy(button, false);
      }
    }
  }

  async function collectPaymentsForExport() {
    const firstPage = await adminApiRequest('/api/admin/payments', {
      query: {
        search: paymentState.filters.search,
        payment_method: paymentState.filters.payment_method,
        payment_status: paymentState.filters.payment_status,
        scope: paymentState.filters.scope,
        page: 1,
        per_page: 100,
      },
      useStoredToken: true,
    });

    const firstData = firstPage?.data || {};
    const meta = normalizeMeta(firstData.meta);
    const items = Array.isArray(firstData.items) ? [...firstData.items] : [];

    for (let page = 2; page <= meta.total_pages; page += 1) {
      const payload = await adminApiRequest('/api/admin/payments', {
        query: {
          search: paymentState.filters.search,
          payment_method: paymentState.filters.payment_method,
          payment_status: paymentState.filters.payment_status,
          scope: paymentState.filters.scope,
          page,
          per_page: 100,
        },
        useStoredToken: true,
      });
      const data = payload?.data || {};
      if (Array.isArray(data.items)) {
        items.push(...data.items);
      }
    }

    return items;
  }

  function escapeCsvValue(value) {
    const raw = String(value ?? '');
    return `"${raw.replace(/"/g, '""')}"`;
  }

  function downloadCsv(filename, content) {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
  }

  document.addEventListener('DOMContentLoaded', initPaymentsAdmin);
})();
