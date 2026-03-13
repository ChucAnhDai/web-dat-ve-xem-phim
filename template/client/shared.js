/* ═══════════════════════════════════════════
   CINEMAX — SHARED JAVASCRIPT
═══════════════════════════════════════════ */

/* ─── SHARED DATA ─── */
const MOVIES = [
  {id:1,title:'Dune: Part Two',genre:'Sci-Fi',rating:8.9,duration:'2h 46m',poster:'https://image.tmdb.org/t/p/w300/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg',status:'now'},
  {id:2,title:'Inside Out 2',genre:'Animation',rating:7.8,duration:'1h 40m',poster:'https://image.tmdb.org/t/p/w300/vpnVM9B6NMmQpWeZvzLvDESb2QY.jpg',status:'now'},
  {id:3,title:'Oppenheimer',genre:'Drama',rating:8.3,duration:'3h 0m',poster:'https://image.tmdb.org/t/p/w300/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg',status:'now'},
  {id:4,title:'Deadpool & Wolverine',genre:'Action',rating:7.9,duration:'2h 7m',poster:'https://image.tmdb.org/t/p/w300/8cdWjvZQUExUUTzyp4t6EDMubfO.jpg',status:'now'},
  {id:5,title:'Kingdom of the Planet of the Apes',genre:'Sci-Fi',rating:7.1,duration:'2h 25m',poster:'https://image.tmdb.org/t/p/w300/gKkl37BQuKTanygYQG1pyYgLVgf.jpg',status:'now'},
  {id:6,title:'The Substance',genre:'Horror',rating:7.5,duration:'2h 21m',poster:'https://image.tmdb.org/t/p/w300/lqoMzCcZYEFK729d6qzt349fB4o.jpg',status:'now'},
  {id:7,title:'A Quiet Place: Day One',genre:'Horror',rating:7.1,duration:'1h 39m',poster:'https://image.tmdb.org/t/p/w300/hukkWxOuHIhNBCaYxhpqxDwbULC.jpg',status:'coming'},
  {id:8,title:'Alien: Romulus',genre:'Horror',rating:7.0,duration:'1h 59m',poster:'https://image.tmdb.org/t/p/w300/b33nnKl1GSFbao4l3fZDDqsMx0F.jpg',status:'coming'},
  {id:9,title:'Gladiator II',genre:'Action',rating:7.2,duration:'2h 28m',poster:'https://image.tmdb.org/t/p/w300/2cxhvwyE0RtuekvA5STUA9sMvPv.jpg',status:'coming'},
  {id:10,title:'Furiosa',genre:'Action',rating:7.8,duration:'2h 28m',poster:'https://image.tmdb.org/t/p/w300/iADOJ8Zymht2JPMoy3R7xceZprc.jpg',status:'coming'},
];

const PRODUCTS = [
  {id:1,name:'Classic Popcorn Combo',cat:'combo',price:12.99,oldPrice:16.99,img:'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&q=80',emoji:'🍿'},
  {id:2,name:'Large Cola',cat:'drinks',price:4.99,oldPrice:null,img:'https://images.unsplash.com/photo-1580031359745-f8ab35a1f9b5?w=400&q=80',emoji:'🥤'},
  {id:3,name:'Nachos & Salsa',cat:'snacks',price:7.99,oldPrice:9.99,img:'https://images.unsplash.com/photo-1513456852971-30c0b8199d4d?w=400&q=80',emoji:'🧀'},
  {id:4,name:'CinemaX T-Shirt',cat:'merch',price:24.99,oldPrice:29.99,img:'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=400&q=80',emoji:'👕'},
  {id:5,name:'VIP Combo Deluxe',cat:'combo',price:22.99,oldPrice:28.99,img:'https://images.unsplash.com/photo-1576866209830-589e1bfbaa4d?w=400&q=80',emoji:'🎉'},
  {id:6,name:'Caramel Popcorn',cat:'popcorn',price:6.99,oldPrice:null,img:'https://images.unsplash.com/photo-1524041255072-7da0525d6b34?w=400&q=80',emoji:'🍿'},
  {id:7,name:'Milkshake Combo',cat:'drinks',price:8.99,oldPrice:10.99,img:'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=400&q=80',emoji:'🥛'},
  {id:8,name:'CinemaX Mug',cat:'merch',price:14.99,oldPrice:null,img:'https://images.unsplash.com/photo-1544787219-7f47ccb76574?w=400&q=80',emoji:'☕'},
];

let CART = JSON.parse(localStorage.getItem('cx_cart') || '[]');
if (!CART.length) {
  CART = [
    {id:1,name:'Classic Popcorn Combo',cat:'combo',price:12.99,qty:1,img:'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=200&q=80'},
    {id:2,name:'Large Cola ×2',cat:'drinks',price:9.98,qty:2,img:'https://images.unsplash.com/photo-1580031359745-f8ab35a1f9b5?w=200&q=80'},
    {id:3,name:'CinemaX T-Shirt',cat:'merch',price:24.99,qty:1,img:'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=200&q=80'},
  ];
}
function saveCart() { try { localStorage.setItem('cx_cart', JSON.stringify(CART)); } catch(e){} }
function cartCount() { return CART.reduce((s,i)=>s+i.qty, 0); }

