<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div id="statTotalUsers" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">...</div><div class="stat-label">Total Users</div></div>
  <div class="stat-card green" style="padding:16px;"><div id="statActiveUsers" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">...</div><div class="stat-label">Active</div></div>
  <div class="stat-card red" style="padding:16px;"><div id="statSuspendedUsers" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">...</div><div class="stat-label">Suspended</div></div>
  <div class="stat-card gold" style="padding:16px;"><div id="statNewUsers" style="font-size:28px;font-family:'Bebas Neue',sans-serif;">...</div><div class="stat-label">New This Week</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="userSearch" type="text" placeholder="Search name, email, phone..." oninput="filterUsers(this.value)">
    </div>
    <select id="userRoleFilter" class="select-filter" onchange="filterUsers()"><option>All Roles</option><option>Admin</option><option>Staff</option><option>Customer</option></select>
    <select id="userStatusFilter" class="select-filter" onchange="filterUsers()"><option>All Status</option><option>Active</option><option>Suspended</option><option>Pending</option></select>
    <div class="toolbar-right">
      <span id="userCount" style="font-size:12px;color:var(--text-dim);">10 users</span>
      <button class="btn btn-ghost btn-sm" onclick="showToast('Users exported','success')">Export</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>User</th><th>Email</th><th>Phone</th><th>Role</th><th>Orders</th><th>Reviews</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
      <tbody id="usersBody"></tbody>
    </table>
  </div>
  <div id="usersPagination"></div>
</div>

<script>
let usersData = [];
let currentPage = 1;
const itemsPerPage = 10;

async function fetchUserStats() {
  try {
    const response = await fetch(`${window.APP_BASE_PATH || ''}/api/admin/users/stats`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const result = await response.json();
    if (result.data) {
      document.getElementById('statTotalUsers').textContent = result.data.total.toLocaleString();
      document.getElementById('statActiveUsers').textContent = result.data.active.toLocaleString();
      document.getElementById('statSuspendedUsers').textContent = result.data.suspended.toLocaleString();
      document.getElementById('statNewUsers').textContent = `+${result.data.new_this_week.toLocaleString()}`;
    }
  } catch (error) {
    console.error('Fetch stats error:', error);
  }
}

async function fetchUsers(page = 1) {
  currentPage = page;
  const search = document.getElementById('userSearch')?.value || '';
  const role = document.getElementById('userRoleFilter')?.value || 'All Roles';
  const status = document.getElementById('userStatusFilter')?.value || 'All Status';
  
  const query = new URLSearchParams({
    page: page,
    limit: itemsPerPage,
    search: search,
    role: role,
    status: status
  });

  try {
    const response = await fetch(`${window.APP_BASE_PATH || ''}/api/admin/users?${query.toString()}`, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    });
    
    const result = await response.json();
    if (result.data) {
      usersData = result.data.users;
      renderUsers(usersData, result.data.pagination);
      document.getElementById('userCount').textContent = `${result.data.pagination.total_items} users`;
    } else if (result.errors) {
      showToast(Object.values(result.errors)[0][0], 'error');
    }
  } catch (error) {
    console.error('Fetch error:', error);
    showToast('Failed to load users', 'error');
  }
}

function userFormBody(user = {}) {
  const role = user.role || 'user';
  const status = user.status || 'Active';
  
  return `<form id="userForm" style="display:flex;flex-direction:column;gap:18px;">
    <input type="hidden" name="id" value="${user.id || ''}">
    <div class="surface-card">
      <div class="surface-card-title">Account Setup</div>
      <div class="surface-card-copy">Configure account access, role, and current status for the user.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Full Name</label><input name="name" class="input" placeholder="Full name" value="${user.name || ''}" required></div>
      <div class="field"><label>Email</label><input name="email" class="input" type="email" placeholder="email@example.com" value="${user.email || ''}" required></div>
      <div class="field"><label>Phone</label><input name="phone" class="input" placeholder="+84 ..." value="${user.phone || ''}"></div>
      <div class="field"><label>Role</label><select name="role" class="select">${buildOptions(['user', 'admin'], role)}</select></div>
      <div class="field"><label>Status</label><select name="status" class="select">${buildOptions(['Active', 'Suspended', 'Pending'], status)}</select></div>
      <div class="field"><label>Password</label><input name="password" class="input" type="password" placeholder="${user.id ? 'Leave blank to keep current' : 'Set password'}" ${user.id ? '' : 'required'}></div>
    </div>
  </form>`;
}

