<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cinemas — CineShop Admin</title>
<link rel="stylesheet" href="shared.css">
</head>
<body>
<div class="layout">
    <div id="sidebarMount"></div>
<div class="main-wrap" id="mainWrap">
        <div id="headerMount"></div>
<div class="page">
      <div class="page-header">
        <div>
          <div class="breadcrumb"><span>Home</span><span class="sep">›</span><span>Cinema Management</span></div>
          <h1 class="page-title">Cinema Management</h1>
          <p class="page-sub">Manage locations, rooms, and capacity</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('Add Cinema', cinemaFormBody())">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Cinema
        </button>
      </div>

      <div class="grid-3">
        <div class="card"><div class="card-body" style="display:flex;align-items:center;gap:16px;">
          <div style="width:52px;height:52px;border-radius:12px;background:var(--red-dim);display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2" width="22" height="22"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg></div>
          <div><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">12</div><div style="font-size:12px;color:var(--text-muted);">Cinema Locations</div></div>
        </div></div>
        <div class="card"><div class="card-body" style="display:flex;align-items:center;gap:16px;">
          <div style="width:52px;height:52px;border-radius:12px;background:rgba(59,130,246,0.1);display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2" width="22" height="22"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg></div>
          <div><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">48</div><div style="font-size:12px;color:var(--text-muted);">Total Rooms</div></div>
        </div></div>
        <div class="card"><div class="card-body" style="display:flex;align-items:center;gap:16px;">
          <div style="width:52px;height:52px;border-radius:12px;background:rgba(34,197,94,0.1);display:flex;align-items:center;justify-content:center;"><svg viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2" width="22" height="22"><path d="M5 13V7a2 2 0 012-2h10a2 2 0 012 2v6"/></svg></div>
          <div><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">6,240</div><div style="font-size:12px;color:var(--text-muted);">Total Seats</div></div>
        </div></div>
      </div>

      <div class="card">
        <div class="toolbar">
          <div class="toolbar-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" placeholder="Search cinemas...">
          </div>
          <select class="select-filter"><option>All Cities</option><option>Ho Chi Minh</option><option>Hanoi</option><option>Da Nang</option><option>Can Tho</option></select>
          <select class="select-filter"><option>All Status</option><option>Active</option><option>Renovation</option><option>Closed</option></select>
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

      <!-- ROOMS TABLE -->
      <div class="card" style="margin-top:20px;">
        <div class="card-header">
          <div><div class="card-title">Rooms</div><div class="card-sub">All rooms across locations</div></div>
          <button class="btn btn-primary btn-sm" onclick="openModal('Add Room', roomFormBody())">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Room
          </button>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Room Name</th><th>Cinema</th><th>Type</th><th>Capacity</th><th>Screen</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="roomsBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="shared.js"></script>
<script>
const cinemasData = [
  {name:'CineShop Galaxy',city:'Ho Chi Minh',addr:'416 Nguyen Thi Minh Khai, Q3',rooms:8,seats:1240,manager:'Tran Minh Duc',status:'Active'},
  {name:'CineShop Premier',city:'Hanoi',addr:'12 Hai Ba Trung, Hoan Kiem',rooms:6,seats:920,manager:'Le Thi Hoa',status:'Active'},
  {name:'CineShop Landmark',city:'Da Nang',addr:'255 Hung Vuong, Hai Chau',rooms:4,seats:640,manager:'Nguyen Quoc Anh',status:'Renovation'},
  {name:'CineShop Crescent',city:'Ho Chi Minh',addr:'101 Ton Dat Tien, Q7',rooms:10,seats:1580,manager:'Pham Van Tuan',status:'Active'},
  {name:'CineShop Royal',city:'Can Tho',addr:'68 Tran Hung Dao, Ninh Kieu',rooms:5,seats:780,manager:'Vo Thi Mai',status:'Active'},
];
const roomsData = [
  {name:'Room 1 — Deluxe',cinema:'Galaxy',type:'Standard 2D',cap:180,screen:'15m HD',status:'Active'},
  {name:'Room 2 — VIP',cinema:'Galaxy',type:'VIP Recliner',cap:80,screen:'12m 4K',status:'Active'},
  {name:'Room 3 — IMAX',cinema:'Galaxy',type:'IMAX',cap:240,screen:'22m IMAX',status:'Active'},
  {name:'Room 4 — Standard',cinema:'Galaxy',type:'Standard 2D',cap:160,screen:'12m HD',status:'Active'},
  {name:'Room 1 — Premium',cinema:'Premier',type:'Premium 3D',cap:120,screen:'14m 3D',status:'Active'},
  {name:'Room 2 — Standard',cinema:'Premier',type:'Standard 2D',cap:150,screen:'12m HD',status:'Maintenance'},
];

