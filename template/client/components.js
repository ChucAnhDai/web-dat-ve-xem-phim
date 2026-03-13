/* ═══════════════════════════════════════════
   CINEMAX — COMPONENT LOADER (No-fetch version)
   ✅ Hoạt động với VS Code Live Server
   ✅ Hoạt động với file:// trực tiếp
   HTML nhúng thẳng — không cần fetch/CORS
═══════════════════════════════════════════ */

const CX = (() => {

  /* ── HEADER TEMPLATE ── */
  const HEADER_HTML = `
<header class="header">
  <button class="hamburger" id="hamburgerBtn" aria-label="Toggle menu">
    <span></span><span></span><span></span>
  </button>
  <a class="header-logo" href="index.html">
    <div class="logo-icon">🎬</div>Cinema<span>X</span>
  </a>
  <nav class="header-nav">
    <a href="index.html"     data-page="home">Home</a>
    <a href="movies.html"    data-page="movies">Movies</a>
    <a href="showtimes.html" data-page="showtimes">Showtimes</a>
    <a href="shop.html"      data-page="shop">Shop</a>
  </nav>
  <div class="header-search">
    <span class="search-icon">🔍</span>
    <input type="text" id="globalSearch" placeholder="Search movies, products..." autocomplete="off">
    <div class="search-dropdown" id="searchDropdown"></div>
  </div>
  <div class="header-actions">
    <a href="cart.html" class="icon-btn" title="Cart">
      🛒<span class="badge" id="cartBadge" style="display:none">0</span>
    </a>
    <button class="icon-btn" onclick="showToast('🔔','Notifications','2 new notifications.')">
      🔔<span class="badge">2</span>
    </button>
    <div class="avatar-btn" tabindex="0">
      JD
      <div class="dropdown">
        <a href="profile.html">👤 My Profile</a>
        <a href="my-tickets.html">🎫 My Tickets</a>
        <a href="my-orders.html">📦 My Orders</a>
        <div class="dropdown-divider"></div>
        <a href="#" onclick="showToast('👋','Goodbye','You have been logged out.')">🚪 Logout</a>
      </div>
    </div>
  </div>
</header>`;

  /* ── SIDEBAR TEMPLATE ── */
  const SIDEBAR_HTML = `
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<aside class="sidebar" id="sidebar">
  <a href="index.html"     class="nav-item" data-page="home"><span class="nav-icon">🏠</span><span class="nav-label">Home</span></a>
  <a href="movies.html"    class="nav-item" data-page="movies"><span class="nav-icon">🎬</span><span class="nav-label">Movies</span></a>
  <a href="showtimes.html" class="nav-item" data-page="showtimes"><span class="nav-icon">🕐</span><span class="nav-label">Showtimes</span></a>
  <a href="shop.html"      class="nav-item" data-page="shop"><span class="nav-icon">🛍️</span><span class="nav-label">Shop</span></a>
  <a href="cart.html"      class="nav-item" data-page="cart">
    <span class="nav-icon">🛒</span><span class="nav-label">Cart</span>
    <span class="nav-badge" id="cartNavBadge" style="display:none">0</span>
  </a>
  <div class="sidebar-section">
    <div class="sidebar-label">Account</div>
    <a href="my-tickets.html" class="nav-item" data-page="my-tickets"><span class="nav-icon">🎫</span><span class="nav-label">My Tickets</span></a>
    <a href="my-orders.html"  class="nav-item" data-page="my-orders"><span class="nav-icon">📦</span><span class="nav-label">My Orders</span></a>
    <a href="profile.html"    class="nav-item" data-page="profile"><span class="nav-icon">👤</span><span class="nav-label">Profile</span></a>
  </div>
  <div class="sidebar-footer">
    <div class="sidebar-user" onclick="location.href='profile.html'" style="cursor:pointer">
      <div class="sidebar-avatar">JD</div>
      <div class="sidebar-user-info">
        <div class="sidebar-user-name">John Doe</div>
        <div class="sidebar-user-tier">👑 VIP Member</div>
      </div>
    </div>
  </div>
</aside>`;

  /* ── FOOTER TEMPLATE ── */
  const FOOTER_HTML = `
<footer class="footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <a href="index.html" class="footer-logo">Cinema<span>X</span></a>
      <p>Your ultimate destination for premium cinema experiences. Enjoy blockbusters, indie films, and exclusive merchandise all in one place.</p>
      <div class="footer-social">
        <a class="social-btn" href="#">f</a>
        <a class="social-btn" href="#">𝕏</a>
        <a class="social-btn" href="#">📸</a>
        <a class="social-btn" href="#">▶</a>
        <a class="social-btn" href="#">♪</a>
      </div>
    </div>
    <div class="footer-col"><h4>Movies</h4><ul>
      <li><a href="movies.html">Now Showing</a></li>
      <li><a href="movies.html">Coming Soon</a></li>
      <li><a href="showtimes.html">Showtimes</a></li>
      <li><a href="movies.html">All Genres</a></li>
    </ul></div>
    <div class="footer-col"><h4>Shop</h4><ul>
      <li><a href="shop.html">All Products</a></li>
      <li><a href="shop.html">Combo Deals</a></li>
      <li><a href="shop.html">Merchandise</a></li>
      <li><a href="#">Gift Cards</a></li>
    </ul></div>
    <div class="footer-col"><h4>Contact</h4><ul>
      <li><a href="#">📍 123 Cinema Street, HCMC</a></li>
      <li><a href="#">📞 +1 800 CINEMA</a></li>
      <li><a href="#">✉️ hello@cinemax.com</a></li>
      <li><a href="#">💬 Live Chat Support</a></li>
    </ul></div>
  </div>
  <div class="footer-bottom">
    <span>© 2026 CinemaX. All rights reserved.</span>
    <div class="footer-legal">
      <a href="#">Privacy Policy</a>
      <a href="#">Terms of Service</a>
      <a href="#">Cookie Policy</a>
    </div>
  </div>
</footer>`;

  /* ── inject: thay <div id="cx-xxx"> bằng HTML thật ── */
  function inject(slotId, html) {
    const slot = document.getElementById(slotId);
    if (!slot) { console.warn('[CX] slot not found: #' + slotId); return; }
    const tpl = document.createElement('template');
    tpl.innerHTML = html.trim();
    slot.replaceWith(tpl.content);
  }

  /* ── đánh dấu active nav ── */
  function setActive(pageId) {
    document.querySelectorAll('[data-page]').forEach(el =>
      el.classList.toggle('active', el.dataset.page === pageId)
    );
  }

  /* ── hamburger / sidebar toggle ── */
  function initSidebar() {
    const btn     = document.getElementById('hamburgerBtn');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!btn || !sidebar) return;
    const open  = () => { sidebar.classList.add('open');    overlay && overlay.classList.add('active');    };
    const close = () => { sidebar.classList.remove('open'); overlay && overlay.classList.remove('active'); };
    btn.addEventListener('click', () => sidebar.classList.contains('open') ? close() : open());
    overlay && overlay.addEventListener('click', close);
    sidebar.querySelectorAll('a.nav-item').forEach(a =>
      a.addEventListener('click', () => { if (window.innerWidth < 992) close(); })
    );
  }

  /* ── live search ── */
  function initSearch() {
    const input    = document.getElementById('globalSearch');
    const dropdown = document.getElementById('searchDropdown');
    if (!input || !dropdown) return;

    function getAllItems() {
      const r = [];
      if (typeof MOVIES   !== 'undefined') MOVIES.forEach(m   => r.push({ icon:'🎬', label:m.title, sub:`${m.genre} · ${m.duration}`, href:'movie-detail.html'   }));
      if (typeof PRODUCTS !== 'undefined') PRODUCTS.forEach(p => r.push({ icon:'🛍️', label:p.name,  sub:`$${p.price.toFixed(2)}`,     href:'product-detail.html' }));
      return r;
    }
    function hl(text, q) {
      return text.replace(new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')})`, 'gi'),
        '<mark style="background:rgba(229,9,20,0.2);color:#E50914;border-radius:2px;padding:0 2px">$1</mark>');
    }

    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      if (!q) { dropdown.classList.remove('open'); return; }
      const hits = getAllItems().filter(r => r.label.toLowerCase().includes(q)).slice(0, 8);
      dropdown.innerHTML = hits.length
        ? hits.map(r => `<a href="${r.href}" class="search-result-item">
            <span class="search-result-icon">${r.icon}</span>
            <span class="search-result-info">
              <span class="search-result-label">${hl(r.label, q)}</span>
              <span class="search-result-sub">${r.sub}</span>
            </span></a>`).join('')
        : `<div class="search-empty">No results for "<strong>${q}</strong>"</div>`;
      dropdown.classList.add('open');
    });
    document.addEventListener('click', e => {
      if (!e.target.closest('.header-search')) dropdown.classList.remove('open');
    });
    input.addEventListener('keydown', e => { if (e.key === 'Escape') { dropdown.classList.remove('open'); input.blur(); } });
  }

  /* ── cart badge ── */
  function updateCartBadges() {
    const count = typeof cartCount === 'function' ? cartCount() : 0;
    ['cartBadge','cartNavBadge'].forEach(id => {
      const el = document.getElementById(id);
      if (!el) return;
      el.textContent = count;
      el.style.display = count > 0 ? 'flex' : 'none';
    });
  }

  /* ── PUBLIC init ── */
  function init(pageId = '') {
    inject('cx-header',  HEADER_HTML);
    inject('cx-sidebar', SIDEBAR_HTML);
    inject('cx-footer',  FOOTER_HTML);
    setActive(pageId);
    initSidebar();
    initSearch();
    updateCartBadges();
  }

  return { init, updateCartBadges };

})();
