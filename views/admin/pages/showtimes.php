<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">›</span><span>Showtimes</span></div>
      <h1 class="page-title">Showtime Management</h1>
      <p class="page-sub">Schedule and manage movie showtimes</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('Add Showtime', showtimeFormBody())">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      Add Showtime
    </button>
  </div>

  <div class="grid-main-side">
    <div style="display:flex;flex-direction:column;gap:20px;">
      <div class="card">
        <div class="card-header">
          <div class="card-title">📅 March 2026</div>
          <div style="display:flex;gap:8px;">
            <button class="btn btn-ghost btn-sm" onclick="showToast('Previous month','info')">‹ Prev</button>
            <button class="btn btn-ghost btn-sm" onclick="showToast('Next month','info')">Next ›</button>
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
  {movie:'🦸 Avengers: Doomsday',cinema:'Galaxy',room:'Room 1',date:'Mar 12',time:'14:30',price:'$7.00',booked:148,cap:180,status:'Active'},
  {movie:'🚀 Cosmic Voyage II',cinema:'Galaxy',room:'Room 3',date:'Mar 12',time:'15:00',price:'$7.00',booked:82,cap:160,status:'Active'},
  {movie:'🎭 The Last Breath',cinema:'Premier',room:'Room 2',date:'Mar 12',time:'16:15',price:'$6.50',booked:65,cap:150,status:'Active'},
  {movie:'👻 Haunted Echoes',cinema:'Galaxy',room:'Room 4',date:'Mar 12',time:'17:30',price:'$6.50',booked:110,cap:160,status:'Active'},
  {movie:'🦸 Avengers: Doomsday',cinema:'Landmark',room:'Room 1',date:'Mar 12',time:'19:00',price:'$8.00',booked:180,cap:180,status:'Sold Out'},
  {movie:'🐉 Dragon Quest',cinema:'Galaxy',room:'Room 2',date:'Mar 15',time:'13:00',price:'$7.00',booked:0,cap:80,status:'Coming Soon'},
  {movie:'😂 Funny Bones 3',cinema:'Premier',room:'Room 1',date:'Mar 20',time:'10:00',price:'$6.00',booked:0,cap:120,status:'Coming Soon'},
];

function showtimeFormBody() {
  return `<div class="form-grid">
    <div class="field"><label>Movie</label><select class="select"><option>Avengers: Doomsday</option><option>Cosmic Voyage II</option><option>The Last Breath</option></select></div>
    <div class="field"><label>Cinema</label><select class="select"><option>CineShop Galaxy</option><option>CineShop Premier</option></select></div>
    <div class="field"><label>Room</label><select class="select"><option>Room 1</option><option>Room 2</option><option>Room 3</option></select></div>
    <div class="field"><label>Date</label><input class="input" type="date"></div>
    <div class="field"><label>Start Time</label><input class="input" type="time"></div>
    <div class="field"><label>End Time</label><input class="input" type="time"></div>
    <div class="field"><label>Ticket Price ($)</label><input class="input" type="number" placeholder="7.00" step="0.50"></div>
    <div class="field"><label>Language</label><select class="select"><option>Original</option><option>Dubbed</option><option>Subtitled</option></select></div>
  </div>`;
}

document.addEventListener('DOMContentLoaded', function(){
  const calEl = document.getElementById('calGrid');
  const days = ['SUN','MON','TUE','WED','THU','FRI','SAT'];
  const events = [3,7,8,9,12,14,15,16,19,21,22,25,26,28];
  let html = days.map(d=>`<div class="cal-day-header">${d}</div>`).join('');
  for(let i=1;i<=31;i++){
    const hasEv = events.includes(i);
    html += `<div class="cal-day ${i===12?'today':''} ${hasEv?'has-event':''}" onclick="showToast('Showtimes for Mar ${i}','info')">${i}</div>`;
  }
  calEl.innerHTML = html;

  document.getElementById('showtimesBody').innerHTML = showtimesData.map(s=>{
    const pct = Math.round((s.booked/s.cap)*100);
    return `<tr>
      <td class="td-bold">${s.movie}</td>
      <td class="td-muted">${s.cinema}</td>
      <td class="td-muted">${s.room}</td>
      <td>${s.date}</td>
      <td style="font-weight:700;">${s.time}</td>
      <td style="color:var(--gold);font-weight:700;">${s.price}</td>
      <td>
        <div style="font-size:11px;color:var(--text-muted);margin-bottom:3px;">${s.booked}/${s.cap}</div>
        <div class="progress-bar" style="width:80px;margin:0;"><div class="progress-fill" style="width:${pct}%;background:${pct>=90?'var(--red)':pct>=60?'var(--gold)':'var(--green)'};"></div></div>
      </td>
      <td>${statusBadge(s.status==='Sold Out'?'Cancelled':s.status==='Coming Soon'?'Coming Soon':'Active')}</td>
      <td><div class="actions-row">
        <button class="action-btn edit" onclick="openModal('Edit Showtime', showtimeFormBody())"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" onclick="showToast('Showtime deleted','error')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`;
  }).join('');
  document.getElementById('showPagination').innerHTML = buildPagination(`Showing 1–${showtimesData.length} of ${showtimesData.length} showtimes`);

  const mb = [{e:'🦸',title:'Avengers: Doomsday',time:'19:00',pct:100},{e:'🚀',title:'Cosmic Voyage II',time:'17:30',pct:78},{e:'🎭',title:'The Last Breath',time:'15:00',pct:62}];
  document.getElementById('mostBooked').innerHTML = mb.map(d=>`
    <div style="padding:12px 16px;border-bottom:1px solid rgba(255,255,255,0.03);">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
        <span style="font-size:16px;">${d.e}</span>
        <span style="font-size:12px;font-weight:600;flex:1;">${d.title}</span>
        <span style="font-size:11px;color:var(--text-muted);">${d.time}</span>
      </div>
      <div style="display:flex;align-items:center;gap:8px;">
        <div class="progress-bar" style="flex:1;"><div class="progress-fill" style="width:${d.pct}%;background:var(--red);"></div></div>
        <span style="font-size:11px;font-weight:700;color:var(--red);width:30px;text-align:right;">${d.pct}%</span>
      </div>
    </div>`).join('');
});
</script>
