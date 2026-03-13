<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Users — CineShop Admin</title>
<link rel="stylesheet" href="shared.css">
</head>
<body>
<div class="layout">
    <div id="sidebarMount"></div>
<div class="main-wrap" id="mainWrap">
        <div id="headerMount"></div>
<div class="page">
      <div class="page-header">
        <div>
          <div class="breadcrumb"><span>Home</span><span class="sep">›</span><span>User Management</span></div>
          <h1 class="page-title">User Management</h1>
          <p class="page-sub">Manage platform users, roles and permissions</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('Add New User', userFormBody())">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add User
        </button>
      </div>

      <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
        <div class="stat-card blue" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">14,872</div><div class="stat-label">Total Users</div></div>
        <div class="stat-card green" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">14,210</div><div class="stat-label">Active</div></div>
        <div class="stat-card red" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">340</div><div class="stat-label">Suspended</div></div>
        <div class="stat-card gold" style="padding:16px;"><div style="font-size:28px;font-family:'Bebas Neue',sans-serif;">+340</div><div class="stat-label">New This Week</div></div>
      </div>

      <div class="card">
        <div class="toolbar">
          <div class="toolbar-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            <input type="text" placeholder="Search name, email, phone..." oninput="filterUsers(this.value)">
          </div>
          <select class="select-filter" onchange="filterUsers()"><option>All Roles</option><option>Admin</option><option>Staff</option><option>Customer</option></select>
          <select class="select-filter"><option>All Status</option><option>Active</option><option>Suspended</option><option>Pending</option></select>
          <div class="toolbar-right">
            <span style="font-size:12px;color:var(--text-dim);" id="userCount">14,872 users</span>
            <button class="btn btn-ghost btn-sm" onclick="showToast('Exported','success')">Export</button>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr>
              <th>User</th><th>Email</th><th>Phone</th><th>Role</th><th>Orders</th><th>Reviews</th><th>Status</th><th>Joined</th><th>Actions</th>
            </tr></thead>
            <tbody id="usersBody"></tbody>
          </table>
        </div>
        <div id="usersPagination"></div>
      </div>

      <!-- ROLES TABLE -->
      <div class="card" style="margin-top:20px;">
        <div class="card-header">
          <div><div class="card-title">Roles & Permissions</div><div class="card-sub">Manage access control</div></div>
          <button class="btn btn-ghost btn-sm" onclick="openModal('Add Role', roleFormBody())">Add Role</button>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Role</th><th>Description</th><th>Users</th><th>Permissions</th><th>Actions</th></tr></thead>
            <tbody>
              <tr>
                <td><span class="badge red">Admin</span></td>
                <td class="td-muted">Full system access</td>
                <td style="font-weight:700;">3</td>
                <td><div style="display:flex;gap:4px;flex-wrap:wrap;"><span class="badge blue">Read</span><span class="badge blue">Write</span><span class="badge blue">Delete</span><span class="badge blue">Manage</span></div></td>
                <td><div class="actions-row"><button class="action-btn edit" onclick="openModal('Edit Role', roleFormBody())"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></div></td>
              </tr>
              <tr>
                <td><span class="badge gold">Staff</span></td>
                <td class="td-muted">Limited management access</td>
                <td style="font-weight:700;">18</td>
                <td><div style="display:flex;gap:4px;flex-wrap:wrap;"><span class="badge blue">Read</span><span class="badge blue">Write</span></div></td>
                <td><div class="actions-row"><button class="action-btn edit" onclick="openModal('Edit Role', roleFormBody())"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></div></td>
              </tr>
              <tr>
                <td><span class="badge gray">Customer</span></td>
                <td class="td-muted">Standard user access</td>
                <td style="font-weight:700;">14,851</td>
                <td><div style="display:flex;gap:4px;flex-wrap:wrap;"><span class="badge gray">Book</span><span class="badge gray">Purchase</span><span class="badge gray">Review</span></div></td>
                <td><div class="actions-row"><button class="action-btn edit" onclick="openModal('Edit Role', roleFormBody())"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></div></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="shared.js"></script>
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

