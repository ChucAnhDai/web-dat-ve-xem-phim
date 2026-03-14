<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">286</div><div class="stat-label">Media Assets</div></div>
  <div class="stat-card gold" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">42</div><div class="stat-label">Campaign Banners</div></div>
  <div class="stat-card green" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">198</div><div class="stat-label">Published</div></div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">18</div><div class="stat-label">Draft Assets</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="productImageSearch" type="text" placeholder="Search image assets..." oninput="filterProductImages(this.value)">
    </div>
    <select id="productImageType" class="select-filter" onchange="filterProductImages()">
      <option>All Types</option>
      <option>Thumbnail</option>
      <option>Gallery</option>
      <option>Banner</option>
      <option>Lifestyle</option>
    </select>
    <select id="productImageStatus" class="select-filter" onchange="filterProductImages()">
      <option>All Status</option>
      <option>Published</option>
      <option>Draft</option>
      <option>Archived</option>
    </select>
    <div class="toolbar-right">
      <span id="productImageCount" style="font-size:12px;color:var(--text-dim);">8 assets</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Asset</th><th>Product</th><th>Type</th><th>Resolution</th><th>Size</th><th>Updated</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody id="productImagesBody"></tbody>
    </table>
  </div>
  <div id="productImagesPagination"></div>
</div>

<script>
const productImagesData = [
  {asset:'Popcorn Combo Hero',product:'Large Popcorn Combo',type:'Banner',resolution:'2400x1200',size:'1.8 MB',updated:'2026-03-12',status:'Published',code:'PH'},
  {asset:'Cola Bottle Front',product:'Coca-Cola 500ml',type:'Thumbnail',resolution:'1080x1080',size:'420 KB',updated:'2026-03-10',status:'Published',code:'CF'},
  {asset:'Hoodie Lifestyle',product:'CineShop Hoodie',type:'Lifestyle',resolution:'1800x2200',size:'2.1 MB',updated:'2026-03-08',status:'Draft',code:'HL'},
  {asset:'Nacho Detail Pack',product:'Nacho + Dip Set',type:'Gallery',resolution:'1400x1400',size:'710 KB',updated:'2026-03-07',status:'Published',code:'NP'},
  {asset:'Candy Mix Card',product:'Premium Candy Mix',type:'Thumbnail',resolution:'1080x1080',size:'390 KB',updated:'2026-03-05',status:'Published',code:'CM'},
  {asset:'Bottle Lifestyle',product:'Water Bottle 1L',type:'Lifestyle',resolution:'1600x2000',size:'980 KB',updated:'2026-03-01',status:'Archived',code:'BL'},
  {asset:'Cap Campaign Banner',product:'CineShop Cap',type:'Banner',resolution:'2200x1000',size:'1.4 MB',updated:'2026-02-27',status:'Published',code:'CB'},
  {asset:'Mug Product Card',product:'Movie Mug',type:'Gallery',resolution:'1200x1200',size:'560 KB',updated:'2026-02-22',status:'Draft',code:'MG'},
];