function cinemaFormBody() {
  return `<div class="form-grid">
    <div class="field"><label>Cinema Name</label><input class="input" placeholder="CineShop ..."></div>
    <div class="field"><label>City</label><select class="select"><option>Ho Chi Minh</option><option>Hanoi</option><option>Da Nang</option><option>Can Tho</option></select></div>
    <div class="field form-full"><label>Address</label><input class="input" placeholder="Full address"></div>
    <div class="field"><label>Phone</label><input class="input" placeholder="+84 ..."></div>
    <div class="field"><label>Manager Name</label><input class="input" placeholder="Manager full name"></div>
    <div class="field"><label>Latitude</label><input class="input" placeholder="10.7769"></div>
    <div class="field"><label>Longitude</label><input class="input" placeholder="106.7009"></div>
    <div class="field"><label>Status</label><select class="select"><option>Active</option><option>Renovation</option><option>Closed</option></select></div>
    <div class="field form-full"><label>Description</label><textarea class="textarea" placeholder="Cinema description..."></textarea></div>
  </div>`;
}

function roomFormBody() {
  return `<div class="form-grid">
    <div class="field"><label>Room Name</label><input class="input" placeholder="Room 1 — Deluxe"></div>
    <div class="field"><label>Cinema</label><select class="select"><option>CineShop Galaxy</option><option>CineShop Premier</option></select></div>
    <div class="field"><label>Room Type</label><select class="select"><option>Standard 2D</option><option>Premium 3D</option><option>VIP Recliner</option><option>IMAX</option><option>4DX</option></select></div>
    <div class="field"><label>Capacity</label><input class="input" type="number" placeholder="0"></div>
    <div class="field"><label>Screen Size</label><input class="input" placeholder="12m HD"></div>
    <div class="field"><label>Status</label><select class="select"><option>Active</option><option>Maintenance</option></select></div>
  </div>`;
}

document.addEventListener('DOMContentLoaded', function(){


  document.getElementById('cinemasBody').innerHTML = cinemasData.map(c=>`
    <tr>
      <td><div class="td-bold">${c.name}</div></td>
      <td class="td-muted">${c.city}</td>
      <td class="td-muted" style="font-size:12px;">${c.addr}</td>
      <td><span style="font-weight:700;">${c.rooms}</span></td>
      <td><span style="font-weight:700;">${c.seats.toLocaleString()}</span></td>
      <td class="td-muted">${c.manager}</td>
      <td>${statusBadge(c.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn view" onclick="showToast('Viewing ${c.name}','info')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn edit" onclick="openModal('Edit Cinema', cinemaFormBody())"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" onclick="showToast('${c.name} deleted','error')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`).join('');
  document.getElementById('cinemaPagination').innerHTML = buildPagination(`Showing 1–${cinemasData.length} of ${cinemasData.length} cinemas`);

  document.getElementById('roomsBody').innerHTML = roomsData.map(r=>`
    <tr>
      <td class="td-bold">${r.name}</td>
      <td class="td-muted">${r.cinema}</td>
      <td><span class="badge blue">${r.type}</span></td>
      <td style="font-weight:700;">${r.cap}</td>
      <td class="td-muted">${r.screen}</td>
      <td>${statusBadge(r.status === 'Maintenance' ? 'Renovation' : 'Active')}</td>
      <td><div class="actions-row">
        <button class="action-btn edit" onclick="openModal('Edit Room', roomFormBody())"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" onclick="showToast('Room deleted','error')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`).join('');
});
</script>
    <div id="footerMount"></div>
</body>
</html>
