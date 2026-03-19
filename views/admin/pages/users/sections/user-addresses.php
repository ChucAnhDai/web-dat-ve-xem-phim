<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div id="statTotalAddresses" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">...</div><div class="stat-label">Saved Addresses</div></div>
  <div class="stat-card green" style="padding:16px;"><div id="statDefaultAddresses" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">...</div><div class="stat-label">Default Addresses</div></div>
  <div class="stat-card gold" style="padding:16px;"><div id="statPickupPoints" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">...</div><div class="stat-label">Pickup Points</div></div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);"><div id="statPendingAddresses" style="font-size:24px;font-family:'Bebas Neue',sans-serif;">...</div><div class="stat-label">Needs Review</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="addressSearch" type="text" placeholder="Search user or address..." oninput="filterAddresses(this.value)">
    </div>
    <select id="addressTypeFilter" class="select-filter" onchange="filterAddresses()"><option>All Types</option><option>Home</option><option>Office</option><option>Pickup</option></select>
    <select id="addressStatusFilter" class="select-filter" onchange="filterAddresses()"><option>All Status</option><option>Verified</option><option>Pending</option><option>Blocked</option></select>
    <div class="toolbar-right">
      <span id="addressCount" style="font-size:12px;color:var(--text-dim);">8 addresses</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th>User</th><th>Label</th><th>Address</th><th>City</th><th>Phone</th><th>Default</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody id="userAddressesBody"></tbody>
    </table>
  </div>
</div>

<script>
let addressesData = [];
let addressCurrentPage = 1;

