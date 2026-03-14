<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card red" style="padding:16px;">
    <div id="movieReviewTotalStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Total Reviews</div>
  </div>
  <div class="stat-card green" style="padding:16px;">
    <div id="movieReviewApprovedStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Approved</div>
  </div>
  <div class="stat-card orange" style="padding:16px;">
    <div id="movieReviewPendingStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Pending</div>
  </div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
    <div id="movieReviewHiddenStat" style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">0</div>
    <div class="stat-label">Hidden</div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="movieReviewSearchInput" type="text" placeholder="Search reviews..." oninput="filterMovieReviews()">
    </div>
    <select id="movieReviewStatusFilter" class="select-filter" onchange="filterMovieReviews()">
      <option value="">All Status</option>
      <option value="pending">Pending</option>
      <option value="approved">Approved</option>
      <option value="rejected">Rejected</option>
    </select>
    <select id="movieReviewVisibilityFilter" class="select-filter" onchange="filterMovieReviews()">
      <option value="">All Visibility</option>
      <option value="visible">Visible</option>
      <option value="hidden">Hidden</option>
    </select>
    <div class="toolbar-right">
      <span style="font-size:12px;color:var(--text-dim);" id="movieReviewCount">0 reviews</span>
      <button class="btn btn-ghost btn-sm" onclick="showToast('Review report exported','success')">Export</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>User</th><th>Movie</th><th>Rating</th><th>Comment</th><th>Status</th><th>Visibility</th><th>Submitted</th><th>Actions</th>
      </tr></thead>
      <tbody id="movieReviewsBody"></tbody>
    </table>
  </div>
  <div id="movieReviewsPagination"></div>
</div>

<script>
const movieReviewsData = [
  { id: 1, user: 'Nguyen An', movie: 'Avengers: Doomsday', rating: 5, comment: 'Great pacing and visuals from start to finish.', status: 'approved', is_visible: true, moderation_note: 'Approved by content team.', created_at: '2026-03-13' },
  { id: 2, user: 'Tran Mai', movie: 'The Last Breath', rating: 4, comment: 'Strong acting and a memorable final scene.', status: 'approved', is_visible: true, moderation_note: 'Public on detail page.', created_at: '2026-03-12' },
  { id: 3, user: 'Le Quang', movie: 'Funny Bones 3', rating: 4, comment: 'Looks promising from the early screening.', status: 'pending', is_visible: false, moderation_note: 'Pending manual review.', created_at: '2026-03-11' },
  { id: 4, user: 'Pham Linh', movie: 'Dragon Quest', rating: 5, comment: 'Beautiful animation and a lot of heart.', status: 'approved', is_visible: true, moderation_note: 'Featured review candidate.', created_at: '2026-03-10' },
  { id: 5, user: 'Bui Nam', movie: 'Neon City', rating: 2, comment: 'The plot was hard to follow.', status: 'rejected', is_visible: false, moderation_note: 'Rejected for spoiler-heavy content.', created_at: '2026-03-08' }
];

function movieReviewFormBody(review = {}) {
  const statuses = [
    { value: 'pending', label: 'Pending' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
  ];
  const visibilityOptions = [
    { value: '1', label: 'Visible' },
    { value: '0', label: 'Hidden' },
  ];

  return `<div class="form-grid">
    <div class="field"><label>Reviewer</label><input class="input" value="${review.user || ''}"></div>
    <div class="field"><label>Movie</label><input class="input" value="${review.movie || ''}"></div>
    <div class="field"><label>Rating</label><input class="input" type="number" min="1" max="5" value="${review.rating || ''}"></div>
    <div class="field"><label>Status</label><select class="select">${buildOptions(statuses, review.status || 'pending')}</select></div>
    <div class="field"><label>Visibility</label><select class="select">${buildOptions(visibilityOptions, review.is_visible ? '1' : '0')}</select></div>
    <div class="field"><label>Submitted At</label><input class="input" value="${review.created_at || ''}"></div>
    <div class="field form-full"><label>Customer Comment</label><textarea class="textarea" placeholder="Review text...">${review.comment || ''}</textarea></div>
    <div class="field form-full"><label>Moderation Note</label><textarea class="textarea" placeholder="Internal moderation note...">${review.moderation_note || ''}</textarea><div class="helper-text">This matches the movie_reviews moderation columns: status, is_visible, and moderation_note.</div></div>
  </div>`;
}

function openReviewModerationModal(review = {}) {
  openModal('Moderate Review', movieReviewFormBody(review), {
    description: 'Approve or reject reviews and control visibility with the same fields stored in movie_reviews.',
    note: 'Schema-aligned preview only. Moderation actions are not persisted yet.',
    submitLabel: 'Apply Moderation',
    successMessage: 'Review moderation preview updated!',
  });
}

function handleMovieSectionAction() {
  const pendingReview = movieReviewsData.find(review => review.status === 'pending') || movieReviewsData[0];
  openReviewModerationModal(pendingReview);
}

function updateMovieReviewStats(data = movieReviewsData) {
  document.getElementById('movieReviewTotalStat').textContent = String(data.length);
  document.getElementById('movieReviewApprovedStat').textContent = String(data.filter(review => review.status === 'approved').length);
  document.getElementById('movieReviewPendingStat').textContent = String(data.filter(review => review.status === 'pending').length);
  document.getElementById('movieReviewHiddenStat').textContent = String(data.filter(review => !review.is_visible).length);
}

function moderateReview(id) {
  const review = movieReviewsData.find(item => item.id === id);
  if (!review) return;
  openReviewModerationModal(review);
}

function renderMovieReviews(data) {
  document.getElementById('movieReviewsBody').innerHTML = data.map(review => `
    <tr>
      <td><div class="td-bold">${review.user}</div></td>
      <td class="td-muted">${review.movie}</td>
      <td>${stars(review.rating)}</td>
      <td class="td-muted">${review.comment}</td>
      <td>${statusBadge(review.status)}</td>
      <td><span class="badge ${review.is_visible ? 'green' : 'gray'}">${review.is_visible ? 'Visible' : 'Hidden'}</span></td>
      <td class="td-muted">${review.created_at}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="showToast('Viewing review detail','info')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
        <button class="action-btn edit" title="Moderate" onclick="moderateReview(${review.id})">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
        </button>
        <button class="action-btn del" title="Hide" onclick="showToast('Review hidden in preview','warning')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
        </button>
      </div></td>
    </tr>`).join('');

  document.getElementById('movieReviewCount').textContent = `${data.length} reviews`;
  document.getElementById('movieReviewsPagination').innerHTML = buildPagination(`Showing 1-${data.length} of ${movieReviewsData.length} reviews`, Math.max(1, Math.ceil(movieReviewsData.length / 10)));
}

function filterMovieReviews() {
  const search = document.getElementById('movieReviewSearchInput').value.trim().toLowerCase();
  const status = document.getElementById('movieReviewStatusFilter').value;
  const visibility = document.getElementById('movieReviewVisibilityFilter').value;

  const filtered = movieReviewsData.filter(review => {
    if (search) {
      const haystack = [review.user, review.movie, review.comment, review.moderation_note].join(' ').toLowerCase();
      if (!haystack.includes(search)) return false;
    }

    if (status && review.status !== status) return false;
    if (visibility === 'visible' && !review.is_visible) return false;
    if (visibility === 'hidden' && review.is_visible) return false;

    return true;
  });

  renderMovieReviews(filtered);
  updateMovieReviewStats(filtered);
}

document.addEventListener('DOMContentLoaded', function () {
  renderMovieReviews(movieReviewsData);
  updateMovieReviewStats(movieReviewsData);
});
</script>
