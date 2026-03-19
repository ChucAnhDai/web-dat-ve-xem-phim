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
let rolesData = [];

async function fetchRoles() {
  const search = document.getElementById('roleSearch').value;
  
  try {
    const response = await fetch(`${window.APP_BASE_PATH || ''}/api/admin/roles`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const result = await response.json();
    if (result.data) {
      rolesData = result.data;
      filterRoles(search);
      renderRoleSummary();
    }
  } catch (error) {
    showToast('Failed to load roles', 'error');
  }
}

function roleFormBody(role = {}) {
  const color = role.color || 'blue';
  const status = role.status || 'Active';
  const selectedPermissions = role.permissions || [];
  const permissions = ['Read', 'Write', 'Delete', 'Manage Users', 'Manage Movies', 'Manage Orders', 'Manage Payments', 'View Reports', 'Book', 'Purchase', 'Review'];

  return `<form id="roleForm" style="display:flex;flex-direction:column;gap:18px;">
    <input type="hidden" name="id" value="${role.id || ''}">
    <div class="surface-card">
      <div class="surface-card-title">Role Blueprint</div>
      <div class="surface-card-copy">Define access boundaries and permissions.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Role Name</label><input name="role_name" class="input" placeholder="Moderator" value="${role.role_name || ''}" required></div>
      <div class="field"><label>Color</label><select name="color" class="select">${buildRoleColorOptions(color)}</select></div>
      <div class="field"><label>Status</label><select name="status" class="select">${buildOptions(['Active', 'Pending', 'Cancelled'], status)}</select></div>
      <div class="field form-full"><label>Description</label><input name="description" class="input" placeholder="Role description" value="${role.description || ''}"></div>
      <div class="field form-full"><label>Permissions</label>
        <div class="check-grid">
          ${permissions.map(p => `
            <label class="check-option">
              <input type="checkbox" name="permissions[]" value="${p}"${selectedPermissions.includes(p) ? ' checked' : ''}>
              <span>${p}</span>
            </label>`).join('')}
        </div>
      </div>
    </div>
  </form>`;
}

async function saveRole() {
  const form = document.getElementById('roleForm');
  const formData = new FormData(form);
  const data = Object.fromEntries(formData.entries());
  const id = data.id;
  delete data.id;
  
  // Handle permissions array
  data.permissions = Array.from(form.querySelectorAll('input[name="permissions[]"]:checked')).map(cb => cb.value);

  const url = id ? `${window.APP_BASE_PATH || ''}/api/admin/roles/${id}` : `${window.APP_BASE_PATH || ''}/api/admin/roles`;
  const method = id ? 'PUT' : 'POST';

  try {
    const response = await fetch(url, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: JSON.stringify(data)
    });

    const result = await response.json();
    if (result.data) {
      showToast(result.data.message, 'success');
      closeModal();
      fetchRoles();
    }
  } catch (error) {
    showToast('Operation failed', 'error');
  }
}

function openRoleModal(title, role = {}) {
  const isEdit = !!role.id;
  openModal(title, roleFormBody(role), {
    description: isEdit ? 'Update role settings and permissions.' : 'Create a new admin role.',
    submitLabel: isEdit ? 'Update Role' : 'Create Role',
    onSubmit: saveRole
  });
}

function handleUserSectionAction() {
  openRoleModal('Add Role');
}

function renderRoleSummary() {
  document.getElementById('roleSummary').innerHTML = rolesData.map(role => `
    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
      <div><div class="td-bold">${role.role_name}</div><div class="td-muted">${role.user_count} users</div></div>
      <span class="badge ${role.color}">${role.status}</span>
    </div>`).join('');
}

function renderRoles(data) {
  document.getElementById('rolesBody').innerHTML = data.map(role => `
    <tr>
      <td><span class="badge ${role.color}">${role.role_name}</span></td>
      <td class="td-muted">${role.description || ''}</td>
      <td style="font-weight:700;">${role.user_count}</td>
      <td><div style="display:flex;gap:4px;flex-wrap:wrap;">${(role.permissions || []).map(p => `<span class="badge blue">${p}</span>`).join('')}</div></td>
      <td>${statusBadge(role.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn edit" title="Edit" onclick="openRoleModal('Edit Role', ${JSON.stringify(role).replace(/"/g, '&quot;')})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" title="Delete" onclick="confirmDeleteRole(${role.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`).join('');
}

async function confirmDeleteRole(id) {
  if (confirm('Are you sure you want to delete this role?')) {
    try {
      const response = await fetch(`${window.APP_BASE_PATH || ''}/api/admin/roles/${id}`, {
        method: 'DELETE',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const result = await response.json();
      if (result.data) {
        showToast(result.data.message, 'success');
        fetchRoles();
      }
    } catch (error) {
      showToast('Delete failed', 'error');
    }
  }
}

function filterRoles(q) {
  const searchTerm = (q || '').trim().toLowerCase();
  const filtered = rolesData.filter(role => searchTerm === '' || role.role_name.toLowerCase().includes(searchTerm) || (role.description || '').toLowerCase().includes(searchTerm));
  renderRoles(filtered);
  document.getElementById('roleCount').textContent = `${filtered.length} roles`;
}

document.addEventListener('DOMContentLoaded', () => fetchRoles());
</script>
