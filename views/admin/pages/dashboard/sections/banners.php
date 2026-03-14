<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">12</div><div class="stat-label">Active Banners</div></div>
  <div class="stat-card gold" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">4</div><div class="stat-label">Homepage Slots</div></div>
  <div class="stat-card green" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">82%</div><div class="stat-label">Avg CTR</div></div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">3</div><div class="stat-label">Drafts</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="bannerSearch" type="text" placeholder="Search banners..." oninput="filterBanners(this.value)">
    </div>
    <select id="bannerStatusFilter" class="select-filter" onchange="filterBanners()"><option>All Status</option><option>Active</option><option>Scheduled</option><option>Draft</option></select>
    <div class="toolbar-right"><span id="bannerCount" style="font-size:12px;color:var(--text-dim);">6 banners</span></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Banner</th><th>Placement</th><th>Campaign</th><th>Schedule</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody id="bannerBody"></tbody>
    </table>
  </div>
</div>

<script>
const bannerData = [
  {name:'Hero Slider 01',placement:'Homepage Hero',campaign:'Avengers Launch',schedule:'2026-03-01 to 2026-03-31',status:'Active',code:'H1'},
  {name:'Snack Combo Push',placement:'Homepage Grid',campaign:'Combo Upsell',schedule:'2026-03-05 to 2026-03-20',status:'Active',code:'SC'},
  {name:'Student Night',placement:'Checkout Promo',campaign:'Student Discount',schedule:'2026-03-15 to 2026-04-01',status:'Scheduled',code:'SN'},
  {name:'Merch Spotlight',placement:'Shop Hero',campaign:'Merch Drop',schedule:'2026-03-08 to 2026-03-30',status:'Active',code:'MS'},
  {name:'Spring Refresh',placement:'Homepage Hero',campaign:'Seasonal Theme',schedule:'Not scheduled',status:'Draft',code:'SR'},
  {name:'VIP Banner',placement:'Members Area',campaign:'VIP Member Deal',schedule:'2026-03-01 to 2026-04-30',status:'Active',code:'VP'},
];

function bannerFormBody(banner = {}) {
  const placement = banner.placement || 'Homepage Hero';
  const status = banner.status || 'Draft';

  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Campaign Placement</div>
      <div class="surface-card-copy">Prepare the banner creative, rollout window, and placement priority for homepage, checkout, or shop slots.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Banner Name</label><input class="input" placeholder="Hero Slider 01" value="${banner.name || ''}"></div>
      <div class="field"><label>Placement</label><select class="select">${buildOptions(['Homepage Hero', 'Homepage Grid', 'Checkout Promo', 'Shop Hero', 'Members Area'], placement)}</select></div>
      <div class="field"><label>Status</label><select class="select">${buildOptions(['Active', 'Scheduled', 'Draft'], status)}</select></div>
      <div class="field"><label>Priority Slot</label><select class="select">${buildOptions(['Primary', 'Secondary', 'Support', 'Experimental'], banner.priority || 'Primary')}</select></div>
      <div class="field form-full"><label>Campaign</label><input class="input" placeholder="Campaign name" value="${banner.campaign || ''}"></div>
      <div class="field"><label>Start Date</label><input class="input" type="date" value="${banner.start || ''}"></div>
      <div class="field"><label>End Date</label><input class="input" type="date" value="${banner.end || ''}"></div>
      <div class="field"><label>CTA Label</label><input class="input" placeholder="Book now" value="${banner.cta || ''}"></div>
      <div class="field"><label>Destination</label><select class="select">${buildOptions(['Movie Detail', 'Promotion Landing', 'Shop Collection', 'Membership Page'], banner.destination || 'Promotion Landing')}</select></div>
      <div class="field form-full"><label>Copy Note</label><textarea class="textarea" placeholder="Short supporting copy for the banner...">${banner.copy || ''}</textarea></div>
      <div class="field form-full"><label>Preview</label>
        <div class="preview-banner">
          <div class="preview-banner-title">${banner.name || 'Banner preview'}</div>
          <div class="preview-banner-copy">${banner.campaign || 'Attach a campaign and short message so the hero area feels intentional before backend wiring.'}</div>
          <div class="meta-pills">
            <span class="badge blue">${placement}</span>
            <span class="badge ${status === 'Draft' ? 'gray' : status === 'Scheduled' ? 'orange' : 'green'}">${status}</span>
            <span class="badge gold">${banner.priority || 'Primary'} slot</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function openBannerModal(title, banner = {}) {
  const isEdit = /^Edit/i.test(title);
  openModal(title, bannerFormBody(banner), {
    description: isEdit
      ? 'Adjust placement, schedule, and rollout copy for this banner slot.'
      : 'Create a new campaign banner and stage how it appears across the admin storefront.',
    note: 'UI preview only. Banner content is not persisted yet.',
    submitLabel: isEdit ? 'Update Banner' : 'Create Banner',
    successMessage: isEdit ? 'Banner preview updated!' : 'Banner preview staged!',
  });
}

function handleDashboardSectionAction() {
  openBannerModal('Add Banner');
}

function renderBanners(data) {
  document.getElementById('bannerBody').innerHTML = data.map(banner => `<tr><td><div style="display:flex;align-items:center;gap:10px;"><div class="poster-img-placeholder">${banner.code}</div><div class="td-bold">${banner.name}</div></div></td><td class="td-muted">${banner.placement}</td><td class="td-muted">${banner.campaign}</td><td class="td-muted">${banner.schedule}</td><td>${statusBadge(banner.status)}</td><td><div class="actions-row"><button class="action-btn edit" title="Edit" onclick="openBannerModal('Edit Banner', {name:'${banner.name}',placement:'${banner.placement}',campaign:'${banner.campaign}',status:'${banner.status}'})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></div></td></tr>`).join('');
}

function filterBanners(q) {
  const searchInput = document.getElementById('bannerSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedStatus = document.getElementById('bannerStatusFilter')?.value || 'All Status';
  const filtered = bannerData.filter(banner => {
    const matchesQuery = searchTerm === '' || banner.name.toLowerCase().includes(searchTerm) || banner.campaign.toLowerCase().includes(searchTerm);
    const matchesStatus = selectedStatus === 'All Status' || banner.status === selectedStatus;
    return matchesQuery && matchesStatus;
  });
  renderBanners(filtered);
  document.getElementById('bannerCount').textContent = `${filtered.length} banners`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterBanners();
});
</script>
