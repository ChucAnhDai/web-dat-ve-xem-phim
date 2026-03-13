<div class="page-header">
  <h1 class="page-title">My Tickets</h1>
  <p class="page-subtitle">Your upcoming and past movie bookings</p>
</div>

<div class="filter-bar">
  <div class="filter-chip active" data-filter="all">All</div>
  <div class="filter-chip" data-filter="confirmed">Upcoming</div>
  <div class="filter-chip" data-filter="cancelled">Cancelled</div>
</div>

<div class="tickets-grid" id="ticketsGrid"></div>

<script>
  // Mock data for simulation
  const TICKETS = [
    {movie:'Dune: Part Two',hall:'Hall 1 — IMAX',date:'Mon Mar 11',time:'4:45 PM',seats:['E5','E6'],status:'confirmed',poster:'https://image.tmdb.org/t/p/w200/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg'},
    {movie:'Inside Out 2',hall:'Hall 3 — Standard',date:'Fri Mar 8',time:'7:30 PM',seats:['D3'],status:'confirmed',poster:'https://image.tmdb.org/t/p/w200/vpnVM9B6NMmQpWeZvzLvDESb2QY.jpg'},
    {movie:'Oppenheimer',hall:'Hall 5 — 4DX',date:'Sat Mar 2',time:'2:00 PM',seats:['G4','G5','G6'],status:'confirmed',poster:'https://image.tmdb.org/t/p/w200/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg'},
    {movie:'Deadpool & Wolverine',hall:'Hall 1 — IMAX',date:'Wed Feb 28',time:'9:00 PM',seats:['B7'],status:'cancelled',poster:'https://image.tmdb.org/t/p/w200/8cdWjvZQUExUUTzyp4t6EDMubfO.jpg'},
    {movie:'The Substance',hall:'Hall 4 — Dolby',date:'Sat Feb 24',time:'6:00 PM',seats:['H1','H2'],status:'confirmed',poster:'https://image.tmdb.org/t/p/w200/lqoMzCcZYEFK729d6qzt349fB4o.jpg'},
  ];

  let currentFilter = 'all';

  function renderTickets() {
    const list = currentFilter === 'all' ? TICKETS : TICKETS.filter(t=>t.status===currentFilter);
    const grid = document.getElementById('ticketsGrid');
    if (!grid) return;
    
    if (!list.length) { 
        grid.innerHTML='<p style="color:var(--text2);padding:40px 0;text-align:center;grid-column:1/-1">No tickets found.</p>'; 
        return; 
    }
    
    grid.innerHTML = list.map(t=>`
      <div class="ticket-card">
        <span class="ticket-status-badge status-${t.status}">${t.status}</span>
        <div class="ticket-top">
          <div class="ticket-poster"><img src="${t.poster}" alt="" onerror="this.parentNode.style.background='var(--bg4)'"></div>
          <div>
            <div class="ticket-movie">${t.movie}</div>
            <div class="ticket-meta">
              <p>🎭 ${t.hall}</p>
              <p>📅 ${t.date}</p>
              <p>⏰ ${t.time}</p>
            </div>
          </div>
        </div>
        <div class="ticket-bottom">
          <div>
            <div style="font-size:10px;color:var(--text3);text-transform:uppercase;letter-spacing:0.5px;margin-bottom:4px">Seats</div>
            <div class="ticket-seats">${t.seats.map(s=>`<span class="ticket-seat-tag">${s}</span>`).join('')}</div>
          </div>
          <div class="qr-code">⬛</div>
        </div>
      </div>`).join('');
  }

  // Initialize event listeners for filters
  document.querySelectorAll('[data-filter]').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('[data-filter]').forEach(c=>c.classList.remove('active'));
      chip.classList.add('active');
      currentFilter = chip.dataset.filter;
      renderTickets();
    });
  });

  // Render on load
  document.addEventListener('DOMContentLoaded', renderTickets);
  // Also call immediately if DOM already loaded (for SPA navigation)
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    renderTickets();
  }
</script>
