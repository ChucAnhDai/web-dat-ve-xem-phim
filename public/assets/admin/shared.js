/**
 * CineShop Admin — shared.js
 * Shared UI behaviors for admin pages.
 */

const _statusColors = {
  'Completed':'green','Active':'green','Now Showing':'green','Delivered':'green',
  'Confirmed':'blue','Shipped':'blue','Coming Soon':'blue',
  'Pending':'orange','Renovation':'orange','Maintenance':'orange',
  'Cancelled':'red','Suspended':'red','Failed':'red',
  'Ended':'gray','Refunded':'purple',
  'Admin':'red','Staff':'gold','Customer':'gray',
  'Success':'green','Low':'orange','MoMo':'purple','VNPay':'blue','PayPal':'blue','Cash':'green',
};

function statusBadge(s) {
  const c = _statusColors[s] || 'gray';
  return `<span class="badge ${c}"><div class="badge-dot"></div>${s}</span>`;
}

function stars(r) {
  if (!r) return '<span style="color:var(--text-dim);font-size:11px;">—</span>';
  let h = '<div class="stars">';
  for (let i = 1; i <= 5; i++) h += `<span class="star ${i <= Math.round(r) ? '' : 'empty'}">★</span>`;
  return h + `<span style="font-size:11px;color:var(--text-muted);margin-left:4px;">${r}</span></div>`;
}

function showToast(message, type = 'info') {
  let box = document.getElementById('_toastBox');
  if (!box) {
    box = document.createElement('div');
    box.id = '_toastBox';
    box.className = 'toast-container';
    document.body.appendChild(box);
  }
  const icons = {
    success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>',
    error:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
  };
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  t.innerHTML = `
    <div class="toast-icon">${icons[type] || icons.info}</div>
    <div class="toast-text">${message}</div>
    <div class="toast-close" onclick="this.parentNode.remove()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
    </div>`;
  box.appendChild(t);
  setTimeout(() => {
    t.style.cssText += 'opacity:0;transform:translateX(20px);transition:all .3s;';
    setTimeout(() => t.remove(), 300);
  }, 3500);
}

function _ensureModal() {
  if (document.getElementById('_modalOverlay')) return;
  const el = document.createElement('div');
  el.className = 'modal-overlay';
  el.id = '_modalOverlay';
  el.innerHTML = `
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title" id="_modalTitle">Modal</div>
        <button class="modal-close" onclick="closeModal()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="modal-body" id="_modalBody"></div>
      <div class="modal-footer">
        <button class="btn btn-ghost btn-sm" onclick="closeModal()">Cancel</button>
        <button class="btn btn-primary btn-sm" onclick="handleModalSave()">Save Changes</button>
      </div>
    </div>`;
  el.addEventListener('click', e => { if (e.target === el) closeModal(); });
  document.body.appendChild(el);
}

function openModal(title, body) {
  _ensureModal();
  document.getElementById('_modalTitle').textContent = title;
  document.getElementById('_modalBody').innerHTML = body;
  document.getElementById('_modalOverlay').classList.add('open');
}
function closeModal() {
  document.getElementById('_modalOverlay')?.classList.remove('open');
}
function handleModalSave() {
  closeModal();
  showToast('Saved successfully!', 'success');
}

let _sidebarCollapsed = false;

function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const mw = document.getElementById('mainWrap');
  const ov = document.getElementById('_sbOverlay');
  if (!sb) return;
  if (window.innerWidth <= 768) {
    sb.classList.toggle('mobile-open');
    ov?.classList.toggle('active');
  } else {
    _sidebarCollapsed = !_sidebarCollapsed;
    sb.classList.toggle('collapsed', _sidebarCollapsed);
    mw?.classList.toggle('collapsed', _sidebarCollapsed);
  }
}

function _closeMobileSidebar() {
  document.getElementById('sidebar')?.classList.remove('mobile-open');
  document.getElementById('_sbOverlay')?.classList.remove('active');
}

function _ensureSidebarOverlay() {
  if (document.getElementById('_sbOverlay')) return;
  const ov = document.createElement('div');
  ov.className = 'sidebar-overlay';
  ov.id = '_sbOverlay';
  ov.addEventListener('click', _closeMobileSidebar);
  document.body.prepend(ov);
}

function _setActiveNav() {
  const current = location.pathname.replace(/\/$/, '');
  document.querySelectorAll('.nav-item[data-page]').forEach(a => {
    const href = a.getAttribute('href') || '';
    if (!href) return;
    const normalized = href.replace(location.origin, '').replace(/\/$/, '');
    if (normalized === current) {
      a.classList.add('active');
    }
  });
}

