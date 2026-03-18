<?php
$currentAdminPage = $activePage ?? 'dashboard';

$navClass = static function (string $page) use ($currentAdminPage): string {
    return $currentAdminPage === $page ? ' active' : '';
};

$navHref = static function (string $path, ?string $section = null) use ($appBase): string {
    $href = rtrim((string) $appBase, '/') . $path;

    if ($section !== null && $section !== '') {
        $href .= '?section=' . rawurlencode($section);
    }

    return htmlspecialchars($href, ENT_QUOTES, 'UTF-8');
};
?>
<nav class="sidebar" id="sidebar">
  <div class="sidebar-logo">
    <a href="<?php echo $navHref('/admin'); ?>" class="logo-mark">CS</a>
    <a href="<?php echo $navHref('/admin'); ?>" class="logo-text">
      <div class="logo-title">CineShop</div>
      <div class="logo-sub">Admin Panel</div>
    </a>
  </div>

  <div class="sidebar-scroll">

    <div class="nav-section">
      <a class="nav-item<?php echo $navClass('dashboard'); ?>" data-page="dashboard" href="<?php echo $navHref('/admin'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <span>Dashboard</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Movie Management</div>
      <a class="nav-item<?php echo $navClass('movies'); ?>" data-page="movies" href="<?php echo $navHref('/admin/movies', 'movies'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2"/><path d="M7 2v20M17 2v20M2 12h20M2 7h5M17 7h5M2 17h5M17 17h5"/></svg>
        <span>Movies</span>
      </a>
      <a class="nav-item<?php echo $navClass('categories'); ?>" data-page="categories" href="<?php echo $navHref('/admin/movies', 'categories'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
        <span>Categories</span>
      </a>
      <a class="nav-item<?php echo $navClass('movie-images'); ?>" data-page="movie-images" href="<?php echo $navHref('/admin/movies', 'movie-images'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        <span>Movie Images</span>
      </a>
      <a class="nav-item<?php echo $navClass('reviews'); ?>" data-page="reviews" href="<?php echo $navHref('/admin/movies', 'reviews'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
        <span>Reviews</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Cinema Management</div>
      <a class="nav-item<?php echo $navClass('cinemas'); ?>" data-page="cinemas" href="<?php echo $navHref('/admin/cinemas', 'cinemas'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        <span>Cinemas</span>
      </a>
      <a class="nav-item<?php echo $navClass('rooms'); ?>" data-page="rooms" href="<?php echo $navHref('/admin/cinemas', 'rooms'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
        <span>Rooms</span>
      </a>
      <a class="nav-item<?php echo $navClass('seats'); ?>" data-page="seats" href="<?php echo $navHref('/admin/seats'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13V7a2 2 0 012-2h10a2 2 0 012 2v6M5 13H3v5h18v-5h-2M5 13h14"/><path d="M8 21v-3M16 21v-3"/></svg>
        <span>Seats</span>
      </a>
      <a class="nav-item<?php echo $navClass('showtimes'); ?>" data-page="showtimes" href="<?php echo $navHref('/admin/showtimes'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
        <span>Showtimes</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Ticket System</div>
      <a class="nav-item<?php echo $navClass('ticket-orders'); ?>" data-page="ticket-orders" href="<?php echo $navHref('/admin/ticket-orders', 'ticket-orders'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12v6a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h6"/><path d="M14.5 4h5.5v5.5"/><path d="M14 10l6-6"/></svg>
        <span>Ticket Orders</span>
      </a>
      <a class="nav-item<?php echo $navClass('ticket-details'); ?>" data-page="ticket-details" href="<?php echo $navHref('/admin/ticket-orders', 'ticket-details'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        <span>Ticket Details</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Shop Management</div>
      <a class="nav-item<?php echo $navClass('products'); ?>" data-page="products" href="<?php echo $navHref('/admin/products', 'products'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
        <span>Products</span>
      </a>
      <a class="nav-item<?php echo $navClass('product-categories'); ?>" data-page="product-categories" href="<?php echo $navHref('/admin/products', 'product-categories'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h16M4 18h7"/></svg>
        <span>Product Categories</span>
      </a>
      <a class="nav-item<?php echo $navClass('product-images'); ?>" data-page="product-images" href="<?php echo $navHref('/admin/products', 'product-images'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
        <span>Product Images</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Shop Orders</div>
      <a class="nav-item<?php echo $navClass('shop-orders'); ?>" data-page="shop-orders" href="<?php echo $navHref('/admin/shop-orders', 'shop-orders'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg>
        <span>Shop Orders</span>
        <div class="nav-badge" id="shopOrdersNavBadge">0</div>
      </a>
      <a class="nav-item<?php echo $navClass('order-details'); ?>" data-page="order-details" href="<?php echo $navHref('/admin/shop-orders', 'order-details'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
        <span>Order Details</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Payments</div>
      <a class="nav-item<?php echo $navClass('payments'); ?>" data-page="payments" href="<?php echo $navHref('/admin/payments', 'payments'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        <span>Payment History</span>
      </a>
      <a class="nav-item<?php echo $navClass('payment-methods'); ?>" data-page="payment-methods" href="<?php echo $navHref('/admin/payments', 'payment-methods'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span>Payment Methods</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Promotions</div>
      <a class="nav-item<?php echo $navClass('promotions'); ?>" data-page="promotions" href="<?php echo $navHref('/admin/promotions', 'promotions'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
        <span>Promotions</span>
      </a>
      <a class="nav-item<?php echo $navClass('product-promotions'); ?>" data-page="product-promotions" href="<?php echo $navHref('/admin/promotions', 'product-promotions'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
        <span>Product Promotions</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Content</div>
      <a class="nav-item<?php echo $navClass('banners'); ?>" data-page="banners" href="<?php echo $navHref('/admin', 'banners'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="5" rx="1"/><rect x="3" y="11" width="7" height="10" rx="1"/><rect x="13" y="11" width="8" height="5" rx="1"/><rect x="13" y="19" width="8" height="2" rx="1"/></svg>
        <span>Banners</span>
      </a>
      <a class="nav-item<?php echo $navClass('notifications'); ?>" data-page="notifications" href="<?php echo $navHref('/admin', 'notifications'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
        <span>Notifications</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">User Management</div>
      <a class="nav-item<?php echo $navClass('users'); ?>" data-page="users" href="<?php echo $navHref('/admin/users', 'users'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
        <span>Users</span>
      </a>
      <a class="nav-item<?php echo $navClass('user-addresses'); ?>" data-page="user-addresses" href="<?php echo $navHref('/admin/users', 'user-addresses'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <span>User Addresses</span>
      </a>
      <a class="nav-item<?php echo $navClass('roles'); ?>" data-page="roles" href="<?php echo $navHref('/admin/users', 'roles'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span>Roles</span>
      </a>
    </div>

    <div class="nav-section">
      <div class="nav-label">Settings</div>
      <a class="nav-item<?php echo $navClass('system-settings'); ?>" data-page="system-settings" href="<?php echo $navHref('/admin', 'system-settings'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/></svg>
        <span>System Settings</span>
      </a>
      <a class="nav-item<?php echo $navClass('admin-profile'); ?>" data-page="admin-profile" href="<?php echo $navHref('/admin', 'admin-profile'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span>Admin Profile</span>
      </a>
    </div>

  </div>
</nav>
