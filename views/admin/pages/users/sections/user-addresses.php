<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">4,218</div><div class="stat-label">Saved Addresses</div></div>
  <div class="stat-card green" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">2,910</div><div class="stat-label">Default Addresses</div></div>
  <div class="stat-card gold" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">1,142</div><div class="stat-label">Pickup Points</div></div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">76</div><div class="stat-label">Needs Review</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="addressSearch" type="text" placeholder="Search user or address..." oninput="filterAddresses(this.value)">
    </div>
    <select id="addressTypeFilter" class="select-filter" onchange="filterAddresses()"><option>All Types</option><option>Home</option><option>Office</option><option>Pickup</option></select>
    <select id="addressStatusFilter" class="select-filter" onchange="filterAddresses()"><option>All Status</option><option>Verified</option><option>Pending</option><option>Blocked</option></select>
    <div class="toolbar-right">
      <span id="addressCount" style="font-size:12px;color:var(--text-dim);">8 addresses</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>User</th><th>Label</th><th>Address</th><th>City</th><th>Phone</th><th>Default</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody id="userAddressesBody"></tbody>
    </table>
  </div>
</div>

<script>
const userAddressesData = [
  {user:'Nguyen Van A',label:'Home',address:'45 Hai Ba Trung, District 3',city:'Ho Chi Minh',phone:'0901234567',primary:'Primary',status:'Verified'},
  {user:'Tran Thi B',label:'Office',address:'12 Le Thanh Ton, District 1',city:'Ho Chi Minh',phone:'0912345678',primary:'Secondary',status:'Verified'},
  {user:'Hoang Minh C',label:'Home',address:'88 Nguyen Hue, District 1',city:'Ho Chi Minh',phone:'0945678901',primary:'Primary',status:'Verified'},
  {user:'Do Thi D',label:'Pickup',address:'CineShop Galaxy Counter',city:'Ho Chi Minh',phone:'0956789012',primary:'Primary',status:'Pending'},
  {user:'Vu Quoc E',label:'Home',address:'255 Hung Vuong, Hai Chau',city:'Da Nang',phone:'0967890123',primary:'Primary',status:'Verified'},
  {user:'Ly Van F',label:'Office',address:'9 Tran Phu, Ngo Quyen',city:'Hai Phong',phone:'0978901234',primary:'Secondary',status:'Pending'},
  {user:'Bui Thi G',label:'Home',address:'68 Tran Hung Dao, Ninh Kieu',city:'Can Tho',phone:'0989012345',primary:'Primary',status:'Verified'},
  {user:'Dang Van H',label:'Pickup',address:'CineShop Premier Counter',city:'Hanoi',phone:'0990123456',primary:'Secondary',status:'Blocked'},
];

function addressFormBody(address = {}) {
  return `<div class="form-grid">
    <div class="field"><label>User</label><input class="input" placeholder="User name" value="${address.user || ''}"></div>
    <div class="field"><label>Label</label><select class="select"><option>Home</option><option>Office</option><option>Pickup</option></select></div>
    <div class="field form-full"><label>Address</label><input class="input" placeholder="Street address" value="${address.address || ''}"></div>
    <div class="field"><label>City</label><input class="input" placeholder="City" value="${address.city || ''}"></div>
    <div class="field"><label>Phone</label><input class="input" placeholder="Phone number" value="${address.phone || ''}"></div>
    <div class="field"><label>Status</label><select class="select"><option>Verified</option><option>Pending</option><option>Blocked</option></select></div>
  </div>`;
}

function handleUserSectionAction() {
  openModal('Add Address', addressFormBody());
}

function renderAddresses(data) {
  document.getElementById('userAddressesBody').innerHTML = data.map(address => `
    <tr>
      <td class="td-bold">${address.user}</td>
      <td><span class="badge gray">${address.label}</span></td>
      <td class="td-muted">${address.address}</td>
      <td class="td-muted">${address.city}</td>
      <td class="td-muted">${address.phone}</td>
      <td><span class="badge ${address.primary === 'Primary' ? 'blue' : 'gray'}">${address.primary}</span></td>
      <td>${statusBadge(address.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn edit" title="Edit" onclick="openModal('Edit Address', addressFormBody({user:'${address.user}',address:'${address.address}',city:'${address.city}',phone:'${address.phone}'}))"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
      </div></td>
    </tr>`).join('');
}

function filterAddresses(q) {
  const searchInput = document.getElementById('addressSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedType = document.getElementById('addressTypeFilter')?.value || 'All Types';
  const selectedStatus = document.getElementById('addressStatusFilter')?.value || 'All Status';
  const filtered = userAddressesData.filter(address => {
    const matchesQuery = searchTerm === '' || address.user.toLowerCase().includes(searchTerm) || address.address.toLowerCase().includes(searchTerm) || address.city.toLowerCase().includes(searchTerm);
    const matchesType = selectedType === 'All Types' || address.label === selectedType;
    const matchesStatus = selectedStatus === 'All Status' || address.status === selectedStatus;
    return matchesQuery && matchesType && matchesStatus;
  });

  renderAddresses(filtered);
  document.getElementById('addressCount').textContent = `${filtered.length} addresses`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterAddresses();
});
</script>
