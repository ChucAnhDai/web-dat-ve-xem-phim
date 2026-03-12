<?php
/** @var string $title */
$title = 'Đăng ký - CinemaX';
?>
<div class="page active" id="page-auth-register">
  <div class="page-header">
    <h1 class="page-title">Tạo Tài Khoản</h1>
    <p class="page-subtitle">Tham gia CinemaX để đặt vé nhanh hơn, lưu lịch sử giao dịch và nhận ưu đãi thành viên.</p>
  </div>

  <div class="auth-wrapper">
    <div class="auth-panel">
      <div class="auth-brand">
        <div class="logo-icon">🎬</div>
        Cinema<span>X</span>
      </div>
      <div class="auth-toggle">
        <button id="loginTabBtn" onclick="window.location.href='/login'">Đăng nhập</button>
        <button id="registerTabBtn" class="active" onclick="window.location.href='/register'">Đăng ký</button>
      </div>

      <div class="auth-form-meta">
        <span class="auth-kicker">Member Access</span>
        <p>Hoàn tất vài bước ngắn để bắt đầu trải nghiệm rạp phim, combo bắp nước và hồ sơ thành viên của bạn trên cùng một nơi.</p>
      </div>

      <form id="registerForm" class="auth-form" action="/register" method="POST" novalidate>
        <div class="form-row">
          <div class="form-group">
            <label>Họ tên</label>
            <div class="auth-input-group">
              <span class="auth-input-icon">👤</span>
              <input class="form-control" type="text" name="name" placeholder="Trần Văn A" required>
            </div>
            <div class="auth-error" id="registerNameError">
               <?php if (isset($errors['name'])) echo htmlspecialchars($errors['name']); ?>
            </div>
          </div>
          <div class="form-group">
            <label>Số điện thoại</label>
            <div class="auth-input-group">
              <span class="auth-input-icon">📱</span>
              <input class="form-control" type="tel" name="phone" placeholder="09xxxxxxxx" required>
            </div>
            <div class="auth-error" id="registerPhoneError">
               <?php if (isset($errors['phone'])) echo htmlspecialchars($errors['phone']); ?>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label>Email</label>
          <div class="auth-input-group">
            <span class="auth-input-icon">📧</span>
            <input class="form-control" type="email" name="email" placeholder="you@email.com" required>
          </div>
          <div class="auth-error" id="registerEmailError">
             <?php if (isset($errors['email'])) echo htmlspecialchars($errors['email']); ?>
          </div>
        </div>

        <div class="form-group">
          <label>Mật khẩu</label>
          <div class="auth-input-group">
            <span class="auth-input-icon">🔒</span>
            <input class="form-control" type="password" name="password" placeholder="Tối thiểu 8 ký tự" required>
            <span class="auth-password-toggle" onclick="togglePassword(this)">Hiện</span>
          </div>
          <div class="auth-error" id="registerPasswordError">
             <?php if (isset($errors['password'])) echo htmlspecialchars($errors['password']); ?>
          </div>
        </div>

        <div class="form-group">
          <label>Xác nhận mật khẩu</label>
          <div class="auth-input-group">
            <span class="auth-input-icon">🔐</span>
            <input class="form-control" type="password" name="confirm_password" placeholder="Nhập lại mật khẩu" required>
            <span class="auth-password-toggle" onclick="togglePassword(this)">Hiện</span>
          </div>
          <div class="auth-error" id="registerConfirmPasswordError"></div>
        </div>

        <div class="auth-password-hints">
          <span>Ít nhất 8 ký tự</span>
          <span>Dùng email đang hoạt động</span>
          <span>Thông tin chính xác để khôi phục tài khoản</span>
        </div>

        <div class="form-group">
          <label>Vai trò</label>
          <select class="form-control" name="role">
            <option value="user" selected>Người dùng</option>
            <option value="admin">Admin</option>
          </select>
        </div>

        <label class="auth-terms">
          <input type="checkbox" name="terms" required>
          <span>Tôi đồng ý với Điều khoản sử dụng và Chính sách bảo mật của CinemaX.</span>
        </label>
        <div class="auth-error" id="registerTermsError"></div>

        <div class="auth-note">Tài khoản sau khi tạo sẽ được đăng nhập ngay trên trình duyệt hiện tại.</div>
        
        <button class="btn btn-primary btn-full btn-lg" type="submit" style="margin-top:16px">Tạo tài khoản</button>
        <p class="auth-helper" style="margin-top:12px">Đã có tài khoản? <a href="/login">Đăng nhập</a></p>
      </form>
    </div>

    <div class="auth-banner">
      <div>
        <h2>Mở Khóa Quyền Lợi Thành Viên</h2>
        <p>Từ lịch sử đặt vé đến thông báo suất chiếu mới, mọi thứ được gói gọn trong một dashboard đồng bộ với phong cách của template CinemaX.</p>
      </div>

      <div class="auth-benefits">
        <div class="auth-benefit">
          <strong>Đặt vé nhanh</strong>
          <span>Lưu thông tin cơ bản để checkout ít thao tác hơn trong những lần sau.</span>
        </div>
        <div class="auth-benefit">
          <strong>Tích điểm thành viên</strong>
          <span>Theo dõi lịch sử mua vé và combo để nhận khuyến mãi cá nhân hóa.</span>
        </div>
        <div class="auth-benefit">
          <strong>Ưu tiên cập nhật</strong>
          <span>Nhận thông báo phim mới, suất chiếu hot và chương trình dành riêng cho hội viên.</span>
        </div>
      </div>

      <div class="auth-note">
        <strong>Tip:</strong> Dùng email thật để nhận mã ưu đãi và hỗ trợ khôi phục tài khoản khi cần.
      </div>
    </div>
  </div>
</div>
