<?php
$identifierValue = $old['identifier'] ?? 'admin';
$rememberValue = !empty($old['remember']);
$credentialError = $errors['credentials'][0] ?? $errors['server'][0] ?? null;
?>
<div class="admin-auth-shell">
  <div class="admin-auth-showcase">
    <div class="admin-auth-badge">Admin Access</div>
    <h1 class="admin-auth-hero-title">Operate the entire CineShop control room from one secure entry point.</h1>
    <p class="admin-auth-hero-copy">Sign in with the provisioned admin account to manage movies, cinemas, payments, and customer operations from one protected workspace.</p>

    <div class="admin-auth-grid">
      <div class="admin-auth-surface">
        <div class="admin-auth-surface-title">Default Local Admin</div>
        <div class="admin-auth-metric">admin / admin</div>
        <div class="admin-auth-surface-copy">A local development admin account is provisioned so you can enter the dashboard immediately after database setup.</div>
      </div>
      <div class="admin-auth-surface">
        <div class="admin-auth-surface-title">Security Layer</div>
        <div class="admin-auth-list">
          <span>Admin-only role enforcement</span>
          <span>JWT-backed admin cookie</span>
          <span>Protected admin page routes</span>
        </div>
      </div>
    </div>

    <div class="admin-auth-status">
      <div class="admin-auth-status-label">Current auth scope</div>
      <div class="meta-pills">
        <span class="badge green">Backend connected</span>
        <span class="badge blue">Role protected</span>
        <span class="badge gold">Movie Management ready</span>
      </div>
    </div>
  </div>

  <div class="admin-auth-card">
    <div class="admin-auth-brand">
      <a href="<?php echo htmlspecialchars($publicBase, ENT_QUOTES, 'UTF-8'); ?>/index.php?url=admin/login" class="admin-auth-mark">CS</a>
      <div>
        <div class="admin-auth-title">CineShop Admin</div>
        <div class="admin-auth-subtitle">Sign in to continue</div>
      </div>
    </div>

    <div class="surface-card">
      <div class="surface-card-title">Local Development Credentials</div>
      <div class="surface-card-copy">Use username <strong>admin</strong> and password <strong>admin</strong> for the pre-provisioned local administrator account.</div>
    </div>

    <form class="admin-auth-form" action="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/login" method="POST" novalidate>
      <?php if ($credentialError !== null): ?>
        <div class="form-alert" style="margin-bottom:16px;">
          <?php echo htmlspecialchars($credentialError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
      <?php endif; ?>

      <div class="field">
        <label for="adminIdentifierInput">Username or Work Email</label>
        <input
          class="input<?php echo isset($errors['identifier']) ? ' error' : ''; ?>"
          id="adminIdentifierInput"
          type="text"
          name="identifier"
          placeholder="admin"
          value="<?php echo htmlspecialchars($identifierValue, ENT_QUOTES, 'UTF-8'); ?>"
          autocomplete="username"
        >
        <?php if (isset($errors['identifier'][0])): ?>
          <div class="field-error"><?php echo htmlspecialchars($errors['identifier'][0], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
      </div>

      <div class="field">
        <label for="adminPasswordInput">Password</label>
        <div class="admin-auth-password">
          <input
            class="input<?php echo isset($errors['password']) ? ' error' : ''; ?>"
            id="adminPasswordInput"
            type="password"
            name="password"
            placeholder="Enter your password"
            value="admin"
            autocomplete="current-password"
          >
          <button class="admin-auth-toggle" type="button" onclick="toggleAdminPassword()">Show</button>
        </div>
        <?php if (isset($errors['password'][0])): ?>
          <div class="field-error"><?php echo htmlspecialchars($errors['password'][0], ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>
      </div>

      <div class="admin-auth-inline">
        <label class="check-option">
          <input type="checkbox" name="remember" value="1"<?php echo $rememberValue ? ' checked' : ''; ?>>
          <span>Remember this workstation for 24 hours</span>
        </label>
        <span class="admin-auth-link">Admin-only session</span>
      </div>

      <button class="btn btn-primary" type="submit" style="width:100%;">Enter Dashboard</button>
      <a class="btn btn-ghost" href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/login" style="width:100%;justify-content:center;">Back to customer login</a>
    </form>

    <div class="admin-auth-footer-note">
      This admin form posts directly to the backend and stores the admin session in a protected cookie for server-rendered dashboard routes.
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof clearAdminClientAuthState === 'function') {
    clearAdminClientAuthState();
  }
});

function toggleAdminPassword() {
  const input = document.getElementById('adminPasswordInput');
  const button = document.querySelector('.admin-auth-toggle');
  if (!input || !button) return;

  const showing = input.type === 'text';
  input.type = showing ? 'password' : 'text';
  button.textContent = showing ? 'Show' : 'Hide';
}
</script>
