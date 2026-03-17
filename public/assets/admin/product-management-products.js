(function () {
  const PRODUCT_STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'active', label: 'Active' },
    { value: 'inactive', label: 'Inactive' },
    { value: 'archived', label: 'Archived' },
  ];
  const PRODUCT_VISIBILITY_OPTIONS = [
    { value: 'featured', label: 'Featured' },
    { value: 'standard', label: 'Standard' },
    { value: 'hidden', label: 'Hidden' },
  ];
  const PRODUCT_MEDIA_SOURCE_OPTIONS = [
    { value: 'url', label: 'Remote URL' },
    { value: 'upload', label: 'Upload File' },
  ];
  const DEFAULT_SUMMARY = {
    total: 0,
    in_stock: 0,
    low_stock: 0,
    out_of_stock: 0,
    draft: 0,
    active: 0,
    inactive: 0,
    archived: 0,
  };
  const PRODUCT_IMAGE_ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  const PRODUCT_IMAGE_MAX_BYTES = 5 * 1024 * 1024;
  const PRODUCT_MAX_GALLERY_ITEMS = 12;
  const currencyFormatter = new Intl.NumberFormat('vi-VN', {
    style: 'currency',
    currency: 'VND',
    maximumFractionDigits: 0,
  });

  const state = {
    items: [],
    categories: [],
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
      category_id: '',
      stock_state: '',
      status: '',
    },
    loading: false,
    categoriesLoaded: false,
    searchTimer: null,
    initialized: false,
  };

  const dom = {};
  let productMediaEditorState = createEmptyProductMediaState();

  function initProductManagementProducts() {
    if (state.initialized || document.body?.dataset?.activePage !== 'products') {
      return;
    }

    cacheDom();
    if (!dom.body) {
      return;
    }

    state.initialized = true;
    bindEvents();
    window.handleProductSectionAction = () => {
      void openCreateProductModal();
    };

    renderSummary();
    renderCategoryOptions();
    renderLoadingRow('Loading products...');
    void refreshProductData();
  }

  function cacheDom() {
    dom.total = document.getElementById('productTotalStat');
    dom.inStock = document.getElementById('productInStockStat');
    dom.lowStock = document.getElementById('productLowStockStat');
    dom.outOfStock = document.getElementById('productOutOfStockStat');
    dom.search = document.getElementById('productSearchInput');
    dom.categoryFilter = document.getElementById('productCategoryFilter');
    dom.stockFilter = document.getElementById('productStockFilter');
    dom.statusFilter = document.getElementById('productStatusFilter');
    dom.count = document.getElementById('productCount');
    dom.requestStatus = document.getElementById('productRequestStatus');
    dom.exportBtn = document.getElementById('productExportBtn');
    dom.body = document.getElementById('productsBody');
    dom.pagination = document.getElementById('productsPagination');
  }

  function bindEvents() {
    dom.search?.addEventListener('input', handleSearchInput);
    dom.categoryFilter?.addEventListener('change', () => {
      state.filters.category_id = dom.categoryFilter.value;
      state.filters.page = 1;
      void loadProducts();
    });
    dom.stockFilter?.addEventListener('change', () => {
      state.filters.stock_state = dom.stockFilter.value;
      state.filters.page = 1;
      void loadProducts();
    });
    dom.statusFilter?.addEventListener('change', () => {
      state.filters.status = dom.statusFilter.value;
      state.filters.page = 1;
      void loadProducts();
    });
    dom.exportBtn?.addEventListener('click', exportCurrentProductView);
    dom.body?.addEventListener('click', handleTableAction);
    dom.pagination?.addEventListener('click', handlePaginationAction);
  }

  async function refreshProductData() {
    await Promise.allSettled([loadCategoryOptions(), loadProducts()]);
  }

  async function loadCategoryOptions() {
    updateRequestStatus('Loading product categories...');

    try {
      const categories = [];
      let page = 1;
      let totalPages = 1;

      while (page <= totalPages) {
        const response = await adminApiRequest('/api/admin/product-categories', {
          query: {
            page,
            per_page: 100,
          },
        });
        const payload = response?.data || {};
        const items = Array.isArray(payload.items) ? payload.items : [];
        const meta = normalizeMeta(payload.meta, { page, per_page: 100 });

        categories.push(...items.map(category => ({
          id: Number(category.id || 0),
          name: category.name || 'Untitled Category',
          slug: category.slug || '',
          visibility: category.visibility || '',
          status: category.status || '',
        })));

        totalPages = meta.total_pages;
        page += 1;
      }

      state.categories = dedupeCategories(categories);
      state.categoriesLoaded = true;
      renderCategoryOptions();
      updateRequestStatus('Product categories synced');
      return state.categories;
    } catch (error) {
      state.categories = [];
      state.categoriesLoaded = false;
      renderCategoryOptions();
      updateRequestStatus('Product category sync failed');
      showToast(errorMessageFromException(error, 'Failed to load product categories.'), 'error');
      throw error;
    }
  }

  async function ensureCategoriesReady() {
    if (state.categoriesLoaded) {
      return true;
    }

    try {
      await loadCategoryOptions();
      return true;
    } catch (error) {
      return false;
    }
  }

  async function loadProducts() {
    setLoading(true, 'Loading products...');

    try {
      const response = await adminApiRequest('/api/admin/products', {
        query: buildProductQuery(),
      });

      const payload = response?.data || {};
      state.items = Array.isArray(payload.items) ? payload.items : [];
      state.meta = normalizeMeta(payload.meta);
      state.summary = normalizeSummary(payload.summary);

      if (state.items.length === 0 && state.meta.total > 0 && state.filters.page > 1) {
        state.filters.page = Math.max(1, state.meta.total_pages);
        return loadProducts();
      }

      renderSummary();
      renderTable();
      renderPagination();
      updateRequestStatus('Product catalog synced');
    } catch (error) {
      state.items = [];
      state.meta = normalizeMeta();
      state.summary = { ...DEFAULT_SUMMARY };
      renderSummary();
      renderErrorRow(errorMessageFromException(error, 'Failed to load products.'));
      renderPagination();
      updateRequestStatus('Product catalog unavailable');
      showToast(errorMessageFromException(error, 'Failed to load products.'), 'error');
    } finally {
      setLoading(false);
    }
  }

  function buildProductQuery() {
    return {
      page: state.filters.page,
      per_page: state.filters.per_page,
      search: state.filters.search,
      category_id: state.filters.category_id,
      stock_state: state.filters.stock_state,
      status: state.filters.status,
    };
  }

  function normalizeMeta(meta = {}, fallback = {}) {
    return {
      total: Number(meta?.total || 0),
      page: Number(meta?.page || fallback.page || state.filters.page || 1),
      per_page: Number(meta?.per_page || fallback.per_page || state.filters.per_page || 10),
      total_pages: Math.max(1, Number(meta?.total_pages || 1)),
    };
  }

  function normalizeSummary(summary = {}) {
    return {
      total: Number(summary?.total || 0),
      in_stock: Number(summary?.in_stock || 0),
      low_stock: Number(summary?.low_stock || 0),
      out_of_stock: Number(summary?.out_of_stock || 0),
      draft: Number(summary?.draft || 0),
      active: Number(summary?.active || 0),
      inactive: Number(summary?.inactive || 0),
      archived: Number(summary?.archived || 0),
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
    if (dom.inStock) dom.inStock.textContent = String(state.summary.in_stock);
    if (dom.lowStock) dom.lowStock.textContent = String(state.summary.low_stock);
    if (dom.outOfStock) dom.outOfStock.textContent = String(state.summary.out_of_stock);
  }

  function renderCategoryOptions() {
    renderCategorySelect(dom.categoryFilter, state.filters.category_id, true);
  }

  function renderCategorySelect(select, selectedValue, includeAllOption) {
    if (!select) {
      return;
    }

    const options = [];
    if (includeAllOption) {
      options.push('<option value="">All Categories</option>');
    } else if (state.categories.length === 0) {
      options.push('<option value="">No categories available</option>');
    } else {
      options.push('<option value="">Select category</option>');
    }

    options.push(...state.categories.map(category => {
      const value = String(category.id);
      const selected = value === String(selectedValue || '') ? ' selected' : '';
      const suffixParts = [];
      if (category.visibility === 'hidden') suffixParts.push('Hidden');
      if (category.status && category.status !== 'active') suffixParts.push(humanizeStatus(category.status));
      const suffix = suffixParts.length > 0 ? ` (${suffixParts.join(', ')})` : '';
      return `<option value="${escapeHtmlAttr(value)}"${selected}>${escapeHtml(category.name || 'Untitled Category')}${escapeHtml(suffix)}</option>`;
    }));

    select.innerHTML = options.join('');
  }

  function renderTable() {
    if (!dom.body) {
      return;
    }

    const total = Number(state.meta.total || 0);
    if (dom.count) {
      dom.count.textContent = `${total} product${total === 1 ? '' : 's'}`;
    }

    if (state.loading && state.items.length === 0) {
      renderLoadingRow('Loading products...');
      return;
    }

    if (state.items.length === 0) {
      renderEmptyRow(
        'No products matched the current filters.',
        'Try clearing the search, changing filters, or add a new product to the catalog.'
      );
      return;
    }

    dom.body.innerHTML = state.items.map(product => buildProductRow(product)).join('');
  }

  function buildProductRow(product) {
    const productThumb = buildProductThumb(product);
    const updatedAt = formatDateValue(product.updated_at || product.created_at);
    const categoryLabel = product.category_name || getCategoryNameById(product.category_id) || 'Unassigned';
    const brandMeta = product.brand ? `<div class="td-muted">${escapeHtml(product.slug || '-')} • ${escapeHtml(product.brand)}</div>` : `<div class="td-muted">${escapeHtml(product.slug || '-')}</div>`;

    return `
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:12px;">
            ${productThumb}
            <div>
              <div class="td-bold">${escapeHtml(product.name || 'Untitled Product')}</div>
              ${brandMeta}
            </div>
          </div>
        </td>
        <td class="td-muted">${escapeHtml(product.sku || '-')}</td>
        <td><span class="badge gray"><div class="badge-dot"></div>${escapeHtml(categoryLabel)}</span></td>
        <td class="td-muted">${escapeHtml(formatCurrency(product.price, product.currency))}</td>
        <td>${stockBadge(product.stock_state, product.stock, product.track_inventory)}</td>
        <td>${visibilityBadge(product.visibility)}</td>
        <td>${statusBadge(product.status || 'draft')}</td>
        <td class="td-muted">${escapeHtml(updatedAt)}</td>
        <td>
          <div class="actions-row">
            <button class="action-btn view" type="button" title="View" data-action="view" data-product-id="${Number(product.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="action-btn edit" type="button" title="Edit" data-action="edit" data-product-id="${Number(product.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="action-btn del" type="button" title="Archive" data-action="archive" data-product-id="${Number(product.id || 0)}" ${product.status === 'archived' ? 'disabled' : ''}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `;
  }

  function buildProductThumb(product) {
    const imageUrl = String(product?.primary_image_url || '').trim();
    const altText = product?.primary_image_alt || product?.name || 'Product image';

    if (imageUrl) {
      return `<img class="poster-thumb" src="${escapeHtmlAttr(imageUrl)}" alt="${escapeHtmlAttr(altText)}" loading="lazy">`;
    }

    const name = product?.name || '';
    const label = String(name || '')
      .trim()
      .split(/\s+/)
      .slice(0, 2)
      .map(part => part.charAt(0).toUpperCase())
      .join('') || 'PR';

    return `<div class="product-thumb" aria-hidden="true">${escapeHtml(label)}</div>`;
  }

  function renderLoadingRow(message) {
    if (!dom.body) {
      return;
    }

    if (dom.count) {
      dom.count.textContent = 'Loading products...';
    }

    dom.body.innerHTML = `
      <tr class="table-empty-row">
        <td colspan="9">
          <div class="table-empty-state">
            <strong>${escapeHtml(message)}</strong>
            <div class="table-meta-text">Please wait while product inventory is being synchronized.</div>
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
    renderEmptyRow('Product data could not be loaded.', message);
    if (dom.count) {
      dom.count.textContent = 'Product data unavailable';
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
        <div class="pagination-info">Showing ${start}-${end} of ${total} products</div>
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
      void loadProducts();
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
    void loadProducts();
  }

  function handleTableAction(event) {
    const button = event.target.closest('button[data-action]');
    if (!button || button.disabled) {
      return;
    }

    const productId = Number(button.dataset.productId || 0);
    if (!productId) {
      showToast('Product ID is missing for this action.', 'error');
      return;
    }

    const action = button.dataset.action;

    if (action === 'view') {
      void openPreviewProductModal(productId);
      return;
    }

    if (action === 'edit') {
      void openEditProductModal(productId);
      return;
    }

    if (action === 'archive') {
      void archiveProduct(productId);
    }
  }

  async function openCreateProductModal() {
    if (!(await ensureCategoriesReady())) {
      showToast('Product categories must be available before creating products.', 'error');
      return;
    }

    openProductEditorModal('Add Product', {});
  }

  async function openEditProductModal(productId) {
    if (!(await ensureCategoriesReady())) {
      showToast('Product categories must be available before editing products.', 'error');
      return;
    }

    updateRequestStatus('Loading product details...');

    try {
      const product = await fetchProductDetail(productId);
      openProductEditorModal('Edit Product', product);
      updateRequestStatus('Product details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load product details');
      showToast(errorMessageFromException(error, 'Failed to load product details.'), 'error');
    }
  }

  async function openPreviewProductModal(productId) {
    updateRequestStatus('Loading product details...');

    try {
      const product = await fetchProductDetail(productId);
      openModal('Product Details', buildProductPreview(product), {
        description: 'Review the inventory, merchandising, and detail metadata stored for this product.',
        note: 'Only active and non-hidden products under active categories appear in the public shop.',
        submitLabel: 'Edit Product',
        cancelLabel: 'Close',
        busyLabel: 'Opening...',
        onSave: async () => {
          closeModal();
          await openEditProductModal(product.id);
        },
      });
      updateRequestStatus('Product details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load product details');
      showToast(errorMessageFromException(error, 'Failed to load product details.'), 'error');
    }
  }

  async function fetchProductDetail(productId) {
    const response = await adminApiRequest(`/api/admin/products/${productId}`);
    return response?.data || {};
  }

  async function archiveProduct(productId) {
    const product = state.items.find(item => Number(item.id) === Number(productId));
    const productName = product?.name || `product #${productId}`;

    if (!window.confirm(`Archive "${productName}"? Archived products become hidden and stop appearing in the public shop.`)) {
      return;
    }

    updateRequestStatus('Archiving product...');

    try {
      const response = await adminApiRequest(`/api/admin/products/${productId}`, {
        method: 'DELETE',
      });

      if (state.items.length === 1 && state.filters.page > 1) {
        state.filters.page -= 1;
      }

      await loadProducts();
      showToast(response?.message || 'Product archived successfully.', 'success');
    } catch (error) {
      updateRequestStatus('Product archive failed');
      showToast(errorMessageFromException(error, 'Failed to archive product.'), 'error');
    }
  }

  function openProductEditorModal(title, product) {
    const isEdit = Number(product?.id || 0) > 0;
    resetProductMediaEditor(product);

    openModal(title, buildProductEditorBody(product), {
      description: isEdit
        ? 'Update this product and keep the public shop catalog in sync with the admin inventory.'
        : 'Create a new product that can be published to the public shop when it is active and visible.',
      note: 'Validation runs in the browser and again in the backend service before any data is committed.',
      submitLabel: isEdit ? 'Update Product' : 'Create Product',
      busyLabel: isEdit ? 'Updating...' : 'Creating...',
      onSave: async () => {
        await submitProductForm(product?.id || null);
      },
    });

    attachProductFormInteractions(product);
  }

  function buildProductEditorBody(product = {}) {
    const attributesJson = product.attributes ? JSON.stringify(product.attributes, null, 2) : '';

    return `
      <form id="productAdminForm" class="form-grid" novalidate>
        <div class="form-alert" data-form-alert hidden></div>

        <div class="field">
          <label for="productCategoryInput">Category</label>
          <select class="select" id="productCategoryInput" data-field-control="category_id" name="category_id">
            ${buildCategoryOptions(product.category_id)}
          </select>
          <div class="field-error" data-field-error="category_id" hidden></div>
        </div>

        <div class="field">
          <label for="productNameInput">Product Name</label>
          <input class="input" id="productNameInput" data-field-control="name" name="name" type="text" placeholder="Large Popcorn Combo" value="${escapeHtmlAttr(product.name || '')}">
          <div class="field-error" data-field-error="name" hidden></div>
        </div>

        <div class="field">
          <label for="productSlugInput">Slug</label>
          <input class="input" id="productSlugInput" data-field-control="slug" name="slug" type="text" placeholder="large-popcorn-combo" value="${escapeHtmlAttr(product.slug || '')}">
          <div class="helper-text">Used by the public product detail URL. Leave blank to generate it from the product name.</div>
          <div class="field-error" data-field-error="slug" hidden></div>
        </div>

        <div class="field">
          <label for="productSkuInput">SKU</label>
          <input class="input" id="productSkuInput" data-field-control="sku" name="sku" type="text" placeholder="SKU-POP-001" value="${escapeHtmlAttr(product.sku || '')}">
          <div class="helper-text">SKU is normalized to uppercase letters, numbers, dashes, and underscores.</div>
          <div class="field-error" data-field-error="sku" hidden></div>
        </div>

        <div class="field">
          <label for="productPriceInput">Selling Price (VND)</label>
          <input class="input" id="productPriceInput" data-field-control="price" name="price" type="number" min="0" step="1000" placeholder="85000" value="${escapeHtmlAttr(formatNumericInput(product.price))}">
          <div class="field-error" data-field-error="price" hidden></div>
        </div>

        <div class="field">
          <label for="productComparePriceInput">Compare-at Price (VND)</label>
          <input class="input" id="productComparePriceInput" data-field-control="compare_at_price" name="compare_at_price" type="number" min="0" step="1000" placeholder="99000" value="${escapeHtmlAttr(formatNumericInput(product.compare_at_price))}">
          <div class="field-error" data-field-error="compare_at_price" hidden></div>
        </div>

        <div class="field">
          <label for="productStockInput">Stock</label>
          <input class="input" id="productStockInput" data-field-control="stock" name="stock" type="number" min="0" step="1" placeholder="25" value="${escapeHtmlAttr(String(product.stock ?? 0))}">
          <div class="field-error" data-field-error="stock" hidden></div>
        </div>

        <div class="field">
          <label for="productTrackInventoryInput">Track Inventory</label>
          <select class="select" id="productTrackInventoryInput" data-field-control="track_inventory" name="track_inventory">
            ${buildOptions([{ value: '1', label: 'Yes' }, { value: '0', label: 'No' }], String(product.track_inventory ?? 1))}
          </select>
          <div class="field-error" data-field-error="track_inventory" hidden></div>
        </div>

        <div class="field">
          <label for="productVisibilityInput">Visibility</label>
          <select class="select" id="productVisibilityInput" data-field-control="visibility" name="visibility">
            ${buildOptions(PRODUCT_VISIBILITY_OPTIONS, product.visibility || 'standard')}
          </select>
          <div class="helper-text">Only featured or standard products can appear in the public catalog.</div>
          <div class="field-error" data-field-error="visibility" hidden></div>
        </div>

        <div class="field">
          <label for="productStatusInput">Status</label>
          <select class="select" id="productStatusInput" data-field-control="status" name="status">
            ${buildOptions(PRODUCT_STATUS_OPTIONS, product.status || 'draft')}
          </select>
          <div class="field-error" data-field-error="status" hidden></div>
        </div>

        <div class="field">
          <label for="productSortOrderInput">Sort Order</label>
          <input class="input" id="productSortOrderInput" data-field-control="sort_order" name="sort_order" type="number" min="0" step="1" placeholder="0" value="${escapeHtmlAttr(String(product.sort_order ?? 0))}">
          <div class="field-error" data-field-error="sort_order" hidden></div>
        </div>

        <div class="field">
          <label for="productBrandInput">Brand</label>
          <input class="input" id="productBrandInput" data-field-control="brand" name="brand" type="text" placeholder="CineShop" value="${escapeHtmlAttr(product.brand || '')}">
          <div class="field-error" data-field-error="brand" hidden></div>
        </div>

        <div class="field">
          <label for="productWeightInput">Weight</label>
          <input class="input" id="productWeightInput" data-field-control="weight" name="weight" type="text" placeholder="380g" value="${escapeHtmlAttr(product.weight || '')}">
          <div class="field-error" data-field-error="weight" hidden></div>
        </div>

        <div class="field">
          <label for="productOriginInput">Origin</label>
          <input class="input" id="productOriginInput" data-field-control="origin" name="origin" type="text" placeholder="Vietnam" value="${escapeHtmlAttr(product.origin || '')}">
          <div class="field-error" data-field-error="origin" hidden></div>
        </div>

        <div class="field form-full">
          <label for="productShortDescriptionInput">Short Description</label>
          <textarea class="textarea" id="productShortDescriptionInput" data-field-control="short_description" name="short_description" placeholder="Short summary used in catalog cards...">${escapeHtml(product.short_description || '')}</textarea>
          <div class="field-error" data-field-error="short_description" hidden></div>
        </div>

        <div class="field form-full">
          <label for="productDescriptionInput">Catalog Description</label>
          <textarea class="textarea" id="productDescriptionInput" data-field-control="description" name="description" placeholder="Main product description shown to customers...">${escapeHtml(product.description || '')}</textarea>
          <div class="field-error" data-field-error="description" hidden></div>
        </div>

        <div class="field form-full">
          <label for="productDetailDescriptionInput">Detailed Description</label>
          <textarea class="textarea" id="productDetailDescriptionInput" data-field-control="detail_description" name="detail_description" placeholder="Additional detail content for the product page...">${escapeHtml(product.detail_description || '')}</textarea>
          <div class="field-error" data-field-error="detail_description" hidden></div>
        </div>

        <div class="field form-full">
          <label for="productAttributesInput">Attributes JSON</label>
          <textarea class="textarea" id="productAttributesInput" data-field-control="attributes" name="attributes" placeholder='{"bundle": true, "cups": 2}'>${escapeHtml(attributesJson)}</textarea>
          <div class="helper-text">Optional structured metadata. Provide a JSON object or array.</div>
          <div class="field-error" data-field-error="attributes" hidden></div>
        </div>

        <div class="field form-full">
          <div class="surface-card">
            <div class="surface-card-title">Product Media</div>
            <div class="surface-card-copy">Each product keeps one thumbnail cover image and can include multiple gallery images. Every image can come from a remote URL or be uploaded directly from your machine.</div>

            <div class="field form-full" style="margin-top:16px;">
              <label style="margin-bottom:0;">Primary Product Image</label>
              <div id="productThumbnailManager" data-field-control="media_thumbnail"></div>
              <div class="field-error" data-field-error="media_thumbnail" hidden></div>
            </div>

            <div class="field form-full" style="margin-top:18px;">
              <div class="product-media-toolbar">
                <label style="margin-bottom:0;">Gallery Images</label>
                <div class="product-media-toolbar-actions">
                  <button class="btn btn-secondary btn-sm" type="button" data-product-media-action="add-gallery">Add Gallery Image</button>
                  <button class="btn btn-ghost btn-sm" type="button" data-product-media-action="bulk-upload-gallery">Upload Multiple Files</button>
                  <input id="productGalleryBulkUploadInput" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" multiple data-product-media-input="gallery_bulk_files" class="product-media-bulk-input">
                </div>
              </div>
              <div class="helper-text">Gallery images appear on the public product detail carousel. You can mix remote URLs, single uploads, or choose multiple local files in one go. Maximum: 12 gallery images.</div>
              <div id="productGalleryManager" class="product-media-stack" data-field-control="media_gallery" style="margin-top:12px;"></div>
              <div class="field-error" data-field-error="media_gallery" hidden></div>
            </div>
          </div>
        </div>
      </form>
    `;
  }

  function attachProductFormInteractions(product = {}) {
    const form = document.getElementById('productAdminForm');
    if (!form) {
      return;
    }

    const nameInput = form.querySelector('[name="name"]');
    const slugInput = form.querySelector('[name="slug"]');
    const skuInput = form.querySelector('[name="sku"]');
    const statusInput = form.querySelector('[name="status"]');
    const visibilityInput = form.querySelector('[name="visibility"]');

    if (slugInput) {
      const initialSlug = String(product.slug || '').trim();
      const derivedSlug = slugifyValue(product.name || '');
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

    skuInput?.addEventListener('input', () => {
      skuInput.value = normalizeSku(skuInput.value || '');
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

    form.addEventListener('click', handleProductMediaClick);
    form.addEventListener('input', handleProductMediaInput);
    form.addEventListener('change', handleProductMediaChange);
    renderProductMediaEditor();
  }

  async function submitProductForm(productId) {
    const form = document.getElementById('productAdminForm');
    if (!form) {
      throw new Error('Product form is unavailable.');
    }

    const { payload, errors } = validateProductPayload(collectProductFormPayload(form));
    if (Object.keys(errors).length > 0) {
      applyFormErrors(form, errors);
      return;
    }

    clearFormErrors(form);
    updateRequestStatus(productId ? 'Updating product...' : 'Creating product...');

    try {
      const response = await adminApiRequest(
        productId ? `/api/admin/products/${productId}` : '/api/admin/products',
        buildProductRequestOptions(payload, productId)
      );

      closeModal();
      if (!productId) {
        state.filters.page = 1;
      }

      await loadProducts();
      showToast(response?.message || (productId ? 'Product updated successfully.' : 'Product created successfully.'), 'success');
    } catch (error) {
      updateRequestStatus('Product save failed');
      if (error instanceof AdminApiError && [404, 409, 422].includes(error.status)) {
        applyFormErrors(form, error.errors);
        return;
      }

      throw error;
    }
  }

  function collectProductFormPayload(form) {
    return {
      category_id: form.querySelector('[name="category_id"]')?.value || '',
      name: form.querySelector('[name="name"]')?.value || '',
      slug: form.querySelector('[name="slug"]')?.value || '',
      sku: form.querySelector('[name="sku"]')?.value || '',
      price: form.querySelector('[name="price"]')?.value || '',
      compare_at_price: form.querySelector('[name="compare_at_price"]')?.value || '',
      stock: form.querySelector('[name="stock"]')?.value || '',
      track_inventory: form.querySelector('[name="track_inventory"]')?.value || '1',
      visibility: form.querySelector('[name="visibility"]')?.value || 'standard',
      status: form.querySelector('[name="status"]')?.value || 'draft',
      sort_order: form.querySelector('[name="sort_order"]')?.value || '0',
      brand: form.querySelector('[name="brand"]')?.value || '',
      weight: form.querySelector('[name="weight"]')?.value || '',
      origin: form.querySelector('[name="origin"]')?.value || '',
      short_description: form.querySelector('[name="short_description"]')?.value || '',
      description: form.querySelector('[name="description"]')?.value || '',
      detail_description: form.querySelector('[name="detail_description"]')?.value || '',
      attributes: form.querySelector('[name="attributes"]')?.value || '',
      media_items: collectProductMediaItems(),
    };
  }

  function createEmptyProductMediaState() {
    return {
      thumbnail: createProductMediaItem({ asset_type: 'thumbnail', source_type: 'url', sort_order: 0, is_primary: 1 }),
      gallery: [],
    };
  }

  function resetProductMediaEditor(product = {}) {
    revokeProductMediaObjectUrls(productMediaEditorState);

    const thumbnailSource = product?.media?.thumbnail || null;
    const gallerySource = Array.isArray(product?.media?.gallery) ? product.media.gallery : [];

    productMediaEditorState = {
      thumbnail: createProductMediaItem({
        ...(thumbnailSource || {}),
        asset_type: 'thumbnail',
        sort_order: 0,
        is_primary: 1,
      }),
      gallery: gallerySource.map((item, index) => createProductMediaItem({
        ...item,
        asset_type: 'gallery',
        sort_order: Number(item?.sort_order ?? index + 1),
        is_primary: 0,
      })),
    };
  }

  function createProductMediaItem(overrides = {}) {
    const assetType = overrides.asset_type === 'thumbnail' ? 'thumbnail' : 'gallery';
    const sourceType = overrides.source_type === 'upload' ? 'upload' : 'url';
    const storedImageUrl = String(overrides.stored_image_url || '').trim();
    const resolvedImageUrl = String(overrides.image_url || '').trim();
    const editableUrl = sourceType === 'url'
      ? String(storedImageUrl || resolvedImageUrl || '').trim()
      : '';
    const file = overrides.file instanceof File ? overrides.file : null;
    const objectUrl = typeof overrides.object_url === 'string' ? overrides.object_url : '';
    const previewUrl = String(overrides.preview_url || '').trim();

    return {
      client_key: String(overrides.client_key || `media-${Math.random().toString(36).slice(2, 10)}`),
      id: Number(overrides.id || 0) || null,
      asset_type: assetType,
      source_type: sourceType,
      existing_source_type: sourceType,
      image_url: editableUrl,
      stored_image_url: storedImageUrl,
      preview_url: previewUrl || resolvedImageUrl || (sourceType === 'url' ? editableUrl : objectUrl),
      alt_text: String(overrides.alt_text || '').trim(),
      sort_order: Number(overrides.sort_order || (assetType === 'thumbnail' ? 0 : 1)) || 0,
      is_primary: assetType === 'thumbnail' ? 1 : 0,
      file,
      object_url: objectUrl,
    };
  }

  function revokeProductMediaObjectUrls(mediaState) {
    if (!mediaState) {
      return;
    }

    const items = [mediaState.thumbnail, ...(Array.isArray(mediaState.gallery) ? mediaState.gallery : [])];
    items.forEach(item => {
      if (item?.object_url) {
        URL.revokeObjectURL(item.object_url);
        item.object_url = '';
      }
    });
  }

  function renderProductMediaEditor() {
    const thumbnailContainer = document.getElementById('productThumbnailManager');
    const galleryContainer = document.getElementById('productGalleryManager');

    if (thumbnailContainer) {
      thumbnailContainer.innerHTML = buildProductMediaCard(productMediaEditorState.thumbnail, {
        title: 'Thumbnail',
        description: 'Required cover image used on product cards and detail pages.',
        removable: false,
      });
    }

    if (!galleryContainer) {
      return;
    }

    if (!productMediaEditorState.gallery.length) {
      galleryContainer.innerHTML = `
        <div class="product-media-empty">
          <strong>No gallery images added yet.</strong>
          <div class="table-meta-text">Add one or more supporting images to enrich the public product detail view.</div>
        </div>
      `;
      return;
    }

    galleryContainer.innerHTML = productMediaEditorState.gallery
      .map((item, index) => buildProductMediaCard(item, {
        title: `Gallery Image ${index + 1}`,
        description: 'Optional supporting image shown inside the detail gallery.',
        removable: true,
      }))
      .join('');
  }

  function buildProductMediaCard(item, options = {}) {
    const previewUrl = resolveProductMediaPreview(item);
    const usesUpload = item.source_type === 'upload';
    const fileSummary = item.file
      ? `Selected file: ${escapeHtml(item.file.name)}`
      : item.stored_image_url && item.source_type === item.existing_source_type && item.source_type === 'upload'
        ? 'Current uploaded file will be kept unless you replace it.'
        : 'No local file selected yet.';

    return `
      <div class="product-media-card" data-product-media-card="${escapeHtmlAttr(item.client_key)}">
        <div class="product-media-card-head">
          <div>
            <div class="surface-card-title">${escapeHtml(options.title || 'Product Media')}</div>
            <div class="table-meta-text">${escapeHtml(options.description || '')}</div>
          </div>
          ${options.removable ? `
            <button class="btn btn-ghost btn-sm" type="button" data-product-media-action="remove-gallery" data-client-key="${escapeHtmlAttr(item.client_key)}">Remove</button>
          ` : ''}
        </div>
        <div class="product-media-card-body">
          <div class="product-media-preview">
            ${previewUrl
              ? `<img src="${escapeHtmlAttr(previewUrl)}" alt="${escapeHtmlAttr(item.alt_text || options.title || 'Product media preview')}" loading="lazy">`
              : '<div class="product-media-placeholder">IMG</div>'}
          </div>
          <div class="product-media-fields">
            <div class="field">
              <label>Source</label>
              <select class="select" data-product-media-input="source_type" data-client-key="${escapeHtmlAttr(item.client_key)}">
                ${buildOptions(PRODUCT_MEDIA_SOURCE_OPTIONS, item.source_type)}
              </select>
            </div>
            <div class="field">
              <label>Alt Text</label>
              <input class="input" type="text" data-product-media-input="alt_text" data-client-key="${escapeHtmlAttr(item.client_key)}" placeholder="Describe this image for accessibility" value="${escapeHtmlAttr(item.alt_text || '')}">
            </div>
            ${item.asset_type === 'gallery' ? `
              <div class="field">
                <label>Sort Order</label>
                <input class="input" type="number" min="0" step="1" data-product-media-input="sort_order" data-client-key="${escapeHtmlAttr(item.client_key)}" value="${escapeHtmlAttr(String(item.sort_order || 0))}">
              </div>
            ` : ''}
            ${usesUpload ? `
              <div class="field form-full">
                <label>Upload File</label>
                <input class="input" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" data-product-media-input="file" data-client-key="${escapeHtmlAttr(item.client_key)}">
                <div class="helper-text">Accepted formats: JPG, PNG, WEBP, GIF. Maximum file size: 5MB.</div>
                <div class="table-meta-text">${fileSummary}</div>
              </div>
            ` : `
              <div class="field form-full">
                <label>Image URL</label>
                <input class="input" type="url" data-product-media-input="image_url" data-client-key="${escapeHtmlAttr(item.client_key)}" placeholder="https://cdn.example.com/products/cover.jpg" value="${escapeHtmlAttr(item.image_url || '')}">
                <div class="helper-text">Only HTTP or HTTPS URLs are accepted.</div>
              </div>
            `}
          </div>
        </div>
      </div>
    `;
  }

  function resolveProductMediaPreview(item) {
    if (item.file && item.object_url) {
      return item.object_url;
    }

    if (item.file && !item.object_url) {
      item.object_url = URL.createObjectURL(item.file);
      return item.object_url;
    }

    if (item.source_type === 'url') {
      return String(item.image_url || '').trim() || String(item.preview_url || '').trim();
    }

    if (item.source_type === item.existing_source_type) {
      return String(item.preview_url || '').trim();
    }

    return '';
  }

  function handleProductMediaClick(event) {
    const button = event.target.closest('[data-product-media-action]');
    if (!button) {
      return;
    }

    const action = button.dataset.productMediaAction;
    if (action === 'add-gallery') {
      event.preventDefault();
      if (productMediaEditorState.gallery.length >= PRODUCT_MAX_GALLERY_ITEMS) {
        showToast(`Only ${PRODUCT_MAX_GALLERY_ITEMS} gallery images are allowed per product.`, 'error');
        return;
      }

      productMediaEditorState.gallery.push(createProductMediaItem({
        asset_type: 'gallery',
        source_type: 'url',
        sort_order: productMediaEditorState.gallery.length + 1,
      }));
      renderProductMediaEditor();
      return;
    }

    if (action === 'bulk-upload-gallery') {
      event.preventDefault();
      document.getElementById('productGalleryBulkUploadInput')?.click();
      return;
    }

    if (action === 'remove-gallery') {
      event.preventDefault();
      const clientKey = button.dataset.clientKey || '';
      productMediaEditorState.gallery = productMediaEditorState.gallery.filter(item => {
        if (item.client_key !== clientKey) {
          return true;
        }

        if (item.object_url) {
          URL.revokeObjectURL(item.object_url);
        }

        return false;
      }).map((item, index) => ({
        ...item,
        sort_order: index + 1,
      }));
      renderProductMediaEditor();
    }
  }

  function handleProductMediaInput(event) {
    const control = event.target.closest('[data-product-media-input]');
    if (!control) {
      return;
    }

    const item = findProductMediaItem(control.dataset.clientKey || '');
    if (!item) {
      return;
    }

    if (control.dataset.productMediaInput === 'alt_text') {
      item.alt_text = control.value || '';
      return;
    }

    if (control.dataset.productMediaInput === 'image_url') {
      item.image_url = control.value || '';
      item.preview_url = control.value || '';
      return;
    }

    if (control.dataset.productMediaInput === 'sort_order') {
      const sortOrder = toNonNegativeInteger(control.value, item.sort_order);
      item.sort_order = sortOrder === null ? item.sort_order : sortOrder;
    }
  }

  function handleProductMediaChange(event) {
    const control = event.target.closest('[data-product-media-input]');
    if (!control) {
      return;
    }

    if (control.dataset.productMediaInput === 'gallery_bulk_files') {
      appendBulkGalleryFiles(control.files);
      control.value = '';
      return;
    }

    const item = findProductMediaItem(control.dataset.clientKey || '');
    if (!item) {
      return;
    }

    if (control.dataset.productMediaInput === 'source_type') {
      item.source_type = control.value === 'upload' ? 'upload' : 'url';
      if (item.source_type === 'url') {
        item.file = null;
        if (item.object_url) {
          URL.revokeObjectURL(item.object_url);
          item.object_url = '';
        }
        item.preview_url = item.image_url || (item.existing_source_type === 'url' ? item.preview_url : '');
      }
      renderProductMediaEditor();
      return;
    }

    if (control.dataset.productMediaInput === 'image_url') {
      item.image_url = control.value || '';
      item.preview_url = control.value || '';
      renderProductMediaEditor();
      return;
    }

    if (control.dataset.productMediaInput === 'file') {
      const file = control.files?.[0] || null;
      if (!file) {
        item.file = null;
        renderProductMediaEditor();
        return;
      }

      const fileError = validateProductImageFile(file);
      if (fileError) {
        control.value = '';
        showToast(fileError, 'error');
        return;
      }

      if (item.object_url) {
        URL.revokeObjectURL(item.object_url);
      }

      item.file = file;
      item.object_url = URL.createObjectURL(file);
      item.preview_url = item.object_url;
      renderProductMediaEditor();
    }
  }

  function appendBulkGalleryFiles(fileList) {
    const files = Array.from(fileList || []).filter(file => file instanceof File);
    if (!files.length) {
      return;
    }

    const remainingSlots = PRODUCT_MAX_GALLERY_ITEMS - productMediaEditorState.gallery.length;
    if (remainingSlots <= 0) {
      showToast(`Only ${PRODUCT_MAX_GALLERY_ITEMS} gallery images are allowed per product.`, 'error');
      return;
    }

    const acceptedFiles = files.slice(0, remainingSlots);
    let addedCount = 0;

    acceptedFiles.forEach((file, index) => {
      const fileError = validateProductImageFile(file);
      if (fileError) {
        showToast(`${file.name}: ${fileError}`, 'error');
        return;
      }

      const sortOrder = productMediaEditorState.gallery.length + addedCount + 1;
      const objectUrl = URL.createObjectURL(file);
      productMediaEditorState.gallery.push(createProductMediaItem({
        asset_type: 'gallery',
        source_type: 'upload',
        sort_order: sortOrder,
        alt_text: deriveProductMediaAltText(file.name),
        file,
        object_url: objectUrl,
        preview_url: objectUrl,
      }));
      addedCount += 1;
    });

    if (files.length > remainingSlots) {
      showToast(`Only ${remainingSlots} more gallery images could be added.`, 'info');
    }

    if (addedCount > 0) {
      renderProductMediaEditor();
      showToast(`${addedCount} gallery image${addedCount > 1 ? 's were' : ' was'} added from your upload.`, 'success');
    }
  }

  function findProductMediaItem(clientKey) {
    if (!clientKey) {
      return null;
    }

    if (productMediaEditorState.thumbnail?.client_key === clientKey) {
      return productMediaEditorState.thumbnail;
    }

    return productMediaEditorState.gallery.find(item => item.client_key === clientKey) || null;
  }

  function collectProductMediaItems() {
    const items = [];
    const thumbnail = normalizeProductMediaForSubmit(productMediaEditorState.thumbnail, 0);
    if (thumbnail) {
      items.push(thumbnail);
    }

    productMediaEditorState.gallery.forEach((item, index) => {
      const normalized = normalizeProductMediaForSubmit(item, index + 1);
      if (normalized) {
        items.push(normalized);
      }
    });

    return items;
  }

  function normalizeProductMediaForSubmit(item, fallbackSortOrder) {
    if (!item) {
      return null;
    }

    const sourceType = item.source_type === 'upload' ? 'upload' : 'url';
    const sortOrder = item.asset_type === 'thumbnail'
      ? 0
      : toNonNegativeInteger(item.sort_order, fallbackSortOrder);

    return {
      id: item.id || null,
      client_key: item.client_key,
      asset_type: item.asset_type === 'thumbnail' ? 'thumbnail' : 'gallery',
      source_type: sourceType,
      image_url: sourceType === 'url' ? String(item.image_url || '').trim() : '',
      existing_image_url: sourceType === item.existing_source_type ? String(item.stored_image_url || '').trim() : '',
      alt_text: normalizeOptionalString(item.alt_text),
      sort_order: sortOrder,
      is_primary: item.asset_type === 'thumbnail' ? 1 : 0,
      file: item.file || null,
    };
  }

  function buildProductRequestOptions(payload, productId) {
    const formData = new FormData();

    formData.append('category_id', String(payload.category_id ?? ''));
    formData.append('name', payload.name || '');
    formData.append('slug', payload.slug || '');
    formData.append('sku', payload.sku || '');
    formData.append('price', payload.price !== null && payload.price !== undefined ? String(payload.price) : '');
    formData.append('compare_at_price', payload.compare_at_price !== null && payload.compare_at_price !== undefined ? String(payload.compare_at_price) : '');
    formData.append('stock', payload.stock !== null && payload.stock !== undefined ? String(payload.stock) : '');
    formData.append('currency', payload.currency || 'VND');
    formData.append('track_inventory', String(payload.track_inventory ?? 1));
    formData.append('visibility', payload.visibility || 'standard');
    formData.append('status', payload.status || 'draft');
    formData.append('sort_order', String(payload.sort_order ?? 0));
    formData.append('brand', payload.brand || '');
    formData.append('weight', payload.weight || '');
    formData.append('origin', payload.origin || '');
    formData.append('short_description', payload.short_description || '');
    formData.append('description', payload.description || '');
    formData.append('detail_description', payload.detail_description || '');
    formData.append('attributes', payload.attributes ? JSON.stringify(payload.attributes) : '');

    const mediaManifest = Array.isArray(payload.media_items) ? payload.media_items.map(item => {
      const manifestItem = {
        id: item.id,
        client_key: item.client_key,
        asset_type: item.asset_type,
        source_type: item.source_type,
        image_url: item.image_url,
        existing_image_url: item.existing_image_url,
        alt_text: item.alt_text,
        sort_order: item.sort_order,
        is_primary: item.is_primary,
      };

      if (item.file instanceof File) {
        const uploadKey = `media_file_${item.client_key}`;
        manifestItem.upload_key = uploadKey;
        formData.append(uploadKey, item.file, item.file.name);
      }

      return manifestItem;
    }) : [];

    formData.append('media_manifest', JSON.stringify(mediaManifest));

    if (productId) {
      formData.append('_method', 'PUT');
    }

    return {
      method: 'POST',
      body: formData,
    };
  }

  function validateProductImageFile(file) {
    if (!(file instanceof File)) {
      return 'Product image file is invalid.';
    }
    if (file.size <= 0) {
      return 'Product image file cannot be empty.';
    }
    if (file.size > PRODUCT_IMAGE_MAX_BYTES) {
      return 'Product image file exceeds the maximum allowed size of 5MB.';
    }
    if (!PRODUCT_IMAGE_ALLOWED_MIME_TYPES.includes(file.type)) {
      return 'Product image file type is not supported.';
    }

    return '';
  }

  function validateProductMediaItems(items) {
    const errors = {};
    if (!Array.isArray(items)) {
      return {
        items: [],
        errors: {
          media_gallery: ['Product media payload is invalid.'],
        },
      };
    }

    const normalizedItems = [];
    let thumbnailCount = 0;
    let galleryCount = 0;

    items.forEach((item, index) => {
      if (!item || typeof item !== 'object') {
        errors.media_gallery = ['Product media payload is invalid.'];
        return;
      }

      const assetType = item.asset_type === 'thumbnail' ? 'thumbnail' : 'gallery';
      const sourceType = item.source_type === 'upload' ? 'upload' : 'url';
      const normalizedItem = {
        id: toPositiveInteger(item.id),
        client_key: String(item.client_key || `media-${index}`),
        asset_type: assetType,
        source_type: sourceType,
        image_url: String(item.image_url || '').trim(),
        existing_image_url: String(item.existing_image_url || '').trim(),
        alt_text: normalizeOptionalString(item.alt_text),
        sort_order: assetType === 'thumbnail'
          ? 0
          : toNonNegativeInteger(item.sort_order, index),
        is_primary: assetType === 'thumbnail' ? 1 : 0,
        file: item.file || null,
      };

      if (assetType === 'thumbnail') {
        thumbnailCount += 1;
      } else {
        galleryCount += 1;
      }

      if (sourceType === 'url') {
        if (!isValidHttpUrl(normalizedItem.image_url)) {
          errors[assetType === 'thumbnail' ? 'media_thumbnail' : 'media_gallery'] = ['Product media URL must be a valid URL.'];
        }
      } else {
        const fileError = normalizedItem.file ? validateProductImageFile(normalizedItem.file) : '';
        const canReuseExistingUpload = normalizedItem.existing_image_url !== '' && !isValidHttpUrl(normalizedItem.existing_image_url);

        if (!normalizedItem.file && !canReuseExistingUpload) {
          errors[assetType === 'thumbnail' ? 'media_thumbnail' : 'media_gallery'] = ['Product media upload file is required.'];
        } else if (fileError) {
          errors[assetType === 'thumbnail' ? 'media_thumbnail' : 'media_gallery'] = [fileError];
        }
      }

      if (assetType === 'gallery' && normalizedItem.sort_order === null) {
        errors.media_gallery = ['Gallery image sort order must be a non-negative integer.'];
      }

      normalizedItems.push(normalizedItem);
    });

    if (thumbnailCount !== 1) {
      errors.media_thumbnail = ['Exactly one primary product image is required.'];
    }
    if (galleryCount > PRODUCT_MAX_GALLERY_ITEMS) {
      errors.media_gallery = ['Gallery image count exceeds the allowed limit.'];
    }

    return {
      items: normalizedItems,
      errors,
    };
  }

  function deriveProductMediaAltText(fileName) {
    return String(fileName || '')
      .replace(/\.[^.]+$/, '')
      .replace(/[-_]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function validateProductPayload(input) {
    const errors = {};
    const mediaResult = validateProductMediaItems(input.media_items);
    const payload = {
      category_id: toPositiveInteger(input.category_id),
      name: String(input.name || '').trim(),
      slug: slugifyValue(input.slug || input.name || ''),
      sku: normalizeSku(input.sku || ''),
      price: toNonNegativeFloat(input.price),
      compare_at_price: input.compare_at_price === '' ? null : toNonNegativeFloat(input.compare_at_price),
      stock: toNonNegativeInteger(input.stock, null),
      currency: 'VND',
      track_inventory: toBooleanInteger(input.track_inventory),
      visibility: String(input.visibility || 'standard').trim().toLowerCase(),
      status: String(input.status || 'draft').trim().toLowerCase(),
      sort_order: toNonNegativeInteger(input.sort_order, 0),
      brand: normalizeOptionalString(input.brand),
      weight: normalizeOptionalString(input.weight),
      origin: normalizeOptionalString(input.origin),
      short_description: normalizeOptionalString(input.short_description),
      description: normalizeOptionalString(input.description),
      detail_description: normalizeOptionalString(input.detail_description),
      attributes: normalizeAttributes(input.attributes),
      media_items: mediaResult.items,
    };

    if (payload.category_id === null) {
      errors.category_id = ['Category ID must be a positive integer.'];
    } else {
      const selectedCategory = state.categories.find(category => Number(category.id) === payload.category_id);
      if (!selectedCategory) {
        errors.category_id = ['Product category not found.'];
      } else if (selectedCategory.status === 'archived') {
        errors.category_id = ['Archived product categories cannot receive products.'];
      }
    }

    if (payload.name === '') {
      errors.name = ['Field is required.'];
    }
    if (payload.slug === '') {
      errors.slug = ['Slug is required.'];
    }
    if (payload.sku === '') {
      errors.sku = ['SKU is required.'];
    }
    if (payload.price === null) {
      errors.price = ['Price must be a non-negative number.'];
    }
    if (input.compare_at_price !== '' && payload.compare_at_price === null) {
      errors.compare_at_price = ['Compare-at price must be a non-negative number.'];
    } else if (payload.price !== null && payload.compare_at_price !== null && payload.compare_at_price < payload.price) {
      errors.compare_at_price = ['Compare-at price must be greater than or equal to the selling price.'];
    }
    if (payload.stock === null) {
      errors.stock = ['Stock must be a non-negative integer.'];
    }
    if (payload.track_inventory === null) {
      errors.track_inventory = ['Track inventory flag is invalid.'];
    }
    if (!PRODUCT_VISIBILITY_OPTIONS.some(option => option.value === payload.visibility)) {
      errors.visibility = ['Product visibility is invalid.'];
    }
    if (!PRODUCT_STATUS_OPTIONS.some(option => option.value === payload.status)) {
      errors.status = ['Product status is invalid.'];
    }
    if (payload.sort_order === null) {
      errors.sort_order = ['Sort order must be a non-negative integer.'];
    }
    if (payload.attributes === false) {
      errors.attributes = ['Attributes must be a valid JSON object/array or a structured payload.'];
    }
    Object.assign(errors, mediaResult.errors);

    if (payload.status === 'archived') {
      payload.visibility = 'hidden';
    }

    return { payload, errors };
  }

  function buildProductPreview(product) {
    const categoryLabel = product.category_name || getCategoryNameById(product.category_id) || 'Unassigned';
    const detailDescription = product.detail_description || 'N/A';
    const attributesJson = product.attributes ? JSON.stringify(product.attributes, null, 2) : 'N/A';
    const mediaItems = [
      product?.media?.thumbnail,
      ...(Array.isArray(product?.media?.gallery) ? product.media.gallery : []),
    ].filter(Boolean);

    return `
      <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="preview-banner">
          <div class="preview-banner-title">${escapeHtml(product.name || 'Untitled Product')}</div>
          <div class="preview-banner-copy">${escapeHtml(product.short_description || product.description || 'No product description has been stored yet.')}</div>
          <div class="meta-pills">
            <span class="badge blue"><div class="badge-dot"></div>${escapeHtml(categoryLabel)}</span>
            <span class="badge gray"><div class="badge-dot"></div>${escapeHtml(product.sku || '-')}</span>
            ${stockBadge(product.stock_state, product.stock, product.track_inventory)}
            ${statusBadge(product.status || 'draft')}
          </div>
        </div>

        <div class="field form-full">
          <label>Product Media</label>
          ${mediaItems.length ? `
            <div class="product-preview-media-row">
              ${mediaItems.map(item => `
                <div class="product-preview-media-card">
                  <div class="product-preview-media-frame">
                    ${item.image_url
                      ? `<img src="${escapeHtmlAttr(item.image_url)}" alt="${escapeHtmlAttr(item.alt_text || item.asset_type || 'Product media')}" loading="lazy">`
                      : '<div class="product-media-placeholder">IMG</div>'}
                  </div>
                  <div class="table-meta-text">${escapeHtml(humanizeStatus(item.asset_type || 'gallery'))}</div>
                </div>
              `).join('')}
            </div>
          ` : '<div class="table-meta-text">No media has been attached to this product yet.</div>'}
        </div>

        <div class="form-grid">
          <div class="field">
            <label>Slug</label>
            <input class="input" type="text" value="${escapeHtmlAttr(product.slug || '')}" readonly>
          </div>
          <div class="field">
            <label>Visibility</label>
            <input class="input" type="text" value="${escapeHtmlAttr(humanizeStatus(product.visibility || 'standard'))}" readonly>
          </div>
          <div class="field">
            <label>Price</label>
            <input class="input" type="text" value="${escapeHtmlAttr(formatCurrency(product.price, product.currency))}" readonly>
          </div>
          <div class="field">
            <label>Compare-at Price</label>
            <input class="input" type="text" value="${escapeHtmlAttr(product.compare_at_price !== null ? formatCurrency(product.compare_at_price, product.currency) : 'N/A')}" readonly>
          </div>
          <div class="field">
            <label>Stock</label>
            <input class="input" type="text" value="${escapeHtmlAttr(String(product.stock ?? 0))}" readonly>
          </div>
          <div class="field">
            <label>Track Inventory</label>
            <input class="input" type="text" value="${escapeHtmlAttr(Number(product.track_inventory) === 1 ? 'Yes' : 'No')}" readonly>
          </div>
          <div class="field">
            <label>Brand</label>
            <input class="input" type="text" value="${escapeHtmlAttr(product.brand || 'N/A')}" readonly>
          </div>
          <div class="field">
            <label>Origin</label>
            <input class="input" type="text" value="${escapeHtmlAttr(product.origin || 'N/A')}" readonly>
          </div>
          <div class="field form-full">
            <label>Description</label>
            <textarea class="textarea" readonly>${escapeHtml(product.description || 'N/A')}</textarea>
          </div>
          <div class="field form-full">
            <label>Detailed Description</label>
            <textarea class="textarea" readonly>${escapeHtml(detailDescription)}</textarea>
          </div>
          <div class="field form-full">
            <label>Attributes JSON</label>
            <textarea class="textarea" readonly>${escapeHtml(attributesJson)}</textarea>
          </div>
        </div>
      </div>
    `;
  }

  function exportCurrentProductView() {
    if (state.items.length === 0) {
      showToast('There are no product rows to export from the current view.', 'info');
      return;
    }

    const headers = [
      'ID',
      'Category',
      'Name',
      'Slug',
      'SKU',
      'Price',
      'Compare At Price',
      'Stock',
      'Track Inventory',
      'Visibility',
      'Status',
      'Stock State',
      'Brand',
      'Updated At',
    ];

    const rows = state.items.map(product => [
      product.id,
      product.category_name || getCategoryNameById(product.category_id) || '',
      product.name,
      product.slug,
      product.sku,
      product.price,
      product.compare_at_price ?? '',
      product.stock,
      Number(product.track_inventory) === 1 ? 'yes' : 'no',
      product.visibility,
      product.status,
      product.stock_state,
      product.brand || '',
      product.updated_at || product.created_at || '',
    ]);

    downloadCsv(`products-page-${state.meta.page}`, headers, rows);
    showToast('Current product page exported to CSV.', 'success');
  }

  function buildCategoryOptions(selectedValue) {
    const options = [];

    if (state.categories.length === 0) {
      options.push('<option value="">No categories available</option>');
    } else {
      options.push('<option value="">Select category</option>');
    }

    options.push(...state.categories.map(category => {
      const value = String(category.id);
      const selected = value === String(selectedValue || '') ? ' selected' : '';
      const suffixParts = [];
      if (category.status && category.status !== 'active') suffixParts.push(humanizeStatus(category.status));
      if (category.visibility === 'hidden') suffixParts.push('Hidden');
      const suffix = suffixParts.length > 0 ? ` (${suffixParts.join(', ')})` : '';
      return `<option value="${escapeHtmlAttr(value)}"${selected}>${escapeHtml(category.name || 'Untitled Category')}${escapeHtml(suffix)}</option>`;
    }));

    return options.join('');
  }

  function getCategoryNameById(categoryId) {
    const category = state.categories.find(item => Number(item.id) === Number(categoryId));
    return category?.name || '';
  }

  function dedupeCategories(categories) {
    const unique = new Map();

    categories.forEach(category => {
      const id = Number(category.id || 0);
      if (!id) {
        return;
      }

      unique.set(id, {
        id,
        name: category.name || 'Untitled Category',
        slug: category.slug || '',
        visibility: category.visibility || '',
        status: category.status || '',
      });
    });

    return Array.from(unique.values());
  }

  function visibilityBadge(value) {
    const normalized = String(value || 'standard').trim().toLowerCase();
    const color = { featured: 'gold', standard: 'blue', hidden: 'gray' }[normalized] || 'gray';
    return `<span class="badge ${color}"><div class="badge-dot"></div>${escapeHtml(humanizeStatus(normalized))}</span>`;
  }

  function stockBadge(stockState, stock, trackInventory) {
    const normalized = String(stockState || '').trim().toLowerCase() || 'in_stock';
    const color = { in_stock: 'green', low_stock: 'orange', out_of_stock: 'red', archived: 'gray' }[normalized] || 'gray';
    const label = normalized === 'archived'
      ? 'Archived'
      : Number(trackInventory) === 0
        ? 'Tracked Off'
        : `${humanizeStatus(normalized)} (${Number(stock || 0)})`;
    return `<span class="badge ${color}"><div class="badge-dot"></div>${escapeHtml(label)}</span>`;
  }

  function formatCurrency(value, currency) {
    if (!Number.isFinite(Number(value))) {
      return 'N/A';
    }

    if (String(currency || 'VND').toUpperCase() !== 'VND') {
      return `${Number(value).toFixed(2)} ${String(currency || '').toUpperCase()}`;
    }

    return currencyFormatter.format(Number(value));
  }

  function formatNumericInput(value) {
    if (value === null || value === undefined || value === '') {
      return '';
    }

    return String(Number(value));
  }

  function normalizeSku(value) {
    return String(value || '')
      .trim()
      .toUpperCase()
      .replace(/[^A-Z0-9_-]+/g, '-')
      .replace(/^-+|-+$/g, '');
  }

  function normalizeOptionalString(value) {
    const normalized = String(value || '').trim();
    return normalized === '' ? '' : normalized;
  }

  function normalizeAttributes(value) {
    const normalized = String(value ?? '').trim();
    if (normalized === '') {
      return null;
    }

    try {
      const parsed = JSON.parse(normalized);
      return Array.isArray(parsed) || (parsed && typeof parsed === 'object') ? parsed : false;
    } catch (error) {
      return false;
    }
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

  function toPositiveInteger(value) {
    const parsed = Number.parseInt(String(value ?? '').trim(), 10);
    return Number.isInteger(parsed) && parsed > 0 ? parsed : null;
  }

  function toNonNegativeInteger(value, emptyFallback = 0) {
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

  function toNonNegativeFloat(value) {
    const normalized = String(value ?? '').trim();
    if (normalized === '') {
      return null;
    }
    const parsed = Number.parseFloat(normalized);
    if (!Number.isFinite(parsed) || parsed < 0) {
      return null;
    }

    return Math.round(parsed * 100) / 100;
  }

  function toBooleanInteger(value) {
    const normalized = String(value ?? '').trim().toLowerCase();
    if (['1', 'true', 'yes', 'on'].includes(normalized)) return 1;
    if (['0', 'false', 'no', 'off'].includes(normalized)) return 0;
    return null;
  }

  document.addEventListener('DOMContentLoaded', initProductManagementProducts);
})();
