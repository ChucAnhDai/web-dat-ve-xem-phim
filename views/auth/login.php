<?php
/** @var string $title */
$errors = is_array($errors ?? null) ? $errors : [];
$old = is_array($old ?? null) ? $old : [];
$redirect = is_string($redirect ?? null) ? $redirect : (is_string($_GET['redirect'] ?? null) ? $_GET['redirect'] : '/');
$redirectQuery = $redirect !== '/' ? '?redirect=' . urlencode($redirect) : '';
$title = 'Dang nhap - CinemaX';
?>
<div class="page active" id="page-auth-login">
  <div class="page-header">
    <h1 class="page-title">Dang nhap</h1>
    <p class="page-subtitle">Dang nhap de tiep tuc trai nghiem CinemaX.</p>
  </div>

  <div class="auth-wrapper">
    <div class="auth-panel">
      <div class="auth-brand">
        <div class="logo-icon">CX</div>
        Cinema<span>X</span>
      </div>

      <div class="auth-toggle">
        <button type="button" id="loginTabBtn" class="active" onclick="window.location.href='<?php echo htmlspecialchars($appBase . '/login' . $redirectQuery, ENT_QUOTES, 'UTF-8'); ?>'">Dang nhap</button>
        <button type="button" id="registerTabBtn" onclick="window.location.href='<?php echo htmlspecialchars($appBase . '/register' . $redirectQuery, ENT_QUOTES, 'UTF-8'); ?>'">Dang ky</button>
      </div>

      <form id="loginForm" class="auth-form" action="<?php echo htmlspecialchars($appBase . '/login', ENT_QUOTES, 'UTF-8'); ?>" method="POST" novalidate>
        <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="auth-error" id="loginGeneralError" style="<?php echo empty($errors['credentials']) && empty($errors['server']) ? 'display:none;' : ''; ?>margin-bottom:12px;">
          <?php echo htmlspecialchars($errors['credentials'][0] ?? $errors['server'][0] ?? '', ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <div class="form-group">
          <label>Email / Phone</label>
          <div class="auth-input-group">
            <span class="auth-input-icon">@</span>
            <input
              class="form-control"
              type="text"
              name="identifier"
              value="<?php echo htmlspecialchars((string) ($old['identifier'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
              placeholder="you@email.com or 0901234567"
              autocomplete="username"
              required
            >
          </div>
          <div class="auth-error" id="loginIdentifierError">
            <?php echo htmlspecialchars($errors['identifier'][0] ?? $errors['email'][0] ?? '', ENT_QUOTES, 'UTF-8'); ?>
          </div>
        </div>

        <div class="form-group">
          <label>Mat khau</label>
          <div class="auth-input-group">
            <span class="auth-input-icon">*</span>
            <input
              class="form-control"
              type="password"
              name="password"
              placeholder="Enter your password"
              autocomplete="current-password"
              required
            >
            <span class="auth-password-toggle" onclick="togglePassword(this)">Hien</span>
          </div>
          <div class="auth-error" id="loginPasswordError">
            <?php echo htmlspecialchars($errors['password'][0] ?? '', ENT_QUOTES, 'UTF-8'); ?>
          </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
          <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text2)">
            <input type="checkbox" name="remember" value="1" style="accent-color:var(--red)" <?php echo !empty($old['remember']) ? 'checked' : ''; ?>>
            Ghi nho dang nhap
          </label>
          <a href="<?php echo htmlspecialchars($appBase . '/forgot-password', ENT_QUOTES, 'UTF-8'); ?>" class="auth-helper">Quen mat khau?</a>
        </div>

        <button class="btn btn-primary btn-full btn-lg" type="submit">Dang nhap</button>
        <p class="auth-helper" style="margin-top:12px">
          Chua co tai khoan?
          <a href="<?php echo htmlspecialchars($appBase . '/register' . $redirectQuery, ENT_QUOTES, 'UTF-8'); ?>">Dang ky</a>
        </p>
      </form>
    </div>

    <div class="auth-banner">
      <div>
        <h2>Truy cap tai khoan nhanh hon</h2>
        <p>Dang nhap de dong bo gio hang, theo doi don hang, va tiep tuc thanh toan ma khong mat trang thai.</p>
      </div>
      <div class="auth-note">
        <strong>Tip:</strong> Ban co the dang nhap bang email hoac so dien thoai da dang ky.
      </div>
    </div>
  </div>
</div>
