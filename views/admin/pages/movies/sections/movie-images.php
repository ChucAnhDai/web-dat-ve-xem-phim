<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card red" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">426</div>
    <div class="stat-label">Total Assets</div>
  </div>
  <div class="stat-card green" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">188</div>
    <div class="stat-label">Approved Posters</div>
  </div>
  <div class="stat-card blue" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">91</div>
    <div class="stat-label">Hero Banners</div>
  </div>
  <div class="stat-card orange" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">23</div>
    <div class="stat-label">Pending Review</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input type="text" placeholder="Search images..." oninput="filterMovieImages(this.value)">
    </div>
    <select class="select-filter" onchange="filterMovieImages()">
      <option>All Asset Types</option>
      <option>Poster</option>
      <option>Banner</option>
      <option>Gallery</option>
    </select>
    <div class="toolbar-right">
      <span style="font-size:12px;color:var(--text-dim);" id="movieImageCount">8 assets</span>
      <button class="btn btn-ghost btn-sm" onclick="showToast('Asset manifest exported','success')">Export</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Preview</th><th>Movie</th><th>Asset Type</th><th>Resolution</th><th>Size</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody id="movieImagesBody"></tbody>
    </table>
  </div>
  <div id="movieImagesPagination"></div>
</div>

<script>
const movieImagesData = [
  {code:'PS',movie:'Avengers: Doomsday',type:'Poster',resolution:'2000x3000',size:'1.8 MB',status:'Active'},
  {code:'HB',movie:'Avengers: Doomsday',type:'Banner',resolution:'2400x900',size:'2.1 MB',status:'Active'},
  {code:'GL',movie:'The Last Breath',type:'Gallery',resolution:'1920x1080',size:'1.1 MB',status:'Pending'},
  {code:'PS',movie:'Cosmic Voyage II',type:'Poster',resolution:'2000x3000',size:'1.7 MB',status:'Active'},
  {code:'GL',movie:'Haunted Echoes',type:'Gallery',resolution:'1920x1080',size:'960 KB',status:'Pending'},
  {code:'HB',movie:'Funny Bones 3',type:'Banner',resolution:'2400x900',size:'1.6 MB',status:'Active'},
  {code:'PS',movie:'Dragon Quest',type:'Poster',resolution:'2000x3000',size:'1.5 MB',status:'Active'},
  {code:'GL',movie:'Neon City',type:'Gallery',resolution:'1920x1080',size:'1.0 MB',status:'Cancelled'},
];

function movieAssetChannelOption(title, copy, checked = true) {
  return `<label class="check-option">
    <input type="checkbox"${checked ? ' checked' : ''}>
    <span><strong>${title}</strong><small>${copy}</small></span>
  </label>`;
}