async function saveUser() {
  const form = document.getElementById('userForm');
  const formData = new FormData(form);
  const data = Object.fromEntries(formData.entries());
  const id = data.id;
  delete data.id;

  const url = id ? `${window.APP_BASE_PATH || ''}/api/admin/users/${id}` : `${window.APP_BASE_PATH || ''}/api/admin/users`;
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
      showToast(result.data.message || 'Success', 'success');
      closeModal();
      fetchUsers(currentPage);
      fetchUserStats();
    } else if (result.errors) {
      const firstError = Object.values(result.errors)[0][0];
      showToast(firstError, 'error');
    }
  } catch (error) {
    showToast('Operation failed', 'error');
  }
}

function viewUser(id) {
  const user = usersData.find(item => item.id == id);
  if (!user) return;
  
  const color = '#' + Math.floor(Math.random()*16777215).toString(16);
  openModal('User Profile', `<div style="display:flex;gap:16px;align-items:center;margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border);"><div style="width:60px;height:60px;border-radius:12px;background:${color}22;color:${color};display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;">${user.name.charAt(0)}</div><div><div style="font-size:18px;font-weight:700;">${user.name}</div><div style="font-size:13px;color:var(--text-muted);">Joined ${user.created_at}</div><div style="margin-top:6px;">${statusBadge(user.role)} ${statusBadge(user.status)}</div></div></div><div class="form-grid"><div class="field"><label>Email</label><input class="input" value="${user.email}" readonly></div><div class="field"><label>Phone</label><input class="input" value="${user.phone || ''}" readonly></div></div>`);
}

function openUserModal(title, user = {}) {
  const isEdit = !!user.id;
  openModal(title, userFormBody(user), {
    description: isEdit 
      ? 'Update profile access and account status for this user.'
      : 'Create a new user profile with access level and contact details.',
    submitLabel: isEdit ? 'Update User' : 'Create User',
    onSubmit: saveUser
  });
}

function handleUserSectionAction() {
  openUserModal('Add New User');
}

function renderUsers(data, pagination) {
  const startItem = data.length === 0 ? 0 : (pagination.current_page - 1) * pagination.limit + 1;
  const endItem = startItem + data.length - 1;
  
  document.getElementById('usersBody').innerHTML = data.map(user => {
    const color = '#' + Math.floor(Math.random()*16777215).toString(16);
    return `
    <tr>
      <td><div class="user-cell"><div class="user-avatar" style="background:${color}22;color:${color};">${user.name.charAt(0)}</div><span class="td-bold">${user.name}</span></div></td>
      <td class="td-muted">${user.email}</td>
      <td class="td-muted">${user.phone || ''}</td>
      <td>${statusBadge(user.role)}</td>
      <td style="font-weight:600;">-</td>
      <td class="td-muted">-</td>
      <td>${statusBadge(user.status)}</td>
      <td class="td-muted">${user.created_at}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="viewUser(${user.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn edit" title="Edit" onclick="openUserModal('Edit User', ${JSON.stringify(user).replace(/"/g, '&quot;')})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" title="Delete" onclick="confirmDeleteUser(${user.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`}).join('');
    
  document.getElementById('usersPagination').innerHTML = buildPagination(
    `Showing ${startItem}-${endItem} of ${pagination.total_items} users`, 
    pagination.total_pages,
    pagination.current_page,
    'fetchUsers'
  );
}

async function confirmDeleteUser(id) {
  if (confirm('Are you sure you want to delete this user?')) {
    try {
      const response = await fetch(`${window.APP_BASE_PATH || ''}/api/admin/users/${id}`, {
        method: 'DELETE',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      const result = await response.json();
      if (result.data) {
        showToast('User deleted', 'success');
        fetchUsers(currentPage);
        fetchUserStats();
      } else {
        showToast('Failed to delete user', 'error');
      }
    } catch (error) {
      showToast('Error deleting user', 'error');
    }
  }
}

function filterUsers() {
  fetchUsers(1);
}

document.addEventListener('DOMContentLoaded', function () {
  fetchUsers();
  fetchUserStats();
});
</script>
