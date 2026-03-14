<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div><div class="card-title">Seat Allocation Queue</div><div class="card-sub">Upcoming screenings that need review</div></div>
  </div>
  <div class="card-body" id="ticketQueue"></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="ticketDetailSearch" type="text" placeholder="Search ticket details..." oninput="filterTicketDetails(this.value)">
    </div>
    <select id="ticketDetailStatus" class="select-filter" onchange="filterTicketDetails()">
      <option>All Status</option><option>Completed</option><option>Pending</option><option>Cancelled</option><option>Refunded</option>
    </select>
    <div class="toolbar-right">
      <span id="ticketDetailCount" style="font-size:12px;color:var(--text-dim);">6 rows</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Order</th><th>Movie</th><th>Room</th><th>Showtime</th><th>Seats</th><th>Customer</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody id="ticketDetailsBody"></tbody>
    </table>
  </div>
</div>

<script>
const ticketDetailsData = [
  {order:'#TK-2031',movie:'Avengers: Doomsday',room:'Room 1',showtime:'2026-03-12 14:30',seats:'C3, C4',customer:'Nguyen Van A',status:'Completed'},
  {order:'#TK-2030',movie:'Cosmic Voyage II',room:'Room 3',showtime:'2026-03-12 15:00',seats:'F7, F8, F9, F10',customer:'Tran Thi B',status:'Completed'},
  {order:'#TK-2029',movie:'The Last Breath',room:'Room 2',showtime:'2026-03-12 16:15',seats:'D6',customer:'Hoang Minh C',status:'Pending'},
  {order:'#TK-2028',movie:'Haunted Echoes',room:'Room 4',showtime:'2026-03-12 17:30',seats:'A3, A4, A5',customer:'Pham Duc Tuan',status:'Completed'},
  {order:'#TK-2026',movie:'Cosmic Voyage II',room:'Room 1',showtime:'2026-03-11 20:00',seats:'B3, B4',customer:'Do Thi D',status:'Refunded'},
  {order:'#TK-2020',movie:'The Heist',room:'Room 2',showtime:'2026-03-08 19:30',seats:'E1, E2',customer:'Le Thi J',status:'Cancelled'},
];

function handleTicketSectionAction() {
  const firstRow = ticketDetailsData[0];
  openModal('Ticket Detail', `<div class="form-grid"><div class="field"><label>Order</label><input class="input" value="${firstRow.order}" readonly></div><div class="field"><label>Seats</label><input class="input" value="${firstRow.seats}" readonly></div><div class="field form-full"><label>Movie</label><input class="input" value="${firstRow.movie}" readonly></div></div>`);
}

function renderTicketQueue() {
  const queueItems = [
    {order:'#TK-2029',label:'Pending payment confirmation',status:'Pending'},
    {order:'#TK-2026',label:'Refund under review',status:'Refunded'},
    {order:'#TK-2020',label:'Cancelled booking audit',status:'Cancelled'},
  ];
  document.getElementById('ticketQueue').innerHTML = queueItems.map(item => `
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
      <div><div class="td-bold">${item.order}</div><div class="td-muted">${item.label}</div></div>
      <div>${statusBadge(item.status)}</div>
    </div>`).join('');
}

function renderTicketDetails(data) {
  document.getElementById('ticketDetailsBody').innerHTML = data.map(row => `
    <tr>
      <td class="td-id">${row.order}</td>
      <td class="td-bold">${row.movie}</td>
      <td class="td-muted">${row.room}</td>
      <td class="td-muted">${row.showtime}</td>
      <td class="td-mono">${row.seats}</td>
      <td class="td-muted">${row.customer}</td>
      <td>${statusBadge(row.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="showToast('Viewing ${row.order}','info')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
      </div></td>
    </tr>`).join('');
}

function filterTicketDetails(q) {
  const searchInput = document.getElementById('ticketDetailSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedStatus = document.getElementById('ticketDetailStatus')?.value || 'All Status';
  const filtered = ticketDetailsData.filter(row => {
    const matchesQuery = searchTerm === '' || row.order.toLowerCase().includes(searchTerm) || row.movie.toLowerCase().includes(searchTerm) || row.customer.toLowerCase().includes(searchTerm);
    const matchesStatus = selectedStatus === 'All Status' || row.status === selectedStatus;
    return matchesQuery && matchesStatus;
  });

  renderTicketDetails(filtered);
  document.getElementById('ticketDetailCount').textContent = `${filtered.length} rows`;
}

document.addEventListener('DOMContentLoaded', function () {
  renderTicketQueue();
  filterTicketDetails();
});
</script>