function movieAssetFormBody(asset = {}) {
  const type = asset.type || 'Poster';
  const status = asset.status || 'Pending';
  const assetName = asset.asset || [asset.movie, type].filter(Boolean).join(' ');

  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Asset Delivery</div>
      <div class="surface-card-copy">Prepare posters, hero banners, and gallery art with the metadata needed for storefront, booking flows, and campaign slots.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Asset Name</label><input class="input" placeholder="Avengers: Doomsday Poster" value="${assetName || ''}"></div>
      <div class="field"><label>Movie</label><input class="input" placeholder="Linked movie title" value="${asset.movie || ''}"></div>
      <div class="field"><label>Asset Type</label><select class="select">${buildOptions(['Poster', 'Banner', 'Gallery', 'Teaser'], type)}</select></div>
      <div class="field"><label>Status</label><select class="select">${buildOptions(['Active', 'Pending', 'Draft', 'Cancelled'], status)}</select></div>
      <div class="field"><label>Resolution</label><input class="input" placeholder="2000x3000" value="${asset.resolution || ''}"></div>
      <div class="field"><label>File Size</label><input class="input" placeholder="1.8 MB" value="${asset.size || ''}"></div>
      <div class="field"><label>Alt Text</label><input class="input" placeholder="Poster artwork description" value="${asset.alt || ''}"></div>
      <div class="field"><label>Focal Point</label><select class="select">${buildOptions(['Center', 'Top Focus', 'Left Focus', 'Right Focus'], asset.focus || 'Center')}</select></div>
      <div class="field form-full"><label>Usage Channels</label><div class="check-grid">
        ${movieAssetChannelOption('Catalog detail page', 'Use this image on movie detail layouts.', true)}
        ${movieAssetChannelOption('Homepage campaign slot', 'Eligible for hero and carousel placements.', type === 'Banner')}
        ${movieAssetChannelOption('Booking confirmations', 'Allow this image in post-purchase messages.', type !== 'Gallery')}
        ${movieAssetChannelOption('Social export pack', 'Expose this version for campaign handoff.', status !== 'Cancelled')}
      </div></div>
      <div class="field form-full"><label>Upload Artwork</label>
        <div class="upload-zone" onclick="showToast('File picker opened','info')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          <p>Drop artwork here or <span>browse</span></p>
          <p style="font-size:11px;margin-top:4px;color:var(--text-dim);">PNG, JPG, WEBP up to 8MB</p>
        </div>
      </div>
      <div class="field form-full"><label>Preview</label>
        <div class="preview-banner">
          <div class="preview-banner-title">${asset.movie || 'Movie artwork preview'}</div>
          <div class="preview-banner-copy">${type} artwork for campaign placements, movie detail pages, and queue moderation before backend storage is added.</div>
          <div class="meta-pills">
            <span class="badge blue">${type}</span>
            <span class="badge gray">${asset.resolution || 'Pending resolution'}</span>
            <span class="badge ${status === 'Cancelled' ? 'red' : status === 'Draft' ? 'gray' : status === 'Pending' ? 'orange' : 'green'}">${status}</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function openMovieAssetModal(title, asset = {}) {
  const isEdit = /^Edit/i.test(title);
  openModal(title, movieAssetFormBody(asset), {
    description: isEdit
      ? 'Replace metadata, crop guidance, and rollout channels for this artwork asset.'
      : 'Upload a new movie artwork asset and stage how it should be used across the admin experience.',
    note: 'UI preview only. Uploads are not stored yet.',
    submitLabel: isEdit ? 'Update Asset' : 'Upload Asset',
    successMessage: isEdit ? 'Asset preview updated!' : 'Asset preview staged!',
  });
}

function handleMovieSectionAction() {
  openMovieAssetModal('Upload Movie Asset');
}

function renderMovieImages(data) {
  document.getElementById('movieImagesBody').innerHTML = data.map(i => `
    <tr>
      <td><div class="poster-img-placeholder">${i.code}</div></td>
      <td><div class="td-bold">${i.movie}</div></td>
      <td><span class="badge gray">${i.type}</span></td>
      <td class="td-muted">${i.resolution}</td>
      <td class="td-muted">${i.size}</td>
      <td>${statusBadge(i.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="Preview" onclick="showToast('Preview ${i.movie} ${i.type}','info')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
        <button class="action-btn edit" title="Replace" onclick="openMovieAssetModal('Edit Movie Asset', {movie:'${i.movie}',type:'${i.type}',resolution:'${i.resolution}',size:'${i.size}',status:'${i.status}'})">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="action-btn del" title="Archive" onclick="showToast('${i.movie} asset archived','warning')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
        </button>
      </div></td>
    </tr>`).join('');
  document.getElementById('movieImagesPagination').innerHTML = buildPagination(`Showing 1-${data.length} of ${data.length} assets`, Math.ceil(data.length / 10));
}

function filterMovieImages(q = '') {
  const filtered = movieImagesData.filter(i =>
    i.movie.toLowerCase().includes(q.toLowerCase()) ||
    i.type.toLowerCase().includes(q.toLowerCase())
  );
  renderMovieImages(filtered);
  document.getElementById('movieImageCount').textContent = `${filtered.length} assets`;
}

document.addEventListener('DOMContentLoaded', function () {
  renderMovieImages(movieImagesData);
});
</script>
