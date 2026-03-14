<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">26</div><div class="stat-label">Assigned Products</div></div>
  <div class="stat-card green" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">14</div><div class="stat-label">Active Assignments</div></div>
  <div class="stat-card gold" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">$128</div><div class="stat-label">Average Savings</div></div>
  <div class="stat-card red" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">4</div><div class="stat-label">Expired Offers</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="productPromoSearch" type="text" placeholder="Search product or code..." oninput="filterProductPromotions(this.value)">
    </div>
    <select id="productPromoStatus" class="select-filter" onchange="filterProductPromotions()">
      <option>All Status</option>
      <option>Active</option>
      <option>Ended</option>
      <option>Coming Soon</option>
    </select>
    <div class="toolbar-right">
      <span id="productPromoCount" style="font-size:12px;color:var(--text-dim);">4 assignments</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Product</th><th>Promo Code</th><th>Original Price</th><th>Discounted</th><th>Savings</th><th>Valid Until</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody id="productPromosBody"></tbody>
    </table>
  </div>
</div>

<script>
const productPromotionsData = [
  {product:'Large Popcorn Combo',code:'SUMMER25',color:'gold',orig:'$8.50',disc:'$6.37',save:'$2.13',until:'2026-03-31',status:'Active'},
  {product:'Drink Bundle x2',code:'VIP20',color:'purple',orig:'$5.00',disc:'$4.00',save:'$1.00',until:'2026-04-30',status:'Active'},
  {product:'CineShop Hoodie',code:'STUDENT10',color:'blue',orig:'$45.00',disc:'$40.50',save:'$4.50',until:'2026-12-31',status:'Active'},
  {product:'Nacho + Dip Set',code:'LOVE50',color:'red',orig:'$6.00',disc:'$3.00',save:'$3.00',until:'2026-02-28',status:'Ended'},
];

function assignPromoFormBody(assignment = {}) {
  return `<div class="form-grid">
    <div class="field"><label>Product</label><input class="input" placeholder="Large Popcorn Combo" value="${assignment.product || ''}"></div>
    <div class="field"><label>Promotion</label><input class="input" placeholder="SUMMER25" value="${assignment.code || ''}"></div>
    <div class="field"><label>Valid From</label><input class="input" type="date"></div>
    <div class="field"><label>Valid Until</label><input class="input" type="date" value="${assignment.until || ''}"></div>
  </div>`;
}

function handlePromotionSectionAction() {
  openModal('Assign Promotion', assignPromoFormBody());
}

function renderProductPromotions(data) {
  document.getElementById('productPromosBody').innerHTML = data.map(item => `
    <tr>
      <td class="td-bold">${item.product}</td>
      <td><span class="badge ${item.color}">${item.code}</span></td>
      <td class="td-muted" style="text-decoration:line-through;">${item.orig}</td>
      <td style="color:var(--green);font-weight:700;">${item.disc}</td>
      <td style="color:var(--red);font-weight:600;">${item.save}</td>
      <td class="td-muted">${item.until}</td>
      <td>${statusBadge(item.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn edit" title="Edit" onclick="openModal('Edit Assignment', assignPromoFormBody({product:'${item.product}',code:'${item.code}',until:'${item.until}'}))"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" title="Remove" onclick="showToast('Promo removed','error')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`).join('');
}

function filterProductPromotions(q) {
  const searchInput = document.getElementById('productPromoSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedStatus = document.getElementById('productPromoStatus')?.value || 'All Status';
  const filtered = productPromotionsData.filter(item => {
    const matchesQuery = searchTerm === '' || item.product.toLowerCase().includes(searchTerm) || item.code.toLowerCase().includes(searchTerm);
    const matchesStatus = selectedStatus === 'All Status' || item.status === selectedStatus;
    return matchesQuery && matchesStatus;
  });

  renderProductPromotions(filtered);
  document.getElementById('productPromoCount').textContent = `${filtered.length} assignments`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterProductPromotions();
});
</script>
