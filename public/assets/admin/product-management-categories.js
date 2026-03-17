(function () {
  const CATEGORY_VISIBILITY_OPTIONS = [
    { value: 'featured', label: 'Featured' },
    { value: 'standard', label: 'Standard' },
    { value: 'hidden', label: 'Hidden' },
  ];
  const CATEGORY_STATUS_OPTIONS = [
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'archived', label: 'Archived' },
  ];
  const DEFAULT_SUMMARY = {
    total: 0,
    featured: 0,
    standard: 0,
    hidden: 0,
    active: 0,
    inactive: 0,
    archived: 0,
    products_tagged: 0,
  };

  const state = {
    items: [],
    meta: {
      total: 0,
      page: 1,
      per_page: 10,
      total_pages: 1,
    },
    summary: { ...DEFAULT_SUMMARY },
    filters: {
      page: 1,
      per_page: 10,
      search: '',
      visibility: '',
      status: '',
    },
    loading: false,
    searchTimer: null,
    initialized: false,
  };

  const dom = {};

  function initProductManagementCategories() {
    if (state.initialized || document.body?.dataset?.activePage !== 'product-categories') {
      return;
    }

    cacheDom();
    if (!dom.body) {
      return;
    }

    state.initialized = true;
    bindEvents();
    window.handleProductSectionAction = openCreateCategoryModal;

    renderSummary();
    renderLoadingRow('Loading product categories...');
    void loadCategories();
  }

  function cacheDom() {
    dom.total = document.getElementById('productCategoryTotalStat');
    dom.featured = document.getElementById('productCategoryFeaturedStat');
    dom.tagged = document.getElementById('productCategoryTaggedStat');
    dom.hidden = document.getElementById('productCategoryHiddenStat');
    dom.search = document.getElementById('productCategorySearchInput');
    dom.visibility = document.getElementById('productCategoryVisibilityFilter');
    dom.status = document.getElementById('productCategoryStatusFilter');
    dom.count = document.getElementById('productCategoryCount');
    dom.requestStatus = document.getElementById('productCategoryRequestStatus');
    dom.exportBtn = document.getElementById('productCategoryExportBtn');
    dom.body = document.getElementById('productCategoriesBody');
    dom.pagination = document.getElementById('productCategoriesPagination');
  }

  function bindEvents() {
    dom.search?.addEventListener('input', handleSearchInput);
    dom.visibility?.addEventListener('change', () => {
      state.filters.visibility = dom.visibility.value;
      state.filters.page = 1;
      void loadCategories();
    });
    dom.status?.addEventListener('change', () => {
      state.filters.status = dom.status.value;
      state.filters.page = 1;
      void loadCategories();
    });
    dom.exportBtn?.addEventListener('click', exportCurrentCategoriesView);
    dom.body?.addEventListener('click', handleTableAction);
    dom.pagination?.addEventListener('click', handlePaginationAction);
  }

  async function loadCategories() {
    setLoading(true, 'Loading product categories...');

    try {
      const response = await adminApiRequest('/api/admin/product-categories', {
        query: buildCategoryQuery(),
      });

      const payload = response?.data || {};
      state.items = Array.isArray(payload.items) ? payload.items : [];
      state.meta = normalizeMeta(payload.meta);
      state.summary = normalizeSummary(payload.summary);

      if (state.items.length === 0 && state.meta.total > 0 && state.filters.page > 1) {
        state.filters.page = Math.max(1, state.meta.total_pages);
        return loadCategories();
      }

      renderSummary();
      renderTable();
      renderPagination();
      updateRequestStatus('Product category catalog synced');
    } catch (error) {
      state.items = [];
      state.meta = normalizeMeta();
      state.summary = { ...DEFAULT_SUMMARY };
      renderSummary();
      renderErrorRow(errorMessageFromException(error, 'Failed to load product categories.'));
      renderPagination();
      updateRequestStatus('Product category catalog unavailable');
      showToast(errorMessageFromException(error, 'Failed to load product categories.'), 'error');
    } finally {
      setLoading(false);
    }
  }

  function buildCategoryQuery() {
    return {
      page: state.filters.page,
      per_page: state.filters.per_page,
      search: state.filters.search,
      visibility: state.filters.visibility,
      status: state.filters.status,
    };
  }

  function normalizeMeta(meta = {}) {
    return {
      total: Number(meta?.total || 0),
      page: Number(meta?.page || state.filters.page || 1),
      per_page: Number(meta?.per_page || state.filters.per_page || 10),
      total_pages: Math.max(1, Number(meta?.total_pages || 1)),
    };
  }

  function normalizeSummary(summary = {}) {
    return {
      total: Number(summary?.total || 0),
      featured: Number(summary?.featured || 0),
      standard: Number(summary?.standard || 0),
      hidden: Number(summary?.hidden || 0),
      active: Number(summary?.active || 0),
      inactive: Number(summary?.inactive || 0),
      archived: Number(summary?.archived || 0),
      products_tagged: Number(summary?.products_tagged || 0),
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

  function renderSummary() {
    if (dom.total) dom.total.textContent = String(state.summary.total);
    if (dom.featured) dom.featured.textContent = String(state.summary.featured);
    if (dom.tagged) dom.tagged.textContent = String(state.summary.products_tagged);
    if (dom.hidden) dom.hidden.textContent = String(state.summary.hidden);
  }

  function renderTable() {
    if (!dom.body) {
      return;
    }

    const total = Number(state.meta.total || 0);
    if (dom.count) {
      dom.count.textContent = `${total} categor${total === 1 ? 'y' : 'ies'}`;
    }

    if (state.loading && state.items.length === 0) {
      renderLoadingRow('Loading product categories...');
      return;
    }

    if (state.items.length === 0) {
      renderEmptyRow(
        'No product categories matched the current filters.',
        'Try clearing the search, changing visibility/status filters, or create a new category.'
      );
      return;
    }

    dom.body.innerHTML = state.items.map(category => buildCategoryRow(category)).join('');
  }

  function buildCategoryRow(category) {
    const updatedAt = formatDateValue(category.updated_at || category.created_at);
    const description = category.description || 'No description';
    const productCount = Number(category.product_count || 0);

    return `
      <tr>
        <td><div class="td-bold">${escapeHtml(category.name || 'Untitled Category')}</div></td>
        <td class="td-muted">${escapeHtml(category.slug || '-')}</td>
        <td class="td-muted">${escapeHtml(description)}</td>
        <td><span class="badge gray"><div class="badge-dot"></div>${escapeHtml(`${productCount} product${productCount === 1 ? '' : 's'}`)}</span></td>
        <td class="td-muted">${escapeHtml(String(category.display_order ?? 0))}</td>
        <td>${visibilityBadge(category.visibility)}</td>
        <td>${statusBadge(category.status || 'inactive')}</td>
        <td class="td-muted">${escapeHtml(updatedAt)}</td>
        <td>
          <div class="actions-row">
            <button class="action-btn view" type="button" title="View" data-action="view" data-category-id="${Number(category.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="action-btn edit" type="button" title="Edit" data-action="edit" data-category-id="${Number(category.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="action-btn del" type="button" title="Archive" data-action="archive" data-category-id="${Number(category.id || 0)}" ${category.status === 'archived' ? 'disabled' : ''}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `;
  }

  function renderLoadingRow(message) {
    if (!dom.body) {
      return;
    }

    if (dom.count) {
      dom.count.textContent = 'Loading categories...';
    }

    dom.body.innerHTML = `
      <tr class="table-empty-row">
        <td colspan="9">
          <div class="table-empty-state">
            <strong>${escapeHtml(message)}</strong>
            <div class="table-meta-text">Please wait while product category metadata is being synchronized.</div>
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
        <td colspan="9">
          <div class="table-empty-state">
            <strong>${escapeHtml(title)}</strong>
            <div class="table-meta-text">${escapeHtml(description)}</div>
          </div>
        </td>
      </tr>
    `;
  }

  function renderErrorRow(message) {
    renderEmptyRow('Product category data could not be loaded.', message);
    if (dom.count) {
      dom.count.textContent = 'Product category data unavailable';
    }
  }

  function renderPagination() {
    if (!dom.pagination) {
      return;
    }

    const total = Number(state.meta.total || 0);
    const page = Number(state.meta.page || 1);
    const totalPages = Math.max(1, Number(state.meta.total_pages || 1));
    const perPage = Math.max(1, Number(state.meta.per_page || 10));
    const start = total === 0 ? 0 : ((page - 1) * perPage) + 1;
    const end = total === 0 ? 0 : Math.min(total, start + state.items.length - 1);
    const pages = buildVisiblePages(page, totalPages);

    dom.pagination.innerHTML = `
      <div class="pagination">
        <div class="pagination-info">Showing ${start}-${end} of ${total} categories</div>
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

  function buildVisiblePages(currentPage, totalPages) {
    if (totalPages <= 5) {
      return Array.from({ length: totalPages }, (_, index) => index + 1);
    }

    const pages = [1];
    const start = Math.max(2, currentPage - 1);
    const end = Math.min(totalPages - 1, currentPage + 1);

    if (start > 2) {
      pages.push('ellipsis');
    }

    for (let page = start; page <= end; page += 1) {
      pages.push(page);
    }

    if (end < totalPages - 1) {
      pages.push('ellipsis');
    }

    pages.push(totalPages);
    return pages;
  }

  function handleSearchInput() {
    window.clearTimeout(state.searchTimer);
    state.searchTimer = window.setTimeout(() => {
      state.filters.search = dom.search?.value.trim() || '';
      state.filters.page = 1;
      void loadCategories();
    }, 300);
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
    void loadCategories();
  }

  function handleTableAction(event) {
    const button = event.target.closest('button[data-action]');
    if (!button || button.disabled) {
      return;
    }

    const categoryId = Number(button.dataset.categoryId || 0);
    if (!categoryId) {
      showToast('Category ID is missing for this action.', 'error');
      return;
    }

    const action = button.dataset.action;

    if (action === 'view') {
      void openPreviewCategoryModal(categoryId);
      return;
    }

    if (action === 'edit') {
      void openEditCategoryModal(categoryId);
      return;
    }

    if (action === 'archive') {
      void archiveCategory(categoryId);
    }
  }

  function openCreateCategoryModal() {
    openCategoryEditorModal('Add Category', {});
  }

  async function openEditCategoryModal(categoryId) {
    updateRequestStatus('Loading category details...');

    try {
      const category = await fetchCategoryDetail(categoryId);
      openCategoryEditorModal('Edit Category', category);
      updateRequestStatus('Category details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load category details');
      showToast(errorMessageFromException(error, 'Failed to load product category details.'), 'error');
    }
  }

  async function openPreviewCategoryModal(categoryId) {
    updateRequestStatus('Loading category details...');

    try {
      const category = await fetchCategoryDetail(categoryId);
      openModal('Product Category Details', buildCategoryPreview(category), {
        description: 'Review the merchandising metadata stored for this product category.',
        note: 'Only active and non-hidden categories appear in the public shop experience.',
        submitLabel: 'Edit Category',
        cancelLabel: 'Close',
        busyLabel: 'Opening...',
        onSave: async () => {
          closeModal();
          await openEditCategoryModal(category.id);
        },
      });
      updateRequestStatus('Category details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load category details');
      showToast(errorMessageFromException(error, 'Failed to load product category details.'), 'error');
    }
  }

  async function fetchCategoryDetail(categoryId) {
    const response = await adminApiRequest(`/api/admin/product-categories/${categoryId}`);
    return response?.data || {};
  }

  async function archiveCategory(categoryId) {
    const category = state.items.find(item => Number(item.id) === Number(categoryId));
    const categoryName = category?.name || `category #${categoryId}`;

    if (!window.confirm(`Archive "${categoryName}"? Hidden archived categories will stop appearing in the user shop.`)) {
      return;
    }

    updateRequestStatus('Archiving category...');

    try {
      const response = await adminApiRequest(`/api/admin/product-categories/${categoryId}`, {
        method: 'DELETE',
      });

      if (state.items.length === 1 && state.filters.page > 1) {
        state.filters.page -= 1;
      }

      await loadCategories();
      showToast(response?.message || 'Product category archived successfully.', 'success');
    } catch (error) {
      updateRequestStatus('Category archive failed');
      showToast(errorMessageFromException(error, 'Failed to archive product category.'), 'error');
    }
  }

  function openCategoryEditorModal(title, category) {
    const isEdit = Number(category?.id || 0) > 0;

    openModal(title, buildCategoryEditorBody(category), {
      description: isEdit
        ? 'Update the category metadata used by both admin inventory and the public shop.'
        : 'Create a new product category that can be assigned to products in the catalog.',
      note: 'Required fields are validated in the browser and again by the backend service.',
      submitLabel: isEdit ? 'Update Category' : 'Create Category',
      busyLabel: isEdit ? 'Updating...' : 'Creating...',
      onSave: async () => {
        await submitCategoryForm(category?.id || null);
      },
    });

    attachCategoryFormInteractions(category);
  }

  function buildCategoryEditorBody(category = {}) {
    return `
      <form id="productCategoryForm" class="form-grid" novalidate>
        <div class="form-alert" data-form-alert hidden></div>

        <div class="field">
          <label for="productCategoryNameInput">Category Name</label>
          <input class="input" id="productCategoryNameInput" data-field-control="name" name="name" type="text" placeholder="Snacks" value="${escapeHtmlAttr(category.name || '')}">
          <div class="field-error" data-field-error="name" hidden></div>
        </div>

        <div class="field">
          <label for="productCategorySlugInput">Slug</label>
          <input class="input" id="productCategorySlugInput" data-field-control="slug" name="slug" type="text" placeholder="snacks" value="${escapeHtmlAttr(category.slug || '')}">
          <div class="helper-text">Used by filters and URLs. It will be generated automatically from the name if left blank.</div>
          <div class="field-error" data-field-error="slug" hidden></div>
        </div>

        <div class="field">
          <label for="productCategoryDisplayOrderInput">Display Order</label>
          <input class="input" id="productCategoryDisplayOrderInput" data-field-control="display_order" name="display_order" type="number" min="0" placeholder="0" value="${escapeHtmlAttr(String(category.display_order ?? 0))}">
          <div class="field-error" data-field-error="display_order" hidden></div>
        </div>

        <div class="field">
          <label for="productCategoryVisibilityInput">Visibility</label>
          <select class="select" id="productCategoryVisibilityInput" data-field-control="visibility" name="visibility">
            ${buildOptions(CATEGORY_VISIBILITY_OPTIONS, category.visibility || 'standard')}
          </select>
          <div class="helper-text">Only categories that stay active and not hidden can surface in the public shop.</div>
          <div class="field-error" data-field-error="visibility" hidden></div>
        </div>

        <div class="field">
          <label for="productCategoryStatusInput">Status</label>
          <select class="select" id="productCategoryStatusInput" data-field-control="status" name="status">
            ${buildOptions(CATEGORY_STATUS_OPTIONS, category.status || 'active')}
          </select>
          <div class="field-error" data-field-error="status" hidden></div>
        </div>

        <div class="field form-full">
          <label for="productCategoryDescriptionInput">Description</label>
          <textarea class="textarea" id="productCategoryDescriptionInput" data-field-control="description" name="description" placeholder="Describe when this category should be used...">${escapeHtml(category.description || '')}</textarea>
          <div class="field-error" data-field-error="description" hidden></div>
        </div>
      </form>
    `;
  }

  function attachCategoryFormInteractions(category = {}) {
    const form = document.getElementById('productCategoryForm');
    if (!form) {
      return;
    }

    const nameInput = form.querySelector('[name="name"]');
    const slugInput = form.querySelector('[name="slug"]');
    const statusInput = form.querySelector('[name="status"]');
    const visibilityInput = form.querySelector('[name="visibility"]');

    if (slugInput) {
      const initialSlug = String(category.slug || '').trim();
      const derivedSlug = slugifyValue(category.name || '');
      slugInput.dataset.autogenerated = (!initialSlug || initialSlug === derivedSlug) ? 'true' : 'false';
    }

    nameInput?.addEventListener('input', () => {
      if (!slugInput) {
        return;
      }

      if (slugInput.dataset.autogenerated === 'true' || slugInput.value.trim() === '') {
        slugInput.value = slugifyValue(nameInput.value || '');
      }
    });

    slugInput?.addEventListener('input', () => {
      const currentNameSlug = slugifyValue(nameInput?.value || '');
      const currentSlug = slugifyValue(slugInput.value || '');
      slugInput.dataset.autogenerated = (!currentSlug || currentSlug === currentNameSlug) ? 'true' : 'false';
    });

    const syncVisibilityWithStatus = () => {
      if (!statusInput || !visibilityInput) {
        return;
      }

      if (statusInput.value === 'archived') {
        visibilityInput.value = 'hidden';
        visibilityInput.disabled = true;
      } else {
        visibilityInput.disabled = false;
      }
    };

    statusInput?.addEventListener('change', syncVisibilityWithStatus);
    syncVisibilityWithStatus();
  }

  async function submitCategoryForm(categoryId) {
    const form = document.getElementById('productCategoryForm');
    if (!form) {
      throw new Error('Product category form is unavailable.');
    }

    const { payload, errors } = validateCategoryPayload(collectCategoryFormPayload(form));
    if (Object.keys(errors).length > 0) {
      applyFormErrors(form, errors);
      return;
    }

    clearFormErrors(form);
    updateRequestStatus(categoryId ? 'Updating category...' : 'Creating category...');

    try {
      const response = await adminApiRequest(categoryId ? `/api/admin/product-categories/${categoryId}` : '/api/admin/product-categories', {
        method: categoryId ? 'PUT' : 'POST',
        body: payload,
      });

      closeModal();
      if (!categoryId) {
        state.filters.page = 1;
      }

      await loadCategories();
      showToast(response?.message || (categoryId ? 'Product category updated successfully.' : 'Product category created successfully.'), 'success');
    } catch (error) {
      updateRequestStatus('Category save failed');
      if (error instanceof AdminApiError && [404, 409, 422].includes(error.status)) {
        applyFormErrors(form, error.errors);
        return;
      }

      throw error;
    }
  }

  function collectCategoryFormPayload(form) {
    return {
      name: form.querySelector('[name="name"]')?.value || '',
      slug: form.querySelector('[name="slug"]')?.value || '',
      display_order: form.querySelector('[name="display_order"]')?.value || '0',
      visibility: form.querySelector('[name="visibility"]')?.value || 'standard',
      status: form.querySelector('[name="status"]')?.value || 'active',
      description: form.querySelector('[name="description"]')?.value || '',
    };
  }

  function validateCategoryPayload(input) {
    const errors = {};
    const payload = {
      name: String(input.name || '').trim(),
      slug: slugifyValue(input.slug || input.name || ''),
      display_order: toNonNegativeInteger(input.display_order, 0),
      visibility: String(input.visibility || 'standard').trim().toLowerCase(),
      status: String(input.status || 'active').trim().toLowerCase(),
      description: String(input.description || '').trim(),
    };

    if (payload.name === '') {
      errors.name = ['Field is required.'];
    }
    if (payload.slug === '') {
      errors.slug = ['Slug is required.'];
    }
    if (payload.display_order === null) {
      errors.display_order = ['Display order must be a non-negative integer.'];
    }
    if (!CATEGORY_VISIBILITY_OPTIONS.some(option => option.value === payload.visibility)) {
      errors.visibility = ['Category visibility is invalid.'];
    }
    if (!CATEGORY_STATUS_OPTIONS.some(option => option.value === payload.status)) {
      errors.status = ['Category status is invalid.'];
    }

    if (payload.status === 'archived') {
      payload.visibility = 'hidden';
    }

    if (payload.description === '') {
      payload.description = '';
    }

    return { payload, errors };
  }

  function buildCategoryPreview(category) {
    const updatedAt = formatDateValue(category.updated_at || category.created_at);
    const description = category.description || 'No description is stored for this category yet.';
    const productCount = Number(category.product_count || 0);

    return `
      <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="preview-banner">
          <div class="preview-banner-title">${escapeHtml(category.name || 'Untitled Category')}</div>
          <div class="preview-banner-copy">${escapeHtml(description)}</div>
          <div class="meta-pills">
            <span class="badge blue"><div class="badge-dot"></div>${escapeHtml(category.slug || '-')}</span>
            <span class="badge gray"><div class="badge-dot"></div>${escapeHtml(`${productCount} product${productCount === 1 ? '' : 's'}`)}</span>
            ${visibilityBadge(category.visibility)}
            ${statusBadge(category.status || 'inactive')}
          </div>
        </div>

        <div class="form-grid">
          <div class="field">
            <label>Display Order</label>
            <input class="input" type="text" value="${escapeHtmlAttr(String(category.display_order ?? 0))}" readonly>
          </div>
          <div class="field">
            <label>Updated</label>
            <input class="input" type="text" value="${escapeHtmlAttr(updatedAt)}" readonly>
          </div>
          <div class="field">
            <label>Visibility</label>
            <input class="input" type="text" value="${escapeHtmlAttr(humanizeStatus(category.visibility || 'standard'))}" readonly>
          </div>
          <div class="field">
            <label>Status</label>
            <input class="input" type="text" value="${escapeHtmlAttr(humanizeStatus(category.status || 'inactive'))}" readonly>
          </div>
          <div class="field form-full">
            <label>Description</label>
            <textarea class="textarea" readonly>${escapeHtml(description)}</textarea>
          </div>
        </div>
      </div>
    `;
  }

  function exportCurrentCategoriesView() {
    if (state.items.length === 0) {
      showToast('There are no category rows to export from the current view.', 'info');
      return;
    }

    const headers = [
      'ID',
      'Name',
      'Slug',
      'Description',
      'Product Count',
      'Display Order',
      'Visibility',
      'Status',
      'Updated At',
    ];

    const rows = state.items.map(category => [
      category.id,
      category.name,
      category.slug,
      category.description || '',
      category.product_count,
      category.display_order,
      category.visibility,
      category.status,
      category.updated_at || category.created_at || '',
    ]);

    downloadCsv(`product-categories-page-${state.meta.page}`, headers, rows);
    showToast('Current product category page exported to CSV.', 'success');
  }

  function visibilityBadge(value) {
    const normalized = String(value || 'standard').trim().toLowerCase();
    const color = {
      featured: 'gold',
      standard: 'blue',
      hidden: 'gray',
    }[normalized] || 'gray';

    return `<span class="badge ${color}"><div class="badge-dot"></div>${escapeHtml(humanizeStatus(normalized))}</span>`;
  }

  function downloadCsv(fileNamePrefix, headers, rows) {
    const csvContent = [headers, ...rows]
      .map(columns => columns.map(toCsvCell).join(','))
      .join('\r\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const exportUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const dateStamp = new Date().toISOString().slice(0, 10);

    link.href = exportUrl;
    link.download = `${fileNamePrefix}-${dateStamp}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(exportUrl);
  }

  function toCsvCell(value) {
    const normalized = String(value ?? '');
    return `"${normalized.replace(/"/g, '""')}"`;
  }

  function formatDateValue(value) {
    if (!value) {
      return 'N/A';
    }

    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) {
      return String(value);
    }

    return new Intl.DateTimeFormat('en-GB', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    }).format(date);
  }

  function toNonNegativeInteger(value, emptyFallback = null) {
    const normalized = String(value ?? '').trim();
    if (normalized === '') {
      return emptyFallback;
    }

    const parsed = Number.parseInt(normalized, 10);
    if (!Number.isInteger(parsed) || parsed < 0) {
      return null;
    }

    return parsed;
  }

  document.addEventListener('DOMContentLoaded', initProductManagementCategories);
})();
