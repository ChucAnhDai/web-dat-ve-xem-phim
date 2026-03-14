<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card red" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">1,284</div>
    <div class="stat-label">Published Reviews</div>
  </div>
  <div class="stat-card gold" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">4.4</div>
    <div class="stat-label">Average Rating</div>
  </div>
  <div class="stat-card orange" style="padding:16px;">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">17</div>
    <div class="stat-label">Pending Moderation</div>
  </div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
    <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">9</div>
    <div class="stat-label">Hidden Reviews</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input type="text" placeholder="Search reviews..." oninput="filterMovieReviews(this.value)">
    </div>
    <select class="select-filter" onchange="filterMovieReviews()">
      <option>All Status</option>
      <option>Confirmed</option>
      <option>Pending</option>
      <option>Cancelled</option>
    </select>
    <div class="toolbar-right">
      <span style="font-size:12px;color:var(--text-dim);" id="movieReviewCount">8 reviews</span>
      <button class="btn btn-ghost btn-sm" onclick="showToast('Review report exported','success')">Export</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>User</th><th>Movie</th><th>Rating</th><th>Comment</th><th>Status</th><th>Submitted</th><th>Actions</th>
      </tr></thead>
      <tbody id="movieReviewsBody"></tbody>
    </table>
  </div>
  <div id="movieReviewsPagination"></div>
</div>

<script>
const movieReviewsData = [
  {user:'Nguyen An',movie:'Avengers: Doomsday',rating:5,comment:'Great pacing and visuals from start to finish.',status:'Confirmed',submitted:'2026-03-13'},
  {user:'Tran Mai',movie:'The Last Breath',rating:4,comment:'Strong acting and a memorable final scene.',status:'Confirmed',submitted:'2026-03-12'},
  {user:'Le Quang',movie:'Cosmic Voyage II',rating:5,comment:'Huge scale and excellent sound design.',status:'Pending',submitted:'2026-03-12'},
  {user:'Pham Linh',movie:'Haunted Echoes',rating:3,comment:'Good atmosphere but a little slow in the middle.',status:'Confirmed',submitted:'2026-03-11'},
  {user:'Hoang Vy',movie:'Funny Bones 3',rating:4,comment:'Crowd pleasing and funny enough for families.',status:'Pending',submitted:'2026-03-10'},
  {user:'Vu Khanh',movie:'Dragon Quest',rating:5,comment:'Beautiful animation and a lot of heart.',status:'Confirmed',submitted:'2026-03-09'},
  {user:'Bui Nam',movie:'Neon City',rating:2,comment:'The plot was hard to follow.',status:'Cancelled',submitted:'2026-03-08'},
  {user:'Dao Nhi',movie:'Iron Circuit',rating:4,comment:'Promising teaser and strong early buzz.',status:'Pending',submitted:'2026-03-07'},
];

function handleMovieSectionAction() {
  showToast('Opening moderation queue','info');
}

function renderMovieReviews(data) {
  document.getElementById('movieReviewsBody').innerHTML = data.map(r => `
    <tr>
      <td><div class="td-bold">${r.user}</div></td>
      <td class="td-muted">${r.movie}</td>
      <td>${stars(r.rating)}</td>
      <td class="td-muted">${r.comment}</td>
      <td>${statusBadge(r.status)}</td>
      <td class="td-muted">${r.submitted}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="showToast('Viewing review from ${r.user}','info')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
        <button class="action-btn edit" title="Approve" onclick="showToast('Review approved','success')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
        </button>
        <button class="action-btn del" title="Hide" onclick="showToast('Review hidden','warning')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
        </button>
      </div></td>
    </tr>`).join('');
  document.getElementById('movieReviewsPagination').innerHTML = buildPagination(`Showing 1-${data.length} of ${data.length} reviews`, Math.ceil(data.length / 10));
}

function filterMovieReviews(q = '') {
  const filtered = movieReviewsData.filter(r =>
    r.user.toLowerCase().includes(q.toLowerCase()) ||
    r.movie.toLowerCase().includes(q.toLowerCase()) ||
    r.comment.toLowerCase().includes(q.toLowerCase())
  );
  renderMovieReviews(filtered);
  document.getElementById('movieReviewCount').textContent = `${filtered.length} reviews`;
}

document.addEventListener('DOMContentLoaded', function () {
  renderMovieReviews(movieReviewsData);
});
</script>
