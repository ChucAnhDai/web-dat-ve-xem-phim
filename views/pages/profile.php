<div class="page-header">
  <h1 class="page-title">My Profile</h1>
  <p class="page-subtitle">Manage your account information</p>
</div>

<div class="profile-header">
  <div class="profile-avatar" id="profileAvatar">--</div>
  <div style="flex:1">
    <div class="profile-name" id="profileDisplayName">Account</div>
    <div class="profile-email" id="profileDisplayEmail">Loading profile data...</div>
    <div class="profile-stats">
      <div class="stat-item">
        <div class="stat-val" id="profileTicketsCount">0</div>
        <div class="stat-label">Tickets</div>
      </div>
      <div class="stat-item">
        <div class="stat-val" id="profileOrdersCount">0</div>
        <div class="stat-label">Orders</div>
      </div>
      <div class="stat-item">
        <div class="stat-val" id="profileSpentAmount">$0.00</div>
        <div class="stat-label">Spent</div>
      </div>
    </div>
  </div>
  <button class="btn btn-secondary" type="button" onclick="showToast('ℹ️','Avatar feature pending','Profile photo updates are not available yet.')">📷 Change Photo</button>
</div>

<div class="profile-tabs">
  <div class="profile-tab active" onclick="switchTab(this,'profile-info')">Profile</div>
  <div class="profile-tab" onclick="switchTab(this,'profile-password')">Password</div>
  <div class="profile-tab" onclick="switchTab(this,'profile-history')">Order History</div>
</div>

<div id="profile-info">
  <div class="profile-form">
    <div class="form-section-title" style="font-size:18px">Personal Information</div>
    <div class="form-row">
      <div class="form-group">
        <label>Name</label>
        <input class="form-control" id="profileFirstName" value="" placeholder="First name">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input class="form-control" id="profileEmailInput" value="" placeholder="Email">
      </div>
    </div>
    <div class="form-group">
      <label>Phone</label>
      <input class="form-control" id="profilePhoneInput" value="" placeholder="No phone number">
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Role</label>
        <input class="form-control" id="profileRoleInput" value="" readonly>
      </div>
      <div class="form-group">
        <label>Member Since</label>
        <input class="form-control" id="profileJoinedAtInput" value="" readonly>
      </div>
    </div>
    <div style="margin-top:8px">
      <button class="btn btn-primary" type="button" onclick="showToast('ℹ️','Update not available','Profile updates have not been connected yet.')">Save Changes</button>
      <button class="btn btn-ghost" type="button" style="margin-left:8px" onclick="hydrateProfile()">Reload</button>
    </div>
  </div>
</div>

<div id="profile-password" style="display:none">
  <div class="profile-form">
    <div class="form-section-title" style="font-size:18px">Change Password</div>
    <div class="form-group">
      <label>Current Password</label>
      <input class="form-control" type="password" id="currentPassword" placeholder="********">
    </div>
    <div class="form-group">
      <label>New Password</label>
      <input class="form-control" type="password" id="newPassword" placeholder="********">
    </div>
    <div class="form-group">
      <label>Confirm New Password</label>
      <input class="form-control" type="password" id="confirmPassword" placeholder="********">
    </div>
    <button class="btn btn-primary" type="button" onclick="updatePassword()">Update Password</button>
  </div>
</div>

<div id="profile-history" style="display:none">
  <div class="orders-table">
    <div class="table-header">
      <span>Order ID</span>
      <span>Items</span>
      <span>Date</span>
      <span>Amount</span>
      <span>Status</span>
      <span>Actions</span>
    </div>
    <div id="profileOrdersBody"></div>
  </div>
</div>