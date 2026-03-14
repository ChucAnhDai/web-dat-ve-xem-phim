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
  return `<div class="form-grid"><div class="field"><label>Full Name</label><input class="input" value="Le Admin"></div><div class="field"><label>Email</label><input class="input" value="admin@cineshop.com"></div><div class="field"><label>Phone</label><input class="input" value="0923456789"></div><div class="field"><label>Timezone</label><input class="input" value="Asia/Saigon"></div></div>`;
}

function handleDashboardSectionAction() {
  openModal('Edit Profile', adminProfileFormBody());
}
</script>