function productImageFormBody(image = {}) {
  const type = image.type || 'Thumbnail';
  const status = image.status || 'Draft';

  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Asset Delivery</div>
      <div class="surface-card-copy">Organize thumbnails, banners, and lifestyle shots so each product has a clear visual role before real uploads are wired in.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Asset Name</label><input class="input" placeholder="Popcorn Combo Hero" value="${image.asset || ''}"></div>
      <div class="field"><label>Product</label><input class="input" placeholder="Linked product" value="${image.product || ''}"></div>
      <div class="field"><label>Type</label><select class="select">${buildOptions(['Thumbnail', 'Gallery', 'Banner', 'Lifestyle'], type)}</select></div>
      <div class="field"><label>Status</label><select class="select">${buildOptions(['Published', 'Draft', 'Archived'], status)}</select></div>
      <div class="field"><label>Resolution</label><input class="input" placeholder="2400x1200" value="${image.resolution || ''}"></div>
      <div class="field"><label>File Size</label><input class="input" placeholder="1.8 MB" value="${image.size || ''}"></div>
      <div class="field"><label>Alt Text</label><input class="input" placeholder="Describe the image for accessibility" value="${image.alt || ''}"></div>
      <div class="field"><label>Campaign Surface</label><select class="select">${buildOptions(['Product page', 'Shop hero', 'Combo upsell', 'Homepage rail'], image.surface || 'Product page')}</select></div>
      <div class="field form-full"><label>Upload File</label><div class="upload-zone" onclick="showToast('File picker opened','info')"><p>Drop file here or <span>browse</span></p><p style="font-size:11px;margin-top:4px;color:var(--text-dim);">PNG, JPG up to 5MB</p></div></div>
      <div class="field form-full"><label>Preview</label>
        <div class="preview-banner">
          <div class="preview-banner-title">${image.asset || 'Product asset preview'}</div>
          <div class="preview-banner-copy">${image.product || 'Link the asset to a product so merch, kiosk, and combo pages can reuse it consistently.'}</div>
          <div class="meta-pills">
            <span class="badge blue">${type}</span>
            <span class="badge ${status === 'Archived' ? 'gray' : status === 'Draft' ? 'orange' : 'green'}">${status}</span>
            <span class="badge gold">${image.resolution || 'Pending size'}</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function openProductImageModal(title, image = {}) {
  const isEdit = /^Edit/i.test(title);
  openModal(title, productImageFormBody(image), {
    description: isEdit
      ? 'Update asset metadata, product linkage, and publishing state for this media item.'
      : 'Stage a new product image asset and preview how it will be categorized in the media library.',
    note: 'UI preview only. Asset uploads are not persisted yet.',
    submitLabel: isEdit ? 'Update Asset' : 'Upload Asset',
    successMessage: isEdit ? 'Asset preview updated!' : 'Asset preview staged!',
  });
}

function handleProductSectionAction() {
  openProductImageModal('Upload Product Image');
}

function renderProductImages(data) {
  const startItem = data.length === 0 ? 0 : 1;
  document.getElementById('productImagesBody').innerHTML = data.map(image => `
    <tr>
      <td><div style="display:flex;align-items:center;gap:10px;"><div class="poster-img-placeholder">${image.code}</div><div class="td-bold">${image.asset}</div></div></td>
      <td class="td-muted">${image.product}</td>
      <td><span class="badge blue">${image.type}</span></td>
      <td class="td-muted">${image.resolution}</td>
      <td class="td-muted">${image.size}</td>
      <td class="td-muted">${image.updated}</td>
      <td>${statusBadge(image.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="Preview" onclick="showToast('Previewing ${image.asset}','info')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn edit" title="Edit" onclick="openProductImageModal('Edit Asset', {asset:'${image.asset}',product:'${image.product}',type:'${image.type}',resolution:'${image.resolution}',size:'${image.size}',status:'${image.status}'})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
      </div></td>
    </tr>`).join('');
  document.getElementById('productImagesPagination').innerHTML = buildPagination(`Showing ${startItem}-${data.length} of ${data.length} assets`, Math.max(1, Math.ceil(data.length / 10)));
}

function filterProductImages(q) {
  const searchInput = document.getElementById('productImageSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedType = document.getElementById('productImageType')?.value || 'All Types';
  const selectedStatus = document.getElementById('productImageStatus')?.value || 'All Status';
  const filtered = productImagesData.filter(image => {
    const matchesQuery = searchTerm === '' || image.asset.toLowerCase().includes(searchTerm) || image.product.toLowerCase().includes(searchTerm);
    const matchesType = selectedType === 'All Types' || image.type === selectedType;
    const matchesStatus = selectedStatus === 'All Status' || image.status === selectedStatus;
    return matchesQuery && matchesType && matchesStatus;
  });

  renderProductImages(filtered);
  document.getElementById('productImageCount').textContent = `${filtered.length} assets`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterProductImages();
});
</script>
