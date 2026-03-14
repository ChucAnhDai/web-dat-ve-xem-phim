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

function buildRoleColorOptions(selectedColor = 'blue') {
  return [
    {label:'Red', value:'red'},
    {label:'Gold', value:'gold'},
    {label:'Blue', value:'blue'},
    {label:'Gray', value:'gray'},
  ].map(color => `<option value="${color.value}"${color.value === selectedColor ? ' selected' : ''}>${color.label}</option>`).join('');
}

function roleFormBody(role = {}) {
  const color = role.color || 'blue';
  const status = role.status || 'Active';
  const selectedPermissions = role.permissions || ['Read', 'Write', 'View Reports'];
  const permissions = ['Read', 'Write', 'Delete', 'Manage Users', 'Manage Movies', 'Manage Orders', 'Manage Payments', 'View Reports', 'Book', 'Purchase', 'Review'];

  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Role Blueprint</div>
      <div class="surface-card-copy">Preview access boundaries, responsibility scope, and permission bundles so admin teams can shape roles before wiring actual policy enforcement.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Role Name</label><input class="input" placeholder="Moderator" value="${role.name || ''}"></div>
      <div class="field"><label>Color</label><select class="select">${buildRoleColorOptions(color)}</select></div>
      <div class="field"><label>Status</label><select class="select">${buildOptions(['Active', 'Pending', 'Cancelled'], status)}</select></div>
      <div class="field"><label>Scope</label><select class="select">${buildOptions(['Global', 'Operations', 'Storefront', 'Support'], role.scope || 'Operations')}</select></div>
      <div class="field form-full"><label>Description</label><input class="input" placeholder="Role description" value="${role.desc || ''}"></div>
      <div class="field form-full"><label>Permissions</label>
        <div class="check-grid">
          ${permissions.map(permission => `
            <label class="check-option">
              <input type="checkbox"${selectedPermissions.includes(permission) ? ' checked' : ''}>
              <span>${permission}</span>
            </label>`).join('')}
        </div>
      </div>
      <div class="field form-full"><label>Preview</label>
        <div class="preview-banner">
          <div class="preview-banner-title">${role.name || 'Role preview'}</div>
          <div class="preview-banner-copy">${role.desc || 'Describe the responsibility boundary for this role so permission choices feel intentional.'}</div>
          <div class="meta-pills">
            <span class="badge ${color}">${color}</span>
            <span class="badge ${status === 'Cancelled' ? 'red' : status === 'Pending' ? 'orange' : 'green'}">${status}</span>
            <span class="badge blue">${selectedPermissions.length} permissions</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function openRoleModal(title, role = {}) {
  const isEdit = /^Edit/i.test(title);
  openModal(title, roleFormBody(role), {
    description: isEdit
      ? 'Adjust access scope, permission packs, and lifecycle state for this admin role.'
      : 'Create a new role with a clear access blueprint and reusable permission set.',
    note: 'UI preview only. Role policies are not persisted yet.',
    submitLabel: isEdit ? 'Update Role' : 'Create Role',
    successMessage: isEdit ? 'Role preview updated!' : 'Role preview staged!',
  });
}

function handleUserSectionAction() {
  openRoleModal('Add Role');
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
        <button class="action-btn edit" title="Edit" onclick="openRoleModal('Edit Role', rolesData.find(item => item.name === '${role.name}'))"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
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
