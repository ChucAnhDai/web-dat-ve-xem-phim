<div class="grid-main-side" style="align-items:start;">
  <div style="display:flex;flex-direction:column;gap:20px;">
    <div class="card">
      <div class="card-body" style="display:flex;flex-direction:column;gap:22px;">
        <div style="display:flex;justify-content:space-between;gap:18px;flex-wrap:wrap;align-items:flex-start;">
          <div style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
            <div style="width:84px;height:84px;border-radius:22px;background:linear-gradient(135deg,rgba(229,9,20,0.2),rgba(201,168,76,0.18));border:1px solid rgba(229,9,20,0.18);color:var(--red);display:flex;align-items:center;justify-content:center;font-size:30px;font-weight:700;box-shadow:0 12px 32px rgba(229,9,20,0.12);">AD</div>
            <div>
              <div style="font-size:28px;font-weight:700;line-height:1.1;">Le Admin</div>
              <div style="font-size:13px;color:var(--text-muted);margin-top:4px;">Super Admin · admin@cineshop.com · Main Operations Console</div>
              <div class="meta-pills" style="margin-top:10px;">
                <span class="badge red"><div class="badge-dot"></div>Admin</span>
                <span class="badge green"><div class="badge-dot"></div>Active</span>
                <span class="badge blue"><div class="badge-dot"></div>2FA Enabled</span>
                <span class="badge gold"><div class="badge-dot"></div>Shift Lead</span>
              </div>
            </div>
          </div>

          <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-ghost btn-sm" onclick="showToast('Opening activity log preview','info')">Activity Log</button>
            <button class="btn btn-ghost btn-sm" onclick="showToast('Security review preview opened','info')">Security Review</button>
          </div>
        </div>

        <div class="surface-card">
          <div class="surface-card-title">Admin Mission Snapshot</div>
          <div class="surface-card-copy">This profile keeps identity, recovery, trusted devices, and operations preferences in one place so admins can audit their own access without leaving the dashboard flow.</div>
        </div>

        <div class="form-grid">
          <div class="field"><label>Full Name</label><input class="input" value="Le Admin" readonly></div>
          <div class="field"><label>Primary Email</label><input class="input" value="admin@cineshop.com" readonly></div>
          <div class="field"><label>Phone</label><input class="input" value="0923456789" readonly></div>
          <div class="field"><label>Timezone</label><input class="input" value="Asia/Saigon" readonly></div>
          <div class="field"><label>Recovery Email</label><input class="input" value="security@cineshop.com" readonly></div>
          <div class="field"><label>Workstation</label><input class="input" value="HQ Control Room" readonly></div>
          <div class="field form-full"><label>Admin Bio</label><textarea class="textarea" readonly>Oversees platform operations, promotions, payment configuration, and rollout quality across the cinema network.</textarea></div>
        </div>
      </div>
    </div>

    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);">
      <div class="stat-card red" style="padding:16px;">
        <div style="font-size:26px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">9</div>
        <div class="stat-label">Managed Areas</div>
      </div>
      <div class="stat-card blue" style="padding:16px;">
        <div style="font-size:26px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">14</div>
        <div class="stat-label">Pending Reviews</div>
      </div>
      <div class="stat-card gold" style="padding:16px;">
        <div style="font-size:26px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">28</div>
        <div class="stat-label">Login Streak</div>
      </div>
      <div class="stat-card green" style="padding:16px;">
        <div style="font-size:26px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">98%</div>
        <div class="stat-label">Security Score</div>
      </div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Access Scope</div>
            <div class="card-sub">Modules and policy bundles currently assigned</div>
          </div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">
          <div class="check-grid">
            <label class="check-option"><input type="checkbox" checked disabled><span>Dashboard and reports</span></label>
            <label class="check-option"><input type="checkbox" checked disabled><span>Movie scheduling</span></label>
            <label class="check-option"><input type="checkbox" checked disabled><span>Cinema operations</span></label>
            <label class="check-option"><input type="checkbox" checked disabled><span>Payments and settlements</span></label>
            <label class="check-option"><input type="checkbox" checked disabled><span>Promotions and banners</span></label>
            <label class="check-option"><input type="checkbox" checked disabled><span>User and role policies</span></label>
          </div>

          <div class="preview-banner">
            <div class="preview-banner-title">Responsibility Focus</div>
            <div class="preview-banner-copy">Primary owner for launch coordination, finance checks, content scheduling, and escalation handling across all admin modules.</div>
            <div class="meta-pills">
              <span class="badge red">Global Scope</span>
              <span class="badge blue">Write Access</span>
              <span class="badge gold">Approvals Enabled</span>
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Notification Preferences</div>
            <div class="card-sub">Operational alerts delivered to this profile</div>
          </div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">
          <label class="check-option"><input type="checkbox" checked disabled><span><strong>Critical outages</strong><small>Booking, gateway, or session failures.</small></span></label>
          <label class="check-option"><input type="checkbox" checked disabled><span><strong>Daily revenue digest</strong><small>Bookings, refunds, and conversion summary at 07:00.</small></span></label>
          <label class="check-option"><input type="checkbox" checked disabled><span><strong>Campaign reminders</strong><small>Warnings before banners and promotions expire.</small></span></label>
          <label class="check-option"><input type="checkbox" disabled><span><strong>Low-priority chatter</strong><small>Muted so the console stays focused on action items.</small></span></label>
        </div>
      </div>
    </div>

    <div class="grid-2">
      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Recent Activity</div>
            <div class="card-sub">Latest actions captured on the admin console</div>
          </div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
          <div class="surface-card">
            <div class="surface-card-title">Updated payment method settings</div>
            <div class="surface-card-copy">Today at 10:42 · Adjusted settlement and maintenance messaging for payment gateways.</div>
          </div>
          <div class="surface-card">
            <div class="surface-card-title">Reviewed ticket refund queue</div>
            <div class="surface-card-copy">Today at 09:15 · Cleared pending requests before morning traffic peak.</div>
          </div>
          <div class="surface-card">
            <div class="surface-card-title">Published homepage banner</div>
            <div class="surface-card-copy">Yesterday at 18:30 · Approved campaign creative for the weekend launch slot.</div>
          </div>
          <div class="surface-card">
            <div class="surface-card-title">Assigned promotion to combo items</div>
            <div class="surface-card-copy">Yesterday at 15:05 · Linked promotion logic to concession bundles.</div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <div>
            <div class="card-title">Trusted Sessions</div>
            <div class="card-sub">Devices and environments with recent access</div>
          </div>
        </div>
        <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
          <div class="preview-banner">
            <div class="preview-banner-title">HQ Control Room</div>
            <div class="preview-banner-copy">Windows workstation · Last active 08:05 · Current primary console.</div>
            <div class="meta-pills">
              <span class="badge green">Trusted</span>
              <span class="badge blue">Current Device</span>
            </div>
          </div>
          <div class="surface-card">
            <div class="surface-card-title">MacBook Air</div>
            <div class="surface-card-copy">Remote review device · Last active yesterday at 21:14.</div>
          </div>
          <div class="surface-card">
            <div class="surface-card-title">Recovery Session</div>
            <div class="surface-card-copy">Security mailbox verification channel · Backup codes refreshed 2026-03-01.</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:16px;">
    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Session Health</div>
          <div class="card-sub">Current access posture for this admin account</div>
        </div>
      </div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:14px;">
        <div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span class="td-muted">Security posture</span><span style="font-weight:700;color:var(--green);">98%</span></div>
          <div class="progress-bar"><div class="progress-fill" style="width:98%;background:var(--green);"></div></div>
        </div>
        <div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span class="td-muted">Recovery readiness</span><span style="font-weight:700;color:var(--gold);">85%</span></div>
          <div class="progress-bar"><div class="progress-fill" style="width:85%;background:var(--gold);"></div></div>
        </div>
        <div>
          <div style="display:flex;justify-content:space-between;margin-bottom:6px;"><span class="td-muted">Notification coverage</span><span style="font-weight:700;color:var(--blue);">72%</span></div>
          <div class="progress-bar"><div class="progress-fill" style="width:72%;background:var(--blue);"></div></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Quick Actions</div>
          <div class="card-sub">Fast links tied to this profile</div>
        </div>
      </div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
        <button class="btn btn-ghost" style="justify-content:flex-start;" onclick="handleDashboardSectionAction()">Edit Profile</button>
        <button class="btn btn-ghost" style="justify-content:flex-start;" onclick="showToast('Backup codes preview opened','info')">View Backup Codes</button>
        <button class="btn btn-ghost" style="justify-content:flex-start;" onclick="showToast('Role assignment preview opened','info')">Review Role Scope</button>
        <a class="btn btn-ghost" href="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/index.php?url=admin/login" style="justify-content:flex-start;">Sign Out</a>
      </div>
    </div>

    <div class="card">
      <div class="card-header">
        <div>
          <div class="card-title">Recovery Checklist</div>
          <div class="card-sub">Recommended profile upkeep</div>
        </div>
      </div>
      <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
        <label class="check-option"><input type="checkbox" checked disabled><span>Recovery email verified</span></label>
        <label class="check-option"><input type="checkbox" checked disabled><span>2FA method tested this week</span></label>
        <label class="check-option"><input type="checkbox" checked disabled><span>Trusted workstation tagged</span></label>
        <label class="check-option"><input type="checkbox" disabled><span>Low-priority alerts still need routing review</span></label>
      </div>
    </div>
  </div>