document.addEventListener('click', e => {
  const drop = document.getElementById('profileDrop');
  const btn  = document.getElementById('avatarBtn');
  if (drop && btn && !btn.contains(e.target) && !drop.contains(e.target)) {
    drop.classList.remove('open');
  }
});

document.addEventListener('DOMContentLoaded', () => {
  _ensureSidebarOverlay();
  _setActiveNav();

  if (window.QUICK_ACTION) {
    const lbl = document.getElementById('quickActionLabel');
    if (lbl) lbl.textContent = window.QUICK_ACTION.label || 'Quick Add';
  }

  const si = document.getElementById('globalSearch');
  if (si) {
    si.addEventListener('keydown', e => {
      if (e.key === 'Enter' && si.value.trim()) showToast(`Searching "${si.value}"…`, 'info');
    });
  }

  document.addEventListener('click', e => {
    const btn = e.target.closest('.tab-btn');
    if (!btn) return;
    btn.closest('.tabs')?.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  });

  document.dispatchEvent(new CustomEvent('componentsReady'));
});

function buildPagination(infoText, totalPages = 3) {
  const show = Math.min(totalPages, 3);
  const pages = Array.from({ length: show }, (_, i) => i + 1);
  return `
  <div class="pagination">
    <div class="pagination-info">${infoText}</div>
    <div class="pagination-btns">
      <button class="pg-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
      </button>
      ${pages.map((p, i) => `<button class="pg-btn${i === 0 ? ' active' : ''}" onclick="showToast('Page ${p}','info')">${p}</button>`).join('')}
      ${totalPages > 3 ? `<button class="pg-btn">…</button><button class="pg-btn" onclick="showToast('Page ${totalPages}','info')">${totalPages}</button>` : ''}
      <button class="pg-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
      </button>
    </div>
  </div>`;
}

function movieFormBody(m = {}) {
  return `<div class="form-grid">
    <div class="field"><label>Movie Title</label><input class="input" placeholder="Enter title" value="${m.title||''}"></div>
    <div class="field"><label>Category</label><select class="select"><option>Action</option><option>Drama</option><option>Comedy</option><option>Horror</option><option>Sci-Fi</option><option>Animation</option><option>Romance</option><option>Thriller</option></select></div>
    <div class="field"><label>Duration (min)</label><input class="input" type="number" placeholder="120" value="${m.dur||''}"></div>
    <div class="field"><label>Release Date</label><input class="input" type="date" value="${m.release||''}"></div>
    <div class="field"><label>Rating (0–5)</label><input class="input" type="number" placeholder="0.0" min="0" max="5" step="0.1" value="${m.rating||''}"></div>
    <div class="field"><label>Status</label><select class="select"><option>Coming Soon</option><option>Now Showing</option><option>Ended</option></select></div>
    <div class="field"><label>Language</label><input class="input" placeholder="English, Vietnamese"></div>
    <div class="field"><label>Trailer URL</label><input class="input" placeholder="https://youtube.com/..."></div>
    <div class="field form-full"><label>Description</label><textarea class="textarea" placeholder="Movie description...">${m.desc||''}</textarea></div>
    <div class="field form-full"><label>Poster Image</label>
      <div class="upload-zone" onclick="showToast('File picker opened','info')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <p>Drop file here or <span>browse</span></p>
        <p style="font-size:11px;margin-top:4px;color:var(--text-dim);">PNG, JPG up to 5MB</p>
      </div>
    </div>
  </div>`;
}

function productFormBody(p = {}) {
  return `<div class="form-grid">
    <div class="field"><label>Product Name</label><input class="input" placeholder="Enter name" value="${p.name||''}"></div>
    <div class="field"><label>Category</label><select class="select"><option>Snacks</option><option>Beverages</option><option>Merchandise</option><option>Combos</option></select></div>
    <div class="field"><label>Price ($)</label><input class="input" type="number" placeholder="0.00" value="${p.price||''}"></div>
    <div class="field"><label>Stock Qty</label><input class="input" type="number" placeholder="0" value="${p.stock||''}"></div>
    <div class="field"><label>Brand</label><input class="input" placeholder="Brand name" value="${p.brand||''}"></div>
    <div class="field"><label>Weight</label><input class="input" placeholder="250g"></div>
    <div class="field"><label>Origin</label><input class="input" placeholder="Vietnam"></div>
    <div class="field"><label>SKU</label><input class="input" placeholder="CS-001"></div>
    <div class="field form-full"><label>Description</label><textarea class="textarea" placeholder="Product description..."></textarea></div>
    <div class="field form-full"><label>Product Images</label>
      <div class="upload-zone" onclick="showToast('File picker opened','info')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <p>Drop files here or <span>browse</span></p>
        <p style="font-size:11px;margin-top:4px;color:var(--text-dim);">PNG, JPG · Multiple allowed</p>
      </div>
    </div>
  </div>`;
}