async function fetchAddressStats() {
  try {
    const response = await fetch(`${window.APP_BASE_PATH || ''}/api/admin/addresses/stats`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const result = await response.json();
    if (result.data) {
      document.getElementById('statTotalAddresses').textContent = result.data.total.toLocaleString();
      document.getElementById('statDefaultAddresses').textContent = result.data.default.toLocaleString();
      document.getElementById('statPickupPoints').textContent = result.data.pickup.toLocaleString();
      document.getElementById('statPendingAddresses').textContent = result.data.needs_review.toLocaleString();
    }
  } catch (error) {
    console.error('Fetch stats error:', error);
  }
}

async function fetchAddresses(page = 1) {
  addressCurrentPage = page;
  const search = document.getElementById('addressSearch').value;
  const label = document.getElementById('addressTypeFilter').value;
  const status = document.getElementById('addressStatusFilter').value;
  
  const query = new URLSearchParams({
    page,
    limit: 10,
    search: search,
    label: label === 'All Types' ? '' : label,
    status: status === 'All Status' ? '' : status
  });

  try {
    const response = await fetch(`${window.APP_BASE_PATH || ''}/api/admin/addresses?${query.toString()}`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const result = await response.json();
    if (result.data) {
      addressesData = result.data.addresses;
      renderAddresses(addressesData, result.data.pagination);
    }
  } catch (error) {
    showToast('Failed to load addresses', 'error');
  }
}

function addressFormBody(address = {}) {
  const label = address.label || 'Home';
  const status = address.status || 'Verified';
  const isPrimary = address.is_primary == 1;

  return `<form id="addressForm" style="display:flex;flex-direction:column;gap:18px;">
    <input type="hidden" name="id" value="${address.id || ''}">
    <div class="surface-card">
      <div class="surface-card-title">Address Book Entry</div>
      <div class="surface-card-copy">Update delivery labels and verification status.</div>
    </div>

    <div class="form-grid">
      <div class="field" style="position:relative;">
        <label>User</label>
        <div class="input-wrap" style="position:relative;cursor:pointer;" onclick="toggleUserDropdown()">
          <input id="userSearchInput" class="input" placeholder="Select or search user..." oninput="searchUsersForAddress(this.value)" ${address.id ? 'disabled' : ''} autocomplete="off" style="padding-right:30px;">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);width:14px;pointer-events:none;opacity:0.5;"><path d="M6 9l6 6 6-6"/></svg>
          <input type="hidden" name="user_id" id="selectedUserId" value="${address.user_id || ''}" required>
        </div>
        <div id="userSearchResults" class="surface-card" style="position:absolute;top:100%;left:0;right:0;z-index:100;display:none;max-height:220px;overflow-y:auto;margin-top:4px;padding:4px;border:1px solid var(--border);box-shadow:0 10px 30px rgba(0,0,0,0.5);background:var(--bg2);"></div>
        <div id="selectedUserDisplay" class="td-bold" style="margin-top:8px;font-size:12px;color:var(--blue);">${address.user_name ? 'Selected: ' + address.user_name : ''}</div>
      </div>
      <div class="field"><label>Label</label><select name="label" class="select">${buildOptions(['Home', 'Office', 'Pickup'], label)}</select></div>
      <div class="field"><label>Status</label><select name="status" class="select">${buildOptions(['Verified', 'Pending', 'Blocked'], status)}</select></div>
      <div class="field"><label>City</label><input name="city" class="input" placeholder="City" value="${address.city || ''}" required></div>
      <div class="field"><label>District</label><input name="district" class="input" placeholder="District" value="${address.district || ''}"></div>
      <div class="field"><label>Phone</label><input name="phone" class="input" placeholder="Phone number" value="${address.phone || ''}"></div>
      <div class="field form-full"><label>Address</label><input name="address" class="input" placeholder="Street address" value="${address.address || ''}" required></div>
      <div class="field form-full">
        <label class="check-option">
          <input type="checkbox" name="is_primary" value="1"${isPrimary ? ' checked' : ''}>
          <span>Set as Default Address</span>
        </label>
      </div>
    </div>
  </form>`;
}

async function toggleUserDropdown() {
  const resultsDiv = document.getElementById('userSearchResults');
  if (resultsDiv.style.display === 'block') {
    resultsDiv.style.display = 'none';
  } else {
    await searchUsersForAddress(''); // Load default list
  }
}

async function searchUsersForAddress(query) {
  const resultsDiv = document.getElementById('userSearchResults');
  
  // Show "Loading..." or similar if needed
  resultsDiv.style.display = 'block';

  try {
    const response = await fetch(`${window.APP_BASE_PATH || ''}/api/admin/users?search=${encodeURIComponent(query)}&limit=10`, {
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const result = await response.json();
    if (result.data && result.data.users && result.data.users.length > 0) {
      resultsDiv.innerHTML = result.data.users.map(u => `
        <div class="user-search-item" style="padding:10px;cursor:pointer;border-radius:6px;transition:all 0.2s;margin-bottom:2px;" 
             onclick="selectUserForAddress(${u.id}, '${u.name}')"
             onmouseover="this.style.background='rgba(255,255,255,0.08)'"
             onmouseout="this.style.background='transparent'">
          <div style="display:flex;justify-content:space-between;align-items:center;">
            <div>
              <div class="td-bold" style="font-size:13px;">${u.name}</div>
              <div class="td-muted" style="font-size:11px;">${u.email}</div>
            </div>
            <div class="badge gray" style="font-size:10px;">ID: ${u.id}</div>
          </div>
        </div>
      `).join('');
    } else {
      resultsDiv.innerHTML = '<div class="td-muted" style="padding:12px;text-align:center;font-size:12px;">No users found</div>';
    }
  } catch (error) {
    console.error('Search failed', error);
  }
}

function selectUserForAddress(id, name) {
  document.getElementById('selectedUserId').value = id;
  document.getElementById('userSearchInput').value = name;
  document.getElementById('selectedUserDisplay').textContent = 'Selected: ' + name;
  document.getElementById('userSearchResults').style.display = 'none';
  // Prevent event bubbling if needed, but here it's fine
}

// Close dropdown when clicking outside
document.addEventListener('click', function(e) {
  const resultsDiv = document.getElementById('userSearchResults');
  const inputWrap = document.querySelector('.input-wrap');
  if (resultsDiv && !resultsDiv.contains(e.target) && !inputWrap.contains(e.target)) {
    resultsDiv.style.display = 'none';
  }
});

async function saveAddress() {
  const form = document.getElementById('addressForm');
  const formData = new FormData(form);
  const data = Object.fromEntries(formData.entries());
  const id = data.id;
  data.is_primary = data.is_primary ? 1 : 0;
  delete data.id;

  const url = id ? `${window.APP_BASE_PATH || ''}/api/admin/addresses/${id}` : `${window.APP_BASE_PATH || ''}/api/admin/addresses`;
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
      fetchAddresses(addressCurrentPage);
      fetchAddressStats();
    } else if (result.errors) {
      showToast(Object.values(result.errors)[0][0], 'error');
    }
  } catch (error) {
    showToast('Operation failed', 'error');
  }
}

function openAddressModal(title, address = {}) {
  const isEdit = !!address.id;
  openModal(title, addressFormBody(address), {
    description: isEdit ? 'Update saved address details.' : 'Create a new saved address entry.',
    submitLabel: isEdit ? 'Update Address' : 'Create Address',
    onSubmit: saveAddress
  });
}

function handleUserSectionAction() {
  openAddressModal('Add Address');
}

function renderAddresses(data, pagination) {
  document.getElementById('userAddressesBody').innerHTML = data.map(address => `
    <tr>
      <td class="td-bold">${address.user_name} (ID: ${address.user_id})</td>
      <td><span class="badge gray">${address.label}</span></td>
      <td class="td-muted">${address.address}</td>
      <td class="td-muted">${address.city}</td>
      <td class="td-muted">${address.phone || address.user_phone || ''}</td>
      <td><span class="badge ${address.is_primary == 1 ? 'blue' : 'gray'}">${address.is_primary == 1 ? 'Primary' : 'Secondary'}</span></td>
      <td>${statusBadge(address.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn edit" title="Edit" onclick="openAddressModal('Edit Address', ${JSON.stringify(address).replace(/"/g, '&quot;')})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" title="Delete" onclick="confirmDeleteAddress(${address.id})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`).join('');
    
  document.getElementById('addressCount').textContent = `${pagination.total_items} addresses`;
}

async function confirmDeleteAddress(id) {
  if (confirm('Are you sure you want to delete this address?')) {
    try {
      const response = await fetch(`${window.APP_BASE_PATH || ''}/api/admin/addresses/${id}`, {
        method: 'DELETE',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });
      const result = await response.json();
      if (result.data) {
        showToast(result.data.message, 'success');
        fetchAddresses(addressCurrentPage);
        fetchAddressStats();
      }
    } catch (error) {
      showToast('Delete failed', 'error');
    }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  fetchAddresses();
  fetchAddressStats();
});
</script>
