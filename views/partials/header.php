<header class="header">
  <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
  <div class="header-logo" onclick="navigateTo('home')">
    <div class="logo-icon">🎬</div>
    Cinema<span>X</span>
  </div>
  <nav class="header-nav">
    <a href="#" class="active" onclick="navigateTo('home');return false">Home</a>
    <a href="#" onclick="navigateTo('movies');return false">Movies</a>
    <a href="#" onclick="navigateTo('showtimes');return false">Showtimes</a>
    <a href="#" onclick="navigateTo('shop');return false">Shop</a>
  </nav>
  <div class="header-search">
    <span class="search-icon">🔍</span>
    <input type="text" placeholder="Search movies, products...">
  </div>
  <div class="header-actions">
    <button class="icon-btn" onclick="navigateTo('shop')">
      🛒
      <span class="badge" id="cartBadge">0</span>
    </button>
    <button class="icon-btn">
      🔔
      <span class="badge">0</span>
    </button>
    <div id="authGuestActions" class="header-auth-actions">
      <button class="btn btn-secondary btn-sm" onclick="window.location.href='<?php echo htmlspecialchars($appBase); ?>/login'">Đăng nhập</button>
      <button class="btn btn-primary btn-sm" onclick="window.location.href='<?php echo htmlspecialchars($appBase); ?>/register'">Đăng ký</button>
    </div>
    <div class="avatar-btn" id="authUserMenu" tabindex="0">
      <span id="authAvatarInitials">JD</span>
      <div class="dropdown">
        <a href="#" onclick="navigateTo('profile');return false">👤 My Profile</a>
        <a href="#" onclick="navigateTo('my-tickets');return false">🎫 My Tickets</a>
        <a href="#" onclick="navigateTo('my-orders');return false">📦 My Orders</a>
        <div class="dropdown-divider"></div>
        <a href="#" onclick="handleLogout();return false">🚪 Logout</a>
      </div>
    </div>
  </div>
</header>
