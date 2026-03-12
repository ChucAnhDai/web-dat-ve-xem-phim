<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="sidebar">
  <div class="nav-item active" onclick="navigateTo('home')">
    <span class="nav-icon">🏠</span> Home
  </div>
  <div class="nav-item" onclick="navigateTo('movies')">
    <span class="nav-icon">🎬</span> Movies
  </div>
  <div class="nav-item" onclick="navigateTo('showtimes')">
    <span class="nav-icon">🕐</span> Showtimes
  </div>
  <div class="nav-item" onclick="navigateTo('shop')">
    <span class="nav-icon">🛍️</span> Shop
  </div>
  <div class="nav-item" onclick="navigateTo('cart')">
    <span class="nav-icon">🛒</span> Cart
    <span class="nav-badge" id="cartNavBadge">0</span>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-label">Account</div>
    <div id="sidebarGuestActions">
      <div class="nav-item" onclick="navigateTo('auth');switchAuthTab('login')">
        <span class="nav-icon">🔐</span> Đăng nhập
      </div>
      <div class="nav-item" onclick="navigateTo('auth');switchAuthTab('register')">
        <span class="nav-icon">🧾</span> Đăng ký
      </div>
    </div>
    <div id="sidebarUserActions">
      <div class="nav-item" onclick="navigateTo('my-tickets')">
        <span class="nav-icon">🎫</span> My Tickets
      </div>
      <div class="nav-item" onclick="navigateTo('my-orders')">
        <span class="nav-icon">📦</span> My Orders
      </div>
      <div class="nav-item" onclick="navigateTo('profile')">
        <span class="nav-icon">👤</span> Profile
      </div>
      <div class="nav-item" onclick="handleLogout()">
        <span class="nav-icon">🚪</span> Đăng xuất
      </div>
    </div>
  </div>
</aside>
