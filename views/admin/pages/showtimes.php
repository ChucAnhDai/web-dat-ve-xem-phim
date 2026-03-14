<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">&gt;</span><span>Showtimes</span></div>
      <h1 class="page-title">Showtime Management</h1>
      <p class="page-sub">Schedule and manage movie showtimes</p>
    </div>
    <button class="btn btn-primary" onclick="openShowtimeModal('Add Showtime')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Showtime
    </button>
  </div>

  <div class="grid-main-side">
    <div style="display:flex;flex-direction:column;gap:20px;">
      <div class="card">
        <div class="card-header">
          <div class="card-title">March 2026</div>
          <div style="display:flex;gap:8px;">
            <button class="btn btn-ghost btn-sm" onclick="showToast('Previous month','info')">Prev</button>
            <button class="btn btn-ghost btn-sm" onclick="showToast('Next month','info')">Next</button>
          </div>
        </div>
        <div class="card-body">
          <div class="cal-grid" id="calGrid"></div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div class="card-title">All Showtimes</div>
          <div class="toolbar-search" style="margin:0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" placeholder="Filter showtimes...">
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Movie</th><th>Cinema</th><th>Room</th><th>Date</th><th>Time</th><th>Price</th><th>Booked</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody id="showtimesBody"></tbody>
          </table>
        </div>
        <div id="showPagination"></div>
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px;">
      <div class="card">
        <div class="card-header"><div class="card-title">Today's Summary</div></div>
        <div class="card-body">
          <div style="display:flex;flex-direction:column;gap:14px;">
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:13px;color:var(--text-muted);">Total Showtimes</span>
              <span style="font-weight:700;font-size:20px;">42</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:13px;color:var(--text-muted);">Seats Available</span>
              <span style="font-weight:700;font-size:20px;color:var(--green);">2,140</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
              <span style="font-size:13px;color:var(--text-muted);">Seats Booked</span>
              <span style="font-weight:700;font-size:20px;color:var(--red);">1,294</span>
            </div>
            <div>
              <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                <span style="font-size:13px;color:var(--text-muted);">Occupancy</span>
                <span style="font-weight:700;color:var(--gold);">60.7%</span>
              </div>
              <div class="progress-bar"><div class="progress-fill" style="width:60.7%;background:var(--gold);"></div></div>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Most Booked Today</div></div>
        <div class="card-body no-pad" id="mostBooked"></div>
      </div>

      <div class="card">
        <div class="card-header"><div class="card-title">Quick Add</div></div>
        <div class="card-body">
          <div style="display:flex;flex-direction:column;gap:12px;">
            <div class="field"><label>Movie</label><select class="select"><option>Avengers: Doomsday</option><option>Cosmic Voyage II</option><option>The Last Breath</option></select></div>
            <div class="field"><label>Cinema</label><select class="select"><option>CineShop Galaxy</option><option>CineShop Premier</option></select></div>
            <div class="field"><label>Room</label><select class="select"><option>Room 1</option><option>Room 2</option><option>Room 3</option></select></div>
            <div class="field"><label>Date</label><input class="input" type="date" value="2026-03-12"></div>
            <div class="field"><label>Start Time</label><input class="input" type="time" value="19:00"></div>
            <div class="field"><label>Ticket Price ($)</label><input class="input" type="number" value="7.00" step="0.50"></div>
            <button class="btn btn-primary" style="width:100%;" onclick="showToast('Showtime added!','success')">Add Showtime</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const showtimesData = [
  {movie:'Avengers: Doomsday',cinema:'Galaxy',room:'Room 1',date:'Mar 12',time:'14:30',price:'$7.00',booked:148,cap:180,status:'Active',format:'2D'},
  {movie:'Cosmic Voyage II',cinema:'Galaxy',room:'Room 3',date:'Mar 12',time:'15:00',price:'$7.00',booked:82,cap:160,status:'Active',format:'IMAX'},
  {movie:'The Last Breath',cinema:'Premier',room:'Room 2',date:'Mar 12',time:'16:15',price:'$6.50',booked:65,cap:150,status:'Active',format:'2D'},
  {movie:'Haunted Echoes',cinema:'Galaxy',room:'Room 4',date:'Mar 12',time:'17:30',price:'$6.50',booked:110,cap:160,status:'Active',format:'3D'},
  {movie:'Avengers: Doomsday',cinema:'Landmark',room:'Room 1',date:'Mar 12',time:'19:00',price:'$8.00',booked:180,cap:180,status:'Sold Out',format:'IMAX'},
  {movie:'Dragon Quest',cinema:'Galaxy',room:'Room 2',date:'Mar 15',time:'13:00',price:'$7.00',booked:0,cap:80,status:'Coming Soon',format:'4DX'},
  {movie:'Funny Bones 3',cinema:'Premier',room:'Room 1',date:'Mar 20',time:'10:00',price:'$6.00',booked:0,cap:120,status:'Coming Soon',format:'2D'},
];

