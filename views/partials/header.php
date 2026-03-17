<!-- ═══════════ HEADER ═══════════ -->
<header class="header">
  <button class="hamburger" id="hamburgerBtn" aria-label="Menu">
    <span></span><span></span><span></span>
  </button>
  <div class="header-logo" onclick="navigateTo('home')">
    <div class="logo-icon">🎬</div>
    Cinema<span>X</span>
  </div>
  <nav class="header-nav">
    <a href="#" class="<?php echo ($activePage ?? '') === 'home' ? 'active' : ''; ?>" onclick="navigateTo('home');return false">Home</a>
    <a href="#" class="<?php echo ($activePage ?? '') === 'movies' ? 'active' : ''; ?>" onclick="navigateTo('movies');return false">Movies</a>
    <a href="#" class="<?php echo ($activePage ?? '') === 'showtimes' ? 'active' : ''; ?>" onclick="navigateTo('showtimes-home');return false">Showtimes</a>
    <a href="#" class="<?php echo ($activePage ?? '') === 'shop' ? 'active' : ''; ?>" onclick="navigateTo('shop');return false">Shop</a>
  </nav>
  <div class="header-search">
    <span class="search-icon">🔍</span>
    <input type="text" placeholder="Search movies, products...">
  </div>
  <div class="header-actions">
    <button class="icon-btn" onclick="navigateTo('cart')">
      🛒
      <span class="badge" id="cartBadge">0</span>
    </button>
    <button class="icon-btn">
      🔔
      <span class="badge">2</span>
    </button>
    <div id="authGuestActions" class="header-auth-actions" style="display:none">
      <button class="btn btn-secondary btn-sm" onclick="navigateTo('auth');switchAuthTab('login')">Đăng nhập</button>
      <button class="btn btn-primary btn-sm" onclick="navigateTo('auth');switchAuthTab('register')">Đăng ký</button>
    </div>
    <div class="avatar-btn" id="authUserMenu" tabindex="0" style="display:none">
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
