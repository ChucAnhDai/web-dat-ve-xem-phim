<div class="admin-auth-shell admin-auth-shell-logout">
  <div class="admin-auth-showcase">
    <div class="admin-auth-badge">Session Exit</div>
    <h1 class="admin-auth-hero-title">Wrap up the admin session cleanly before handing the console to the next operator.</h1>
    <p class="admin-auth-hero-copy">This sign-out screen previews the last-mile experience for admins leaving the dashboard after reviewing users, payments, and campaign changes.</p>

    <div class="admin-auth-grid">
      <div class="admin-auth-surface">
        <div class="admin-auth-surface-title">Current Session</div>
        <div class="admin-auth-metric">Super Admin</div>
        <div class="admin-auth-surface-copy">Signed in from the main operations console with notifications and quick actions enabled.</div>
      </div>
      <div class="admin-auth-surface">
        <div class="admin-auth-surface-title">Before leaving</div>
        <div class="admin-auth-list">
          <span>Confirm active edits are saved</span>
          <span>Close shared workstation tabs</span>
          <span>Return to secure admin login</span>
        </div>
      </div>
    </div>
  </div>

  <div class="admin-auth-card">
    <div class="admin-auth-brand">
      <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin" class="admin-auth-mark">CS</a>
      <div>
        <div class="admin-auth-title">Ready to sign out?</div>
        <div class="admin-auth-subtitle">Review your admin session summary</div>
      </div>
    </div>

    <div class="preview-banner">
      <div class="preview-banner-title">Admin User</div>
      <div class="preview-banner-copy">admin@cineshop.com has access to dashboard, finance, user policies, and promotional content controls.</div>
      <div class="meta-pills">
        <span class="badge red">Super Admin</span>
        <span class="badge blue">Main Console</span>
        <span class="badge gold">UI prototype</span>
      </div>
    </div>

    <div class="admin-auth-actions">
      <a class="btn btn-primary" href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/login" style="justify-content:center;">Sign Out to Login</a>
      <a class="btn btn-ghost" href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin" style="justify-content:center;">Stay in Dashboard</a>
    </div>

    <div class="surface-card">
      <div class="surface-card-title">What this page is for</div>
      <div class="surface-card-copy">Use this screen as the admin logout UX shell. Real token invalidation or session cleanup can be added later without changing route structure.</div>
    </div>
  </div>
</div>