/* ─── RENDER HELPERS ─── */
function renderMovieCard(m, page='movie-detail.html') {
  return `
  <div class="card movie-card" onclick="location.href='${page}'">
    <div class="movie-poster">
      <img src="${m.poster}" alt="${m.title}" loading="lazy" onerror="this.parentNode.style.background='var(--bg4)'">
      <div class="genre-badge">${m.genre}</div>
      <div class="rating-badge">⭐ ${m.rating}</div>
      <div class="movie-poster-overlay">
        <button class="btn btn-primary btn-sm" onclick="event.stopPropagation();location.href='seat-selection.html'">🎫 Book</button>
        <button class="btn btn-secondary btn-sm" onclick="event.stopPropagation();showToast('🔖','Added','${m.title} added to watchlist.')">+ List</button>
      </div>
    </div>
    <div class="movie-info">
      <div class="movie-title">${m.title}</div>
      <div class="movie-meta"><span>⭐ ${m.rating}</span><span class="dot"></span><span>${m.duration}</span></div>
    </div>
    <div class="movie-actions">
      <button class="btn btn-primary btn-sm" style="flex:1" onclick="event.stopPropagation();location.href='movie-detail.html'">Details</button>
      <button class="btn btn-secondary btn-sm" style="flex:1" onclick="event.stopPropagation();location.href='seat-selection.html'">Book</button>
    </div>
  </div>`;
}

function renderProductCard(p) {
  return `
  <div class="card product-card" onclick="location.href='product-detail.html'">
    <div class="product-img">
      <img src="${p.img}" alt="${p.name}" loading="lazy" onerror="this.parentNode.innerHTML='<div style=\\'display:flex;align-items:center;justify-content:center;height:100%;font-size:48px\\'>${p.emoji}</div>'">
    </div>
    <div class="product-info">
      <div class="product-cat">${p.cat}</div>
      <div class="product-name">${p.name}</div>
      <div class="product-price">$${p.price.toFixed(2)}${p.oldPrice?`<span class="price-old">$${p.oldPrice.toFixed(2)}</span>`:''}</div>
      <button class="btn btn-primary btn-sm btn-full" onclick="event.stopPropagation();addProductToCart(${p.id})">🛒 Add to Cart</button>
    </div>
  </div>`;
}

function addProductToCart(id) {
  const p = PRODUCTS.find(x=>x.id===id);
  if (!p) return;
  const existing = CART.find(x=>x.id===id);
  if (existing) existing.qty++;
  else CART.push({id:p.id,name:p.name,cat:p.cat,price:p.price,qty:1,img:p.img});
  saveCart();
  updateCartBadges();
  showToast('🛒','Added to Cart',`${p.name} added!`);
}

function updateCartBadges() {
  const c = cartCount();
  document.querySelectorAll('#cartBadge,#cartNavBadge').forEach(el => { if(el) el.textContent = c; });
}

