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

function reviewWorkflowOption(title, copy, checked = true) {
  return `<label class="check-option">
    <input type="checkbox"${checked ? ' checked' : ''}>
    <span><strong>${title}</strong><small>${copy}</small></span>
  </label>`;
}

function reviewQueueFormBody() {
  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Queue Rules</div>
      <div class="surface-card-copy">Set the moderation defaults that determine how new reviews are triaged before they appear on the customer-facing movie pages.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Default Queue</label><select class="select">${buildOptions(['Newest First', 'Priority Flags First', 'Pending Only'], 'Pending Only')}</select></div>
      <div class="field"><label>Auto-Hold Threshold</label><select class="select">${buildOptions(['Rating <= 2 stars', 'Contains flagged keywords', 'Manual review only'], 'Contains flagged keywords')}</select></div>
      <div class="field"><label>Escalation Owner</label><input class="input" placeholder="Content moderation team" value="Cinema QA Team"></div>
      <div class="field"><label>Response SLA</label><input class="input" placeholder="Within 6 hours" value="Within 4 hours"></div>
      <div class="field form-full"><label>Keyword Watchlist</label><textarea class="textarea" placeholder="Spoiler, scam, offensive language...">spoiler, scam, harassment, leaked ending</textarea><div class="helper-text">Use comma-separated keywords to simulate automatic review flags in the UI.</div></div>
      <div class="field form-full"><label>Workflow Toggles</label><div class="check-grid">
        ${reviewWorkflowOption('Hold low ratings for review', 'Keep 1-2 star reviews in queue until a moderator checks them.', true)}
        ${reviewWorkflowOption('Highlight spoiler-risk text', 'Surface likely spoiler phrases to reviewers first.', true)}
        ${reviewWorkflowOption('Notify support when hidden', 'Send hidden-review events to customer support inbox.', false)}
        ${reviewWorkflowOption('Allow featured review pickup', 'Make approved reviews available for marketing reuse.', true)}
      </div></div>
    </div>
  </div>`;
}

function reviewModerationFormBody(review = {}) {
  const status = review.status || 'Pending';

  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">${review.user || 'Reviewer'} on ${review.movie || 'Movie title'}</div>
      <div class="surface-card-copy">${review.comment || 'Review content preview appears here.'}</div>
      <div class="meta-pills">
        <span class="badge gray">${review.movie || 'Unassigned movie'}</span>
        <span class="badge ${status === 'Cancelled' ? 'red' : status === 'Pending' ? 'orange' : 'blue'}">${status}</span>
        <span class="badge gold">${review.rating || 0}/5</span>
      </div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Moderation Status</label><select class="select">${buildOptions(['Confirmed', 'Pending', 'Cancelled'], status)}</select></div>
      <div class="field"><label>Visibility</label><select class="select">${buildOptions(['Public', 'Hidden', 'Escalated', 'Needs Reply'], status === 'Cancelled' ? 'Hidden' : 'Public')}</select></div>
      <div class="field"><label>Reviewer Name</label><input class="input" placeholder="Reviewer name" value="${review.user || ''}"></div>
      <div class="field"><label>Submitted At</label><input class="input" value="${review.submitted || ''}"></div>
      <div class="field form-full"><label>Customer Comment</label><textarea class="textarea" placeholder="Review text...">${review.comment || ''}</textarea></div>
      <div class="field form-full"><label>Moderator Actions</label><div class="check-grid">
        ${reviewWorkflowOption('Publish to public page', 'Show the review on the movie detail page.', status !== 'Cancelled')}
        ${reviewWorkflowOption('Feature in highlights', 'Allow the review to appear in featured snippets.', review.rating >= 4)}
        ${reviewWorkflowOption('Escalate to support', 'Route this review to support for follow-up.', status === 'Pending')}
        ${reviewWorkflowOption('Send reviewer response', 'Prepare an outbound acknowledgement email.', false)}
      </div></div>
      <div class="field form-full"><label>Internal Note</label><textarea class="textarea" placeholder="Leave moderation notes for the admin team...">${review.note || ''}</textarea><div class="helper-text">Internal notes are visible only to admins in this UI prototype.</div></div>
    </div>
  </div>`;
}

function openReviewQueueModal() {
  openModal('Review Queue', reviewQueueFormBody(), {
    description: 'Set how pending reviews are triaged, flagged, and handed off before publication.',
    note: 'UI preview only. Queue rules are not connected to backend moderation yet.',
    submitLabel: 'Save Queue Rules',
    successMessage: 'Queue rule preview updated!',
  });
}

function openReviewModerationModal(review = {}) {
  openModal('Moderate Review', reviewModerationFormBody(review), {
    description: 'Review the customer comment, choose visibility, and leave internal notes for the moderation team.',
    note: 'UI preview only. This does not publish or hide the review yet.',
    submitLabel: 'Apply Moderation',
    successMessage: 'Review moderation preview updated!',
  });
}

function handleMovieSectionAction() {
  openReviewQueueModal();
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
        <button class="action-btn edit" title="Approve" onclick="openReviewModerationModal({user:'${r.user}',movie:'${r.movie}',rating:${r.rating},comment:'${r.comment}',status:'${r.status}',submitted:'${r.submitted}'})">
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
