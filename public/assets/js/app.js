const APP_BASE_PATH = typeof window.APP_BASE_PATH === 'string'
  ? window.APP_BASE_PATH
  : '';

const routeMap = {
  home: '/',
  movies: '/movies',
  showtimes: '/showtimes',
  'showtimes-home': '/showtimes',
  shop: '/shop',
  'product-detail': '/shop/product-detail',
  cart: '/cart',
  profile: '/profile',
  auth: '/login',
  login: '/login',
  register: '/register',
  'movie-detail': '/movies',
  'seat-selection': '/showtimes',
  checkout: '/cart',
  'my-tickets': '/my-tickets',
  'my-orders': '/my-orders'
};

const movies = [
  { id: 1, title: 'Dune: Part Two', genre: 'Sci-Fi', rating: 8.9, duration: '2h 46m', poster: 'https://image.tmdb.org/t/p/w300/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg', status: 'now' },
  { id: 2, title: 'Inside Out 2', genre: 'Animation', rating: 7.8, duration: '1h 40m', poster: 'https://image.tmdb.org/t/p/w300/vpnVM9B6NMmQpWeZvzLvDESb2QY.jpg', status: 'now' },
  { id: 3, title: 'Oppenheimer', genre: 'Drama', rating: 8.3, duration: '3h 0m', poster: 'https://image.tmdb.org/t/p/w300/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg', status: 'now' },
  { id: 4, title: 'Deadpool & Wolverine', genre: 'Action', rating: 7.9, duration: '2h 7m', poster: 'https://image.tmdb.org/t/p/w300/8cdWjvZQUExUUTzyp4t6EDMubfO.jpg', status: 'now' },
  { id: 5, title: 'Kingdom of the Planet of the Apes', genre: 'Sci-Fi', rating: 7.1, duration: '2h 25m', poster: 'https://image.tmdb.org/t/p/w300/gKkl37BQuKTanygYQG1pyYgLVgf.jpg', status: 'now' },
  { id: 6, title: 'The Substance', genre: 'Horror', rating: 7.5, duration: '2h 21m', poster: 'https://image.tmdb.org/t/p/w300/lqoMzCcZYEFK729d6qzt349fB4o.jpg', status: 'now' },
  { id: 7, title: 'A Quiet Place: Day One', genre: 'Horror', rating: 7.1, duration: '1h 39m', poster: 'https://image.tmdb.org/t/p/w300/hukkWxOuHIhNBCaYxhpqxDwbULC.jpg', status: 'coming' },
  { id: 8, title: 'Alien: Romulus', genre: 'Horror', rating: 7.0, duration: '1h 59m', poster: 'https://image.tmdb.org/t/p/w300/b33nnKl1GSFbao4l3fZDDqsMx0F.jpg', status: 'coming' },
  { id: 9, title: 'Gladiator II', genre: 'Action', rating: 7.2, duration: '2h 28m', poster: 'https://image.tmdb.org/t/p/w300/2cxhvwyE0RtuekvA5STUA9sMvPv.jpg', status: 'coming' },
  { id: 10, title: 'Furiosa', genre: 'Action', rating: 7.8, duration: '2h 28m', poster: 'https://image.tmdb.org/t/p/w300/iADOJ8Zymht2JPMoy3R7xceZprc.jpg', status: 'coming' }
];

