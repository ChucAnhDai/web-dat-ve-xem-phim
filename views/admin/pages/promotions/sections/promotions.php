<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
  <div class="stat-card gold" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;color:var(--gold);">18</div><div class="stat-label">Active Promos</div></div>
  <div class="stat-card blue" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">6</div><div class="stat-label">Upcoming</div></div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">24</div><div class="stat-label">Expired</div></div>
  <div class="stat-card green" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">8,920</div><div class="stat-label">Total Used</div></div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;" id="promotionGrid"></div>

<script>
const promotionsData = [
  {title:'Summer Cinema Blast',code:'SUMMER25',disc:25,type:'Cinema',start:'2026-03-01',end:'2026-03-31',used:4218,status:'Active'},
  {title:'VIP Member Deal',code:'VIP20',disc:20,type:'All',start:'2026-03-01',end:'2026-04-30',used:840,status:'Active'},
  {title:'Valentine Special',code:'LOVE50',disc:50,type:'Cinema',start:'2026-02-14',end:'2026-02-28',used:620,status:'Ended'},
  {title:'Weekend Warrior',code:'WEEKEND15',disc:15,type:'All',start:'2026-03-15',end:'2026-06-30',used:0,status:'Coming Soon'},
  {title:'Student Discount',code:'STUDENT10',disc:10,type:'Cinema',start:'2026-01-01',end:'2026-12-31',used:1240,status:'Active'},
  {title:'Opening Night',code:'OPEN30',disc:30,type:'Shop',start:'2026-04-01',end:'2026-04-07',used:0,status:'Coming Soon'},
];

function promoFormBody(promo = {}) {
  const appliesTo = promo.type || 'All';
  const status = promo.status || 'Draft';
  const channels = promo.channels || ['Homepage slot', 'Checkout banner'];

  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Campaign Offer Setup</div>
      <div class="surface-card-copy">Configure the discount mechanics, campaign window, and storefront surfaces so each promo feels launch-ready in the admin preview.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Promotion Title</label><input class="input" placeholder="Summer Cinema Blast" value="${promo.title || ''}"></div>
      <div class="field"><label>Promo Code</label><input class="input" placeholder="SUMMER25" style="text-transform:uppercase;font-family:monospace;" value="${promo.code || ''}"></div>
      <div class="field"><label>Discount Type</label><select class="select">${buildOptions(['Percentage (%)', 'Fixed Amount ($)'], promo.discountType || 'Percentage (%)')}</select></div>
      <div class="field"><label>Discount Value</label><input class="input" type="number" placeholder="25" value="${promo.disc || ''}"></div>
      <div class="field"><label>Applies To</label><select class="select">${buildOptions(['All', 'Cinema', 'Shop'], appliesTo)}</select></div>
      <div class="field"><label>Status</label><select class="select">${buildOptions(['Draft', 'Active', 'Coming Soon', 'Ended'], status)}</select></div>
      <div class="field"><label>Max Uses</label><input class="input" type="number" placeholder="Unlimited" value="${promo.maxUses || ''}"></div>
      <div class="field"><label>Minimum Spend</label><input class="input" placeholder="$0" value="${promo.minimum || ''}"></div>
      <div class="field"><label>Start Date</label><input class="input" type="date" value="${promo.start || ''}"></div>
      <div class="field"><label>End Date</label><input class="input" type="date" value="${promo.end || ''}"></div>
      <div class="field"><label>Audience</label><select class="select">${buildOptions(['All guests', 'Members only', 'Students', 'First purchase'], promo.audience || 'All guests')}</select></div>
      <div class="field form-full"><label>Promotion Surfaces</label>
        <div class="check-grid">
          ${['Homepage slot', 'Checkout banner', 'Membership hub', 'Movie detail page', 'Snack shop grid', 'Email campaign'].map(channel => `
            <label class="check-option">
              <input type="checkbox"${channels.includes(channel) ? ' checked' : ''}>
              <span>${channel}</span>
            </label>`).join('')}
        </div>
      </div>
      <div class="field form-full"><label>Description</label><textarea class="textarea" placeholder="Promotion description">${promo.description || ''}</textarea></div>
      <div class="field form-full"><label>Preview</label>
        <div class="preview-banner">
          <div class="preview-banner-title">${promo.title || 'Promotion preview'}</div>
          <div class="preview-banner-copy">${promo.code || 'PROMO'} · ${promo.disc || '--'} ${promo.discountType === 'Fixed Amount ($)' ? 'USD' : '%'} off for ${appliesTo.toLowerCase()} orders.</div>
          <div class="meta-pills">
            <span class="badge gold">${promo.code || 'CODE'}</span>
            <span class="badge blue">${appliesTo}</span>
            <span class="badge ${status === 'Ended' ? 'gray' : status === 'Coming Soon' ? 'blue' : status === 'Draft' ? 'orange' : 'green'}">${status}</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function openPromotionModal(title, promo = {}) {
  const isEdit = /^Edit/i.test(title);
  openModal(title, promoFormBody(promo), {
    description: isEdit
      ? 'Update the campaign window, discount setup, and rollout surfaces for this promotion.'
      : 'Create a new promotion and preview how it should launch across cinema and shop touchpoints.',
    note: 'UI preview only. Promotion settings are not persisted yet.',
    submitLabel: isEdit ? 'Update Promotion' : 'Create Promotion',
    successMessage: isEdit ? 'Promotion preview updated!' : 'Promotion preview staged!',
  });
}

function handlePromotionSectionAction() {
  openPromotionModal('New Promotion');
}

function renderPromotions() {
  document.getElementById('promotionGrid').innerHTML = promotionsData.map(promo => `
    <div class="promo-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
        <div class="promo-percent">${promo.disc}%</div>
        ${statusBadge(promo.status)}
      </div>
      <div class="promo-title">${promo.title}</div>
      <div style="margin-top:6px;display:flex;align-items:center;gap:8px;">
        <span class="badge gray" style="font-family:monospace;letter-spacing:1px;">${promo.code}</span>
        <span class="badge blue" style="font-size:10px;">${promo.type}</span>
      </div>
      <div class="promo-dates">${promo.start} - ${promo.end} / ${promo.used.toLocaleString()} used</div>
      <div class="promo-actions">
        <button class="btn btn-ghost btn-sm" style="flex:1;" onclick="openPromotionModal('Edit Promotion', {title:'${promo.title}',code:'${promo.code}',disc:${promo.disc},type:'${promo.type}',start:'${promo.start}',end:'${promo.end}',status:'${promo.status}'})">Edit</button>
        <button class="btn btn-ghost btn-sm" onclick="showToast('${promo.code} copied','success')">Copy</button>
        <button class="btn btn-ghost btn-sm" onclick="showToast('${promo.code} archived','warning')">Hide</button>
      </div>
    </div>`).join('');
}

document.addEventListener('DOMContentLoaded', function () {
  renderPromotions();
});
</script>
