(function () {
  const state = {
    items: [],
    meta: {
      total: 0,
      page: 1,
      per_page: 10,
      total_pages: 1,
    },
    summary: {
      total: 0,
      active: 0,
      inactive: 0,
      tagged_movies: 0,
    },
    filters: {
      page: 1,
      per_page: 10,
      search: '',
      status: '',
    },
    loading: false,
    searchTimer: null,
    initialized: false,
  };

  const dom = {};

  function initMovieManagementCategories() {
    if (state.initialized || document.body?.dataset?.activePage !== 'categories') {
      return;
    }

    cacheDom();
    if (!dom.body) {
      return;
    }

    state.initialized = true;
    bindEvents();
    window.handleMovieSectionAction = openCreateCategoryModal;

    renderSummary();
    renderLoadingRow('Loading movie categories...');
    loadCategories();
  }

  function cacheDom() {
    dom.total = document.getElementById('movieCategoryTotalStat');
    dom.active = document.getElementById('movieCategoryActiveStat');
    dom.tagged = document.getElementById('movieCategoryMovieLinkStat');
    dom.inactive = document.getElementById('movieCategoryInactiveStat');
    dom.search = document.getElementById('movieCategorySearchInput');
    dom.status = document.getElementById('movieCategoryStatusFilter');
    dom.count = document.getElementById('movieCategoryCount');
    dom.requestStatus = document.getElementById('movieCategoryRequestStatus');
    dom.exportBtn = document.getElementById('movieCategoryExportBtn');
    dom.body = document.getElementById('movieCategoriesBody');
    dom.pagination = document.getElementById('movieCategoriesPagination');
  }

  function bindEvents() {
    dom.search?.addEventListener('input', handleSearchInput);
    dom.status?.addEventListener('change', () => {
      state.filters.status = dom.status.value;
      state.filters.page = 1;
      loadCategories();
    });
    dom.exportBtn?.addEventListener('click', exportCurrentCategoriesView);
    dom.body?.addEventListener('click', handleTableAction);
    dom.pagination?.addEventListener('click', handlePaginationAction);
  }

  async function loadCategories() {
    setLoading(true, 'Loading movie categories...');

    try {
      const response = await adminApiRequest('/api/admin/movie-categories', {
        query: {
          page: state.filters.page,
          per_page: state.filters.per_page,
          search: state.filters.search,
          status: state.filters.status,
        },
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
      updateRequestStatus('Category catalog synced');
    } catch (error) {
      state.items = [];
      state.meta = normalizeMeta();
      state.summary = normalizeSummary();
      renderSummary();
      renderErrorRow(errorMessageFromException(error, 'Failed to load movie categories.'));
      renderPagination();
      updateRequestStatus('Category catalog unavailable');
      showToast(errorMessageFromException(error, 'Failed to load movie categories.'), 'error');
    } finally {
      setLoading(false);
    }
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
      active: Number(summary?.active || 0),
      inactive: Number(summary?.inactive || 0),
      tagged_movies: Number(summary?.tagged_movies || 0),
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
    if (dom.active) dom.active.textContent = String(state.summary.active);
    if (dom.inactive) dom.inactive.textContent = String(state.summary.inactive);
    if (dom.tagged) dom.tagged.textContent = String(state.summary.tagged_movies);
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
      renderLoadingRow('Loading movie categories...');
      return;
    }

    if (state.items.length === 0) {
      renderEmptyRow(
        'No categories matched the current filters.',
        'Try clearing the search, changing the status filter, or create a new category.'
      );
      return;
    }

    dom.body.innerHTML = state.items.map(category => buildCategoryRow(category)).join('');
  }

  function buildCategoryRow(category) {
    const categoryStatus = Number(category.is_active) === 1 ? 'active' : 'inactive';
    const updatedAt = formatCategoryDate(category.updated_at || category.created_at);
    const description = category.description || 'No description';

    return `
      <tr>
        <td><div class="td-bold">${escapeHtml(category.name || 'Untitled Category')}</div></td>
        <td class="td-muted">${escapeHtml(category.slug || '-')}</td>
        <td class="td-muted">${escapeHtml(description)}</td>
        <td><span class="badge gray">${escapeHtml(`${Number(category.movie_count || 0)} titles`)}</span></td>
        <td class="td-muted">${escapeHtml(String(category.display_order ?? 0))}</td>
        <td>${statusBadge(categoryStatus)}</td>
        <td class="td-muted">${escapeHtml(updatedAt)}</td>
        <td>
          <div class="actions-row">
            <button class="action-btn view" type="button" title="View" data-action="view" data-category-id="${Number(category.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="action-btn edit" type="button" title="Edit" data-action="edit" data-category-id="${Number(category.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="action-btn del" type="button" title="Deactivate" data-action="deactivate" data-category-id="${Number(category.id || 0)}" ${Number(category.is_active) === 1 ? '' : 'disabled'}>
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
        <td colspan="8">
          <div class="table-empty-state">
            <strong>${escapeHtml(message)}</strong>
            <div class="table-meta-text">Please wait while category metadata is being synchronized.</div>
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
    renderEmptyRow('Category data could not be loaded.', message);
    if (dom.count) {
      dom.count.textContent = 'Category data unavailable';
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
      loadCategories();
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
    loadCategories();
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
      openPreviewCategoryModal(categoryId);
      return;
    }

    if (action === 'edit') {
      openEditCategoryModal(categoryId);
      return;
    }

    if (action === 'deactivate') {
      deactivateCategory(categoryId);
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
      showToast(errorMessageFromException(error, 'Failed to load category details.'), 'error');
    }
  }

  async function openPreviewCategoryModal(categoryId) {
    updateRequestStatus('Loading category details...');

    try {
      const category = await fetchCategoryDetail(categoryId);
      openModal('Category Details', buildCategoryPreview(category), {
        description: 'Read the schema-aligned metadata stored for this movie category.',
        note: 'This preview comes directly from the admin category API.',
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
      showToast(errorMessageFromException(error, 'Failed to load category details.'), 'error');
    }
  }

  async function fetchCategoryDetail(categoryId) {
    const response = await adminApiRequest(`/api/admin/movie-categories/${categoryId}`);
    return response?.data || {};
  }

  async function deactivateCategory(categoryId) {
    const category = state.items.find(item => Number(item.id) === Number(categoryId));
    const categoryName = category?.name || `category #${categoryId}`;

    if (!window.confirm(`Deactivate "${categoryName}"? Existing movie links will be kept, but this category will stop being active.`)) {
      return;
    }

    updateRequestStatus('Deactivating category...');

    try {
      const response = await adminApiRequest(`/api/admin/movie-categories/${categoryId}`, {
        method: 'DELETE',
      });

      if (state.items.length === 1 && state.filters.page > 1) {
        state.filters.page -= 1;
      }

      await loadCategories();
      showToast(response?.message || 'Category deactivated successfully.', 'success');
    } catch (error) {
      updateRequestStatus('Category deactivation failed');
      showToast(errorMessageFromException(error, 'Failed to deactivate category.'), 'error');
    }
  }

  function openCategoryEditorModal(title, category) {
    const isEdit = Number(category?.id || 0) > 0;

    openModal(title, buildCategoryEditorBody(category), {
      description: isEdit
        ? 'Update metadata that maps directly to the movie_categories table.'
        : 'Create a movie category using the same fields defined in the SQL schema.',
      note: 'Required fields are validated in both the browser and the backend service.',
      submitLabel: isEdit ? 'Update Category' : 'Create Category',
      busyLabel: isEdit ? 'Updating...' : 'Creating...',
      onSave: async () => {
        await submitCategoryForm(category?.id || null);
      },
    });

    attachCategoryFormInteractions(category);
  }

  function buildCategoryEditorBody(category = {}) {
    const status = Number(category.is_active ?? 1) === 1 ? 'active' : 'inactive';

    return `
      <form id="movieCategoryForm" class="form-grid" novalidate>
        <div class="form-alert" data-form-alert hidden></div>

        <div class="field">
          <label for="movieCategoryNameInput">Category Name</label>
          <input class="input" id="movieCategoryNameInput" data-field-control="name" name="name" type="text" placeholder="Action" value="${escapeHtmlAttr(category.name || '')}">
          <div class="field-error" data-field-error="name" hidden></div>
        </div>

        <div class="field">
          <label for="movieCategorySlugInput">Slug</label>
          <input class="input" id="movieCategorySlugInput" data-field-control="slug" name="slug" type="text" placeholder="action" value="${escapeHtmlAttr(category.slug || '')}">
          <div class="field-error" data-field-error="slug" hidden></div>
        </div>

        <div class="field">
          <label for="movieCategoryDisplayOrderInput">Sort Order</label>
          <input class="input" id="movieCategoryDisplayOrderInput" data-field-control="display_order" name="display_order" type="number" min="0" placeholder="0" value="${escapeHtmlAttr(category.display_order ?? 0)}">
          <div class="field-error" data-field-error="display_order" hidden></div>
        </div>

        <div class="field">
          <label for="movieCategoryStatusInput">Status</label>
          <select class="select" id="movieCategoryStatusInput" data-field-control="status" name="status">
            ${buildOptions([
              { value: 'active', label: 'Active' },
              { value: 'inactive', label: 'Inactive' },
            ], status)}
          </select>
          <div class="field-error" data-field-error="is_active" hidden></div>
        </div>

        <div class="field form-full">
          <label for="movieCategoryDescriptionInput">Description</label>
          <textarea class="textarea" id="movieCategoryDescriptionInput" data-field-control="description" name="description" placeholder="Short description for this category...">${escapeHtml(category.description || '')}</textarea>
          <div class="helper-text">This maps directly to movie_categories: name, slug, description, display_order, and is_active.</div>
          <div class="field-error" data-field-error="description" hidden></div>
        </div>
      </form>
    `;
  }

  function attachCategoryFormInteractions(category = {}) {
    const form = document.getElementById('movieCategoryForm');
    if (!form) {
      return;
    }

    const nameInput = form.querySelector('[name="name"]');
    const slugInput = form.querySelector('[name="slug"]');

    if (slugInput) {
      slugInput.dataset.manual = category.slug ? '1' : '0';
      slugInput.addEventListener('input', () => {
        slugInput.dataset.manual = slugInput.value.trim() === '' ? '0' : '1';
      });
    }

    nameInput?.addEventListener('input', () => {
      if (slugInput && slugInput.dataset.manual !== '1') {
        slugInput.value = slugifyValue(nameInput.value);
      }
    });
  }

  async function submitCategoryForm(categoryId) {
    const form = document.getElementById('movieCategoryForm');
    if (!form) {
      throw new Error('Category form is unavailable.');
    }

    const { payload, errors } = validateCategoryPayload(collectCategoryFormPayload(form));
    if (Object.keys(errors).length > 0) {
      applyFormErrors(form, errors);
      return;
    }

    clearFormErrors(form);

    try {
      const response = await adminApiRequest(categoryId ? `/api/admin/movie-categories/${categoryId}` : '/api/admin/movie-categories', {
        method: categoryId ? 'PUT' : 'POST',
        body: payload,
      });

      closeModal();
      if (!categoryId) {
        state.filters.page = 1;
      }

      await loadCategories();
      showToast(response?.message || (categoryId ? 'Category updated successfully.' : 'Category created successfully.'), 'success');
    } catch (error) {
      if (error instanceof AdminApiError && (error.status === 409 || error.status === 422)) {
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
      display_order: form.querySelector('[name="display_order"]')?.value || '',
      status: form.querySelector('[name="status"]')?.value || 'active',
      description: form.querySelector('[name="description"]')?.value || '',
    };
  }

  function validateCategoryPayload(input) {
    const errors = {};
    const payload = {
      name: String(input.name || '').trim(),
      slug: slugifyValue(input.slug || input.name || ''),
      description: String(input.description || '').trim(),
      display_order: toNonNegativeInteger(input.display_order),
      status: String(input.status || '').trim().toLowerCase(),
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
    if (!['active', 'inactive'].includes(payload.status)) {
      errors.is_active = ['Category active flag is invalid.'];
    }

    payload.is_active = payload.status === 'active' ? 1 : 0;
    payload.description = payload.description || null;

    return { payload, errors };
  }

  function buildCategoryPreview(category) {
    const categoryStatus = Number(category.is_active) === 1 ? 'active' : 'inactive';
    const updatedAt = formatCategoryDate(category.updated_at || category.created_at);

    return `
      <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="preview-banner">
          <div class="preview-banner-title">${escapeHtml(category.name || 'Untitled Category')}</div>
          <div class="preview-banner-copy">${escapeHtml(category.description || 'No description is stored for this category yet.')}</div>
          <div class="meta-pills">
            <span class="badge blue">${escapeHtml(category.slug || '-')}</span>
            <span class="badge gray">${escapeHtml(`${Number(category.movie_count || 0)} titles`)}</span>
            ${statusBadge(categoryStatus)}
          </div>
        </div>

        <div class="form-grid">
          <div class="field">
            <label>Sort Order</label>
            <input class="input" type="text" value="${escapeHtmlAttr(String(category.display_order ?? 0))}" readonly>
          </div>
          <div class="field">
            <label>Updated</label>
            <input class="input" type="text" value="${escapeHtmlAttr(updatedAt)}" readonly>
          </div>
          <div class="field form-full">
            <label>Description</label>
            <textarea class="textarea" readonly>${escapeHtml(category.description || 'N/A')}</textarea>
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
      'Movie Count',
      'Display Order',
      'Is Active',
      'Updated At',
    ];

    const rows = state.items.map(category => [
      category.id,
      category.name,
      category.slug,
      category.description || '',
      category.movie_count,
      category.display_order,
      Number(category.is_active) === 1 ? 'active' : 'inactive',
      category.updated_at || category.created_at || '',
    ]);

    const csvContent = [headers, ...rows]
      .map(columns => columns.map(toCsvCell).join(','))
      .join('\r\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const exportUrl = URL.createObjectURL(blob);
    const link = document.createElement('a');
    const dateStamp = new Date().toISOString().slice(0, 10);

    link.href = exportUrl;
    link.download = `movie-categories-page-${state.meta.page}-${dateStamp}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(exportUrl);

    showToast('Current category page exported to CSV.', 'success');
  }

  function toCsvCell(value) {
    const normalized = String(value ?? '');
    return `"${normalized.replace(/"/g, '""')}"`;
  }

  function formatCategoryDate(value) {
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

  function toNonNegativeInteger(value) {
    const parsed = Number.parseInt(String(value ?? '').trim(), 10);
    if (!Number.isInteger(parsed)) {
      return null;
    }

    return parsed >= 0 ? parsed : null;
  }

  document.addEventListener('DOMContentLoaded', initMovieManagementCategories);
})();
