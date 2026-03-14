<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card red" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">48</div>
    <div class="stat-label">Total Rooms</div>
  </div>
  <div class="stat-card gold" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">9</div>
    <div class="stat-label">Premium Screens</div>
  </div>
  <div class="stat-card blue" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">7</div>
    <div class="stat-label">Formats</div>
  </div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">2,820</div>
    <div class="stat-label">Tracked Capacity</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="roomSearch" type="text" placeholder="Search rooms..." oninput="filterRooms(this.value)">
    </div>
    <select id="roomCinemaFilter" class="select-filter" onchange="filterRooms()">
      <option>All Cinemas</option>
      <option>Galaxy</option>
      <option>Premier</option>
      <option>Landmark</option>
      <option>Crescent</option>
      <option>Royal</option>
    </select>
    <select id="roomTypeFilter" class="select-filter" onchange="filterRooms()">
      <option>All Types</option>
      <option>Standard 2D</option>
      <option>Premium 3D</option>
      <option>VIP Recliner</option>
      <option>IMAX</option>
      <option>4DX</option>
      <option>ScreenX</option>
      <option>Dolby Atmos</option>
    </select>
    <select id="roomStatusFilter" class="select-filter" onchange="filterRooms()">
      <option>All Status</option>
      <option>Active</option>
      <option>Maintenance</option>
    </select>
    <div class="toolbar-right">
      <span id="roomCount" style="font-size:12px;color:var(--text-dim);">14 rooms</span>
      <button class="btn btn-ghost btn-sm" onclick="window.location.href='<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/seats'">Manage Seats</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Room Name</th><th>Cinema</th><th>Type</th><th>Capacity</th><th>Screen</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody id="roomsBody"></tbody>
    </table>
  </div>
  <div id="roomsPagination"></div>
</div>

<script>
const roomRecords = [
  {name:'Room 1 - Deluxe',cinema:'Galaxy',type:'Standard 2D',cap:180,screen:'15m HD',status:'Active'},
  {name:'Room 2 - VIP',cinema:'Galaxy',type:'VIP Recliner',cap:80,screen:'12m 4K',status:'Active'},
  {name:'Room 3 - IMAX',cinema:'Galaxy',type:'IMAX',cap:240,screen:'22m IMAX',status:'Active'},
  {name:'Room 4 - Standard',cinema:'Galaxy',type:'Standard 2D',cap:160,screen:'12m HD',status:'Active'},
  {name:'Room 1 - Premium',cinema:'Premier',type:'Premium 3D',cap:120,screen:'14m 3D',status:'Active'},
  {name:'Room 2 - Standard',cinema:'Premier',type:'Standard 2D',cap:150,screen:'12m HD',status:'Maintenance'},
  {name:'Room 3 - Atmos',cinema:'Premier',type:'Dolby Atmos',cap:110,screen:'13m Dolby',status:'Active'},
  {name:'Room 1 - Signature',cinema:'Landmark',type:'4DX',cap:96,screen:'11m Motion',status:'Maintenance'},
  {name:'Room 2 - Classic',cinema:'Landmark',type:'Standard 2D',cap:140,screen:'12m HD',status:'Active'},
  {name:'Room 5 - Family',cinema:'Crescent',type:'Standard 2D',cap:130,screen:'11m HD',status:'Active'},
  {name:'Room 6 - ScreenX',cinema:'Crescent',type:'ScreenX',cap:170,screen:'17m ScreenX',status:'Active'},
  {name:'Room 1 - Luxe',cinema:'Royal',type:'VIP Recliner',cap:72,screen:'10m 4K',status:'Active'},
  {name:'Room 2 - Event',cinema:'Royal',type:'Premium 3D',cap:124,screen:'14m 3D',status:'Active'},
  {name:'Room 3 - Standard',cinema:'Royal',type:'Standard 2D',cap:148,screen:'12m HD',status:'Active'},
];