</div>

<script>
function adminProfileFormBody() {
  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Profile Preferences</div>
      <div class="surface-card-copy">Keep identity, recovery, trusted-device handling, and operational alert preferences aligned before backend saving is connected.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Full Name</label><input class="input" value="Le Admin"></div>
      <div class="field"><label>Email</label><input class="input" value="admin@cineshop.com"></div>
      <div class="field"><label>Phone</label><input class="input" value="0923456789"></div>
      <div class="field"><label>Timezone</label><select class="select">${buildOptions(['Asia/Saigon', 'Asia/Bangkok', 'Asia/Tokyo', 'UTC'], 'Asia/Saigon')}</select></div>
      <div class="field"><label>Recovery Email</label><input class="input" value="security@cineshop.com"></div>
      <div class="field"><label>Primary Workstation</label><input class="input" value="HQ Control Room"></div>
      <div class="field"><label>Two-Factor Auth</label><select class="select">${buildOptions(['Enabled', 'Backup Codes Only', 'Disabled'], 'Enabled')}</select></div>
      <div class="field"><label>Alert Intensity</label><select class="select">${buildOptions(['Critical Only', 'Operational', 'Full Overview'], 'Operational')}</select></div>
      <div class="field form-full"><label>Admin Bio</label><textarea class="textarea" placeholder="Short admin bio">Oversees platform operations, promotions, and payment configuration across the cinema system.</textarea></div>
      <div class="field form-full"><label>Notification Preferences</label><div class="check-grid">
        <label class="check-option"><input type="checkbox" checked><span><strong>Critical outages</strong><small>Receive alerts for booking, payment, and API downtime.</small></span></label>
        <label class="check-option"><input type="checkbox" checked><span><strong>Daily revenue digest</strong><small>Get a snapshot of bookings, refunds, and conversion trends.</small></span></label>
        <label class="check-option"><input type="checkbox" checked><span><strong>Campaign reminders</strong><small>Receive reminders before banners and promotions expire.</small></span></label>
        <label class="check-option"><input type="checkbox"><span><strong>Device login alerts</strong><small>Warn when a new workstation accesses the admin panel.</small></span></label>
      </div></div>
      <div class="field form-full"><label>Trusted Session Rules</label><div class="check-grid">
        <label class="check-option"><input type="checkbox" checked><span>Remember primary workstation for 7 days</span></label>
        <label class="check-option"><input type="checkbox" checked><span>Require re-check on remote devices</span></label>
        <label class="check-option"><input type="checkbox"><span>Auto-expire idle sessions after 30 minutes</span></label>
      </div></div>
      <div class="field form-full"><label>Preview</label>
        <div class="preview-banner">
          <div class="preview-banner-title">Le Admin</div>
          <div class="preview-banner-copy">Super Admin with operational access, recovery routing, and curated alert preferences for the CineShop control room.</div>
          <div class="meta-pills">
            <span class="badge red">Admin</span>
            <span class="badge green">2FA Enabled</span>
            <span class="badge blue">Operational Alerts</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function handleDashboardSectionAction() {
  openModal('Edit Profile', adminProfileFormBody(), {
    description: 'Update account identity, recovery settings, trusted session rules, and admin notification preferences.',
    note: 'UI preview only. Profile changes are not persisted yet.',
    submitLabel: 'Update Profile',
    successMessage: 'Profile preview updated!',
  });
}
</script>
