<!-- ═══════════ SIDEBAR ═══════════ -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="sidebar">
  <div class="nav-item <?php echo ($activePage ?? '') === 'home' ? 'active' : ''; ?>" onclick="navigateTo('home')">
    <span class="nav-icon">🏠</span> Home
  </div>
  <div class="nav-item <?php echo ($activePage ?? '') === 'movies' ? 'active' : ''; ?>" onclick="navigateTo('movies')">
    <span class="nav-icon">🎬</span> Movies
  </div>
  <div class="nav-item <?php echo ($activePage ?? '') === 'showtimes' ? 'active' : ''; ?>" onclick="navigateTo('showtimes-home')">
    <span class="nav-icon">🕐</span> Showtimes
  </div>
  <div class="nav-item <?php echo ($activePage ?? '') === 'shop' ? 'active' : ''; ?>" onclick="navigateTo('shop')">
    <span class="nav-icon">🛍️</span> Shop
  </div>
  <div class="nav-item <?php echo ($activePage ?? '') === 'cart' ? 'active' : ''; ?>" onclick="navigateTo('cart')">
    <span class="nav-icon">🛒</span> Cart
    <span class="nav-badge" id="cartNavBadge">3</span>
  </div>

  <div class="sidebar-section">
    <div class="sidebar-label">Account</div>
    <div id="sidebarGuestActions" style="display:none">
      <div class="nav-item" onclick="navigateTo('auth');switchAuthTab('login')">
        <span class="nav-icon">🔐</span> Đăng nhập
      </div>
      <div class="nav-item" onclick="navigateTo('auth');switchAuthTab('register')">
        <span class="nav-icon">🧾</span> Đăng ký
      </div>
    </div>
    <div id="sidebarUserActions" style="display:none">
      <div class="nav-item <?php echo ($activePage ?? '') === 'my-tickets' ? 'active' : ''; ?>" onclick="navigateTo('my-tickets')">
        <span class="nav-icon">🎫</span> My Tickets
      </div>
      <div class="nav-item <?php echo ($activePage ?? '') === 'my-orders' ? 'active' : ''; ?>" onclick="navigateTo('my-orders')">
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

  <div class="sidebar-footer" id="sidebarFooter" style="display:none">
    <div class="sidebar-user" onclick="navigateTo('profile')">
      <div class="sidebar-avatar" id="sidebarAvatar">??</div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name" id="sidebarUserName">User Name</div>
        <div class="sidebar-user-tier" id="sidebarUserTier">👑 Member</div>
      </div>
    </div>
  </div>
</aside>
