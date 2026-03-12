<div class="page-header">
  <h1 class="page-title">My Profile</h1>
  <p class="page-subtitle">Manage your account information</p>
</div>

<div class="profile-header">
  <div class="profile-avatar" id="profileAvatar">JD</div>
  <div style="flex:1">
    <div class="profile-name">John Doe</div>
    <div class="profile-email">john.doe@email.com</div>
    <div class="profile-stats">
      <div class="stat-item">
        <div class="stat-val">24</div>
        <div class="stat-label">Tickets</div>
      </div>
      <div class="stat-item">
        <div class="stat-val">12</div>
        <div class="stat-label">Orders</div>
      </div>
      <div class="stat-item">
        <div class="stat-val">$280</div>
        <div class="stat-label">Spent</div>
      </div>
    </div>
  </div>
  <button class="btn btn-secondary" type="button">📷 Change Photo</button>
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
        <label>First Name</label>
        <input class="form-control" id="profileFirstName" value="John">
      </div>
      <div class="form-group">
        <label>Last Name</label>
        <input class="form-control" id="profileLastName" value="Doe">
      </div>
    </div>
    <div class="form-group">
      <label>Email</label>
      <input class="form-control" id="profileEmailInput" value="john.doe@email.com">
    </div>
    <div class="form-group">
      <label>Phone</label>
      <input class="form-control" value="+1 555 123 4567">
    </div>
    <div class="form-group">
      <label>Date of Birth</label>
      <input class="form-control" type="date" value="1992-05-15">
    </div>
    <div style="margin-top:8px">
      <button class="btn btn-primary" type="button" onclick="showToast('✅','Profile Updated','Your changes have been saved.')">Save Changes</button>
      <button class="btn btn-ghost" type="button" style="margin-left:8px">Cancel</button>
    </div>
  </div>
</div>

<div id="profile-password" style="display:none">
  <div class="profile-form">
    <div class="form-section-title" style="font-size:18px">Change Password</div>
    <div class="form-group">
      <label>Current Password</label>
      <input class="form-control" type="password" placeholder="••••••••">
    </div>
    <div class="form-group">
      <label>New Password</label>
      <input class="form-control" type="password" placeholder="••••••••">
    </div>
    <div class="form-group">
      <label>Confirm New Password</label>
      <input class="form-control" type="password" placeholder="••••••••">
    </div>
    <button class="btn btn-primary" type="button" onclick="showToast('🔒','Password Updated','Your password has been changed.')">Update Password</button>
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
