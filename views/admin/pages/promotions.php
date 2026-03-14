<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">›</span><span>Promotions</span></div>
      <h1 class="page-title">Promotions</h1>
      <p class="page-sub">Create and manage discount campaigns</p>
    </div>
    <button class="btn btn-gold" onclick="openModal('New Promotion', promoFormBody())">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      New Promotion
    </button>
  </div>

  <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:24px;">
    <div class="stat-card gold" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;color:var(--gold);">18</div><div class="stat-label">Active Promos</div></div>
    <div class="stat-card blue" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">6</div><div class="stat-label">Upcoming</div></div>
    <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">24</div><div class="stat-label">Expired</div></div>
    <div class="stat-card green" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">8,920</div><div class="stat-label">Total Used</div></div>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px;" id="promoGrid"></div>

  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Product Promotions</div><div class="card-sub">Assign promotions to specific products</div></div>
      <button class="btn btn-ghost btn-sm" onclick="openModal('Assign Promotion', assignPromoFormBody())">Assign Promotion</button>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Product</th><th>Promo Code</th><th>Original Price</th><th>Discounted</th><th>Savings</th><th>Valid Until</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody id="productPromosBody"></tbody>
      </table>
    </div>
  </div>
</div>

<script>
const promos = [
  {title:'Summer Cinema Blast',code:'SUMMER25',disc:25,type:'Cinema',start:'Mar 1',end:'Mar 31',used:4218,status:'Active'},
  {title:'VIP Member Deal',code:'VIP20',disc:20,type:'All',start:'Mar 1',end:'Apr 30',used:840,status:'Active'},
  {title:'Valentine Special',code:'LOVE50',disc:50,type:'Cinema',start:'Feb 14',end:'Feb 28',used:620,status:'Ended'},
  {title:'Weekend Warrior',code:'WEEKEND15',disc:15,type:'All',start:'Mar 15',end:'Jun 30',used:0,status:'Coming Soon'},
  {title:'Student Discount',code:'STUDENT10',disc:10,type:'Cinema',start:'Jan 1',end:'Dec 31',used:1240,status:'Active'},
  {title:'Opening Night',code:'OPEN30',disc:30,type:'Shop',start:'Apr 1',end:'Apr 7',used:0,status:'Coming Soon'},
  {title:'Loyalty Reward',code:'LOYAL5',disc:5,type:'All',start:'Mar 1',end:'Dec 31',used:2840,status:'Active'},
  {title:'Flash Friday',code:'FLASH40',disc:40,type:'Shop',start:'Mar 14',end:'Mar 14',used:0,status:'Coming Soon'},
];

const productPromos = [
  {product:'🍿 Large Popcorn Combo',code:'SUMMER25',color:'gold',orig:'$8.50',disc:'$6.37',save:'$2.13',until:'Mar 31, 2026',status:'Active'},
  {product:'🥤 Drink Bundle x2',code:'VIP20',color:'purple',orig:'$5.00',disc:'$4.00',save:'$1.00',until:'Apr 30, 2026',status:'Active'},
  {product:'👕 CineShop Hoodie',code:'STUDENT10',color:'blue',orig:'$45.00',disc:'$40.50',save:'$4.50',until:'Dec 31, 2026',status:'Active'},
  {product:'🌮 Nacho + Dip Set',code:'LOVE50',color:'red',orig:'$6.00',disc:'$3.00',save:'$3.00',until:'Feb 28, 2026',status:'Ended'},
];

function promoFormBody() {
  return `<div class="form-grid">
    <div class="field"><label>Promotion Title</label><input class="input" placeholder="Summer Cinema Blast"></div>
    <div class="field"><label>Promo Code</label><input class="input" placeholder="SUMMER25" style="text-transform:uppercase;font-family:monospace;"></div>
    <div class="field"><label>Discount Type</label><select class="select"><option>Percentage (%)</option><option>Fixed Amount ($)</option></select></div>
    <div class="field"><label>Discount Value</label><input class="input" type="number" placeholder="25" min="1" max="100"></div>
    <div class="field"><label>Applies To</label><select class="select"><option>All</option><option>Cinema Tickets</option><option>Shop Products</option></select></div>
    <div class="field"><label>Max Uses</label><input class="input" type="number" placeholder="Unlimited"></div>
    <div class="field"><label>Start Date</label><input class="input" type="date"></div>
    <div class="field"><label>End Date</label><input class="input" type="date"></div>
    <div class="field form-full"><label>Description</label><textarea class="textarea" placeholder="Promotion description..."></textarea></div>
  </div>`;
}

function assignPromoFormBody() {
  return `<div class="form-grid">
    <div class="field"><label>Product</label><select class="select"><option>Large Popcorn Combo</option><option>Drink Bundle x2</option><option>CineShop Hoodie</option></select></div>
    <div class="field"><label>Promotion</label><select class="select"><option>SUMMER25 (25% off)</option><option>VIP20 (20% off)</option><option>STUDENT10 (10% off)</option></select></div>
    <div class="field"><label>Valid From</label><input class="input" type="date"></div>
    <div class="field"><label>Valid Until</label><input class="input" type="date"></div>
  </div>`;
}

document.addEventListener('DOMContentLoaded', function(){
  document.getElementById('promoGrid').innerHTML = promos.map(p=>`
    <div class="promo-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
        <div class="promo-percent">${p.disc}%</div>
        ${statusBadge(p.status==='Active'?'Active':p.status==='Coming Soon'?'Coming Soon':'Ended')}
      </div>
      <div class="promo-title">${p.title}</div>
      <div style="margin-top:6px;display:flex;align-items:center;gap:8px;">
        <span class="badge gray" style="font-family:monospace;letter-spacing:1px;">${p.code}</span>
        <span class="badge blue" style="font-size:10px;">${p.type}</span>
      </div>
      <div class="promo-dates">${p.start} — ${p.end} · ${p.used.toLocaleString()} used</div>
      <div class="promo-actions">
        <button class="btn btn-ghost btn-sm" style="flex:1;" onclick="openModal('Edit Promotion', promoFormBody())">Edit</button>
        <button class="btn btn-ghost btn-sm" onclick="showToast('${p.code} copied!','success')">Copy Code</button>
        <button class="btn btn-ghost btn-sm" onclick="showToast('Deleted','error')">×</button>
      </div>
    </div>`).join('');

  document.getElementById('productPromosBody').innerHTML = productPromos.map(p=>`
    <tr>
      <td class="td-bold">${p.product}</td>
      <td><span class="badge ${p.color}">${p.code}</span></td>
      <td class="td-muted" style="text-decoration:line-through;">${p.orig}</td>
      <td style="color:var(--green);font-weight:700;">${p.disc}</td>
      <td style="color:var(--red);font-weight:600;">${p.save}</td>
      <td class="td-muted">${p.until}</td>
      <td>${statusBadge(p.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn edit" onclick="openModal('Edit Promo Assign', assignPromoFormBody())"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" onclick="showToast('Promo removed','error')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`).join('');
});
</script>
