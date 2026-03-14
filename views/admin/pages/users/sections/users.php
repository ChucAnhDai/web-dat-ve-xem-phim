<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">14,872</div><div class="stat-label">Total Users</div></div>
  <div class="stat-card green" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">14,210</div><div class="stat-label">Active</div></div>
  <div class="stat-card red" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">340</div><div class="stat-label">Suspended</div></div>
  <div class="stat-card gold" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">+340</div><div class="stat-label">New This Week</div></div>
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
const usersData = [
  {name:'Nguyen Van A',email:'nguyenvana@gmail.com',phone:'0901234567',role:'Customer',orders:14,reviews:8,status:'Active',joined:'2024-03-12',color:'#3B82F6'},
  {name:'Tran Thi B',email:'tranthib@email.com',phone:'0912345678',role:'Customer',orders:8,reviews:3,status:'Active',joined:'2024-06-01',color:'#A855F7'},
  {name:'Le Admin',email:'admin@cineshop.com',phone:'0923456789',role:'Admin',orders:0,reviews:0,status:'Active',joined:'2023-01-01',color:'#E50914'},
  {name:'Pham Staff',email:'staff@cineshop.com',phone:'0934567890',role:'Staff',orders:2,reviews:1,status:'Active',joined:'2024-01-15',color:'#C9A84C'},
  {name:'Hoang Minh C',email:'hminhc@yahoo.com',phone:'0945678901',role:'Customer',orders:31,reviews:14,status:'Active',joined:'2023-08-20',color:'#22C55E'},
  {name:'Do Thi D',email:'dothid@proton.me',phone:'0956789012',role:'Customer',orders:5,reviews:2,status:'Suspended',joined:'2024-11-05',color:'#F59E0B'},
  {name:'Vu Quoc E',email:'vuquoce@gmail.com',phone:'0967890123',role:'Customer',orders:22,reviews:9,status:'Active',joined:'2024-02-28',color:'#06B6D4'},
  {name:'Ly Van F',email:'lyvanf@email.vn',phone:'0978901234',role:'Customer',orders:1,reviews:0,status:'Pending',joined:'2026-03-10',color:'#8B5CF6'},
  {name:'Bui Thi G',email:'buithig@gmail.com',phone:'0989012345',role:'Customer',orders:18,reviews:7,status:'Active',joined:'2024-05-15',color:'#EC4899'},
  {name:'Dang Van H',email:'dangvanh@hotmail.com',phone:'0990123456',role:'Staff',orders:0,reviews:0,status:'Active',joined:'2023-09-01',color:'#14B8A6'},
];

function userFormBody(user = {}) {
  return `<div class="form-grid">
    <div class="field"><label>Full Name</label><input class="input" placeholder="Full name" value="${user.name || ''}"></div>
    <div class="field"><label>Email</label><input class="input" type="email" placeholder="email@example.com" value="${user.email || ''}"></div>
    <div class="field"><label>Phone</label><input class="input" placeholder="+84 ..." value="${user.phone || ''}"></div>
    <div class="field"><label>Role</label><select class="select"><option>Customer</option><option>Staff</option><option>Admin</option></select></div>
    <div class="field"><label>Status</label><select class="select"><option>Active</option><option>Suspended</option><option>Pending</option></select></div>
    <div class="field"><label>Password</label><input class="input" type="password" placeholder="Set new password"></div>
  </div>`;
}

function viewUser(name) {
  const user = usersData.find(item => item.name === name);
  if (!user) return;
  openModal('User Profile', `<div style="display:flex;gap:16px;align-items:center;margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border);"><div style="width:60px;height:60px;border-radius:12px;background:${user.color}22;color:${user.color};display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;">${user.name.charAt(0)}</div><div><div style="font-size:18px;font-weight:700;">${user.name}</div><div style="font-size:13px;color:var(--text-muted);">Joined ${user.joined}</div><div style="margin-top:6px;">${statusBadge(user.role)} ${statusBadge(user.status)}</div></div></div><div class="form-grid"><div class="field"><label>Email</label><input class="input" value="${user.email}" readonly></div><div class="field"><label>Phone</label><input class="input" value="${user.phone}" readonly></div><div class="field"><label>Total Orders</label><input class="input" value="${user.orders} orders" readonly></div><div class="field"><label>Reviews</label><input class="input" value="${user.reviews}" readonly></div></div>`);
}

function handleUserSectionAction() {
  openModal('Add New User', userFormBody());
}

function renderUsers(data) {
  const startItem = data.length === 0 ? 0 : 1;
  document.getElementById('usersBody').innerHTML = data.map(user => `
    <tr>
      <td><div class="user-cell"><div class="user-avatar" style="background:${user.color}22;color:${user.color};">${user.name.charAt(0)}</div><span class="td-bold">${user.name}</span></div></td>
      <td class="td-muted">${user.email}</td>
      <td class="td-muted">${user.phone}</td>
      <td>${statusBadge(user.role)}</td>
      <td style="font-weight:600;">${user.orders}</td>
      <td class="td-muted">${user.reviews}</td>
      <td>${statusBadge(user.status)}</td>
      <td class="td-muted">${user.joined}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="viewUser('${user.name}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn edit" title="Edit" onclick="openModal('Edit User', userFormBody({name:'${user.name}',email:'${user.email}',phone:'${user.phone}'}))"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" title="Suspend" onclick="showToast('User suspended','warning')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`).join('');
  document.getElementById('usersPagination').innerHTML = buildPagination(`Showing ${startItem}-${data.length} of ${data.length} users`, Math.max(1, Math.ceil(data.length / 10)));
}

function filterUsers(q) {
  const searchInput = document.getElementById('userSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedRole = document.getElementById('userRoleFilter')?.value || 'All Roles';
  const selectedStatus = document.getElementById('userStatusFilter')?.value || 'All Status';
  const filtered = usersData.filter(user => {
    const matchesQuery = searchTerm === '' || user.name.toLowerCase().includes(searchTerm) || user.email.toLowerCase().includes(searchTerm) || user.phone.toLowerCase().includes(searchTerm);
    const matchesRole = selectedRole === 'All Roles' || user.role === selectedRole;
    const matchesStatus = selectedStatus === 'All Status' || user.status === selectedStatus;
    return matchesQuery && matchesRole && matchesStatus;
  });

  renderUsers(filtered);
  document.getElementById('userCount').textContent = `${filtered.length} users`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterUsers();
});
</script>
