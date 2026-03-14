<div class="grid-2" style="margin-bottom:20px;">
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Role Summary</div><div class="card-sub">Current access distribution</div></div>
    </div>
    <div class="card-body" id="roleSummary"></div>
  </div>
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Permission Packs</div><div class="card-sub">Reusable policy templates</div></div>
    </div>
    <div class="card-body">
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <span class="badge blue">Read</span>
        <span class="badge blue">Write</span>
        <span class="badge blue">Delete</span>
        <span class="badge blue">Manage Users</span>
        <span class="badge blue">Manage Movies</span>
        <span class="badge blue">Manage Orders</span>
        <span class="badge blue">Manage Payments</span>
        <span class="badge blue">View Reports</span>
      </div>
    </div>
  </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="roleSearch" type="text" placeholder="Search role..." oninput="filterRoles(this.value)">
    </div>
    <div class="toolbar-right">
      <span id="roleCount" style="font-size:12px;color:var(--text-dim);">4 roles</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>Role</th><th>Description</th><th>Users</th><th>Permissions</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody id="rolesBody"></tbody>
    </table>
  </div>
</div>

<script>
const rolesData = [
  {name:'Admin',color:'red',desc:'Full system access with configuration control.',users:3,permissions:['Read','Write','Delete','Manage Users','Manage Movies','Manage Orders','Manage Payments','View Reports'],status:'Active'},
  {name:'Staff',color:'gold',desc:'Limited management access for daily operations.',users:18,permissions:['Read','Write','Manage Movies','Manage Orders'],status:'Active'},
  {name:'Customer',color:'gray',desc:'Standard account access for booking and purchasing.',users:14851,permissions:['Book','Purchase','Review'],status:'Active'},
  {name:'Support',color:'blue',desc:'Can review tickets, orders, and address issues.',users:5,permissions:['Read','Manage Orders','View Reports'],status:'Pending'},
];

function roleFormBody(role = {}) {
  return `<div class="form-grid">
    <div class="field"><label>Role Name</label><input class="input" placeholder="Moderator" value="${role.name || ''}"></div>
    <div class="field"><label>Color</label><select class="select"><option>Red</option><option>Gold</option><option>Blue</option><option>Gray</option></select></div>
    <div class="field"><label>Status</label><select class="select"><option>Active</option><option>Pending</option><option>Cancelled</option></select></div>
    <div class="field form-full"><label>Description</label><input class="input" placeholder="Role description" value="${role.desc || ''}"></div>
    <div class="field form-full"><label>Permissions</label><div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">${['Read','Write','Delete','Manage Users','Manage Movies','Manage Orders','Manage Payments','View Reports'].map(permission => `<label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;"><input type="checkbox" style="accent-color:var(--red);">${permission}</label>`).join('')}</div></div>
  </div>`;
}

function handleUserSectionAction() {
  openModal('Add Role', roleFormBody());
}

function renderRoleSummary() {
  document.getElementById('roleSummary').innerHTML = rolesData.map(role => `
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
      <div><div class="td-bold">${role.name}</div><div class="td-muted">${role.users.toLocaleString()} users</div></div>
      <span class="badge ${role.color}">${role.status}</span>
    </div>`).join('');
}

function renderRoles(data) {
  document.getElementById('rolesBody').innerHTML = data.map(role => `
    <tr>
      <td><span class="badge ${role.color}">${role.name}</span></td>
      <td class="td-muted">${role.desc}</td>
      <td style="font-weight:700;">${role.users.toLocaleString()}</td>
      <td><div style="display:flex;gap:4px;flex-wrap:wrap;">${role.permissions.map(permission => `<span class="badge blue">${permission}</span>`).join('')}</div></td>
      <td>${statusBadge(role.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn edit" title="Edit" onclick="openModal('Edit Role', roleFormBody({name:'${role.name}',desc:'${role.desc}'}))"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
      </div></td>
    </tr>`).join('');
}

function filterRoles(q) {
  const searchInput = document.getElementById('roleSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const filtered = rolesData.filter(role => searchTerm === '' || role.name.toLowerCase().includes(searchTerm) || role.desc.toLowerCase().includes(searchTerm));
  renderRoles(filtered);
  document.getElementById('roleCount').textContent = `${filtered.length} roles`;
}

document.addEventListener('DOMContentLoaded', function () {
  renderRoleSummary();
  filterRoles();
});
</script>
