<?php
/** @var string $title */
$title = 'Đăng nhập - CinemaX';
?>
<div class="page active" id="page-auth-login">
  <div class="page-header">
    <h1 class="page-title">Đăng nhập</h1>
    <p class="page-subtitle">Đăng nhập để trải nghiệm CinemaX</p>
  </div>

  <div class="auth-wrapper">
    <div class="auth-panel">
      <div class="auth-brand">
        <div class="logo-icon">🎬</div>
        Cinema<span>X</span>
      </div>
      <div class="auth-toggle">
        <button id="loginTabBtn" class="active" onclick="window.location.href='<?php echo htmlspecialchars($appBase); ?>/login'">Đăng nhập</button>
        <button id="registerTabBtn" onclick="window.location.href='<?php echo htmlspecialchars($appBase); ?>/register'">Đăng ký</button>
      </div>

      <form id="loginForm" class="auth-form" action="<?php echo htmlspecialchars($appBase); ?>/login" method="POST">
        <div class="form-group">
          <label>Email</label>
          <div class="auth-input-group">
            <span class="auth-input-icon">📧</span>
            <input class="form-control" type="email" name="email" placeholder="you@email.com" required>
          </div>
          <div class="auth-error" id="loginEmailError">
            <?php if (isset($errors['email'])) echo htmlspecialchars($errors['email']); ?>
          </div>
        </div>
        
        <div class="form-group">
          <label>Mật khẩu</label>
          <div class="auth-input-group">
            <span class="auth-input-icon">🔒</span>
            <input class="form-control" type="password" name="password" placeholder="••••••••" required>
            <span class="auth-password-toggle" onclick="togglePassword(this)">Hiện</span>
          </div>
          <div class="auth-error" id="loginPasswordError">
             <?php if (isset($errors['password'])) echo htmlspecialchars($errors['password']); ?>
          </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
          <label style="display:flex;align-items:center;gap:8px;font-size:12px;color:var(--text2)">
            <input type="checkbox" name="remember" style="accent-color:var(--red)"> Ghi nhớ đăng nhập
          </label>
          <a href="/forgot-password" class="auth-helper">Quên mật khẩu?</a>
        </div>
        
        <button class="btn btn-primary btn-full btn-lg" type="submit">Đăng nhập</button>
        <p class="auth-helper" style="margin-top:12px">Chưa có tài khoản? <a href="<?php echo htmlspecialchars($appBase); ?>/register">Đăng ký</a></p>
      </form>
    </div>

    <div class="auth-banner">
      <div>
        <h2>Trải nghiệm điện ảnh đỉnh cao</h2>
        <p>Đăng nhập để đặt vé nhanh hơn, quản lý lịch xem phim và nhận ưu đãi thành viên.</p>
      </div>
      <div class="auth-note">
        <strong>Tip:</strong> Sử dụng email hợp lệ để nhận mã ưu đãi từ CinemaX.
      </div>
    </div>
  </div>
</div>
