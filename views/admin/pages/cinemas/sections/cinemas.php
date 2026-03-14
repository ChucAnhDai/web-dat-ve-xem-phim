<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card red" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">12</div>
    <div class="stat-label">Cinema Locations</div>
  </div>
  <div class="stat-card blue" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">5</div>
    <div class="stat-label">Operating Cities</div>
  </div>
  <div class="stat-card green" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">48</div>
    <div class="stat-label">Open Rooms</div>
  </div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">6,240</div>
    <div class="stat-label">Total Seats</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="cinemaSearch" type="text" placeholder="Search cinemas..." oninput="filterCinemas(this.value)">
    </div>
    <select id="cinemaCityFilter" class="select-filter" onchange="filterCinemas()">
      <option>All Cities</option>
      <option>Ho Chi Minh</option>
      <option>Hanoi</option>
      <option>Da Nang</option>
      <option>Can Tho</option>
      <option>Hai Phong</option>
    </select>
    <select id="cinemaStatusFilter" class="select-filter" onchange="filterCinemas()">
      <option>All Status</option>
      <option>Active</option>
      <option>Renovation</option>
      <option>Closed</option>
    </select>
    <div class="toolbar-right">
      <span id="cinemaCount" style="font-size:12px;color:var(--text-dim);">12 cinemas</span>
      <button class="btn btn-ghost btn-sm" onclick="window.location.href='<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/cinemas?section=rooms'">View Rooms</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Cinema Name</th><th>City</th><th>Address</th><th>Rooms</th><th>Total Seats</th><th>Manager</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody id="cinemasBody"></tbody>
    </table>
  </div>
  <div id="cinemaPagination"></div>
</div>

<script>
const cinemaRecords = [
  {name:'CineShop Galaxy',city:'Ho Chi Minh',addr:'416 Nguyen Thi Minh Khai, District 3',rooms:8,seats:1240,manager:'Tran Minh Duc',status:'Active'},
  {name:'CineShop Premier',city:'Hanoi',addr:'12 Hai Ba Trung, Hoan Kiem',rooms:6,seats:920,manager:'Le Thi Hoa',status:'Active'},
  {name:'CineShop Landmark',city:'Da Nang',addr:'255 Hung Vuong, Hai Chau',rooms:4,seats:640,manager:'Nguyen Quoc Anh',status:'Renovation'},
  {name:'CineShop Crescent',city:'Ho Chi Minh',addr:'101 Ton Dat Tien, District 7',rooms:10,seats:1580,manager:'Pham Van Tuan',status:'Active'},
  {name:'CineShop Royal',city:'Can Tho',addr:'68 Tran Hung Dao, Ninh Kieu',rooms:5,seats:780,manager:'Vo Thi Mai',status:'Active'},
  {name:'CineShop Riverside',city:'Hai Phong',addr:'9 Tran Phu, Ngo Quyen',rooms:7,seats:1080,manager:'Dang Quoc Viet',status:'Active'},
  {name:'CineShop Pearl',city:'Ho Chi Minh',addr:'27 Dien Bien Phu, Binh Thanh',rooms:3,seats:420,manager:'Pham Thanh Nam',status:'Closed'},
  {name:'CineShop Heritage',city:'Hanoi',addr:'89 Lang Ha, Dong Da',rooms:5,seats:760,manager:'Nguyen Thi Mai',status:'Active'},
  {name:'CineShop Marina',city:'Da Nang',addr:'5 Bach Dang, Hai Chau',rooms:4,seats:600,manager:'Le Quoc Huy',status:'Renovation'},
  {name:'CineShop Sun',city:'Can Tho',addr:'120 Mau Than, Ninh Kieu',rooms:4,seats:540,manager:'Hoang Thanh Binh',status:'Active'},
  {name:'CineShop Metro',city:'Ho Chi Minh',addr:'55 Phan Xich Long, Phu Nhuan',rooms:6,seats:860,manager:'Bui Minh Tuan',status:'Active'},
  {name:'CineShop Lotus',city:'Hanoi',addr:'144 Xuan Thuy, Cau Giay',rooms:5,seats:820,manager:'Tran Thu Ha',status:'Active'},
];