function renderUsers(data) {
  document.getElementById('usersBody').innerHTML = data.map(u=>`
    <tr>
      <td><div class="user-cell">
        <div class="user-avatar" style="background:${u.color}22;color:${u.color};">${u.name.charAt(0)}</div>
        <span class="td-bold">${u.name}</span>
      </div></td>
      <td class="td-muted">${u.email}</td>
      <td class="td-muted">${u.phone}</td>
      <td>${statusBadge(u.role)}</td>
      <td style="font-weight:600;">${u.orders}</td>
      <td class="td-muted">${u.reviews}</td>
      <td>${statusBadge(u.status)}</td>
      <td class="td-muted">${u.joined}</td>
      <td><div class="actions-row">
        <button class="action-btn view" onclick="viewUser('${u.name}')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn edit" onclick="openModal('Edit User', userFormBody({name:'${u.name}',email:'${u.email}',phone:'${u.phone}'}))"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" onclick="showToast('User suspended','warning')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`).join('');
  document.getElementById('usersPagination').innerHTML = buildPagination(`Showing 1–${data.length} of 14,872 users`, 1487);
}

function filterUsers(q='') {
  const filtered = usersData.filter(u => u.name.toLowerCase().includes(q.toLowerCase()) || u.email.toLowerCase().includes(q.toLowerCase()));
  renderUsers(filtered);
  document.getElementById('userCount').textContent = `${filtered.length} shown`;
}

function viewUser(name) {
  const u = usersData.find(x=>x.name===name);
  if(!u) return;
  openModal(`${u.name} — User Profile`, `
    <div style="display:flex;gap:16px;align-items:center;margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid var(--border);">
      <div style="width:60px;height:60px;border-radius:12px;background:${u.color}22;color:${u.color};display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:700;">${u.name.charAt(0)}</div>
      <div>
        <div style="font-size:18px;font-weight:700;">${u.name}</div>
        <div style="font-size:13px;color:var(--text-muted);">Joined ${u.joined}</div>
        <div style="margin-top:6px;">${statusBadge(u.role)} ${statusBadge(u.status)}</div>
      </div>
    </div>
    <div class="form-grid">
      <div class="field"><label>Email</label><input class="input" value="${u.email}" readonly></div>
      <div class="field"><label>Phone</label><input class="input" value="${u.phone}" readonly></div>
      <div class="field"><label>Total Orders</label><input class="input" value="${u.orders} orders" readonly></div>
      <div class="field"><label>Reviews Written</label><input class="input" value="${u.reviews} reviews" readonly></div>
    </div>
    <div style="border-top:1px solid var(--border);margin-top:16px;padding-top:16px;">
      <div style="font-size:12px;font-weight:600;color:var(--text-muted);letter-spacing:0.5px;text-transform:uppercase;margin-bottom:10px;">Actions</div>
      <div style="display:flex;gap:8px;">
        <button class="btn btn-ghost btn-sm" onclick="showToast('Viewing ${u.name} orders','info')">View Orders</button>
        <button class="btn btn-ghost btn-sm" style="border-color:var(--orange);color:var(--orange);" onclick="showToast('${u.name} suspended','warning')">Suspend</button>
        <button class="btn btn-ghost btn-sm" style="border-color:var(--red);color:var(--red);" onclick="showToast('Are you sure? This cannot be undone.','error')">Delete</button>
      </div>
    </div>`);
}

function userFormBody(u={}) {
  return `<div class="form-grid">
    <div class="field"><label>Full Name</label><input class="input" placeholder="Full name" value="${u.name||''}"></div>
    <div class="field"><label>Email</label><input class="input" type="email" placeholder="email@example.com" value="${u.email||''}"></div>
    <div class="field"><label>Phone</label><input class="input" placeholder="+84 ..."></div>
    <div class="field"><label>Role</label><select class="select"><option>Customer</option><option>Staff</option><option>Admin</option></select></div>
    <div class="field"><label>Status</label><select class="select"><option>Active</option><option>Suspended</option></select></div>
    <div class="field"><label>Password</label><input class="input" type="password" placeholder="Set new password"></div>
  </div>`;
}

function roleFormBody() {
  return `<div class="form-grid">
    <div class="field"><label>Role Name</label><input class="input" placeholder="e.g. Moderator"></div>
    <div class="field"><label>Color</label><select class="select"><option>Red (Admin)</option><option>Gold (Staff)</option><option>Blue</option><option>Green</option></select></div>
    <div class="field form-full"><label>Description</label><input class="input" placeholder="Role description"></div>
    <div class="field form-full">
      <label>Permissions</label>
      <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:8px;">
        ${['Read','Write','Delete','Manage Users','Manage Movies','Manage Orders','Manage Payments','View Reports'].map(p=>`
          <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
            <input type="checkbox" style="accent-color:var(--red);">${p}
          </label>`).join('')}
      </div>
    </div>
  </div>`;
}

document.addEventListener('DOMContentLoaded', function(){

  renderUsers(usersData);
});
</script>
    <div id="footerMount"></div>
</body>
</html>
