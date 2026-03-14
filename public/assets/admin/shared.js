/**
 * CineShop Admin — shared.js
 * Shared UI behaviors for admin pages.
 */

const _statusColors = {
  completed:'green',
  active:'green',
  approved:'green',
  now_showing:'green',
  delivered:'green',
  confirmed:'blue',
  shipped:'blue',
  coming_soon:'blue',
  scheduled:'blue',
  pending:'orange',
  renovation:'orange',
  maintenance:'orange',
  cancelled:'red',
  suspended:'red',
  failed:'red',
  disabled:'red',
  blocked:'red',
  sold_out:'red',
  rejected:'red',
  ended:'gray',
  refunded:'purple',
  draft:'gray',
  archived:'gray',
  hidden:'gray',
  inactive:'gray',
  verified:'green',
  published:'green',
  admin:'red',
  staff:'gold',
  customer:'gray',
  success:'green',
  low:'orange',
  momo:'purple',
  vnpay:'blue',
  paypal:'blue',
  cash:'green',
  'Completed':'green','Active':'green','Now Showing':'green','Delivered':'green',
  'Confirmed':'blue','Shipped':'blue','Coming Soon':'blue','Scheduled':'blue',
  'Pending':'orange','Renovation':'orange','Maintenance':'orange',
  'Cancelled':'red','Suspended':'red','Failed':'red','Disabled':'red','Blocked':'red','Sold Out':'red',
  'Ended':'gray','Refunded':'purple','Draft':'gray','Archived':'gray','Hidden':'gray',
  'Verified':'green','Published':'green',
  'Admin':'red','Staff':'gold','Customer':'gray',
  'Success':'green','Low':'orange','MoMo':'purple','VNPay':'blue','PayPal':'blue','Cash':'green',
};

const _statusLabels = {
  draft: 'Draft',
  active: 'Active',
  inactive: 'Inactive',
  coming_soon: 'Coming Soon',
  now_showing: 'Now Showing',
  ended: 'Ended',
  archived: 'Archived',
  pending: 'Pending',
  approved: 'Approved',
  rejected: 'Rejected',
  sold_out: 'Sold Out',
};

const ADMIN_AUTH_STORAGE_KEY = 'cinemax_token';

class AdminApiError extends Error {
  constructor(message, status = 500, errors = {}, payload = null) {
    super(message);
    this.name = 'AdminApiError';
    this.status = status;
    this.errors = errors || {};
    this.payload = payload;
  }
}

function adminAppUrl(path) {
  const basePath = typeof window.APP_BASE_PATH === 'string' ? window.APP_BASE_PATH : '';
  const rawPath = String(path || '');
  const normalizedPath = rawPath.startsWith('/') ? rawPath : `/${rawPath}`;
  return `${basePath}${normalizedPath}`;
}

function getAdminAuthToken() {
  try {
    if (window.sessionStorage) {
      const sessionToken = window.sessionStorage.getItem(ADMIN_AUTH_STORAGE_KEY);
      if (sessionToken) return sessionToken;
    }

    return window.localStorage ? window.localStorage.getItem(ADMIN_AUTH_STORAGE_KEY) : null;
  } catch (error) {
    return null;
  }
}

function clearAdminClientAuthState() {
  try {
    window.localStorage?.removeItem(ADMIN_AUTH_STORAGE_KEY);
    window.sessionStorage?.removeItem(ADMIN_AUTH_STORAGE_KEY);
  } catch (error) {
    // Ignore storage cleanup failures in locked-down browsers.
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

function buildQueryString(params = {}) {
  const searchParams = new URLSearchParams();

  Object.entries(params || {}).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '') return;

    if (Array.isArray(value)) {
      value.forEach(item => {
        if (item !== null && item !== undefined && item !== '') {
          searchParams.append(key, String(item));
        }
      });
      return;
    }

    searchParams.append(key, String(value));
  });

  const query = searchParams.toString();
  return query ? `?${query}` : '';
}

function firstApiErrorMessage(errors, fallback = 'Request failed.') {
  if (!errors || typeof errors !== 'object') return fallback;

  for (const messages of Object.values(errors)) {
    if (Array.isArray(messages) && messages.length > 0) {
      return String(messages[0]);
    }
  }

  return fallback;
}

