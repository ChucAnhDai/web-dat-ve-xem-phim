<div class="admin-auth-shell">
  <div class="admin-auth-showcase">
    <div class="admin-auth-badge">Admin Access</div>
    <h1 class="admin-auth-hero-title">Operate the entire CineShop control room from one secure entry point.</h1>
    <p class="admin-auth-hero-copy">Review release pipelines, payments, users, and content scheduling before you step into the dashboard. This screen follows the admin module structure and is ready for real auth wiring next.</p>

    <div class="admin-auth-grid">
      <div class="admin-auth-surface">
        <div class="admin-auth-surface-title">Shift Snapshot</div>
        <div class="admin-auth-metric">42 live showtimes</div>
        <div class="admin-auth-surface-copy">Operations, promotions, and finance stay aligned in a single admin surface.</div>
      </div>
      <div class="admin-auth-surface">
        <div class="admin-auth-surface-title">Security Layer</div>
        <div class="admin-auth-list">
          <span>Role-aware access</span>
          <span>Password and recovery flow</span>
          <span>Session device review</span>
        </div>
      </div>
    </div>

    <div class="admin-auth-status">
      <div class="admin-auth-status-label">Recommended before sign in</div>
      <div class="meta-pills">
        <span class="badge green">2FA ready</span>
        <span class="badge blue">Audit logging</span>
        <span class="badge gold">Night shift handoff</span>
      </div>
    </div>
  </div>

  <div class="admin-auth-card">
    <div class="admin-auth-brand">
      <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/login" class="admin-auth-mark">CS</a>
      <div>
        <div class="admin-auth-title">CineShop Admin</div>
        <div class="admin-auth-subtitle">Sign in to continue</div>
      </div>
    </div>

    <div class="surface-card">
      <div class="surface-card-title">Access Preview</div>
      <div class="surface-card-copy">UI only for now. The form is structured for future backend integration without changing the admin route layout.</div>
    </div>

    <form class="admin-auth-form" onsubmit="window.location.href='<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin'; return false;">
      <div class="field">
        <label>Work Email</label>
        <input class="input" type="email" placeholder="admin@cineshop.com" value="admin@cineshop.com">
      </div>

      <div class="field">
        <label>Password</label>
        <div class="admin-auth-password">
          <input class="input" id="adminPasswordInput" type="password" placeholder="Enter your password" value="password-demo">
          <button class="admin-auth-toggle" type="button" onclick="toggleAdminPassword()">Show</button>
        </div>
      </div>

      <div class="form-grid">
        <div class="field">
          <label>Admin Role</label>
          <select class="select">
            <option selected>Super Admin</option>
            <option>Operations Lead</option>
            <option>Finance Manager</option>
            <option>Support Lead</option>
          </select>
        </div>
        <div class="field">
          <label>Shift</label>
          <select class="select">
            <option selected>Morning Console</option>
            <option>Afternoon Console</option>
            <option>Night Console</option>
            <option>Remote Review</option>
          </select>
        </div>
      </div>

      <div class="admin-auth-inline">
        <label class="check-option">
          <input type="checkbox" checked>
          <span>Remember this workstation</span>
        </label>
        <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/logout" class="admin-auth-link">Review sign-out flow</a>
      </div>

      <button class="btn btn-primary" type="submit" style="width:100%;">Enter Dashboard</button>
      <a class="btn btn-ghost" href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/login" style="width:100%;justify-content:center;">Back to customer login</a>
    </form>

    <div class="admin-auth-footer-note">
      Use this page as the admin auth shell. Hook the form to real login logic later without moving files out of the `views/admin` structure.
    </div>
  </div>
</div>

<script>
function toggleAdminPassword() {
  const input = document.getElementById('adminPasswordInput');
  const button = document.querySelector('.admin-auth-toggle');
  if (!input || !button) return;

  const showing = input.type === 'text';
  input.type = showing ? 'password' : 'text';
  button.textContent = showing ? 'Show' : 'Hide';
}
</script>