function cinemaFormBody(cinema = {}) {
  return `<div class="form-grid">
    <div class="field"><label>Cinema Name</label><input class="input" placeholder="CineShop ..." value="${cinema.name || ''}"></div>
    <div class="field"><label>City</label><select class="select"><option>Ho Chi Minh</option><option>Hanoi</option><option>Da Nang</option><option>Can Tho</option><option>Hai Phong</option></select></div>
    <div class="field form-full"><label>Address</label><input class="input" placeholder="Full address" value="${cinema.addr || ''}"></div>
    <div class="field"><label>Phone</label><input class="input" placeholder="+84 ..." value="${cinema.phone || ''}"></div>
    <div class="field"><label>Manager Name</label><input class="input" placeholder="Manager full name" value="${cinema.manager || ''}"></div>
    <div class="field"><label>Latitude</label><input class="input" placeholder="10.7769" value="${cinema.lat || ''}"></div>
    <div class="field"><label>Longitude</label><input class="input" placeholder="106.7009" value="${cinema.lng || ''}"></div>
    <div class="field"><label>Status</label><select class="select"><option>Active</option><option>Renovation</option><option>Closed</option></select></div>
    <div class="field form-full"><label>Description</label><textarea class="textarea" placeholder="Cinema description...">${cinema.desc || ''}</textarea></div>
  </div>`;
}

function handleCinemaSectionAction() {
  openModal('Add Cinema', cinemaFormBody());
}

function renderCinemas(data) {
  const startItem = data.length === 0 ? 0 : 1;
  document.getElementById('cinemasBody').innerHTML = data.map(cinema => `
    <tr>
      <td><div class="td-bold">${cinema.name}</div></td>
      <td class="td-muted">${cinema.city}</td>
      <td class="td-muted" style="font-size:12px;">${cinema.addr}</td>
      <td><span style="font-weight:700;">${cinema.rooms}</span></td>
      <td><span style="font-weight:700;">${cinema.seats.toLocaleString()}</span></td>
      <td class="td-muted">${cinema.manager}</td>
      <td>${statusBadge(cinema.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="showToast('Viewing ${cinema.name}','info')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
        <button class="action-btn edit" title="Edit" onclick="openModal('Edit Cinema', cinemaFormBody({name:'${cinema.name}',addr:'${cinema.addr}',manager:'${cinema.manager}'}))">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="action-btn gold" title="Rooms" onclick="window.location.href='<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/cinemas?section=rooms'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        </button>
        <button class="action-btn del" title="Delete" onclick="showToast('${cinema.name} deleted','error')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
        </button>
      </div></td>
    </tr>`).join('');
  document.getElementById('cinemaPagination').innerHTML = buildPagination(`Showing ${startItem}-${data.length} of ${data.length} cinemas`, Math.max(1, Math.ceil(data.length / 10)));
}

function filterCinemas(q) {
  const searchInput = document.getElementById('cinemaSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedCity = document.getElementById('cinemaCityFilter')?.value || 'All Cities';
  const selectedStatus = document.getElementById('cinemaStatusFilter')?.value || 'All Status';
  const filtered = cinemaRecords.filter(cinema => {
    const matchesQuery = searchTerm === '' ||
      cinema.name.toLowerCase().includes(searchTerm) ||
      cinema.city.toLowerCase().includes(searchTerm) ||
      cinema.manager.toLowerCase().includes(searchTerm);
    const matchesCity = selectedCity === 'All Cities' || cinema.city === selectedCity;
    const matchesStatus = selectedStatus === 'All Status' || cinema.status === selectedStatus;

    return matchesQuery && matchesCity && matchesStatus;
  });

  renderCinemas(filtered);
  document.getElementById('cinemaCount').textContent = `${filtered.length} cinemas`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterCinemas();
});
</script>