function normalizeShowtimeTitle(title = '') {
  return title.trim();
}

function showtimeDateToISO(label = '') {
  const match = label.match(/([A-Za-z]{3})\s+(\d{1,2})/);
  if (!match) return '';

  const months = {
    Jan: '01', Feb: '02', Mar: '03', Apr: '04', May: '05', Jun: '06',
    Jul: '07', Aug: '08', Sep: '09', Oct: '10', Nov: '11', Dec: '12',
  };

  return `2026-${months[match[1]] || '01'}-${String(match[2]).padStart(2, '0')}`;
}

function showtimeFormBody(showtime = {}) {
  const movie = showtime.movie || 'Avengers: Doomsday';
  const cinema = showtime.cinema || 'Galaxy';
  const room = showtime.room || 'Room 1';
  const status = showtime.status || 'Scheduled';

  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Screening Plan</div>
      <div class="surface-card-copy">Preview how each showtime fits into the programming calendar with room, pricing, and seat availability context before schedule persistence exists.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Movie</label><select class="select">${buildOptions(['Avengers: Doomsday', 'Cosmic Voyage II', 'The Last Breath', 'Haunted Echoes', 'Dragon Quest', 'Funny Bones 3'], movie)}</select></div>
      <div class="field"><label>Cinema</label><select class="select">${buildOptions(['Galaxy', 'Premier', 'Landmark'], cinema)}</select></div>
      <div class="field"><label>Room</label><select class="select">${buildOptions(['Room 1', 'Room 2', 'Room 3', 'Room 4'], room)}</select></div>
      <div class="field"><label>Status</label><select class="select">${buildOptions(['Scheduled', 'Active', 'Sold Out', 'Coming Soon'], status)}</select></div>
      <div class="field"><label>Date</label><input class="input" type="date" value="${showtime.date || ''}"></div>
      <div class="field"><label>Start Time</label><input class="input" type="time" value="${showtime.start || ''}"></div>
      <div class="field"><label>End Time</label><input class="input" type="time" value="${showtime.end || ''}"></div>
      <div class="field"><label>Ticket Price ($)</label><input class="input" type="number" placeholder="7.00" step="0.50" value="${showtime.price || ''}"></div>
      <div class="field"><label>Format</label><select class="select">${buildOptions(['2D', '3D', 'IMAX', '4DX'], showtime.format || '2D')}</select></div>
      <div class="field"><label>Language</label><select class="select">${buildOptions(['Original', 'Dubbed', 'Subtitled'], showtime.language || 'Original')}</select></div>
      <div class="field"><label>Sales Window</label><input class="input" placeholder="Open 48h before show" value="${showtime.sales || 'Open 48h before show'}"></div>
      <div class="field form-full"><label>Programming Note</label><textarea class="textarea" placeholder="Short operational note for this screening...">${showtime.note || ''}</textarea></div>
      <div class="field form-full"><label>Preview</label>
        <div class="preview-banner">
          <div class="preview-banner-title">${movie}</div>
          <div class="preview-banner-copy">${cinema} · ${room} · ${showtime.dateLabel || showtime.date || 'Pick a date'} at ${showtime.start || '--:--'} with ${showtime.price || '--'} USD ticket pricing.</div>
          <div class="meta-pills">
            <span class="badge blue">${status}</span>
            <span class="badge gold">${showtime.booked || 0}/${showtime.cap || '--'} seats</span>
            <span class="badge gray">${showtime.format || '2D'}</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function openShowtimeModal(title, showtime = {}) {
  const isEdit = /^Edit/i.test(title);
  openModal(title, showtimeFormBody(showtime), {
    description: isEdit
      ? 'Adjust room assignment, ticket pricing, and screening status for this scheduled showtime.'
      : 'Create a new screening slot with date, room, pricing, and availability context.',
    note: 'UI preview only. Showtime data is not persisted yet.',
    submitLabel: isEdit ? 'Update Showtime' : 'Create Showtime',
    successMessage: isEdit ? 'Showtime preview updated!' : 'Showtime preview staged!',
  });
}

document.addEventListener('DOMContentLoaded', function () {
  const calEl = document.getElementById('calGrid');
  const days = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
  const events = [3, 7, 8, 9, 12, 14, 15, 16, 19, 21, 22, 25, 26, 28];
  let html = days.map(day => `<div class="cal-day-header">${day}</div>`).join('');

  for (let i = 1; i <= 31; i++) {
    const hasEvent = events.includes(i);
    html += `<div class="cal-day ${i === 12 ? 'today' : ''} ${hasEvent ? 'has-event' : ''}" onclick="showToast('Showtimes for Mar ${i}','info')">${i}</div>`;
  }

  calEl.innerHTML = html;

  document.getElementById('showtimesBody').innerHTML = showtimesData.map(showtime => {
    const pct = Math.round((showtime.booked / showtime.cap) * 100);
    const movieTitle = normalizeShowtimeTitle(showtime.movie);

    return `<tr>
      <td class="td-bold">${showtime.movie}</td>
      <td class="td-muted">${showtime.cinema}</td>
      <td class="td-muted">${showtime.room}</td>
      <td>${showtime.date}</td>
      <td style="font-weight:700;">${showtime.time}</td>
      <td style="color:var(--gold);font-weight:700;">${showtime.price}</td>
      <td>
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px;">${showtime.booked}/${showtime.cap}</div>
        <div class="progress-bar" style="width:80px;margin:0;"><div class="progress-fill" style="width:${pct}%;background:${pct >= 90 ? 'var(--red)' : pct >= 60 ? 'var(--gold)' : 'var(--green)'};"></div></div>
      </td>
      <td>${statusBadge(showtime.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn edit" onclick="openShowtimeModal('Edit Showtime', {movie:'${movieTitle}',cinema:'${showtime.cinema}',room:'${showtime.room}',date:'${showtimeDateToISO(showtime.date)}',dateLabel:'${showtime.date}',start:'${showtime.time}',price:'${showtime.price.replace('$', '')}',status:'${showtime.status}',booked:'${showtime.booked}',cap:'${showtime.cap}',format:'${showtime.format}'})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" onclick="showToast('Showtime deleted','error')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`;
  }).join('');

  document.getElementById('showPagination').innerHTML = buildPagination(`Showing 1-${showtimesData.length} of ${showtimesData.length} showtimes`);

  const mostBooked = [
    {title:'Avengers: Doomsday',time:'19:00',pct:100},
    {title:'Cosmic Voyage II',time:'17:30',pct:78},
    {title:'The Last Breath',time:'15:00',pct:62},
  ];

  document.getElementById('mostBooked').innerHTML = mostBooked.map(item => `
    <div style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.03);">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
        <span style="font-size:12px;font-weight:600;flex:1;">${item.title}</span>
        <span style="font-size:11px;color:var(--text-muted);">${item.time}</span>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <div class="progress-bar" style="flex:1;"><div class="progress-fill" style="width:${item.pct}%;background:var(--red);"></div></div>
        <span style="font-size:11px;font-weight:700;color:var(--red);width:30px;text-align:right;">${item.pct}%</span>
      </div>
    </div>`).join('');
});
</script>