async function adminApiRequest(path, options = {}) {
  const {
    method = 'GET',
    query = {},
    body,
    headers = {},
    useStoredToken = false,
  } = options;

  const requestHeaders = { ...headers };
  let requestBody = body;

  if (body !== undefined && !(body instanceof FormData)) {
    requestHeaders['Content-Type'] = requestHeaders['Content-Type'] || 'application/json';
    requestBody = JSON.stringify(body);
  }

  const token = useStoredToken ? getAdminAuthToken() : null;
  if (token) {
    requestHeaders.Authorization = `Bearer ${token}`;
  }

  const response = await fetch(`${adminAppUrl(path)}${buildQueryString(query)}`, {
    method,
    headers: requestHeaders,
    body: requestBody,
    credentials: 'same-origin',
  });

  const contentType = response.headers.get('content-type') || '';
  let payload = null;

  if (contentType.includes('application/json')) {
    payload = await response.json();
  } else {
    const text = await response.text();
    payload = text ? { message: text } : {};
  }

  if (!response.ok) {
    throw new AdminApiError(
      payload?.message || firstApiErrorMessage(payload?.errors, 'Request failed.'),
      response.status,
      payload?.errors || {},
      payload
    );
  }

  return payload || {};
}

function errorMessageFromException(error, fallback = 'Something went wrong.') {
  if (error instanceof AdminApiError) {
    return error.message || firstApiErrorMessage(error.errors, fallback);
  }

  if (error && typeof error.message === 'string' && error.message.trim() !== '') {
    return error.message;
  }

  return fallback;
}

function setButtonBusy(button, isBusy, busyLabel = 'Processing...') {
  if (!button) return;

  if (isBusy) {
    if (!button.dataset.originalLabel) {
      button.dataset.originalLabel = button.textContent;
    }
    button.disabled = true;
    button.textContent = busyLabel;
    return;
  }

  button.disabled = false;
  if (button.dataset.originalLabel) {
    button.textContent = button.dataset.originalLabel;
    delete button.dataset.originalLabel;
  }
}

function clearFormErrors(container) {
  if (!container) return;

  container.querySelectorAll('.field-error').forEach(node => {
    node.textContent = '';
    node.hidden = true;
  });

  container.querySelectorAll('.input.error, .select.error, .textarea.error').forEach(node => {
    node.classList.remove('error');
    node.removeAttribute('aria-invalid');
  });

  const formAlert = container.querySelector('[data-form-alert]');
  if (formAlert) {
    formAlert.innerHTML = '';
    formAlert.hidden = true;
  }
}

function applyFormErrors(container, errors) {
  clearFormErrors(container);

  if (!container || !errors || typeof errors !== 'object') {
    return;
  }

  const summaryMessages = [];

  Object.entries(errors).forEach(([field, messages]) => {
    if (!Array.isArray(messages) || messages.length === 0) return;

    const fieldControl = container.querySelector(`[data-field-control="${field}"]`);
    const fieldError = container.querySelector(`[data-field-error="${field}"]`);
    const message = String(messages[0]);

    if (fieldControl) {
      fieldControl.classList.add('error');
      fieldControl.setAttribute('aria-invalid', 'true');
    }

    if (fieldError) {
      fieldError.textContent = message;
      fieldError.hidden = false;
    }

    summaryMessages.push(message);
  });

  const formAlert = container.querySelector('[data-form-alert]');
  if (formAlert && summaryMessages.length > 0) {
    formAlert.innerHTML = summaryMessages.map(message => `<div>${escapeHtml(message)}</div>`).join('');
    formAlert.hidden = false;
  }
}

function humanizeStatus(value) {
  const raw = String(value || '').trim();
  if (!raw) return 'Unknown';

  const label = _statusLabels[raw] || _statusLabels[raw.toLowerCase()];
  if (label) return label;

  return raw
    .replace(/_/g, ' ')
    .replace(/\b\w/g, letter => letter.toUpperCase());
}