const products = [
  { id: 1, name: 'Classic Popcorn Combo', cat: 'combo', price: 12.99, oldPrice: 16.99, img: 'https://images.unsplash.com/photo-1578849278619-e73505e9610f?auto=format&fit=crop&w=900&q=80', emoji: '🍿' },
  { id: 2, name: 'Large Cola', cat: 'drinks', price: 4.99, oldPrice: null, img: 'https://images.unsplash.com/photo-1622483767028-3f66f32aef97?auto=format&fit=crop&w=900&q=80', emoji: '🥤' },
  { id: 3, name: 'Nachos & Salsa', cat: 'snacks', price: 7.99, oldPrice: 9.99, img: 'https://images.unsplash.com/photo-1513456852971-30c0b8199d4d?auto=format&fit=crop&w=900&q=80', emoji: '🧀' },
  { id: 4, name: 'CinemaX T-Shirt', cat: 'merch', price: 24.99, oldPrice: 29.99, img: 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=900&q=80', emoji: '👕' },
  { id: 5, name: 'VIP Combo Deluxe', cat: 'combo', price: 22.99, oldPrice: 28.99, img: 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?auto=format&fit=crop&w=900&q=80', emoji: '🎉' },
  { id: 6, name: 'Caramel Popcorn', cat: 'popcorn', price: 6.99, oldPrice: null, img: 'https://images.unsplash.com/photo-1585647347384-2593bc35786b?auto=format&fit=crop&w=900&q=80', emoji: '🍿' },
  { id: 7, name: 'Milkshake Combo', cat: 'drinks', price: 8.99, oldPrice: 10.99, img: 'https://images.unsplash.com/photo-1579954115545-a95591f28bfc?auto=format&fit=crop&w=900&q=80', emoji: '🥛' },
  { id: 8, name: 'CinemaX Mug', cat: 'merch', price: 14.99, oldPrice: null, img: 'https://images.unsplash.com/photo-1514228742587-6b1558fcf93a?auto=format&fit=crop&w=900&q=80', emoji: '☕' }
];

let cartItems = [
  { id: 1, name: 'Ultimate Popcorn Combo', cat: 'combo', price: 14.99, qty: 1, img: 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=200&q=80' },
  { id: 2, name: 'Large Cola ×2', cat: 'drinks', price: 9.98, qty: 1, img: 'https://images.unsplash.com/photo-1580031359745-f8ab35a1f9b5?w=200&q=80' },
  { id: 3, name: 'CinemaX T-Shirt', cat: 'merch', price: 24.99, qty: 1, img: 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=200&q=80' }
];

let cartCount = cartItems.length;
let productQty = 1;
let hamburgerBtn;
let sidebar;
let sidebarOverlay;
let currentProfile = null;

function appUrl(path) {
  const normalizedPath = path.startsWith('/') ? path : `/${path}`;
  return `${APP_BASE_PATH}${normalizedPath}`;
}

function isValidEmail(email) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function isValidPhone(phone) {
  return /^[0-9+\s().-]{9,20}$/.test(String(phone || '').trim());
}

function isProfilePage() {
  return Boolean(document.getElementById('profileAvatar'));
}

function redirectProfileToLogin() {
  if (isProfilePage()) {
    window.location.href = appUrl('/login');
  }
}

function formatCurrency(amount) {
  const value = Number(amount || 0);
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD'
  }).format(Number.isFinite(value) ? value : 0);
}

function formatDateLabel(value) {
  if (!value) return 'N/A';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return String(value);
  }

  return new Intl.DateTimeFormat('en-GB', {
    day: '2-digit',
    month: 'short',
    year: 'numeric'
  }).format(date);
}

function getInitials(name) {
  return String(name || '')
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map(part => part[0])
    .join('')
    .toUpperCase();
}

function splitName(name) {
  const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
  return {
    firstName: parts[0] || '',
    lastName: parts.slice(1).join(' ')
  };
}

function titleCase(value) {
  const text = String(value || '').trim();
  return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
}

function formatProfileStatus(status) {
  const value = String(status || '').toLowerCase();
  const labels = {
    paid: 'Paid',
    pending: 'Pending',
    cancelled: 'Cancelled',
    shipping: 'Shipping',
    completed: 'Completed'
  };

  return labels[value] || titleCase(value) || 'Unknown';
}

function profileStatusClass(status) {
  const value = String(status || '').toLowerCase();
  if (value === 'paid' || value === 'completed') return 'success';
  if (value === 'shipping') return 'info';
  if (value === 'cancelled') return 'danger';
  return 'pending';
}

function renderProfileDetails(profile) {
  currentProfile = profile || null;

  const profileData = currentProfile || {};
  const name = profileData.name || 'Account';
  const email = profileData.email || 'No email available';
  const phone = profileData.phone || '';
  const role = titleCase(profileData.role || 'user');
  const memberSince = formatDateLabel(profileData.created_at);
  const initials = getInitials(name) || 'NA';
  const nameParts = splitName(name);
  const stats = profileData.stats || {};

  const avatar = document.getElementById('authAvatarInitials');
  if (avatar) avatar.textContent = initials;

  const profileName = document.getElementById('profileDisplayName') || document.querySelector('.profile-name');
  const profileEmail = document.getElementById('profileDisplayEmail') || document.querySelector('.profile-email');
  if (profileName) profileName.textContent = name;
  if (profileEmail) profileEmail.textContent = email;

  const profileAvatar = document.getElementById('profileAvatar');
  if (profileAvatar) profileAvatar.textContent = initials;

  const firstNameInput = document.getElementById('profileFirstName');
  const lastNameInput = document.getElementById('profileLastName');
  const emailInput = document.getElementById('profileEmailInput');
  const phoneInput = document.getElementById('profilePhoneInput');
  const roleInput = document.getElementById('profileRoleInput');
  const joinedAtInput = document.getElementById('profileJoinedAtInput');

  if (firstNameInput) firstNameInput.value = nameParts.firstName;
  if (lastNameInput) lastNameInput.value = nameParts.lastName;
  if (emailInput) emailInput.value = profileData.email || '';
  if (phoneInput) phoneInput.value = phone;
  if (roleInput) roleInput.value = role;
  if (joinedAtInput) joinedAtInput.value = memberSince;

  const ticketsCount = document.getElementById('profileTicketsCount');
  const ordersCount = document.getElementById('profileOrdersCount');
  const spentAmount = document.getElementById('profileSpentAmount');
  if (ticketsCount) ticketsCount.textContent = String(stats.tickets || 0);
  if (ordersCount) ordersCount.textContent = String(stats.orders || 0);
  if (spentAmount) spentAmount.textContent = formatCurrency(stats.spent || 0);
  
  const sidebarAvatar = document.getElementById('sidebarAvatar');
  const sidebarUserName = document.getElementById('sidebarUserName');
  const sidebarUserTier = document.getElementById('sidebarUserTier');
  if (sidebarAvatar) sidebarAvatar.textContent = initials;
  if (sidebarUserName) sidebarUserName.textContent = name;
  if (sidebarUserTier) sidebarUserTier.textContent = `👑 ${role} Member`;

  renderProfileOrders('profileOrdersBody', profileData.orders || []);
}

function navigateTo(page) {
  if (!routeMap[page]) {
    showToast('ℹ️', 'Tính năng mẫu', 'Trang chi tiết sẽ được tách tiếp ở bước sau.');
    return;
  }

  window.location.href = appUrl(routeMap[page]);
}

function initSidebar() {
  hamburgerBtn = document.getElementById('hamburgerBtn');
  sidebar = document.getElementById('sidebar');
  sidebarOverlay = document.getElementById('sidebarOverlay');
  if (!hamburgerBtn || !sidebar || !sidebarOverlay) return;

  hamburgerBtn.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    sidebarOverlay.classList.toggle('active');
  });
  sidebarOverlay.addEventListener('click', closeSidebar);
}

