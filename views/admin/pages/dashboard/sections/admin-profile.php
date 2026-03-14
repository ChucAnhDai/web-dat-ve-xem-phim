<div class="grid-2">
  <div class="card">
    <div class="card-body">
      <div style="display:flex;gap:16px;align-items:center;margin-bottom:20px;">
        <div style="width:72px;height:72px;border-radius:18px;background:rgba(229,9,20,0.15);color:var(--red);display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;">AD</div>
        <div>
          <div style="font-size:22px;font-weight:700;">Le Admin</div>
          <div style="font-size:13px;color:var(--text-muted);">Super Admin / admin@cineshop.com</div>
          <div style="margin-top:8px;"><span class="badge red"><div class="badge-dot"></div>Admin</span> <span class="badge green"><div class="badge-dot"></div>Active</span></div>
        </div>
      </div>
      <div class="form-grid">
        <div class="field"><label>Full Name</label><input class="input" value="Le Admin"></div>
        <div class="field"><label>Email</label><input class="input" value="admin@cineshop.com"></div>
        <div class="field"><label>Phone</label><input class="input" value="0923456789"></div>
        <div class="field"><label>Timezone</label><input class="input" value="Asia/Saigon"></div>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Recent Activity</div></div>
    <div class="card-body">
      <div style="display:flex;flex-direction:column;gap:12px;">
        <div><div class="td-bold">Updated payment method settings</div><div class="td-muted">Today at 10:42</div></div>
        <div><div class="td-bold">Reviewed ticket refund queue</div><div class="td-muted">Today at 09:15</div></div>
        <div><div class="td-bold">Published homepage banner</div><div class="td-muted">Yesterday at 18:30</div></div>
        <div><div class="td-bold">Assigned promotion to combo items</div><div class="td-muted">Yesterday at 15:05</div></div>
      </div>
    </div>
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <div class="card-header"><div class="card-title">Security Preferences</div></div>
  <div class="card-body">
    <div class="form-grid">
      <div class="field"><label>Two-Factor Auth</label><input class="input" value="Enabled"></div>
      <div class="field"><label>Last Password Change</label><input class="input" value="2026-02-28"></div>
      <div class="field"><label>Last Login</label><input class="input" value="2026-03-14 08:05"></div>
      <div class="field"><label>Recovery Email</label><input class="input" value="security@cineshop.com"></div>
    </div>
  </div>
</div>

<script>
function adminProfileFormBody() {
  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Profile Preferences</div>
      <div class="surface-card-copy">Keep the admin identity card, notification preferences, and security recovery details aligned before backend saving is connected.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Full Name</label><input class="input" value="Le Admin"></div>
      <div class="field"><label>Email</label><input class="input" value="admin@cineshop.com"></div>
      <div class="field"><label>Phone</label><input class="input" value="0923456789"></div>
      <div class="field"><label>Timezone</label><select class="select">${buildOptions(['Asia/Saigon', 'Asia/Bangkok', 'Asia/Tokyo', 'UTC'], 'Asia/Saigon')}</select></div>
      <div class="field"><label>Recovery Email</label><input class="input" value="security@cineshop.com"></div>
      <div class="field"><label>Two-Factor Auth</label><select class="select">${buildOptions(['Enabled', 'Backup Codes Only', 'Disabled'], 'Enabled')}</select></div>
      <div class="field form-full"><label>Admin Bio</label><textarea class="textarea" placeholder="Short admin bio">Oversees platform operations, promotions, and payment configuration across the cinema system.</textarea></div>
      <div class="field form-full"><label>Notification Preferences</label><div class="check-grid">
        <label class="check-option"><input type="checkbox" checked><span><strong>Critical outages</strong><small>Receive alerts for booking, payment, and API downtime.</small></span></label>
        <label class="check-option"><input type="checkbox" checked><span><strong>Daily revenue digest</strong><small>Get a snapshot of bookings, refunds, and conversion trends.</small></span></label>
        <label class="check-option"><input type="checkbox"><span><strong>Campaign reminders</strong><small>Receive reminders before banners and promotions expire.</small></span></label>
      </div></div>
    </div>
  </div>`;
}

function handleDashboardSectionAction() {
  openModal('Edit Profile', adminProfileFormBody(), {
    description: 'Update account details, recovery settings, and admin notification preferences.',
    note: 'UI preview only. Profile changes are not persisted yet.',
    submitLabel: 'Update Profile',
    successMessage: 'Profile preview updated!',
  });
}
</script>