function statusBadge(s) {
  const raw = String(s || '').trim();
  const label = humanizeStatus(raw);
  const c = _statusColors[raw] || _statusColors[raw.toLowerCase()] || _statusColors[label] || 'gray';
  return `<span class="badge ${c}"><div class="badge-dot"></div>${label}</span>`;
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

let _modalConfig = {
  submitLabel: 'Save Changes',
  cancelLabel: 'Cancel',
  description: '',
  note: '',
  successMessage: 'Saved successfully!',
  onSave: null,
  busyLabel: 'Saving...',
};

function _deriveModalSubmitLabel(title = '') {
  const rules = [
    [/^Add New (.+)$/i, 'Create $1'],
    [/^Add (.+)$/i, 'Create $1'],
    [/^New (.+)$/i, 'Create $1'],
    [/^Compose (.+)$/i, 'Create $1'],
    [/^Upload (.+)$/i, 'Upload $1'],
    [/^Assign (.+)$/i, 'Assign $1'],
    [/^Edit (.+)$/i, 'Update $1'],
    [/^Moderate (.+)$/i, 'Apply $1'],
  ];

  for (const [pattern, template] of rules) {
    const match = title.match(pattern);
    if (match) return template.replace('$1', match[1]);
  }

  return 'Save Changes';
}

function _ensureModal() {
  if (document.getElementById('_modalOverlay')) return;
  const el = document.createElement('div');
  el.className = 'modal-overlay';
  el.id = '_modalOverlay';
  el.innerHTML = `
    <div class="modal">
      <div class="modal-header">
        <div class="modal-header-copy">
          <div class="modal-title" id="_modalTitle">Modal</div>
          <div class="modal-description" id="_modalDescription" hidden></div>
        </div>
        <button class="modal-close" onclick="closeModal()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
        </button>
      </div>
      <div class="modal-body" id="_modalBody"></div>
      <div class="modal-footer">
        <div class="modal-footer-note" id="_modalNote" hidden></div>
        <div class="modal-footer-actions">
          <button class="btn btn-ghost btn-sm" id="_modalCancelBtn" onclick="closeModal()">Cancel</button>
          <button class="btn btn-primary btn-sm" id="_modalSaveBtn" onclick="handleModalSave()">Save Changes</button>
        </div>
      </div>
    </div>`;
  el.addEventListener('click', e => { if (e.target === el) closeModal(); });
  document.body.appendChild(el);
}

function openModal(title, body, options = {}) {
  _ensureModal();
  _modalConfig = {
    submitLabel: _deriveModalSubmitLabel(title),
    cancelLabel: 'Cancel',
    description: '',
    note: '',
    successMessage: 'Saved successfully!',
    onSave: null,
    busyLabel: 'Saving...',
    ...options,
  };

  document.getElementById('_modalTitle').textContent = title;
  document.getElementById('_modalBody').innerHTML = body;
  document.getElementById('_modalCancelBtn').textContent = _modalConfig.cancelLabel;
  document.getElementById('_modalSaveBtn').textContent = _modalConfig.submitLabel;

  const description = document.getElementById('_modalDescription');
  if (description) {
    description.textContent = _modalConfig.description;
    description.hidden = !_modalConfig.description;
  }

  const note = document.getElementById('_modalNote');
  if (note) {
    note.textContent = _modalConfig.note;
    note.hidden = !_modalConfig.note;
  }

  document.getElementById('_modalOverlay').classList.add('open');
}
function closeModal() {
  document.getElementById('_modalOverlay')?.classList.remove('open');
}
async function handleModalSave() {
  if (typeof _modalConfig.onSave === 'function') {
    const saveButton = document.getElementById('_modalSaveBtn');

    try {
      setButtonBusy(saveButton, true, _modalConfig.busyLabel || 'Saving...');
      await Promise.resolve(_modalConfig.onSave());
    } catch (error) {
      showToast(errorMessageFromException(error, 'Failed to save changes.'), 'error');
    } finally {
      setButtonBusy(saveButton, false);
    }

    return;
  }

  closeModal();
  showToast(_modalConfig.successMessage || 'Saved successfully!', 'success');
}

let _sidebarCollapsed = false;

function toggleSidebar() {
  const sb = document.getElementById('sidebar');
  const mw = document.getElementById('mainWrap');
  const ov = document.getElementById('_sbOverlay');
  if (!sb) return;
  const layout = document.querySelector('.layout');
  if (window.innerWidth <= 768) {
    sb.classList.toggle('mobile-open');
    ov?.classList.toggle('active');
  } else {
    _sidebarCollapsed = !_sidebarCollapsed;
    sb.classList.toggle('collapsed', _sidebarCollapsed);
    layout?.classList.toggle('sidebar-collapsed', _sidebarCollapsed);
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
  const activePage = document.body?.dataset?.activePage || '';
  const navItems = document.querySelectorAll('.nav-item[data-page]');

  navItems.forEach(item => {
    item.classList.remove('active');
  });

  if (activePage) {
    navItems.forEach(item => {
      item.classList.toggle('active', item.dataset.page === activePage);
    });
    return;
  }

  const current = location.pathname.replace(/\/$/, '') + location.search;
  navItems.forEach(item => {
    const href = item.getAttribute('href') || '';
    if (!href) return;

    const url = new URL(href, location.origin);
    const normalized = url.pathname.replace(/\/$/, '') + url.search;
    item.classList.toggle('active', normalized === current);
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

function buildOptions(options, selectedValue = '') {
  return options.map(option => {
    const value = typeof option === 'string' ? option : option.value;
    const label = typeof option === 'string' ? option : option.label;
    const selected = String(value) === String(selectedValue) ? ' selected' : '';
    return `<option value="${escapeHtmlAttr(value)}"${selected}>${escapeHtml(label)}</option>`;
  }).join('');
}

function formatMovieDuration(minutes) {
  const totalMinutes = Number(minutes || 0);
  if (!Number.isFinite(totalMinutes) || totalMinutes <= 0) return 'N/A';

  const hours = Math.floor(totalMinutes / 60);
  const mins = totalMinutes % 60;
  if (!hours) return `${mins} min`;

  return mins ? `${hours}h ${mins}m` : `${hours}h`;
}

function slugifyValue(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '');
}

function movieFormBody(m = {}) {
  const categories = ['Action', 'Drama', 'Comedy', 'Horror', 'Sci-Fi', 'Animation', 'Romance', 'Thriller'];
  const statuses = [
    { value: 'draft', label: 'Draft' },
    { value: 'coming_soon', label: 'Coming Soon' },
    { value: 'now_showing', label: 'Now Showing' },
    { value: 'ended', label: 'Ended' },
    { value: 'archived', label: 'Archived' },
  ];
  const ageRatings = ['P', 'K', 'T13', 'T16', 'T18', 'PG-13', 'R'];
  const slug = m.slug || slugifyValue(m.title || '');
  m.rating = m.average_rating ?? m.rating ?? '';
  return `<div class="form-grid">
    <div class="field"><label>Movie Title</label><input class="input" placeholder="Enter title" value="${m.title||''}"></div>
    <div class="field"><label>Slug</label><input class="input" placeholder="movie-slug" value="${slug}"></div>
    <div class="field"><label>Primary Category</label><select class="select">${buildOptions(categories, m.primary_category || 'Action')}</select></div>
    <div class="field"><label>Status</label><select class="select">${buildOptions(statuses, m.status || 'draft')}</select></div>
    <div class="field"><label>Duration (minutes)</label><input class="input" type="number" min="1" placeholder="120" value="${m.duration_minutes||''}"></div>
    <div class="field"><label>Release Date</label><input class="input" type="date" value="${m.release_date||''}"></div>
    <div class="field"><label>Average Rating (0-5)</label><input class="input" type="number" placeholder="0.0" min="0" max="5" step="0.1" value="${m.rating||''}"></div>
    <div class="field"><label>Age Rating</label><select class="select">${buildOptions(ageRatings, m.age_rating || 'PG-13')}</select></div>
    <div class="field"><label>Language</label><input class="input" placeholder="English, Vietnamese" value="${m.language||''}"></div>
    <div class="field"><label>Director</label><input class="input" placeholder="Director name" value="${m.director||''}"></div>
    <div class="field"><label>Writer</label><input class="input" placeholder="Writer name" value="${m.writer||''}"></div>
    <div class="field"><label>Studio</label><input class="input" placeholder="Studio name" value="${m.studio||''}"></div>
    <div class="field form-full"><label>Cast Summary</label><input class="input" placeholder="Lead cast, comma separated" value="${m.cast_text||''}"></div>
    <div class="field"><label>Poster URL</label><input class="input" placeholder="https://cdn.example.com/poster.jpg" value="${m.poster_url||''}"></div>
    <div class="field"><label>Trailer URL</label><input class="input" placeholder="https://youtube.com/watch?v=..." value="${m.trailer_url||''}"></div>
    <div class="field form-full"><label>Summary</label><textarea class="textarea" placeholder="Movie summary...">${m.summary||''}</textarea><div class="helper-text">Additional posters, banners, and gallery art are managed in the Movie Images section.</div></div>
  </div>`;
}

function productFormBody(p = {}) {
  const categories = ['Snacks', 'Beverages', 'Merchandise', 'Combos'];
  return `<div class="form-grid">
    <div class="field"><label>Product Name</label><input class="input" placeholder="Enter name" value="${p.name||''}"></div>
    <div class="field"><label>Category</label><select class="select">${buildOptions(categories, p.cat || 'Snacks')}</select></div>
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