function closeSidebar() {
  if (!sidebar || !sidebarOverlay) return;
  sidebar.classList.remove('open');
  sidebarOverlay.classList.remove('active');
}

function showToast(icon, title, msg) {
  const container = document.getElementById('toastContainer');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = 'toast';
  toast.innerHTML = `<div class="toast-icon">${icon}</div><div class="toast-text"><div class="toast-title">${title}</div><div class="toast-msg">${msg}</div></div>`;
  container.appendChild(toast);

  setTimeout(() => {
    toast.style.animation = 'fadeOut 0.3s ease forwards';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

function switchAuthTab(tab) {
  navigateTo(tab === 'register' ? 'register' : 'login');
}

function togglePassword(el) {
  const input = el.parentElement.querySelector('input');
  if (!input) return;

  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  el.textContent = isHidden ? 'Ẩn' : 'Hiện';
}

function setAuthErrors(prefix, errors) {
  ['name', 'phone', 'email', 'password'].forEach(field => {
    const el = document.getElementById(`${prefix}${field.charAt(0).toUpperCase()}${field.slice(1)}Error`);
    if (el) el.textContent = errors?.[field]?.[0] || '';
  });
}

function clearAuthErrors(prefix) {
  setAuthErrors(prefix, {});
  ['ConfirmPassword', 'Terms'].forEach(field => {
    const el = document.getElementById(`${prefix}${field}Error`);
    if (el) el.textContent = '';
  });
}

function saveAuthToken(token) {
  if (token) localStorage.setItem('cinemax_token', token);
}

function getAuthToken() {
  return localStorage.getItem('cinemax_token');
}

function clearAuthToken() {
  localStorage.removeItem('cinemax_token');
}

function updateAuthUI(isLoggedIn) {
  const guestActions = document.getElementById('authGuestActions');
  const userMenu = document.getElementById('authUserMenu');
  const sidebarGuest = document.getElementById('sidebarGuestActions');
  const sidebarUser = document.getElementById('sidebarUserActions');

  if (guestActions) guestActions.style.display = isLoggedIn ? 'none' : 'flex';
  if (userMenu) userMenu.style.display = isLoggedIn ? 'flex' : 'none';
  if (sidebarGuest) sidebarGuest.style.display = isLoggedIn ? 'none' : 'block';
  if (sidebarUser) sidebarUser.style.display = isLoggedIn ? 'block' : 'none';

  const sidebarFooter = document.getElementById('sidebarFooter');
  if (sidebarFooter) sidebarFooter.style.display = isLoggedIn ? 'block' : 'none';
}

function ensureAuthForPage() {
  const authOnlyPages = new Set(['profile', 'my-tickets']);
  const activePage = document.body?.dataset?.activePage || '';
  if (!authOnlyPages.has(activePage)) return;

  if (!getAuthToken()) {
    updateAuthUI(false);
    navigateTo('login');
  }
}

async function hydrateProfile() {
  const token = getAuthToken();
  if (!token) {
    currentProfile = null;
    updateAuthUI(false);
    renderProfileOrders('profileOrdersBody', []);
    redirectProfileToLogin();
    return;
  }

  try {
    const res = await fetch(appUrl('/api/auth/profile'), {
      headers: { Authorization: `Bearer ${token}` }
    });
    if (!res.ok) {
      currentProfile = null;
      clearAuthToken();
      updateAuthUI(false);
      renderProfileOrders('profileOrdersBody', []);
      redirectProfileToLogin();
      return;
    }

    const data = await res.json();
    renderProfileDetails(data.data || null);
    updateAuthUI(true);
  } catch (error) {
    currentProfile = null;
    updateAuthUI(false);
    renderProfileOrders('profileOrdersBody', []);
    redirectProfileToLogin();
  }
}

async function updatePassword() {
  const currentPassword = document.getElementById('currentPassword')?.value;
  const newPassword = document.getElementById('newPassword')?.value;
  const confirmPassword = document.getElementById('confirmPassword')?.value;

  if (!currentPassword || !newPassword || !confirmPassword) {
    showToast('⚠️', 'Thiếu thông tin', 'Vui lòng nhập đầy đủ các trường.');
    return;
  }

  if (newPassword !== confirmPassword) {
    showToast('⚠️', 'Lỗi xác nhận', 'Mật khẩu mới không khớp.');
    return;
  }

  const token = getAuthToken();
  if (!token) {
    navigateTo('login');
    return;
  }

  try {
    const res = await fetch(appUrl('/api/auth/update-password'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: JSON.stringify({
        current_password: currentPassword,
        new_password: newPassword,
        confirm_password: confirmPassword
      })
    });

    const data = await res.json();
    if (!res.ok) {
      const firstError = data.errors ? Object.values(data.errors)[0][0] : 'Cập nhật thất bại.';
      showToast('⚠️', 'Lỗi', firstError);
      return;
    }

    showToast('✅', 'Thành công', 'Mật khẩu đã được cập nhật.');
    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
  } catch (error) {
    showToast('⚠️', 'Lỗi kết nối', 'Không thể cập nhật mật khẩu.');
  }
}

function initAuthUI() {
  if (getAuthToken()) {
    hydrateProfile();
  } else {
    updateAuthUI(false);
  }
}

async function handleLogin(event) {
  event.preventDefault();
  clearAuthErrors('login');

  const form = event.target;
  const email = form.email.value.trim();
  const password = form.password.value;

  if (!email) {
    setAuthErrors('login', { email: ['Vui lòng nhập email.'] });
    return;
  }
  if (!isValidEmail(email)) {
    setAuthErrors('login', { email: ['Email không đúng định dạng.'] });
    return;
  }
  if (!password) {
    setAuthErrors('login', { password: ['Vui lòng nhập mật khẩu.'] });
    return;
  }

  try {
    const res = await fetch(appUrl('/api/auth/login'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password })
    });
    const data = await res.json();

    if (!res.ok) {
      setAuthErrors('login', data.errors || {});
      showToast('⚠️', 'Đăng nhập thất bại', 'Vui lòng kiểm tra thông tin.');
      return;
    }

    saveAuthToken(data.data?.token);
    await hydrateProfile();
    showToast('✅', 'Đăng nhập thành công', 'Chào mừng bạn trở lại!');
    navigateTo('home');
  } catch (error) {
    showToast('⚠️', 'Lỗi kết nối', 'Không thể đăng nhập.');
  }
}

async function handleRegister(event) {
  event.preventDefault();
  clearAuthErrors('register');

  const form = event.target;
  const payload = {
    name: form.name.value.trim(),
    phone: form.phone.value.trim(),
    email: form.email.value.trim(),
    password: form.password.value
  };
  const confirmPassword = form.confirm_password?.value || '';
  const acceptedTerms = Boolean(form.terms?.checked);

  if (!payload.phone) {
    setAuthErrors('register', { phone: ['Vui lòng nhập số điện thoại.'] });
    return;
  }
  if (!isValidPhone(payload.phone)) {
    setAuthErrors('register', { phone: ['Số điện thoại không hợp lệ.'] });
    return;
  }
  if (!payload.name) {
    setAuthErrors('register', { name: ['Vui lòng nhập họ tên.'] });
    return;
  }
  if (!payload.email) {
    setAuthErrors('register', { email: ['Vui lòng nhập email.'] });
    return;
  }
  if (!isValidEmail(payload.email)) {
    setAuthErrors('register', { email: ['Email không đúng định dạng.'] });
    return;
  }
  if (!payload.password) {
    setAuthErrors('register', { password: ['Vui lòng nhập mật khẩu.'] });
    return;
  }
  if (payload.password.length < 8) {
    setAuthErrors('register', { password: ['Mật khẩu phải có ít nhất 8 ký tự.'] });
    return;
  }
  if (payload.password !== confirmPassword) {
    const confirmError = document.getElementById('registerConfirmPasswordError');
    if (confirmError) confirmError.textContent = 'Mật khẩu xác nhận không khớp.';
    return;
  }
  if (!acceptedTerms) {
    const termsError = document.getElementById('registerTermsError');
    if (termsError) termsError.textContent = 'Bạn cần đồng ý với điều khoản để tiếp tục.';
    return;
  }

  try {
    const res = await fetch(appUrl('/api/auth/register'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();

    if (!res.ok) {
      setAuthErrors('register', data.errors || {});
      showToast('⚠️', 'Đăng ký thất bại', 'Vui lòng kiểm tra thông tin.');
      return;
    }

    saveAuthToken(data.data?.token);
    await hydrateProfile();
    showToast('🎉', 'Đăng ký thành công', 'Chào mừng bạn đến CinemaX!');
    navigateTo('home');
  } catch (error) {
    showToast('⚠️', 'Lỗi kết nối', 'Không thể đăng ký.');
  }
}

async function handleLogout() {
  const token = getAuthToken();
  if (!token) {
    updateAuthUI(false);
    navigateTo('home');
    return;
  }

  try {
    await fetch(appUrl('/api/auth/logout'), {
      method: 'POST',
      headers: { Authorization: `Bearer ${token}` }
    });
  } catch (error) {
    // ignore
  }

  clearAuthToken();
  updateAuthUI(false);
  showToast('👋', 'Đã đăng xuất', 'Hẹn gặp lại bạn.');
  navigateTo('home');
}

function renderMovieCard(movie) {
  return `
    <div class="card movie-card" onclick="navigateTo('movie-detail')">
      <div class="movie-poster">
        <img src="${movie.poster}" alt="${movie.title}" loading="lazy" onerror="this.parentNode.style.background='var(--bg4)'">
        <div class="genre-badge">${movie.genre}</div>
        <div class="rating-badge">⭐ ${movie.rating}</div>
        <div class="movie-poster-overlay">
          <button class="btn btn-primary btn-sm" onclick="event.stopPropagation();navigateTo('seat-selection')">🎫 Book</button>
          <button class="btn btn-secondary btn-sm" onclick="event.stopPropagation()">+ Watchlist</button>
        </div>
      </div>
      <div class="movie-info">
        <div class="movie-title">${movie.title}</div>
        <div class="movie-meta">
          <span>⭐ ${movie.rating}</span>
          <span class="dot"></span>
          <span>${movie.duration}</span>
        </div>
      </div>
      <div class="movie-actions">
        <button class="btn btn-primary btn-sm" style="flex:1" onclick="event.stopPropagation();navigateTo('movie-detail')">Details</button>
        <button class="btn btn-secondary btn-sm" style="flex:1" onclick="event.stopPropagation();navigateTo('seat-selection')">Book</button>
      </div>
    </div>
  `;
}

function renderProductCard(product) {
  return `
    <div class="card product-card" onclick="navigateTo('product-detail')">
      <div class="product-img">
        <img src="${product.img}" alt="${product.name}" loading="lazy" onerror="this.onerror=null;this.replaceWith(Object.assign(document.createElement('div'),{className:'product-img-fallback',textContent:'${product.emoji}'}))">
      </div>
      <div class="product-info">
        <div class="product-cat">${product.cat}</div>
        <div class="product-name">${product.name}</div>
        <div class="product-price">
          $${product.price.toFixed(2)}
          ${product.oldPrice ? `<span class="price-old">$${product.oldPrice.toFixed(2)}</span>` : ''}
        </div>
        <button class="btn btn-primary btn-sm btn-full" onclick="event.stopPropagation();addToCartProduct('${product.name.replace(/'/g, "\\'")}')">🛒 Add to Cart</button>
      </div>
    </div>
  `;
}

function populateGrids() {
  const nowShowing = movies.filter(movie => movie.status === 'now').slice(0, 5);
  const comingSoon = movies.filter(movie => movie.status === 'coming').slice(0, 5);

  const nowShowingGrid = document.getElementById('nowShowingGrid');
  const comingSoonGrid = document.getElementById('comingSoonGrid');
  const popularProductsGrid = document.getElementById('popularProductsGrid');
  const allMoviesGrid = document.getElementById('allMoviesGrid');
  const shopGrid = document.getElementById('shopGrid');
  const relatedProductsGrid = document.getElementById('relatedProductsGrid');

  if (nowShowingGrid) nowShowingGrid.innerHTML = nowShowing.map(renderMovieCard).join('');
  if (comingSoonGrid) comingSoonGrid.innerHTML = comingSoon.map(renderMovieCard).join('');
  if (popularProductsGrid) popularProductsGrid.innerHTML = products.slice(0, 4).map(renderProductCard).join('');
  if (allMoviesGrid) allMoviesGrid.innerHTML = movies.map(renderMovieCard).join('');
  if (shopGrid) shopGrid.innerHTML = products.map(renderProductCard).join('');
  if (relatedProductsGrid) relatedProductsGrid.innerHTML = products.slice(0, 4).map(renderProductCard).join('');
}

function renderShowtimes() {
  const showtimesGrid = document.getElementById('showtimesGrid');
  if (!showtimesGrid) return;

  const showtimes = [
    { movie: 'Dune: Part Two', poster: 'https://image.tmdb.org/t/p/w200/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg', times: ['1:30 PM', '4:45 PM', '8:00 PM', '10:30 PM'], hall: 'Hall 1 — IMAX' },
    { movie: 'Inside Out 2', poster: 'https://image.tmdb.org/t/p/w200/vpnVM9B6NMmQpWeZvzLvDESb2QY.jpg', times: ['11:00 AM', '2:15 PM', '5:30 PM', '9:15 PM'], hall: 'Hall 3 — Standard' },
    { movie: 'Oppenheimer', poster: 'https://image.tmdb.org/t/p/w200/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg', times: ['3:00 PM', '7:15 PM'], hall: 'Hall 5 — 4DX' }
  ];

  showtimesGrid.innerHTML = showtimes.map(showtime => `
    <div class="card" style="display:flex;gap:16px;padding:16px;margin-bottom:12px;align-items:center;flex-wrap:wrap">
      <img src="${showtime.poster}" alt="${showtime.movie}" style="width:52px;border-radius:6px" onerror="this.style.display='none'">
      <div style="flex:1;min-width:150px">
        <div style="font-size:15px;font-weight:700;margin-bottom:2px">${showtime.movie}</div>
        <div style="font-size:11px;color:var(--text3)">${showtime.hall}</div>
      </div>
      <div class="time-chips" style="flex-wrap:wrap">
        ${showtime.times.map(time => `<div class="time-chip" onclick="showToast('🎫','Suất chiếu mẫu','${showtime.movie} - ${time}')">${time}</div>`).join('')}
      </div>
      <button class="btn btn-primary btn-sm" onclick="navigateTo('movies')">Details</button>
    </div>
  `).join('');
}

function renderOrders(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  container.innerHTML = orders.map(order => `
    <div class="table-row">
      <span class="order-id">${order.id}</span>
      <div class="order-items-preview">
        ${order.imgs.map(img => `<div class="order-item-thumb"><img src="${img}" alt="" onerror="this.style.display='none'"></div>`).join('')}
        ${order.items > 1 ? `<div class="more-items">+${order.items - 1}</div>` : ''}
      </div>
      <span style="font-size:12px;color:var(--text2)">${order.date}</span>
      <span style="font-weight:600">${order.total}</span>
      <span class="ticket-status status-${order.status}">${order.status}</span>
      <button class="btn btn-secondary btn-sm" onclick="showToast('📦','Order ${order.id}','Viewing order details')">View</button>
    </div>
  `).join('');
}

function renderProfileOrders(containerId, ordersData = currentProfile?.orders || []) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const profileOrders = Array.isArray(ordersData) ? ordersData : [];
  if (!profileOrders.length) {
    container.innerHTML = '<div class="table-empty">No order history found for this account yet.</div>';
    return;
  }

  container.innerHTML = profileOrders.map(order => {
    const itemCount = Number(order.items_count || 0);
    const orderType = order.order_type === 'ticket' ? 'Ticket order' : 'Shop order';
    const itemLabel = `${itemCount} ${itemCount === 1 ? 'item' : 'items'}`;
    const statusClass = profileStatusClass(order.status);
    const statusLabel = formatProfileStatus(order.status);

    return `
      <div class="table-row">
        <span class="order-id">${order.order_code}</span>
        <div class="order-items-preview">
          <span class="order-type-badge">${orderType}</span>
          <span class="order-items-text">${itemLabel}</span>
        </div>
        <span style="font-size:12px;color:var(--text2)">${formatDateLabel(order.order_date)}</span>
        <span style="font-weight:600">${formatCurrency(order.total_amount)}</span>
        <span class="ticket-status status-${statusClass}">${statusLabel}</span>
        <button class="btn btn-secondary btn-sm" onclick="showToast('ℹ️','Order details','Detailed order view has not been connected yet.')">View</button>
      </div>
    `;
  }).join('');
}

function switchTab(el, section) {
  document.querySelectorAll('.profile-tab').forEach(tab => tab.classList.remove('active'));
  el.classList.add('active');

  ['profile-info', 'profile-password', 'profile-history'].forEach(id => {
    const sectionEl = document.getElementById(id);
    if (sectionEl) sectionEl.style.display = id === section ? 'block' : 'none';
  });

  if (section === 'profile-history') {
    renderProfileOrders('profileOrdersBody');
  }
}

function updateCartBadges() {
  const cartBadge = document.getElementById('cartBadge');
  const cartNavBadge = document.getElementById('cartNavBadge');
  if (cartBadge) cartBadge.textContent = String(cartCount);
  if (cartNavBadge) cartNavBadge.textContent = String(cartCount);
}

function renderCartItems() {
  const cartItemsList = document.getElementById('cartItemsList');
  if (!cartItemsList) {
    updateCartBadges();
    return;
  }

  cartItemsList.innerHTML = cartItems.map(item => `
    <div class="cart-item-row">
      <div class="cart-item-product">
        <div class="cart-item-img"><img src="${item.img}" alt="${item.name}" onerror="this.style.display='none'"></div>
        <div>
          <div class="cart-item-name">${item.name}</div>
          <div class="cart-item-cat">${item.cat}</div>
        </div>
      </div>
      <div class="cart-price">$${item.price.toFixed(2)}</div>
      <div class="qty-control">
        <div class="qty-btn" onclick="changeCartQty(${item.id}, -1)">−</div>
        <div class="qty-val">${item.qty}</div>
        <div class="qty-btn" onclick="changeCartQty(${item.id}, 1)">+</div>
      </div>
      <div class="cart-subtotal">$${(item.price * item.qty).toFixed(2)}</div>
      <div class="remove-btn" onclick="removeCartItem(${item.id})">✕</div>
    </div>
  `).join('');

  updateCartBadges();
}

function changeCartQty(id, delta) {
  const item = cartItems.find(cartItem => cartItem.id === id);
  if (!item) return;

  item.qty = Math.max(1, item.qty + delta);
  renderCartItems();
}

function removeCartItem(id) {
  const target = cartItems.find(cartItem => cartItem.id === id);
  cartItems = cartItems.filter(cartItem => cartItem.id !== id);
  cartCount = cartItems.length;
  renderCartItems();

  if (target) {
    showToast('🗑️', 'Removed', `${target.name} removed from cart.`);
  }
}

function addToCartProduct(name) {
  cartCount += 1;
  updateCartBadges();
  showToast('🛒', 'Added to Cart', `${name} has been added!`);
}

function addToCart() {
  addToCartProduct('Ultimate Popcorn Combo');
}

function buyNow() {
  addToCart();
  navigateTo('cart');
}

function changeQty(delta) {
  productQty = Math.max(1, Math.min(10, productQty + delta));
  const quantityEl = document.getElementById('productQty');
  if (quantityEl) quantityEl.textContent = String(productQty);
}

document.addEventListener('click', event => {
  const catTab = event.target.closest('[data-cat]');
  if (catTab) {
    document.querySelectorAll('.cat-tab').forEach(tab => tab.classList.remove('active'));
    catTab.classList.add('active');

    const category = catTab.dataset.cat;
    const filteredProducts = category === 'all'
      ? products
      : products.filter(product => product.cat === category);
    const shopGrid = document.getElementById('shopGrid');
    if (shopGrid) {
      shopGrid.innerHTML = filteredProducts.map(renderProductCard).join('');
    }
  }

  if (event.target.classList.contains('filter-chip')) {
    const parent = event.target.parentElement;
    if (parent) {
      parent.querySelectorAll('.filter-chip').forEach(chip => chip.classList.remove('active'));
    }
    event.target.classList.add('active');
  }

  const productThumb = event.target.closest('.product-thumb');
  if (productThumb) {
    document.querySelectorAll('.product-thumb').forEach(thumb => thumb.classList.remove('active'));
    productThumb.classList.add('active');
    const mainImage = document.getElementById('mainProductImg');
    const thumbImage = productThumb.querySelector('img');
    if (mainImage && thumbImage) {
      mainImage.src = thumbImage.src.replace('w=200', 'w=600');
    }
  }
});

document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initAuthUI();
  ensureAuthForPage();

  const loginForm = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  if (loginForm) loginForm.addEventListener('submit', handleLogin);
  if (registerForm) registerForm.addEventListener('submit', handleRegister);

  populateGrids();
  renderShowtimes();
  renderCartItems();
  updateCartBadges();
});
