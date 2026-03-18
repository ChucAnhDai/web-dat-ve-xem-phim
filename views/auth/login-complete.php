<?php
$redirectPath = is_string($redirectPath ?? null) ? $redirectPath : '/';
$authToken = is_string($authToken ?? null) ? $authToken : '';
$persistAuth = !empty($persistAuth);
?>
<div class="page active" id="page-auth-login-complete">
  <div class="page-header">
    <h1 class="page-title">Signing In</h1>
    <p class="page-subtitle">Completing your sign-in and returning you to the app.</p>
  </div>

  <div class="auth-wrapper">
    <div class="auth-panel" style="max-width:640px">
      <div class="auth-note">
        Your session is being restored. If nothing happens, use the button below.
      </div>
      <p style="margin-top:16px">
        <a class="btn btn-primary btn-lg" href="<?php echo htmlspecialchars(($appBase ?? '') . $redirectPath, ENT_QUOTES, 'UTF-8'); ?>">Continue</a>
      </p>
      <noscript>
        <p class="auth-helper" style="margin-top:12px">JavaScript is required to finish storing your session in this browser.</p>
      </noscript>
    </div>
  </div>
</div>
<script>
(() => {
  const token = <?php echo json_encode($authToken, JSON_UNESCAPED_UNICODE); ?>;
  const redirectPath = <?php echo json_encode($redirectPath, JSON_UNESCAPED_UNICODE); ?>;
  const appBase = <?php echo json_encode($appBase ?? '', JSON_UNESCAPED_UNICODE); ?>;
  const persistent = <?php echo $persistAuth ? 'true' : 'false'; ?>;

  if (!token) {
    window.location.replace(`${appBase}/login`);
    return;
  }

  try {
    if (persistent) {
      window.localStorage.setItem('cinemax_token', token);
      window.sessionStorage.removeItem('cinemax_token');
    } else {
      window.sessionStorage.setItem('cinemax_token', token);
      window.localStorage.removeItem('cinemax_token');
    }
  } catch (error) {
    // Ignore storage errors and continue the redirect.
  }

  window.location.replace(`${appBase}${redirectPath}`);
})();
</script>