function roomFormBody(room = {}) {
  const cinema = room.cinema || 'Galaxy';
  const type = room.type || 'Standard 2D';
  const status = room.status || 'Active';
  const features = room.features || ['Wheelchair row', 'Laser projection'];

  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Auditorium Configuration</div>
      <div class="surface-card-copy">Map the room format, seating profile, and premium capabilities so scheduling and seat maps can inherit the right presentation style later.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Room Name</label><input class="input" placeholder="Room 1 - Deluxe" value="${room.name || ''}"></div>
      <div class="field"><label>Cinema</label><select class="select">${buildOptions(['Galaxy', 'Premier', 'Landmark', 'Crescent', 'Royal'], cinema)}</select></div>
      <div class="field"><label>Room Type</label><select class="select">${buildOptions(['Standard 2D', 'Premium 3D', 'VIP Recliner', 'IMAX', '4DX', 'ScreenX', 'Dolby Atmos'], type)}</select></div>
      <div class="field"><label>Status</label><select class="select">${buildOptions(['Active', 'Maintenance'], status)}</select></div>
      <div class="field"><label>Capacity</label><input class="input" type="number" placeholder="0" value="${room.cap || ''}"></div>
      <div class="field"><label>Screen Size</label><input class="input" placeholder="12m HD" value="${room.screen || ''}"></div>
      <div class="field"><label>Projection</label><select class="select">${buildOptions(['Laser', 'Digital 4K', 'IMAX Dual', 'Motion Rig'], room.projection || 'Laser')}</select></div>
      <div class="field"><label>Sound Profile</label><select class="select">${buildOptions(['Stereo', 'Dolby 7.1', 'Dolby Atmos', 'Immersive 360'], room.sound || 'Dolby Atmos')}</select></div>
      <div class="field"><label>Cleaning Buffer</label><input class="input" placeholder="15 minutes" value="${room.buffer || '15 minutes'}"></div>
      <div class="field"><label>Seat Map Theme</label><select class="select">${buildOptions(['Classic', 'Recliner', 'Premium Mix', 'Event Mode'], room.map || 'Classic')}</select></div>
      <div class="field form-full"><label>Room Features</label>
        <div class="check-grid">
          ${['Wheelchair row', 'Laser projection', '3D glasses station', 'Couple seats', 'Recliner zone', 'Translated captions'].map(feature => `
            <label class="check-option">
              <input type="checkbox"${features.includes(feature) ? ' checked' : ''}>
              <span>${feature}</span>
            </label>`).join('')}
        </div>
      </div>
      <div class="field form-full"><label>Preview</label>
        <div class="preview-banner">
          <div class="preview-banner-title">${room.name || 'Room configuration preview'}</div>
          <div class="preview-banner-copy">${cinema} cinema · ${room.screen || 'Screen details pending'} · ${room.sound || 'Dolby Atmos'} sound profile</div>
          <div class="meta-pills">
            <span class="badge blue">${type}</span>
            <span class="badge ${status === 'Maintenance' ? 'orange' : 'green'}">${status}</span>
            <span class="badge gold">${room.cap || '--'} seats</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function openRoomModal(title, room = {}) {
  const isEdit = /^Edit/i.test(title);
  openModal(title, roomFormBody(room), {
    description: isEdit
      ? 'Adjust auditorium type, seat capacity, and premium presentation features for this room.'
      : 'Create a new room profile with the format, capacity, and operations settings your schedule will depend on.',
    note: 'UI preview only. Room data is not persisted yet.',
    submitLabel: isEdit ? 'Update Room' : 'Create Room',
    successMessage: isEdit ? 'Room preview updated!' : 'Room preview staged!',
  });
}

function handleCinemaSectionAction() {
  openRoomModal('Add Room');
}

function renderRooms(data) {
  const startItem = data.length === 0 ? 0 : 1;
  document.getElementById('roomsBody').innerHTML = data.map(room => `
    <tr>
      <td class="td-bold">${room.name}</td>
      <td class="td-muted">${room.cinema}</td>
      <td><span class="badge blue">${room.type}</span></td>
      <td style="font-weight:700;">${room.cap}</td>
      <td class="td-muted">${room.screen}</td>
      <td>${statusBadge(room.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="showToast('Viewing ${room.name}','info')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
        <button class="action-btn edit" title="Edit" onclick="openRoomModal('Edit Room', {name:'${room.name}',cinema:'${room.cinema}',type:'${room.type}',cap:'${room.cap}',screen:'${room.screen}',status:'${room.status}'})">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="action-btn gold" title="Seats" onclick="window.location.href='<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/seats'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13V7a2 2 0 012-2h10a2 2 0 012 2v6M5 13H3v5h18v-5h-2M5 13h14"/><path d="M8 21v-3M16 21v-3"/></svg>
        </button>
        <button class="action-btn del" title="Delete" onclick="showToast('${room.name} deleted','error')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
        </button>
      </div></td>
    </tr>`).join('');
  document.getElementById('roomsPagination').innerHTML = buildPagination(`Showing ${startItem}-${data.length} of ${data.length} rooms`, Math.max(1, Math.ceil(data.length / 10)));
}

function filterRooms(q) {
  const searchInput = document.getElementById('roomSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedCinema = document.getElementById('roomCinemaFilter')?.value || 'All Cinemas';
  const selectedType = document.getElementById('roomTypeFilter')?.value || 'All Types';
  const selectedStatus = document.getElementById('roomStatusFilter')?.value || 'All Status';
  const filtered = roomRecords.filter(room => {
    const matchesQuery = searchTerm === '' ||
      room.name.toLowerCase().includes(searchTerm) ||
      room.cinema.toLowerCase().includes(searchTerm) ||
      room.type.toLowerCase().includes(searchTerm);
    const matchesCinema = selectedCinema === 'All Cinemas' || room.cinema === selectedCinema;
    const matchesType = selectedType === 'All Types' || room.type === selectedType;
    const matchesStatus = selectedStatus === 'All Status' || room.status === selectedStatus;

    return matchesQuery && matchesCinema && matchesType && matchesStatus;
  });

  renderRooms(filtered);
  document.getElementById('roomCount').textContent = `${filtered.length} rooms`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterRooms();
});
</script>
