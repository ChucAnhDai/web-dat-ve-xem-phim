<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">124</div><div class="stat-label">Sent Today</div></div>
  <div class="stat-card green" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">96%</div><div class="stat-label">Delivery Rate</div></div>
  <div class="stat-card gold" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">42</div><div class="stat-label">Scheduled</div></div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">8</div><div class="stat-label">Drafts</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="noticeSearch" type="text" placeholder="Search notifications..." oninput="filterNotifications(this.value)">
    </div>
    <select id="noticeStatusFilter" class="select-filter" onchange="filterNotifications()"><option>All Status</option><option>Sent</option><option>Scheduled</option><option>Draft</option></select>
    <div class="toolbar-right"><span id="noticeCount" style="font-size:12px;color:var(--text-dim);">6 notifications</span></div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Title</th><th>Channel</th><th>Audience</th><th>Scheduled</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody id="notificationsBody"></tbody>
    </table>
  </div>
</div>

<script>
const notificationsData = [
  {title:'Weekend promo blast',channel:'Push + Email',audience:'All users',scheduled:'2026-03-14 09:00',status:'Sent'},
  {title:'Student discount reminder',channel:'Push',audience:'Student segment',scheduled:'2026-03-15 10:00',status:'Scheduled'},
  {title:'Refund status update',channel:'Email',audience:'Refund queue',scheduled:'2026-03-13 16:00',status:'Sent'},
  {title:'New merch drop',channel:'Push + Banner',audience:'Shop buyers',scheduled:'2026-03-17 12:00',status:'Scheduled'},
  {title:'Maintenance notice',channel:'In-app',audience:'All users',scheduled:'Not scheduled',status:'Draft'},
  {title:'VIP early access',channel:'Email',audience:'VIP members',scheduled:'2026-03-18 08:00',status:'Scheduled'},
];

function notificationFormBody(notification = {}) {
  const channel = notification.channel || 'Push + Email';
  const status = notification.status || 'Draft';

  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Audience Delivery</div>
      <div class="surface-card-copy">Stage the message, target audience, and timing before wiring real delivery logic.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Title</label><input class="input" placeholder="Weekend promo blast" value="${notification.title || ''}"></div>
      <div class="field"><label>Channel</label><select class="select">${buildOptions(['Push', 'Email', 'Push + Email', 'In-app', 'Push + Banner'], channel)}</select></div>
      <div class="field"><label>Audience</label><input class="input" placeholder="All users" value="${notification.audience || ''}"></div>
      <div class="field"><label>Status</label><select class="select">${buildOptions(['Draft', 'Scheduled', 'Sent'], status)}</select></div>
      <div class="field"><label>Schedule Date</label><input class="input" type="date" value="${notification.date || ''}"></div>
      <div class="field"><label>Schedule Time</label><input class="input" type="time" value="${notification.time || ''}"></div>
      <div class="field"><label>Priority</label><select class="select">${buildOptions(['Standard', 'High Visibility', 'Critical'], notification.priority || 'Standard')}</select></div>
      <div class="field"><label>Fallback CTA</label><input class="input" placeholder="View offer" value="${notification.cta || ''}"></div>
      <div class="field form-full"><label>Message</label><textarea class="textarea" placeholder="Notification body">${notification.message || ''}</textarea></div>
      <div class="field form-full"><label>Preview</label>
        <div class="preview-banner">
          <div class="preview-banner-title">${notification.title || 'Notification preview'}</div>
          <div class="preview-banner-copy">${notification.message || 'Use a concise message that works well across push, email, and in-app layouts.'}</div>
          <div class="meta-pills">
            <span class="badge blue">${channel}</span>
            <span class="badge gray">${notification.audience || 'Audience not set'}</span>
            <span class="badge ${status === 'Sent' ? 'green' : status === 'Scheduled' ? 'orange' : 'gray'}">${status}</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function openNotificationModal(title, notification = {}) {
  const isEdit = /^Edit/i.test(title);
  openModal(title, notificationFormBody(notification), {
    description: isEdit
      ? 'Update message copy, audience targeting, and send timing for this notification.'
      : 'Compose a new notice and preview its tone before connecting real delivery.',
    note: 'UI preview only. Notifications are not sent from this prototype.',
    submitLabel: isEdit ? 'Update Notice' : 'Create Notice',
    successMessage: isEdit ? 'Notification preview updated!' : 'Notification preview staged!',
  });
}

function handleDashboardSectionAction() {
  openNotificationModal('Compose Notification');
}

function renderNotifications(data) {
  document.getElementById('notificationsBody').innerHTML = data.map(item => `<tr><td><div class="td-bold">${item.title}</div></td><td class="td-muted">${item.channel}</td><td class="td-muted">${item.audience}</td><td class="td-muted">${item.scheduled}</td><td>${statusBadge(item.status)}</td><td><div class="actions-row"><button class="action-btn edit" title="Edit" onclick="openNotificationModal('Edit Notification', {title:'${item.title}',channel:'${item.channel}',audience:'${item.audience}',status:'${item.status}'})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></div></td></tr>`).join('');
}

function filterNotifications(q) {
  const searchInput = document.getElementById('noticeSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedStatus = document.getElementById('noticeStatusFilter')?.value || 'All Status';
  const filtered = notificationsData.filter(item => {
    const matchesQuery = searchTerm === '' || item.title.toLowerCase().includes(searchTerm) || item.audience.toLowerCase().includes(searchTerm);
    const matchesStatus = selectedStatus === 'All Status' || item.status === selectedStatus;
    return matchesQuery && matchesStatus;
  });
  renderNotifications(filtered);
  document.getElementById('noticeCount').textContent = `${filtered.length} notifications`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterNotifications();
});
</script>