/* ─── TOAST ─── */
function showToast(icon, title, msg) {
  let container = document.getElementById('toastContainer');
  if (!container) {
    container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.innerHTML = `<div class="toast-icon">${icon}</div><div><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>`;
  container.appendChild(toast);
  setTimeout(() => { toast.style.animation = 'fadeOut 0.3s ease forwards'; setTimeout(()=>toast.remove(),300); }, 3000);
}

/* ─── SIDEBAR TOGGLE ─── */
function initSidebar() {
  const btn = document.getElementById('hamburgerBtn');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');
  if (!btn || !sidebar) return;
  btn.addEventListener('click', () => { sidebar.classList.toggle('open'); overlay.classList.toggle('active'); });
  overlay && overlay.addEventListener('click', () => { sidebar.classList.remove('open'); overlay.classList.remove('active'); });
}

/* ─── SET ACTIVE NAV ─── */
function setActiveNav(pageId) {
  document.querySelectorAll('.nav-item[data-page], .header-nav a[data-page]').forEach(el => {
    el.classList.toggle('active', el.dataset.page === pageId);
  });
}

/* ─── FILTER CHIPS ─── */
function initFilterChips() {
  document.addEventListener('click', e => {
    if (e.target.classList.contains('filter-chip')) {
      const group = e.target.closest('.filter-bar, .filter-chips-group');
      if (group) group.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
      e.target.classList.add('active');
    }
  });
}

/* ─── SHARED HEADER HTML ─── */
function getHeaderHTML(activePage) {
  return `
  <header class="header">
    <button class="hamburger" id="hamburgerBtn"><span></span><span></span><span></span></button>
    <a class="header-logo" href="index.html"><div class="logo-icon">🎬</div>Cinema<span>X</span></a>
    <nav class="header-nav">
      <a href="index.html" ${activePage==='home'?'class="active"':''}>Home</a>
      <a href="movies.html" ${activePage==='movies'?'class="active"':''}>Movies</a>
      <a href="showtimes.html" ${activePage==='showtimes'?'class="active"':''}>Showtimes</a>
      <a href="shop.html" ${activePage==='shop'?'class="active"':''}>Shop</a>
    </nav>
    <div class="header-search">
      <span class="search-icon">🔍</span>
      <input type="text" placeholder="Search movies, products...">
    </div>
    <div class="header-actions">
      <a href="cart.html" class="icon-btn">🛒<span class="badge" id="cartBadge">3</span></a>
      <button class="icon-btn">🔔<span class="badge">2</span></button>
      <div class="avatar-btn" tabindex="0">JD
        <div class="dropdown">
          <a href="profile.html">👤 My Profile</a>
          <a href="my-tickets.html">🎫 My Tickets</a>
          <a href="my-orders.html">📦 My Orders</a>
          <div class="dropdown-divider"></div>
          <a href="#">🚪 Logout</a>
        </div>
      </div>
    </div>
  </header>`;
}

function getSidebarHTML(activePage) {
  const items = [
    {page:'home',icon:'🏠',label:'Home',href:'index.html'},
    {page:'movies',icon:'🎬',label:'Movies',href:'movies.html'},
    {page:'showtimes',icon:'🕐',label:'Showtimes',href:'showtimes.html'},
    {page:'shop',icon:'🛍️',label:'Shop',href:'shop.html'},
    {page:'cart',icon:'🛒',label:'Cart',href:'cart.html',badge:true},
    {page:'my-tickets',icon:'🎫',label:'My Tickets',href:'my-tickets.html',section:true},
    {page:'my-orders',icon:'📦',label:'My Orders',href:'my-orders.html'},
    {page:'profile',icon:'👤',label:'Profile',href:'profile.html'},
  ];
  let html = `<div class="sidebar-overlay" id="sidebarOverlay"></div><aside class="sidebar" id="sidebar">`;
  let inSection = false;
  items.forEach(item => {
    if (item.section && !inSection) {
      html += `<div class="sidebar-section"><div class="sidebar-label">Account</div>`;
      inSection = true;
    }
    html += `<a href="${item.href}" class="nav-item${activePage===item.page?' active':''}">
      <span class="nav-icon">${item.icon}</span>${item.label}
      ${item.badge?`<span class="nav-badge" id="cartNavBadge">${cartCount()}</span>`:''}
    </a>`;
  });
  if (inSection) html += `</div>`;
  html += `</aside>`;
  return html;
}

function getFooterHTML() {
  return `
  <footer class="footer">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="logo">Cinema<span>X</span></div>
        <p>Your ultimate destination for premium cinema experiences. Enjoy blockbusters, indie films, and exclusive merchandise.</p>
        <div class="footer-social">
          <div class="social-btn">f</div><div class="social-btn">𝕏</div>
          <div class="social-btn">📸</div><div class="social-btn">▶</div>
        </div>
      </div>
      <div class="footer-col"><h4>Movies</h4><ul>
        <li><a href="movies.html">Now Showing</a></li>
        <li><a href="movies.html">Coming Soon</a></li>
        <li><a href="showtimes.html">Showtimes</a></li>
      </ul></div>
      <div class="footer-col"><h4>Shop</h4><ul>
        <li><a href="shop.html">All Products</a></li>
        <li><a href="shop.html">Combos</a></li>
        <li><a href="shop.html">Merchandise</a></li>
      </ul></div>
      <div class="footer-col"><h4>Contact</h4><ul>
        <li><a href="#">📍 123 Cinema Street</a></li>
        <li><a href="#">📞 +1 800 CINEMA</a></li>
        <li><a href="#">✉️ hello@cinemax.com</a></li>
      </ul></div>
    </div>
    <div class="footer-bottom">
      <span>© 2026 CinemaX. All rights reserved.</span>
      <div style="display:flex;gap:16px"><a href="#">Privacy</a><a href="#">Terms</a></div>
    </div>
  </footer>`;
}

/* ─── INJECT LAYOUT ─── */
function injectLayout(activePage) {
  const headerSlot = document.getElementById('header-slot');
  const sidebarSlot = document.getElementById('sidebar-slot');
  const footerSlot = document.getElementById('footer-slot');
  if (headerSlot) headerSlot.outerHTML = getHeaderHTML(activePage);
  if (sidebarSlot) sidebarSlot.outerHTML = getSidebarHTML(activePage);
  if (footerSlot) footerSlot.outerHTML = getFooterHTML();
  updateCartBadges();
  initSidebar();
  initFilterChips();
}
