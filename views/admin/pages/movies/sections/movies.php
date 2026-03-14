<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card red" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">248</div>
    <div class="stat-label">Total Movies</div>
  </div>
  <div class="stat-card green" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">84</div>
    <div class="stat-label">Now Showing</div>
  </div>
  <div class="stat-card blue" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">32</div>
    <div class="stat-label">Coming Soon</div>
  </div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">132</div>
    <div class="stat-label">Ended</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input type="text" placeholder="Search movies..." oninput="filterMovies(this.value)">
    </div>
    <select class="select-filter" onchange="filterMovies()">
      <option>All Categories</option>
      <option>Action</option><option>Drama</option><option>Comedy</option>
      <option>Horror</option><option>Sci-Fi</option><option>Animation</option><option>Romance</option><option>Thriller</option>
    </select>
    <select class="select-filter" onchange="filterMovies()">
      <option>All Status</option>
      <option>Now Showing</option><option>Coming Soon</option><option>Ended</option>
    </select>
    <div class="toolbar-right">
      <span style="font-size:12px;color:var(--text-dim);" id="movieCount">248 movies</span>
      <button class="btn btn-ghost btn-sm" onclick="showToast('Exported to CSV','success')">Export</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Poster</th><th>Movie Title</th><th>Category</th><th>Duration</th>
        <th>Release Date</th><th>Rating</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody id="moviesBody"></tbody>
    </table>
  </div>
  <div id="moviesPagination"></div>
</div>

<script>
const moviesData = [
  {title:'Avengers: Doomsday',cat:'Action',dur:'150 min',release:'2026-03-01',rating:4.8,status:'Now Showing',thumb:'AD'},
  {title:'The Last Breath',cat:'Drama',dur:'128 min',release:'2026-02-14',rating:4.5,status:'Now Showing',thumb:'LB'},
  {title:'Cosmic Voyage II',cat:'Sci-Fi',dur:'142 min',release:'2026-03-10',rating:4.7,status:'Now Showing',thumb:'CV'},
  {title:'Haunted Echoes',cat:'Horror',dur:'108 min',release:'2026-01-28',rating:3.9,status:'Now Showing',thumb:'HE'},
  {title:'Funny Bones 3',cat:'Comedy',dur:'95 min',release:'2026-03-20',rating:4.2,status:'Coming Soon',thumb:'FB'},
  {title:'Dragon Quest',cat:'Animation',dur:'112 min',release:'2026-04-01',rating:4.6,status:'Coming Soon',thumb:'DQ'},
  {title:'The Heist',cat:'Thriller',dur:'132 min',release:'2025-12-15',rating:4.4,status:'Ended',thumb:'TH'},
  {title:'Neon City',cat:'Sci-Fi',dur:'138 min',release:'2026-04-15',rating:0,status:'Coming Soon',thumb:'NC'},
  {title:'Love In Tokyo',cat:'Romance',dur:'104 min',release:'2025-11-01',rating:4.1,status:'Ended',thumb:'LT'},
  {title:'Iron Circuit',cat:'Action',dur:'145 min',release:'2026-05-01',rating:0,status:'Coming Soon',thumb:'IC'},
  {title:'Shadow Protocol',cat:'Thriller',dur:'125 min',release:'2026-02-28',rating:4.3,status:'Now Showing',thumb:'SP'},
  {title:'Ocean Drift',cat:'Drama',dur:'118 min',release:'2025-10-10',rating:4.0,status:'Ended',thumb:'OD'},
];

function handleMovieSectionAction() {
  openModal('Add New Movie', movieFormBody());
}

function renderMovies(data) {
  document.getElementById('moviesBody').innerHTML = data.map(m => `
    <tr>
      <td><div class="poster-img-placeholder">${m.thumb}</div></td>
      <td><div class="td-bold">${m.title}</div></td>
      <td><span class="badge gray">${m.cat}</span></td>
      <td class="td-muted">${m.dur}</td>
      <td class="td-muted">${m.release}</td>
      <td>${stars(m.rating)}</td>
      <td>${statusBadge(m.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="showToast('Viewing ${m.title}','info')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
        <button class="action-btn edit" title="Edit" onclick="openModal('Edit Movie', movieFormBody({title:'${m.title}',cat:'${m.cat}',dur:'${m.dur}',release:'${m.release}',rating:${m.rating}}))">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        </button>
        <button class="action-btn del" title="Delete" onclick="showToast('${m.title} deleted','error')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
        </button>
        <button class="action-btn gold" title="Add Showtime" onclick="window.location.href='<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/showtimes'">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        </button>
      </div></td>
    </tr>`).join('');
  document.getElementById('moviesPagination').innerHTML = buildPagination(`Showing 1-${data.length} of ${data.length} movies`, Math.ceil(data.length / 10));
}

function filterMovies(q = '') {
  const filtered = moviesData.filter(m =>
    m.title.toLowerCase().includes(q.toLowerCase()) ||
    m.cat.toLowerCase().includes(q.toLowerCase())
  );
  renderMovies(filtered);
  document.getElementById('movieCount').textContent = `${filtered.length} movies`;
}

document.addEventListener('DOMContentLoaded', function () {
  renderMovies(moviesData);
});
</script>
