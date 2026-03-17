(function () {
  const IMAGE_TYPE_OPTIONS = [
    { value: 'thumbnail', label: 'Thumbnail' },
    { value: 'gallery', label: 'Gallery' },
    { value: 'banner', label: 'Banner' },
    { value: 'lifestyle', label: 'Lifestyle' },
  ];
  const IMAGE_STATUS_OPTIONS = [
    { value: 'draft', label: 'Draft' },
    { value: 'active', label: 'Active' },
    { value: 'archived', label: 'Archived' },
  ];
  const IMAGE_SOURCE_OPTIONS = [
    { value: 'url', label: 'Remote URL' },
    { value: 'upload', label: 'Upload File' },
  ];
  const DEFAULT_SUMMARY = {
    total: 0,
    primary: 0,
    thumbnail: 0,
    gallery: 0,
    banner: 0,
    lifestyle: 0,
    draft: 0,
    active: 0,
    archived: 0,
  };
  const IMAGE_ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
  const IMAGE_MAX_BYTES = 5 * 1024 * 1024;

  const state = {
    items: [],
    products: [],
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
      product_id: '',
      asset_type: '',
      status: '',
    },
    loading: false,
    productsLoaded: false,
    searchTimer: null,
    initialized: false,
  };

  const dom = {};

  function initProductManagementImages() {
    if (state.initialized || document.body?.dataset?.activePage !== 'product-images') {
      return;
    }

    cacheDom();
    if (!dom.body) {
      return;
    }

    state.initialized = true;
    bindEvents();
    window.handleProductSectionAction = () => {
      void openCreateImageModal();
    };

    renderSummary();
    renderProductOptions();
    renderLoadingRow('Loading product images...');
    void refreshImageData();
  }

  function cacheDom() {
    dom.total = document.getElementById('productImageTotalStat');
    dom.banner = document.getElementById('productImageBannerStat');
    dom.active = document.getElementById('productImageActiveStat');
    dom.draft = document.getElementById('productImageDraftStat');
    dom.search = document.getElementById('productImageSearchInput');
    dom.productFilter = document.getElementById('productImageProductFilter');
    dom.typeFilter = document.getElementById('productImageTypeFilter');
    dom.statusFilter = document.getElementById('productImageStatusFilter');
    dom.count = document.getElementById('productImageCount');
    dom.requestStatus = document.getElementById('productImageRequestStatus');
    dom.exportBtn = document.getElementById('productImageExportBtn');
    dom.body = document.getElementById('productImagesBody');
    dom.pagination = document.getElementById('productImagesPagination');
  }

  function bindEvents() {
    dom.search?.addEventListener('input', handleSearchInput);
    dom.productFilter?.addEventListener('change', () => {
      state.filters.product_id = dom.productFilter.value;
      state.filters.page = 1;
      void loadImages();
    });
    dom.typeFilter?.addEventListener('change', () => {
      state.filters.asset_type = dom.typeFilter.value;
      state.filters.page = 1;
      void loadImages();
    });
    dom.statusFilter?.addEventListener('change', () => {
      state.filters.status = dom.statusFilter.value;
      state.filters.page = 1;
      void loadImages();
    });
    dom.exportBtn?.addEventListener('click', exportCurrentImageView);
    dom.body?.addEventListener('click', handleTableAction);
    dom.pagination?.addEventListener('click', handlePaginationAction);
  }

  async function refreshImageData() {
    await Promise.allSettled([loadProductOptions(), loadImages()]);
  }

  async function loadProductOptions() {
    updateRequestStatus('Loading products...');

    try {
      const products = [];
      let page = 1;
      let totalPages = 1;

      while (page <= totalPages) {
        const response = await adminApiRequest('/api/admin/products', {
          query: {
            page,
            per_page: 100,
          },
        });
        const payload = response?.data || {};
        const items = Array.isArray(payload.items) ? payload.items : [];
        const meta = normalizeMeta(payload.meta, { page, per_page: 100 });

        products.push(...items.map(product => ({
          id: Number(product.id || 0),
          name: product.name || 'Untitled Product',
          slug: product.slug || '',
          status: product.status || '',
        })));

        totalPages = meta.total_pages;
        page += 1;
      }

      state.products = dedupeProducts(products);
      state.productsLoaded = true;
      renderProductOptions();
      updateRequestStatus('Product options synced');
      return state.products;
    } catch (error) {
      state.products = [];
      state.productsLoaded = false;
      renderProductOptions();
      updateRequestStatus('Product option sync failed');
      showToast(errorMessageFromException(error, 'Failed to load product options.'), 'error');
      throw error;
    }
  }

  async function ensureProductsReady() {
    if (state.productsLoaded) {
      return true;
    }

    try {
      await loadProductOptions();
      return true;
    } catch (error) {
      return false;
    }
  }

  async function loadImages() {
    setLoading(true, 'Loading product images...');

    try {
      const response = await adminApiRequest('/api/admin/product-images', {
        query: buildImageQuery(),
      });

      const payload = response?.data || {};
      state.items = Array.isArray(payload.items) ? payload.items : [];
      state.meta = normalizeMeta(payload.meta);
      state.summary = normalizeSummary(payload.summary);

      if (state.items.length === 0 && state.meta.total > 0 && state.filters.page > 1) {
        state.filters.page = Math.max(1, state.meta.total_pages);
        return loadImages();
      }

      renderSummary();
      renderTable();
      renderPagination();
      updateRequestStatus('Product image catalog synced');
    } catch (error) {
      state.items = [];
      state.meta = normalizeMeta();
      state.summary = { ...DEFAULT_SUMMARY };
      renderSummary();
      renderErrorRow(errorMessageFromException(error, 'Failed to load product images.'));
      renderPagination();
      updateRequestStatus('Product image catalog unavailable');
      showToast(errorMessageFromException(error, 'Failed to load product images.'), 'error');
    } finally {
      setLoading(false);
    }
  }

  function buildImageQuery() {
    return {
      page: state.filters.page,
      per_page: state.filters.per_page,
      search: state.filters.search,
      product_id: state.filters.product_id,
      asset_type: state.filters.asset_type,
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
      primary: Number(summary?.primary || 0),
      thumbnail: Number(summary?.thumbnail || 0),
      gallery: Number(summary?.gallery || 0),
      banner: Number(summary?.banner || 0),
      lifestyle: Number(summary?.lifestyle || 0),
      draft: Number(summary?.draft || 0),
      active: Number(summary?.active || 0),
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
    if (dom.banner) dom.banner.textContent = String(state.summary.banner);
    if (dom.active) dom.active.textContent = String(state.summary.active);
    if (dom.draft) dom.draft.textContent = String(state.summary.draft);
  }

  function renderProductOptions() {
    renderProductSelect(dom.productFilter, state.filters.product_id, true);
  }

  function renderProductSelect(select, selectedValue, includeAllOption) {
    if (!select) {
      return;
    }

    const options = [];
    if (includeAllOption) {
      options.push('<option value="">All Products</option>');
    } else if (state.products.length === 0) {
      options.push('<option value="">No products available</option>');
    } else {
      options.push('<option value="">Select product</option>');
    }

    options.push(...state.products.map(product => {
      const value = String(product.id);
      const selected = value === String(selectedValue || '') ? ' selected' : '';
      const suffix = product.status && product.status !== 'active' ? ` (${humanizeStatus(product.status)})` : '';
      return `<option value="${escapeHtmlAttr(value)}"${selected}>${escapeHtml(product.name || 'Untitled Product')}${escapeHtml(suffix)}</option>`;
    }));

    select.innerHTML = options.join('');
  }

  function renderTable() {
    if (!dom.body) {
      return;
    }

    const total = Number(state.meta.total || 0);
    if (dom.count) {
      dom.count.textContent = `${total} asset${total === 1 ? '' : 's'}`;
    }

    if (state.loading && state.items.length === 0) {
      renderLoadingRow('Loading product images...');
      return;
    }

    if (state.items.length === 0) {
      renderEmptyRow(
        'No product images matched the current filters.',
        'Try clearing the search, changing filters, or add a new product image.'
      );
      return;
    }

    dom.body.innerHTML = state.items.map(image => buildImageRow(image)).join('');
  }

  function buildImageRow(image) {
    const preview = buildImageThumb(image.image_url, image.alt_text || image.product_name || image.asset_type);
    const updatedAt = formatDateValue(image.updated_at || image.created_at);
    const productLabel = image.product_name || getProductNameById(image.product_id) || `Product #${image.product_id}`;
    const productMeta = image.product_slug ? `<div class="td-muted">${escapeHtml(image.product_slug)}</div>` : '';

    return `
      <tr>
        <td>${preview}</td>
        <td>
          <div class="td-bold">${escapeHtml(productLabel)}</div>
          ${productMeta}
        </td>
        <td><span class="badge blue"><div class="badge-dot"></div>${escapeHtml(humanizeStatus(image.asset_type || 'gallery'))}</span></td>
        <td class="td-muted">${escapeHtml(image.alt_text || 'No alt text')}</td>
        <td class="td-muted">${escapeHtml(String(image.sort_order ?? 0))}</td>
        <td>${primaryBadge(image.is_primary)}</td>
        <td>${statusBadge(image.status || 'draft')}</td>
        <td class="td-muted">${escapeHtml(updatedAt)}</td>
        <td>
          <div class="actions-row">
            <button class="action-btn view" type="button" title="View" data-action="view" data-image-id="${Number(image.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="action-btn edit" type="button" title="Edit" data-action="edit" data-image-id="${Number(image.id || 0)}">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="action-btn del" type="button" title="Archive" data-action="archive" data-image-id="${Number(image.id || 0)}" ${image.status === 'archived' ? 'disabled' : ''}>
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            </button>
          </div>
        </td>
      </tr>
    `;
  }

  function buildImageThumb(url, altText) {
    if (url) {
      return `<img class="poster-thumb" src="${escapeHtmlAttr(url)}" alt="${escapeHtmlAttr(altText || 'Product image')}" loading="lazy">`;
    }

    return '<div class="poster-img-placeholder" style="width:38px;height:54px;">IMG</div>';
  }

  function renderLoadingRow(message) {
    if (!dom.body) {
      return;
    }

    if (dom.count) {
      dom.count.textContent = 'Loading assets...';
    }

    dom.body.innerHTML = `
      <tr class="table-empty-row">
        <td colspan="9">
          <div class="table-empty-state">
            <strong>${escapeHtml(message)}</strong>
            <div class="table-meta-text">Please wait while product image metadata is being synchronized.</div>
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
    renderEmptyRow('Product image data could not be loaded.', message);
    if (dom.count) {
      dom.count.textContent = 'Product image data unavailable';
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
        <div class="pagination-info">Showing ${start}-${end} of ${total} assets</div>
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
      void loadImages();
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
    void loadImages();
  }

  function handleTableAction(event) {
    const button = event.target.closest('button[data-action]');
    if (!button || button.disabled) {
      return;
    }

    const imageId = Number(button.dataset.imageId || 0);
    if (!imageId) {
      showToast('Product image ID is missing for this action.', 'error');
      return;
    }

    const action = button.dataset.action;

    if (action === 'view') {
      void openPreviewImageModal(imageId);
      return;
    }

    if (action === 'edit') {
      void openEditImageModal(imageId);
      return;
    }

    if (action === 'archive') {
      void archiveImage(imageId);
    }
  }

  async function openCreateImageModal() {
    if (!(await ensureProductsReady())) {
      showToast('Products must be available before creating product images.', 'error');
      return;
    }

    openImageEditorModal('Upload Image', {});
  }

  async function openEditImageModal(imageId) {
    if (!(await ensureProductsReady())) {
      showToast('Products must be available before editing product images.', 'error');
      return;
    }

    updateRequestStatus('Loading image details...');

    try {
      const image = await fetchImageDetail(imageId);
      openImageEditorModal('Edit Image', image);
      updateRequestStatus('Product image details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load image details');
      showToast(errorMessageFromException(error, 'Failed to load product image details.'), 'error');
    }
  }

  async function openPreviewImageModal(imageId) {
    updateRequestStatus('Loading image details...');

    try {
      const image = await fetchImageDetail(imageId);
      openModal('Product Image Details', buildImagePreview(image), {
        description: 'Review the asset metadata used by product cards, banners, and detail galleries.',
        note: 'Only active images on public products can appear in the user-facing shop.',
        submitLabel: 'Edit Image',
        cancelLabel: 'Close',
        busyLabel: 'Opening...',
        onSave: async () => {
          closeModal();
          await openEditImageModal(image.id);
        },
      });
      updateRequestStatus('Product image details loaded');
    } catch (error) {
      updateRequestStatus('Failed to load image details');
      showToast(errorMessageFromException(error, 'Failed to load product image details.'), 'error');
    }
  }

  async function fetchImageDetail(imageId) {
    const response = await adminApiRequest(`/api/admin/product-images/${imageId}`);
    return response?.data || {};
  }

  async function archiveImage(imageId) {
    const image = state.items.find(item => Number(item.id) === Number(imageId));
    const imageName = image?.product_name
      ? `${image.product_name} / ${humanizeStatus(image.asset_type || 'gallery')}`
      : `image #${imageId}`;

    if (!window.confirm(`Archive "${imageName}"? Archived images lose primary status and stop appearing in the public shop.`)) {
      return;
    }

    updateRequestStatus('Archiving image...');

    try {
      const response = await adminApiRequest(`/api/admin/product-images/${imageId}`, {
        method: 'DELETE',
      });

      if (state.items.length === 1 && state.filters.page > 1) {
        state.filters.page -= 1;
      }

      await loadImages();
      showToast(response?.message || 'Product image archived successfully.', 'success');
    } catch (error) {
      updateRequestStatus('Product image archive failed');
      showToast(errorMessageFromException(error, 'Failed to archive product image.'), 'error');
    }
  }

  function openImageEditorModal(title, image) {
    const isEdit = Number(image?.id || 0) > 0;

    openModal(title, buildImageEditorBody(image), {
      description: isEdit
        ? 'Update the image metadata that powers thumbnails, banners, and gallery slots for this product.'
        : 'Create a new image record for a product using either a remote URL or a direct file upload.',
      note: 'Validation runs in the browser and again in the backend service before any image metadata is persisted.',
      submitLabel: isEdit ? 'Update Image' : 'Create Image',
      busyLabel: isEdit ? 'Updating...' : 'Creating...',
      onSave: async () => {
        await submitImageForm(image?.id || null);
      },
    });

    attachImageFormInteractions(image);
  }

  function buildImageEditorBody(image = {}) {
    const editorMode = Number(image.id || 0) > 0 ? 'edit' : 'create';
    const sourceType = image.source_type === 'upload' ? 'upload' : 'url';
    const storedImageUrl = String(image.stored_image_url || '').trim();
    const editableUrl = sourceType === 'url'
      ? String(storedImageUrl || image.image_url || '').trim()
      : '';
    const previewUrl = String(image.image_url || '').trim();

    return `
      <form id="productImageForm" class="form-grid" novalidate data-editor-mode="${escapeHtmlAttr(editorMode)}">
        <div class="form-alert" data-form-alert hidden></div>

        <div class="field form-full">
          <label for="productImageProductInput">Product</label>
          <select class="select" id="productImageProductInput" data-field-control="product_id" name="product_id">
            ${buildProductOptions(image.product_id)}
          </select>
          <div class="field-error" data-field-error="product_id" hidden></div>
        </div>

        <div class="field">
          <label for="productImageTypeInput">Asset Type</label>
          <select class="select" id="productImageTypeInput" data-field-control="asset_type" name="asset_type">
            ${buildOptions(IMAGE_TYPE_OPTIONS, image.asset_type || 'thumbnail')}
          </select>
          <div class="field-error" data-field-error="asset_type" hidden></div>
        </div>

        <div class="field">
          <label for="productImageStatusInput">Status</label>
          <select class="select" id="productImageStatusInput" data-field-control="status" name="status">
            ${buildOptions(IMAGE_STATUS_OPTIONS, image.status || 'draft')}
          </select>
          <div class="field-error" data-field-error="status" hidden></div>
        </div>

        <div class="field">
          <label for="productImagePrimaryInput">Primary Image</label>
          <select class="select" id="productImagePrimaryInput" data-field-control="is_primary" name="is_primary">
            ${buildOptions([{ value: '1', label: 'Yes' }, { value: '0', label: 'No' }], String(image.is_primary ?? 0))}
          </select>
          <div class="helper-text">Only one primary image is allowed per product and asset type.</div>
          <div class="field-error" data-field-error="is_primary" hidden></div>
        </div>

        <div class="field">
          <label for="productImageSortOrderInput">Sort Order</label>
          <input class="input" id="productImageSortOrderInput" data-field-control="sort_order" name="sort_order" type="number" min="0" step="1" placeholder="0" value="${escapeHtmlAttr(String(image.sort_order ?? 0))}">
          <div class="field-error" data-field-error="sort_order" hidden></div>
        </div>

        <div class="field form-full">
          <label for="productImageSourceInput">Image Source</label>
          <select class="select" id="productImageSourceInput" data-field-control="source_type" name="source_type">
            ${buildOptions(IMAGE_SOURCE_OPTIONS, sourceType)}
          </select>
          <div class="helper-text">Switch between a remote image URL and a direct upload from your machine.</div>
          <div class="field-error" data-field-error="source_type" hidden></div>
        </div>

        <div class="field form-full">
          <div class="product-media-card">
            <div class="product-media-card-head">
              <div>
                <div class="surface-card-title">Image Preview</div>
                <div class="table-meta-text">Review the current or newly selected image before saving.</div>
              </div>
            </div>
            <div class="product-media-card-body">
              <div class="product-media-preview" id="productImagePreviewFrame">
                ${previewUrl
                  ? `<img src="${escapeHtmlAttr(previewUrl)}" alt="${escapeHtmlAttr(image.alt_text || image.product_name || 'Product image preview')}" id="productImagePreview" loading="lazy">`
                  : '<div class="product-media-placeholder" id="productImagePreviewPlaceholder">IMG</div>'}
              </div>
              <div class="product-media-fields">
                <div class="field form-full" id="productImageUrlField">
                  <label for="productImageUrlInput">Image URL</label>
                  <input class="input" id="productImageUrlInput" data-field-control="image_url" name="image_url" type="url" placeholder="https://cdn.example.com/shop/products/popcorn-thumb.jpg" value="${escapeHtmlAttr(editableUrl)}">
                  <div class="helper-text">Only valid HTTP or HTTPS URLs are accepted.</div>
                  <div class="field-error" data-field-error="image_url" hidden></div>
                </div>

                <div class="field form-full" id="productImageUploadField">
                  <label for="productImageFileInput">Upload File</label>
                  <input class="input" id="productImageFileInput" data-field-control="image_file" name="image_file" type="file" accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif" multiple>
                  <div class="helper-text">Accepted formats: JPG, PNG, WEBP, GIF. Maximum file size: 5MB. In create mode, you can choose multiple files to create multiple image records in one save.</div>
                  <div class="table-meta-text" id="productImageFileHint">${storedImageUrl && sourceType === 'upload' ? 'Current uploaded file will be kept unless you choose a replacement.' : 'No local file selected yet.'}</div>
                  <div class="field-error" data-field-error="image_file" hidden></div>
                  <div class="field-error" data-field-error="items_manifest" hidden></div>
                </div>
              </div>
            </div>
          </div>
          <input type="hidden" name="existing_image_url" value="${escapeHtmlAttr(storedImageUrl)}">
          <input type="hidden" name="existing_source_type" value="${escapeHtmlAttr(sourceType)}">
          <input type="hidden" name="preview_url" value="${escapeHtmlAttr(previewUrl)}">
        </div>

        <div class="field form-full">
          <label for="productImageAltTextInput">Alt Text</label>
          <input class="input" id="productImageAltTextInput" data-field-control="alt_text" name="alt_text" type="text" placeholder="Describe the image for accessibility" value="${escapeHtmlAttr(image.alt_text || '')}">
          <div class="field-error" data-field-error="alt_text" hidden></div>
        </div>
      </form>
    `;
  }

  function attachImageFormInteractions(image = {}) {
    const form = document.getElementById('productImageForm');
    if (!form) {
      return;
    }

    const assetTypeInput = form.querySelector('[name="asset_type"]');
    const statusInput = form.querySelector('[name="status"]');
    const primaryInput = form.querySelector('[name="is_primary"]');
    const sourceInput = form.querySelector('[name="source_type"]');
    const urlInput = form.querySelector('[name="image_url"]');
    const fileInput = form.querySelector('[name="image_file"]');
    const existingSourceInput = form.querySelector('[name="existing_source_type"]');
    const previewUrlInput = form.querySelector('[name="preview_url"]');
    const existingImageInput = form.querySelector('[name="existing_image_url"]');

    const syncPrimaryWithStatus = () => {
      if (!statusInput || !primaryInput) {
        return;
      }

      if (statusInput.value === 'archived') {
        primaryInput.value = '0';
        primaryInput.disabled = true;
      } else {
        primaryInput.disabled = false;
      }
    };

    statusInput?.addEventListener('change', syncPrimaryWithStatus);
    syncPrimaryWithStatus();

    assetTypeInput?.addEventListener('change', () => {
      syncImageSourceMode(form);
      renderImageFormPreview(form);
    });

    sourceInput?.addEventListener('change', () => {
      syncImageSourceMode(form);
      renderImageFormPreview(form);
    });

    urlInput?.addEventListener('input', () => {
      previewUrlInput.value = urlInput.value || '';
      renderImageFormPreview(form);
    });

    fileInput?.addEventListener('change', () => {
      const files = Array.from(fileInput.files || []);
      if (!files.length) {
        renderImageFormPreview(form);
        return;
      }

      for (const file of files) {
        const fileError = validateImageUploadFile(file);
        if (fileError) {
          fileInput.value = '';
          showToast(`${file.name}: ${fileError}`, 'error');
          return;
        }
      }

      renderImageFormPreview(form);
    });

    if (existingSourceInput && !existingSourceInput.value) {
      existingSourceInput.value = image.source_type === 'upload' ? 'upload' : 'url';
    }
    if (existingImageInput && !existingImageInput.value && image.stored_image_url) {
      existingImageInput.value = image.stored_image_url;
    }
    if (previewUrlInput && !previewUrlInput.value && image.image_url) {
      previewUrlInput.value = image.image_url;
    }

    syncImageSourceMode(form);
    renderImageFormPreview(form);
  }

  async function submitImageForm(imageId) {
    const form = document.getElementById('productImageForm');
    if (!form) {
      throw new Error('Product image form is unavailable.');
    }

    const rawPayload = collectImageFormPayload(form);
    if (shouldUseBulkImageCreate(rawPayload, imageId)) {
      const { items, errors } = buildBulkImageCreatePayload(rawPayload);
      if (Object.keys(errors).length > 0) {
        applyFormErrors(form, errors);
        return;
      }

      clearFormErrors(form);
      updateRequestStatus(`Creating ${items.length} product images...`);

      try {
        const response = await adminApiRequest(
          '/api/admin/product-images/bulk',
          buildBulkImageRequestOptions(items)
        );

        closeModal();
        state.filters.page = 1;
        await loadImages();
        showToast(response?.message || `${items.length} product images created successfully.`, 'success');
      } catch (error) {
        updateRequestStatus('Product image batch save failed');
        if (error instanceof AdminApiError && [404, 409, 422].includes(error.status)) {
          applyFormErrors(form, normalizeBatchImageErrors(error.errors));
          return;
        }

        throw error;
      }

      return;
    }

    const { payload, errors } = validateImagePayload(rawPayload);
    if (Object.keys(errors).length > 0) {
      applyFormErrors(form, errors);
      return;
    }

    clearFormErrors(form);
    updateRequestStatus(imageId ? 'Updating product image...' : 'Creating product image...');

    try {
      const response = await adminApiRequest(
        imageId ? `/api/admin/product-images/${imageId}` : '/api/admin/product-images',
        buildImageRequestOptions(payload, imageId)
      );

      closeModal();
      if (!imageId) {
        state.filters.page = 1;
      }

      await loadImages();
      showToast(response?.message || (imageId ? 'Product image updated successfully.' : 'Product image created successfully.'), 'success');
    } catch (error) {
      updateRequestStatus('Product image save failed');
      if (error instanceof AdminApiError && [404, 409, 422].includes(error.status)) {
        applyFormErrors(form, error.errors);
        return;
      }

      throw error;
    }
  }

  function collectImageFormPayload(form) {
    const imageFiles = Array.from(form.querySelector('[name="image_file"]')?.files || []);

    return {
      product_id: form.querySelector('[name="product_id"]')?.value || '',
      asset_type: form.querySelector('[name="asset_type"]')?.value || '',
      source_type: form.querySelector('[name="source_type"]')?.value || 'url',
      image_url: form.querySelector('[name="image_url"]')?.value || '',
      existing_image_url: form.querySelector('[name="existing_image_url"]')?.value || '',
      existing_source_type: form.querySelector('[name="existing_source_type"]')?.value || 'url',
      image_file: imageFiles[0] || null,
      image_files: imageFiles,
      alt_text: form.querySelector('[name="alt_text"]')?.value || '',
      sort_order: form.querySelector('[name="sort_order"]')?.value || '',
      is_primary: form.querySelector('[name="is_primary"]')?.value || '0',
      status: form.querySelector('[name="status"]')?.value || 'draft',
    };
  }

  function validateImagePayload(input) {
    const errors = {};
    const payload = {
      product_id: toPositiveInteger(input.product_id),
      asset_type: String(input.asset_type || '').trim().toLowerCase(),
      source_type: String(input.source_type || 'url').trim().toLowerCase(),
      image_url: String(input.image_url || '').trim(),
      existing_image_url: String(input.existing_image_url || '').trim(),
      existing_source_type: String(input.existing_source_type || 'url').trim().toLowerCase(),
      image_file: input.image_file || null,
      alt_text: normalizeOptionalString(input.alt_text),
      sort_order: toNonNegativeInteger(input.sort_order, 0),
      is_primary: toBooleanInteger(input.is_primary),
      status: String(input.status || 'draft').trim().toLowerCase(),
    };

    if (payload.product_id === null) {
      errors.product_id = ['Product ID must be a positive integer.'];
    } else {
      const selectedProduct = state.products.find(product => Number(product.id) === payload.product_id);
      if (!selectedProduct) {
        errors.product_id = ['Product not found.'];
      } else if (selectedProduct.status === 'archived' && payload.status !== 'archived') {
        errors.product_id = ['Archived products cannot receive non-archived images.'];
      }
    }

    if (!IMAGE_TYPE_OPTIONS.some(option => option.value === payload.asset_type)) {
      errors.asset_type = ['Image asset type is invalid.'];
    }
    if (!IMAGE_SOURCE_OPTIONS.some(option => option.value === payload.source_type)) {
      errors.source_type = ['Image source type is invalid.'];
    }
    if (payload.source_type === 'url') {
      if (!isValidHttpUrl(payload.image_url)) {
        errors.image_url = ['Image URL must be a valid URL.'];
      }
    } else {
      const fileError = payload.image_file ? validateImageUploadFile(payload.image_file) : '';
      const canReuseExistingUpload = payload.existing_source_type === 'upload'
        && payload.source_type === 'upload'
        && payload.existing_image_url !== ''
        && !isValidHttpUrl(payload.existing_image_url);

      if (!payload.image_file && !canReuseExistingUpload) {
        errors.image_file = ['Image upload file is required.'];
      } else if (fileError) {
        errors.image_file = [fileError];
      }
    }
    if (payload.sort_order === null) {
      errors.sort_order = ['Sort order must be a non-negative integer.'];
    }
    if (payload.is_primary === null) {
      errors.is_primary = ['Primary flag is invalid.'];
    }
    if (!IMAGE_STATUS_OPTIONS.some(option => option.value === payload.status)) {
      errors.status = ['Image status is invalid.'];
    }

    if (payload.status === 'archived') {
      payload.is_primary = 0;
    }

    return { payload, errors };
  }

  function shouldUseBulkImageCreate(input, imageId) {
    return !imageId
      && String(input?.source_type || '').trim().toLowerCase() === 'upload'
      && Array.isArray(input?.image_files)
      && input.image_files.length > 1;
  }

  function buildBulkImageCreatePayload(input) {
    const files = Array.isArray(input?.image_files) ? input.image_files.filter(file => file instanceof File) : [];
    if (files.length < 2) {
      return {
        items: [],
        errors: {
          image_file: ['Select at least two files to create multiple product images at once.'],
        },
      };
    }

    const baseValidation = validateImagePayload({
      ...input,
      image_file: files[0],
    });
    const errors = { ...baseValidation.errors };

    if (Object.keys(errors).length > 0) {
      return { items: [], errors };
    }

    const basePayload = baseValidation.payload;
    const items = files.map((file, index) => ({
      ...basePayload,
      image_file: file,
      existing_image_url: '',
      sort_order: (basePayload.sort_order ?? 0) + index,
      is_primary: index === 0 ? (basePayload.is_primary ?? 0) : 0,
      alt_text: basePayload.alt_text || deriveImageAltText(file.name),
    }));

    return { items, errors: {} };
  }

  function buildImageRequestOptions(payload, imageId) {
    const formData = new FormData();
    const hasUploadFile = payload.image_file instanceof File;

    formData.append('product_id', String(payload.product_id ?? ''));
    formData.append('asset_type', payload.asset_type || 'gallery');
    formData.append('source_type', payload.source_type || 'url');
    formData.append('image_url', payload.source_type === 'url' ? payload.image_url || '' : '');
    formData.append('existing_image_url', payload.source_type === 'upload' ? payload.existing_image_url || '' : '');
    formData.append('alt_text', payload.alt_text || '');
    formData.append('sort_order', String(payload.sort_order ?? 0));
    formData.append('is_primary', String(payload.is_primary ?? 0));
    formData.append('status', payload.status || 'draft');

    if (hasUploadFile) {
      formData.append('upload_key', 'image_file');
      formData.append('image_file', payload.image_file, payload.image_file.name);
    }

    if (imageId) {
      formData.append('_method', 'PUT');
    }

    return {
      method: 'POST',
      body: formData,
    };
  }

  function buildBulkImageRequestOptions(items) {
    const formData = new FormData();
    const manifest = Array.isArray(items) ? items.map((item, index) => {
      const uploadKey = `image_file_${index}`;
      if (item.image_file instanceof File) {
        formData.append(uploadKey, item.image_file, item.image_file.name);
      }

      return {
        product_id: item.product_id,
        asset_type: item.asset_type || 'gallery',
        source_type: 'upload',
        image_url: '',
        existing_image_url: '',
        upload_key: uploadKey,
        alt_text: item.alt_text || '',
        sort_order: item.sort_order ?? index,
        is_primary: item.is_primary ?? 0,
        status: item.status || 'draft',
      };
    }) : [];

    formData.append('items_manifest', JSON.stringify(manifest));

    return {
      method: 'POST',
      body: formData,
    };
  }

  function validateImageUploadFile(file) {
    if (!(file instanceof File)) {
      return 'Product image file is invalid.';
    }
    if (file.size <= 0) {
      return 'Product image file cannot be empty.';
    }
    if (file.size > IMAGE_MAX_BYTES) {
      return 'Product image file exceeds the maximum allowed size of 5MB.';
    }
    if (!IMAGE_ALLOWED_MIME_TYPES.includes(file.type)) {
      return 'Product image file type is not supported.';
    }

    return '';
  }

  function syncImageSourceMode(form) {
    const sourceInput = form?.querySelector('[name="source_type"]');
    const assetTypeInput = form?.querySelector('[name="asset_type"]');
    const fileInput = form?.querySelector('[name="image_file"]');
    const primaryInput = form?.querySelector('[name="is_primary"]');
    const urlField = document.getElementById('productImageUrlField');
    const uploadField = document.getElementById('productImageUploadField');

    if (!sourceInput || !urlField || !uploadField) {
      return;
    }

    const usesUpload = sourceInput.value === 'upload';
    urlField.hidden = usesUpload;
    uploadField.hidden = !usesUpload;

    if (!fileInput) {
      return;
    }

    const isCreateMode = form?.dataset?.editorMode === 'create';
    const allowBulkUpload = isCreateMode && usesUpload;
    fileInput.multiple = allowBulkUpload;

    if (!allowBulkUpload && fileInput.files && fileInput.files.length > 1) {
      fileInput.value = '';
    }

    if (primaryInput && allowBulkUpload && fileInput.files && fileInput.files.length > 1) {
      primaryInput.value = assetTypeInput?.value === 'thumbnail' ? '1' : '0';
    }
  }

  function renderImageFormPreview(form) {
    const sourceInput = form?.querySelector('[name="source_type"]');
    const assetTypeInput = form?.querySelector('[name="asset_type"]');
    const urlInput = form?.querySelector('[name="image_url"]');
    const fileInput = form?.querySelector('[name="image_file"]');
    const previewUrlInput = form?.querySelector('[name="preview_url"]');
    const existingSourceInput = form?.querySelector('[name="existing_source_type"]');
    const existingImageInput = form?.querySelector('[name="existing_image_url"]');
    const previewFrame = document.getElementById('productImagePreviewFrame');
    const fileHint = document.getElementById('productImageFileHint');

    if (!sourceInput || !previewFrame) {
      return;
    }

    const selectedFiles = Array.from(fileInput?.files || []);
    const selectedFile = selectedFiles[0] || null;
    let previewUrl = '';
    const previousObjectUrl = previewFrame.dataset.objectUrl || '';

    if (selectedFile) {
      if (previousObjectUrl) {
        URL.revokeObjectURL(previousObjectUrl);
      }
      previewUrl = URL.createObjectURL(selectedFile);
      previewFrame.dataset.objectUrl = previewUrl;
      if (fileHint) {
        fileHint.textContent = selectedFiles.length > 1
          ? `Selected ${selectedFiles.length} files. First file: ${selectedFile.name}`
          : `Selected file: ${selectedFile.name}`;
      }
    } else {
      if (previousObjectUrl) {
        URL.revokeObjectURL(previousObjectUrl);
        delete previewFrame.dataset.objectUrl;
      }

      if (sourceInput.value === 'url') {
        previewUrl = String(urlInput?.value || '').trim();
      } else if (existingSourceInput?.value === 'upload') {
        previewUrl = String(previewUrlInput?.value || '').trim();
      }

      if (fileHint) {
        fileHint.textContent = existingImageInput?.value && sourceInput.value === 'upload'
          ? 'Current uploaded file will be kept unless you choose a replacement.'
          : 'No local file selected yet.';
      }
    }

    previewFrame.innerHTML = previewUrl
      ? `<img src="${escapeHtmlAttr(previewUrl)}" alt="Product image preview" id="productImagePreview" loading="lazy">`
      : '<div class="product-media-placeholder" id="productImagePreviewPlaceholder">IMG</div>';

    if (fileHint && selectedFiles.length > 1 && assetTypeInput?.value !== 'gallery') {
      fileHint.textContent += ' Multiple files will be created with incrementing sort order.';
    }
  }

  function normalizeBatchImageErrors(errors) {
    if (!errors || typeof errors !== 'object') {
      return {};
    }

    if (Array.isArray(errors.items_manifest) && errors.items_manifest.length > 0) {
      const normalized = { ...errors };
      delete normalized.items_manifest;
      normalized.image_file = errors.items_manifest;

      return normalized;
    }

    return errors;
  }

  function deriveImageAltText(fileName) {
    return String(fileName || '')
      .replace(/\.[^.]+$/, '')
      .replace(/[-_]+/g, ' ')
      .replace(/\s+/g, ' ')
      .trim();
  }

  function buildImagePreview(image) {
    const updatedAt = formatDateValue(image.updated_at || image.created_at);
    const productLabel = image.product_name || getProductNameById(image.product_id) || `Product #${image.product_id}`;
    const sourceLabel = image.source_type === 'upload' ? 'Upload File' : 'Remote URL';
    const sourceValue = image.source_type === 'upload'
      ? (image.stored_image_url || 'Uploaded file')
      : (image.image_url || 'N/A');
    const previewImage = image.image_url
      ? `<img src="${escapeHtmlAttr(image.image_url)}" alt="${escapeHtmlAttr(image.alt_text || productLabel)}" style="width:100%;max-width:280px;border-radius:12px;border:1px solid var(--border);background:var(--bg3);object-fit:cover;">`
      : '<div class="poster-img-placeholder" style="width:100%;max-width:280px;height:180px;">IMG</div>';

    return `
      <div style="display:flex;flex-direction:column;gap:18px;">
        <div class="preview-banner">
          <div class="preview-banner-title">${escapeHtml(productLabel)}</div>
          <div class="preview-banner-copy">${escapeHtml(image.alt_text || 'No alt text has been stored for this image yet.')}</div>
          <div class="meta-pills">
            <span class="badge blue"><div class="badge-dot"></div>${escapeHtml(humanizeStatus(image.asset_type || 'gallery'))}</span>
            ${primaryBadge(image.is_primary)}
            ${statusBadge(image.status || 'draft')}
          </div>
        </div>

        <div style="display:flex;justify-content:center;">${previewImage}</div>

        <div class="form-grid">
          <div class="field">
            <label>${escapeHtml(sourceLabel)}</label>
            <input class="input" type="text" value="${escapeHtmlAttr(sourceValue)}" readonly>
          </div>
          <div class="field">
            <label>Updated</label>
            <input class="input" type="text" value="${escapeHtmlAttr(updatedAt)}" readonly>
          </div>
          <div class="field">
            <label>Sort Order</label>
            <input class="input" type="text" value="${escapeHtmlAttr(String(image.sort_order ?? 0))}" readonly>
          </div>
          <div class="field">
            <label>Product Slug</label>
            <input class="input" type="text" value="${escapeHtmlAttr(image.product_slug || 'N/A')}" readonly>
          </div>
          <div class="field form-full">
            <label>Alt Text</label>
            <textarea class="textarea" readonly>${escapeHtml(image.alt_text || 'N/A')}</textarea>
          </div>
        </div>
      </div>
    `;
  }

  function exportCurrentImageView() {
    if (state.items.length === 0) {
      showToast('There are no product image rows to export from the current view.', 'info');
      return;
    }

    const headers = [
      'ID',
      'Product ID',
      'Product Name',
      'Product Slug',
      'Asset Type',
      'Image URL',
      'Alt Text',
      'Sort Order',
      'Is Primary',
      'Status',
      'Updated At',
    ];

    const rows = state.items.map(image => [
      image.id,
      image.product_id,
      image.product_name || '',
      image.product_slug || '',
      image.asset_type || '',
      image.image_url || '',
      image.alt_text || '',
      image.sort_order,
      Number(image.is_primary) === 1 ? 'yes' : 'no',
      image.status || '',
      image.updated_at || image.created_at || '',
    ]);

    downloadCsv(`product-images-page-${state.meta.page}`, headers, rows);
    showToast('Current product image page exported to CSV.', 'success');
  }

  function buildProductOptions(selectedValue) {
    const options = [];

    if (state.products.length === 0) {
      options.push('<option value="">No products available</option>');
    } else {
      options.push('<option value="">Select product</option>');
    }

    options.push(...state.products.map(product => {
      const value = String(product.id);
      const selected = value === String(selectedValue || '') ? ' selected' : '';
      const suffix = product.status && product.status !== 'active' ? ` (${humanizeStatus(product.status)})` : '';
      return `<option value="${escapeHtmlAttr(value)}"${selected}>${escapeHtml(product.name || 'Untitled Product')}${escapeHtml(suffix)}</option>`;
    }));

    return options.join('');
  }

  function getProductNameById(productId) {
    const product = state.products.find(item => Number(item.id) === Number(productId));
    return product?.name || '';
  }

  function dedupeProducts(products) {
    const unique = new Map();

    products.forEach(product => {
      const id = Number(product.id || 0);
      if (!id) {
        return;
      }

      unique.set(id, {
        id,
        name: product.name || 'Untitled Product',
        slug: product.slug || '',
        status: product.status || '',
      });
    });

    return Array.from(unique.values());
  }

  function primaryBadge(value) {
    const isPrimary = Number(value) === 1;
    const color = isPrimary ? 'gold' : 'gray';
    const label = isPrimary ? 'Primary' : 'Secondary';
    return `<span class="badge ${color}"><div class="badge-dot"></div>${label}</span>`;
  }

  function normalizeOptionalString(value) {
    const normalized = String(value || '').trim();
    return normalized === '' ? '' : normalized;
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

  function toBooleanInteger(value) {
    const normalized = String(value ?? '').trim().toLowerCase();
    if (['1', 'true', 'yes', 'on'].includes(normalized)) return 1;
    if (['0', 'false', 'no', 'off'].includes(normalized)) return 0;
    return null;
  }

  function isValidHttpUrl(value) {
    try {
      const url = new URL(String(value || '').trim());
      return ['http:', 'https:'].includes(url.protocol);
    } catch (error) {
      return false;
    }
  }

  document.addEventListener('DOMContentLoaded', initProductManagementImages);
})();
