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
  'shop-checkout': '/shop/checkout',
  cart: '/cart',
  profile: '/profile',
  auth: '/login',
  login: '/login',
  register: '/register',
  'movie-detail': '/movie-detail',
  'seat-selection': '/showtimes',
  checkout: '/checkout',
  'my-tickets': '/my-tickets',
  'my-orders': '/my-orders',
  'guest-order-lookup': '/my-orders?lookup=1'
};

const movies = [
  { id: 1, slug: 'dune-part-two', title: 'Dune: Part Two', primary_category: 'Sci-Fi', average_rating: 4.8, duration_minutes: 166, poster_url: 'https://image.tmdb.org/t/p/w300/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg', release_date: '2024-03-01', status: 'now_showing' },
  { id: 2, slug: 'inside-out-2', title: 'Inside Out 2', primary_category: 'Animation', average_rating: 4.3, duration_minutes: 100, poster_url: 'https://image.tmdb.org/t/p/w300/vpnVM9B6NMmQpWeZvzLvDESb2QY.jpg', release_date: '2024-06-14', status: 'now_showing' },
  { id: 3, slug: 'oppenheimer', title: 'Oppenheimer', primary_category: 'Drama', average_rating: 4.6, duration_minutes: 180, poster_url: 'https://image.tmdb.org/t/p/w300/8Gxv8gSFCU0XGDykEGv7zR1n2ua.jpg', release_date: '2023-07-21', status: 'now_showing' },
  { id: 4, slug: 'deadpool-wolverine', title: 'Deadpool & Wolverine', primary_category: 'Action', average_rating: 4.4, duration_minutes: 127, poster_url: 'https://image.tmdb.org/t/p/w300/8cdWjvZQUExUUTzyp4t6EDMubfO.jpg', release_date: '2024-07-26', status: 'now_showing' },
  { id: 5, slug: 'kingdom-of-the-planet-of-the-apes', title: 'Kingdom of the Planet of the Apes', primary_category: 'Sci-Fi', average_rating: 4.1, duration_minutes: 145, poster_url: 'https://image.tmdb.org/t/p/w300/gKkl37BQuKTanygYQG1pyYgLVgf.jpg', release_date: '2024-05-10', status: 'now_showing' },
  { id: 6, slug: 'the-substance', title: 'The Substance', primary_category: 'Horror', average_rating: 4.2, duration_minutes: 141, poster_url: 'https://image.tmdb.org/t/p/w300/lqoMzCcZYEFK729d6qzt349fB4o.jpg', release_date: '2024-09-20', status: 'now_showing' },
  { id: 7, slug: 'a-quiet-place-day-one', title: 'A Quiet Place: Day One', primary_category: 'Horror', average_rating: 4.0, duration_minutes: 99, poster_url: 'https://image.tmdb.org/t/p/w300/hukkWxOuHIhNBCaYxhpqxDwbULC.jpg', release_date: '2024-06-28', status: 'coming_soon' },
  { id: 8, slug: 'alien-romulus', title: 'Alien: Romulus', primary_category: 'Horror', average_rating: 4.0, duration_minutes: 119, poster_url: 'https://image.tmdb.org/t/p/w300/b33nnKl1GSFbao4l3fZDDqsMx0F.jpg', release_date: '2024-08-16', status: 'coming_soon' },
  { id: 9, slug: 'gladiator-ii', title: 'Gladiator II', primary_category: 'Action', average_rating: 4.1, duration_minutes: 148, poster_url: 'https://image.tmdb.org/t/p/w300/2cxhvwyE0RtuekvA5STUA9sMvPv.jpg', release_date: '2024-11-22', status: 'coming_soon' },
  { id: 10, slug: 'furiosa', title: 'Furiosa', primary_category: 'Action', average_rating: 4.4, duration_minutes: 148, poster_url: 'https://image.tmdb.org/t/p/w300/iADOJ8Zymht2JPMoy3R7xceZprc.jpg', release_date: '2024-05-24', status: 'coming_soon' }
];

movies.forEach(movie => {
  movie.genre = movie.primary_category;
  movie.rating = movie.average_rating;
  movie.duration = formatMovieDuration(movie.duration_minutes);
  movie.poster = movie.poster_url;
});

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

const shopCatalogState = {
  categories: [],
  items: [],
  relatedItems: [],
  activeCategorySlug: 'all',
  search: '',
  sort: 'featured',
  priceRange: 'all',
  currentPage: 1,
  totalPages: 1,
  totalItems: 0,
  currentProduct: null,
  gallery: []
};

let shopSearchDebounce = null;

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

function formatShopCurrency(amount, currency = 'VND') {
  const value = Number(amount || 0);
  const normalizedCurrency = String(currency || 'VND').toUpperCase();
  const locale = normalizedCurrency === 'VND' ? 'vi-VN' : 'en-US';

  return new Intl.NumberFormat(locale, {
    style: 'currency',
    currency: normalizedCurrency,
    maximumFractionDigits: normalizedCurrency === 'VND' ? 0 : 2
  }).format(Number.isFinite(value) ? value : 0);
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function productDetailUrl(slug) {
  return appUrl(`/shop/product-detail?slug=${encodeURIComponent(slug || '')}`);
}

function shopApiUrl(path, params = {}) {
  const query = new URLSearchParams();

  Object.entries(params).forEach(([key, value]) => {
    if (value === null || value === undefined || value === '' || value === 'all') {
      return;
    }

    query.set(key, String(value));
  });

  const suffix = query.toString() ? `?${query.toString()}` : '';
  return appUrl(`${path}${suffix}`);
}

async function fetchJson(url, options = {}) {
  const headers = new Headers(options.headers || {});
  if (!headers.has('Authorization')) {
    const token = getAuthToken();
    if (token) {
      headers.set('Authorization', `Bearer ${token}`);
    }
  }

  const response = await fetch(url, {
    credentials: 'same-origin',
    ...options,
    headers
  });
  const payload = await response.json().catch(() => ({}));

  if (!response.ok) {
    const firstError = payload?.errors
      ? Object.values(payload.errors)[0]?.[0]
      : 'Request failed.';
    throw new Error(firstError || 'Request failed.');
  }

  return payload?.data || {};
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

function formatMovieDuration(minutes) {
  const totalMinutes = Number(minutes || 0);
  if (!Number.isFinite(totalMinutes) || totalMinutes <= 0) return 'N/A';

  const hours = Math.floor(totalMinutes / 60);
  const mins = totalMinutes % 60;
  if (!hours) return `${mins}m`;

  return mins ? `${hours}h ${mins}m` : `${hours}h`;
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

function profileOrderHref(order) {
  const orderType = String(order?.order_type || '').trim().toLowerCase();
  const orderId = Number(order?.order_id || 0);

  if (orderType === 'shop' && Number.isInteger(orderId) && orderId > 0) {
    return appUrl(`/my-orders?open=${encodeURIComponent(String(orderId))}`);
  }

  if (orderType === 'ticket') {
    return appUrl('/my-tickets');
  }

  return '';
}

function profileOrderActionLabel(order) {
  return String(order?.order_type || '').trim().toLowerCase() === 'ticket'
    ? 'Open Tickets'
    : 'View';
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
  const fieldMap = {
    name: 'Name',
    phone: 'Phone',
    email: 'Email',
    identifier: 'Identifier',
    password: 'Password'
  };

  Object.entries(fieldMap).forEach(([field, suffix]) => {
    const el = document.getElementById(`${prefix}${suffix}Error`);
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
  if (token) {
    localStorage.setItem('cinemax_token', token);
  }

  document.dispatchEvent(new CustomEvent('cinemax:auth-changed', {
    detail: { isLoggedIn: Boolean(token) }
  }));
}

function getAuthToken() {
  return localStorage.getItem('cinemax_token');
}

function clearAuthToken() {
  localStorage.removeItem('cinemax_token');
  document.dispatchEvent(new CustomEvent('cinemax:auth-changed', {
    detail: { isLoggedIn: false }
  }));
}

Object.assign(window, {
  saveAuthToken,
  getAuthToken,
  clearAuthToken
});

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
  const identifier = String(form.identifier?.value || form.email?.value || '').trim();
  const password = form.password.value;

  if (!identifier) {
    setAuthErrors('login', { identifier: ['Vui l貌ng nh岷璸 email ho岷 s峄?膽i峄噉 tho岷.'] });
    return;
  }
  if (!password) {
    setAuthErrors('login', { password: ['Vui l貌ng nh岷璸 m岷璽 kh岷﹗.'] });
    return;
  }

  try {
    const res = await fetch(appUrl('/api/auth/login'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ identifier, password })
    });
    const data = await res.json();

    if (!res.ok) {
      setAuthErrors('login', data.errors || {});
      showToast('鈿狅笍', '膼膬ng nh岷璸 th岷 b岷', 'Vui l貌ng ki峄僲 tra th么ng tin.');
      return;
    }

    saveAuthToken(data.data?.token);
    await hydrateProfile();
    showToast('✅', 'Đăng nhập thành công', 'Chào mừng bạn trở lại!');
    navigateTo('home');
  } catch (error) {
    showToast('鈿狅笍', 'L峄梚 k岷縯 n峄慽', 'Kh么ng th峄?膽膬ng nh岷璸.');
  }

  return;
  /*

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
      body: JSON.stringify({ identifier, password })
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
  */
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
  const detailUrl = movie?.slug
    ? appUrl(`/movie-detail?slug=${encodeURIComponent(movie.slug)}`)
    : appUrl('/movies');
  const bookUrl = movie?.status === 'now_showing'
    ? detailUrl
    : appUrl('/movies');

  return `
    <div class="card movie-card" onclick="window.location.href='${detailUrl}'">
      <div class="movie-poster">
        <img src="${movie.poster_url}" alt="${movie.title}" loading="lazy" onerror="this.parentNode.style.background='var(--bg4)'">
        <div class="genre-badge">${movie.primary_category}</div>
        <div class="rating-badge">⭐ ${movie.rating}</div>
        <div class="movie-poster-overlay">
          <button class="btn btn-primary btn-sm" onclick="event.stopPropagation();window.location.href='${bookUrl}'">🎫 Book</button>
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
        <button class="btn btn-primary btn-sm" style="flex:1" onclick="event.stopPropagation();window.location.href='${detailUrl}'">Details</button>
        <button class="btn btn-secondary btn-sm" style="flex:1" onclick="event.stopPropagation();window.location.href='${bookUrl}'">Book</button>
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
  const nowShowing = movies.filter(movie => movie.status === 'now_showing').slice(0, 5);
  const comingSoon = movies.filter(movie => movie.status === 'coming_soon').slice(0, 5);
  const activePage = document.body?.dataset?.activePage || '';

  const nowShowingGrid = document.getElementById('nowShowingGrid');
  const comingSoonGrid = document.getElementById('comingSoonGrid');
  const popularProductsGrid = document.getElementById('popularProductsGrid');
  const allMoviesGrid = document.getElementById('allMoviesGrid');
  const shopGrid = document.getElementById('shopGrid');
  const relatedProductsGrid = document.getElementById('relatedProductsGrid');

  if (nowShowingGrid) nowShowingGrid.innerHTML = nowShowing.map(renderMovieCard).join('');
  if (comingSoonGrid) comingSoonGrid.innerHTML = comingSoon.map(renderMovieCard).join('');
  if (popularProductsGrid) popularProductsGrid.innerHTML = products.slice(0, 4).map(renderProductCard).join('');
  if (allMoviesGrid && activePage !== 'movies') allMoviesGrid.innerHTML = movies.map(renderMovieCard).join('');
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

  container.querySelectorAll('button.btn.btn-secondary.btn-sm').forEach((button, index) => {
    const order = profileOrders[index] || null;
    const actionHref = profileOrderHref(order);

    button.removeAttribute('onclick');
    if (!actionHref) {
      button.textContent = 'Unavailable';
      button.disabled = true;
      return;
    }

    button.textContent = profileOrderActionLabel(order);
    button.addEventListener('click', event => {
      event.preventDefault();
      window.location.href = actionHref;
    });
  });
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
  if (typeof window.updateCartBadges === 'function' && window.updateCartBadges !== updateCartBadges) {
    window.updateCartBadges();
    return;
  }

  const cartBadge = document.getElementById('cartBadge');
  const cartNavBadge = document.getElementById('cartNavBadge');
  if (cartBadge) cartBadge.textContent = String(cartCount);
  if (cartNavBadge) cartNavBadge.textContent = String(cartCount);
}

function renderCartItems() {
  if (typeof window.renderCartItems === 'function' && window.renderCartItems !== renderCartItems) {
    window.renderCartItems();
    return;
  }

  const cartItemsList = document.getElementById('cartItemsList');
  const cartContent = document.getElementById('cartContent');
  const cartEmptyState = document.getElementById('cartEmptyState');
  const cartSubtitle = document.getElementById('cartSubtitle');

  if (cartSubtitle) {
    cartSubtitle.textContent = `${cartItems.length} items in your cart`;
  }

  if (!cartItemsList) {
    updateCartBadges();
    return;
  }

  if (cartItems.length === 0) {
    if (cartContent) cartContent.style.display = 'none';
    if (cartEmptyState) cartEmptyState.style.display = 'flex';
  } else {
    if (cartContent) cartContent.style.display = 'grid';
    if (cartEmptyState) cartEmptyState.style.display = 'none';

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
  }

  // Cập nhật tóm tắt đơn hàng
  const subtotal = cartItems.reduce((acc, item) => acc + (item.price * item.qty), 0);
  const discount = subtotal > 40 ? 5.00 : 0.00; // Giảm giá giả lập
  const total = Math.max(0, subtotal - discount);

  const subtotalLabelEl = document.getElementById('summarySubtotalLabel');
  const subtotalEl = document.getElementById('summarySubtotal');
  const discountEl = document.getElementById('summaryDiscount');
  const totalEl = document.getElementById('summaryTotal');

  if (subtotalLabelEl) subtotalLabelEl.textContent = `Subtotal (${cartItems.length} items)`;
  if (subtotalEl) subtotalEl.textContent = formatCurrency(subtotal);
  if (discountEl) discountEl.textContent = discount > 0 ? `− ${formatCurrency(discount)}` : '$0.00';
  if (totalEl) totalEl.textContent = formatCurrency(total);

  updateCartBadges();
}



function changeCartQty(id, delta) {
  if (typeof window.changeCartQty === 'function' && window.changeCartQty !== changeCartQty) {
    window.changeCartQty(id, delta);
    return;
  }

  const item = cartItems.find(cartItem => cartItem.id === id);
  if (!item) return;

  item.qty = Math.max(1, item.qty + delta);
  renderCartItems();
}

function removeCartItem(id) {
  if (typeof window.removeCartItem === 'function' && window.removeCartItem !== removeCartItem) {
    window.removeCartItem(id);
    return;
  }

  const target = cartItems.find(cartItem => cartItem.id === id);
  cartItems = cartItems.filter(cartItem => cartItem.id !== id);
  cartCount = cartItems.length;
  renderCartItems();

  if (target) {
    showToast('🗑️', 'Removed', `${target.name} removed from cart.`);
  }
}

function clearCart() {
  if (typeof window.clearCart === 'function' && window.clearCart !== clearCart) {
    window.clearCart();
    return;
  }

  if (cartItems.length === 0) {
    showToast('ℹ️', 'Empty Cart', 'Your cart is already empty.');
    return;
  }
  
  cartItems = [];
  cartCount = 0;
  renderCartItems();
  showToast('🗑️', 'Cart Cleared', 'All items have been removed.');
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

function shopCartMaxQuantity() {
  return Math.max(1, Number(window.SHOP_RUNTIME_CONFIG?.cart?.max_quantity_per_item || 10));
}

function changeQty(delta) {
  const product = shopCatalogState.currentProduct || {};
  const maxByConfig = shopCartMaxQuantity();
  const maxByStock = Number(product?.track_inventory) === 1
    ? Math.max(1, Number(product?.stock || 0))
    : maxByConfig;
  const maxQuantity = Math.max(1, Math.min(maxByConfig, maxByStock));

  productQty = Math.max(1, Math.min(maxQuantity, productQty + delta));
  const quantityEl = document.getElementById('productQty');
  if (quantityEl) quantityEl.textContent = String(productQty);
}

function renderProductCard(product) {
  const detailUrl = product?.slug
    ? productDetailUrl(product.slug)
    : appUrl('/shop');
  const imageUrl = product?.primary_image_url || '';
  const imageAlt = escapeHtml(product?.primary_image_alt || product?.name || 'Product');
  const categoryName = escapeHtml(product?.category_name || 'Shop item');
  const productName = escapeHtml(product?.name || 'Product');
  const summary = escapeHtml(product?.summary || 'Freshly curated for your next movie night.');
  const price = formatShopCurrency(product?.price || 0, product?.currency || 'VND');
  const canAddToCart = product?.is_available !== false && String(product?.stock_state || 'in_stock') !== 'out_of_stock';
  const addButtonText = canAddToCart ? 'Add to Cart' : 'Out of Stock';
  const compareAtPrice = product?.compare_at_price
    ? `<span class="price-old">${formatShopCurrency(product.compare_at_price, product.currency || 'VND')}</span>`
    : '';

  return `
    <div class="card product-card" data-product-link="${detailUrl}">
      <div class="product-img">
        ${imageUrl
          ? `<img src="${escapeHtml(imageUrl)}" alt="${imageAlt}" loading="lazy" onerror="this.onerror=null;this.insertAdjacentHTML('afterend','<div class=&quot;product-img-fallback&quot;>Item</div>');this.remove()">`
          : '<div class="product-img-fallback">Item</div>'}
      </div>
      <div class="product-info">
        <div class="product-cat">${categoryName}</div>
        <div class="product-name">${productName}</div>
        <div class="product-summary">${summary}</div>
        <div class="product-price">
          ${price}
          ${compareAtPrice}
        </div>
        <button
          class="btn btn-primary btn-sm btn-full"
          type="button"
          data-add-product-id="${Number(product?.id || 0)}"
          data-add-product-name="${productName}"
          ${canAddToCart ? '' : 'disabled'}
        >${addButtonText}</button>
      </div>
    </div>
  `;
}

function populateGrids() {
  const nowShowing = movies.filter(movie => movie.status === 'now_showing').slice(0, 5);
  const comingSoon = movies.filter(movie => movie.status === 'coming_soon').slice(0, 5);
  const activePage = document.body?.dataset?.activePage || '';

  const nowShowingGrid = document.getElementById('nowShowingGrid');
  const comingSoonGrid = document.getElementById('comingSoonGrid');
  const allMoviesGrid = document.getElementById('allMoviesGrid');

  if (nowShowingGrid) nowShowingGrid.innerHTML = nowShowing.map(renderMovieCard).join('');
  if (comingSoonGrid) comingSoonGrid.innerHTML = comingSoon.map(renderMovieCard).join('');
  if (allMoviesGrid && activePage !== 'movies') allMoviesGrid.innerHTML = movies.map(renderMovieCard).join('');
}

function priceRangeBounds(range) {
  switch (range) {
    case 'under_100000':
      return { max_price: 100000 };
    case '100000_200000':
      return { min_price: 100000, max_price: 200000 };
    case 'over_200000':
      return { min_price: 200000 };
    default:
      return {};
  }
}

function stockBadgeState(stockState) {
  const normalized = String(stockState || 'in_stock').toLowerCase();

  if (normalized === 'out_of_stock') {
    return { label: 'Out of Stock', className: 'out-of-stock' };
  }
  if (normalized === 'low_stock') {
    return { label: 'Low Stock', className: 'low-stock' };
  }

  return { label: 'In Stock', className: 'in-stock' };
}

function renderShopPaginationButtons() {
  const totalPages = Number(shopCatalogState.totalPages || 1);
  const currentPage = Number(shopCatalogState.currentPage || 1);
  if (totalPages <= 1) return '';

  const buttons = [];
  const start = Math.max(1, currentPage - 2);
  const end = Math.min(totalPages, start + 4);

  buttons.push(`
    <button class="catalog-page-btn" type="button" data-shop-page="${Math.max(1, currentPage - 1)}" ${currentPage <= 1 ? 'disabled' : ''}>
      Prev
    </button>
  `);

  for (let page = start; page <= end; page += 1) {
    buttons.push(`
      <button class="catalog-page-btn ${page === currentPage ? 'active' : ''}" type="button" data-shop-page="${page}">
        ${page}
      </button>
    `);
  }

  buttons.push(`
    <button class="catalog-page-btn" type="button" data-shop-page="${Math.min(totalPages, currentPage + 1)}" ${currentPage >= totalPages ? 'disabled' : ''}>
      Next
    </button>
  `);

  return buttons.join('');
}

function updateShopCatalogUrl() {
  if (!document.getElementById('shopGrid')) return;

  const params = new URLSearchParams();
  if (shopCatalogState.activeCategorySlug && shopCatalogState.activeCategorySlug !== 'all') {
    params.set('category', shopCatalogState.activeCategorySlug);
  }
  if (shopCatalogState.search) {
    params.set('search', shopCatalogState.search);
  }
  if (shopCatalogState.sort && shopCatalogState.sort !== 'featured') {
    params.set('sort', shopCatalogState.sort);
  }
  if (shopCatalogState.priceRange && shopCatalogState.priceRange !== 'all') {
    params.set('price', shopCatalogState.priceRange);
  }
  if (shopCatalogState.currentPage > 1) {
    params.set('page', String(shopCatalogState.currentPage));
  }

  const nextUrl = `${window.location.pathname}${params.toString() ? `?${params.toString()}` : ''}`;
  window.history.replaceState({}, '', nextUrl);
}

function applyShopCatalogStateFromUrl() {
  const params = new URLSearchParams(window.location.search);
  shopCatalogState.activeCategorySlug = params.get('category') || 'all';
  shopCatalogState.search = params.get('search') || '';
  shopCatalogState.sort = params.get('sort') || 'featured';
  shopCatalogState.priceRange = params.get('price') || 'all';
  shopCatalogState.currentPage = Math.max(1, Number(params.get('page') || 1));

  const searchInput = document.getElementById('shopSearchInput');
  const sortSelect = document.getElementById('shopSortSelect');
  const priceFilter = document.getElementById('shopPriceFilter');
  if (searchInput) searchInput.value = shopCatalogState.search;
  if (sortSelect) sortSelect.value = shopCatalogState.sort;
  if (priceFilter) priceFilter.value = shopCatalogState.priceRange;
}

function setShopRequestStatus(message, isError = false) {
  const statusEl = document.getElementById('shopRequestStatus');
  if (!statusEl) return;

  statusEl.textContent = message;
  statusEl.style.color = isError ? '#fca5a5' : '';
}

function setShopCatalogMeta(text) {
  const metaEl = document.getElementById('shopCatalogMetaText');
  if (metaEl) metaEl.textContent = text;
}

function renderShopCategoryTabs() {
  const container = document.getElementById('categoryTabs');
  if (!container) return;

  const allTab = `
    <button class="cat-tab ${shopCatalogState.activeCategorySlug === 'all' ? 'active' : ''}" type="button" data-category-slug="all">
      <span class="cat-tab-icon">All</span> All Products
    </button>
  `;
  const categoryTabs = (shopCatalogState.categories || []).map(category => `
    <button class="cat-tab ${shopCatalogState.activeCategorySlug === category.slug ? 'active' : ''}" type="button" data-category-slug="${escapeHtml(category.slug)}">
      <span class="cat-tab-icon">${category.is_featured ? 'Hot' : 'Shop'}</span> ${escapeHtml(category.name)}
    </button>
  `);

  container.innerHTML = [allTab, ...categoryTabs].join('');
}

function renderShopCatalog() {
  const grid = document.getElementById('shopGrid');
  const emptyState = document.getElementById('shopCatalogEmpty');
  const pagination = document.getElementById('shopPagination');
  const paginationInfo = document.getElementById('shopPaginationInfo');
  const paginationButtons = document.getElementById('shopPaginationButtons');

  if (!grid || !emptyState || !pagination || !paginationInfo || !paginationButtons) return;

  if (!shopCatalogState.items.length) {
    grid.innerHTML = '';
    emptyState.hidden = false;
    emptyState.innerHTML = '<div><strong>No products found.</strong>Try another search, category, or price range.</div>';
    pagination.hidden = true;
    return;
  }

  emptyState.hidden = true;
  grid.innerHTML = shopCatalogState.items.map(renderProductCard).join('');
  paginationInfo.textContent = `Showing ${shopCatalogState.items.length} of ${shopCatalogState.totalItems} products`;
  paginationButtons.innerHTML = renderShopPaginationButtons();
  pagination.hidden = shopCatalogState.totalPages <= 1;
}

async function loadHomePopularProducts() {
  const grid = document.getElementById('popularProductsGrid');
  if (!grid) return;

  try {
    const data = await fetchJson(shopApiUrl('/api/shop/products', {
      per_page: 4,
      featured_only: 1,
      sort: 'featured'
    }));

    const items = Array.isArray(data.items) ? data.items : [];
    grid.innerHTML = items.map(renderProductCard).join('');
  } catch (error) {
    grid.innerHTML = '<div class="catalog-empty-state"><div><strong>Shop unavailable right now.</strong>Please try again in a moment.</div></div>';
  }
}

async function loadShopCategories() {
  const data = await fetchJson(shopApiUrl('/api/shop/categories'));
  shopCatalogState.categories = Array.isArray(data.items) ? data.items : [];
  renderShopCategoryTabs();
}

async function loadShopProducts(page = shopCatalogState.currentPage) {
  shopCatalogState.currentPage = Math.max(1, Number(page || 1));
  setShopRequestStatus('Loading products...');
  setShopCatalogMeta('Loading products...');

  const priceBounds = priceRangeBounds(shopCatalogState.priceRange);
  const params = {
    page: shopCatalogState.currentPage,
    per_page: 12,
    sort: shopCatalogState.sort,
    search: shopCatalogState.search || null,
    category_slug: shopCatalogState.activeCategorySlug !== 'all' ? shopCatalogState.activeCategorySlug : null,
    min_price: priceBounds.min_price,
    max_price: priceBounds.max_price
  };

  try {
    const data = await fetchJson(shopApiUrl('/api/shop/products', params));
    shopCatalogState.items = Array.isArray(data.items) ? data.items : [];
    shopCatalogState.totalPages = Number(data.meta?.total_pages || 1);
    shopCatalogState.totalItems = Number(data.meta?.total || shopCatalogState.items.length);
    shopCatalogState.currentPage = Number(data.meta?.page || shopCatalogState.currentPage);

    renderShopCatalog();
    setShopCatalogMeta(`${shopCatalogState.totalItems} products available`);
    setShopRequestStatus(`Page ${shopCatalogState.currentPage} of ${shopCatalogState.totalPages}`);
    updateShopCatalogUrl();
  } catch (error) {
    shopCatalogState.items = [];
    renderShopCatalog();
    setShopCatalogMeta('Unable to load products');
    setShopRequestStatus(error.message || 'Catalog request failed', true);
  }
}

function bindShopCatalogControls() {
  const shopGrid = document.getElementById('shopGrid');
  if (!shopGrid || shopGrid.dataset.bound === '1') return;

  const searchInput = document.getElementById('shopSearchInput');
  const sortSelect = document.getElementById('shopSortSelect');
  const priceFilter = document.getElementById('shopPriceFilter');

  if (searchInput) {
    searchInput.addEventListener('input', event => {
      window.clearTimeout(shopSearchDebounce);
      shopSearchDebounce = window.setTimeout(() => {
        shopCatalogState.search = String(event.target?.value || '').trim();
        shopCatalogState.currentPage = 1;
        loadShopProducts(1);
      }, 250);
    });
  }

  if (sortSelect) {
    sortSelect.addEventListener('change', event => {
      shopCatalogState.sort = String(event.target?.value || 'featured');
      shopCatalogState.currentPage = 1;
      loadShopProducts(1);
    });
  }

  if (priceFilter) {
    priceFilter.addEventListener('change', event => {
      shopCatalogState.priceRange = String(event.target?.value || 'all');
      shopCatalogState.currentPage = 1;
      loadShopProducts(1);
    });
  }

  shopGrid.dataset.bound = '1';
}

async function initializeShopCatalogPage() {
  if (!document.getElementById('shopGrid')) return;

  applyShopCatalogStateFromUrl();
  bindShopCatalogControls();

  try {
    await loadShopCategories();
  } catch (error) {
    setShopRequestStatus(error.message || 'Unable to load categories', true);
  }

  await loadShopProducts(shopCatalogState.currentPage);
}

function renderProductDetailView(product, gallery, relatedProducts) {
  const emptyState = document.getElementById('productDetailEmpty');
  const layout = document.getElementById('productDetailLayout');
  const stockBadge = document.getElementById('productStockBadge');
  const categoryLabel = document.getElementById('productCategoryLabel');
  const title = document.getElementById('productDetailTitle');
  const price = document.getElementById('productDetailPrice');
  const description = document.getElementById('productDetailDesc');
  const highlights = document.getElementById('productHighlightList');
  const mainImage = document.getElementById('mainProductImg');
  const thumbs = document.getElementById('productThumbs');
  const relatedGrid = document.getElementById('relatedProductsGrid');
  const relatedEmpty = document.getElementById('relatedProductsEmpty');
  const qtyHint = document.getElementById('productQtyHint');
  const addToCartButton = document.querySelector('[data-add-current-product]');
  const buyNowButton = document.querySelector('[data-buy-current-product]');
  const badgeState = stockBadgeState(product?.stock_state);
  const detailGallery = Array.isArray(gallery) && gallery.length ? gallery : [];
  const firstImage = detailGallery[0]?.image_url || product?.primary_image_url || '';
  const detailHighlights = Array.isArray(product?.highlights) ? product.highlights : [];
  const maxByConfig = shopCartMaxQuantity();
  const maxByStock = Number(product?.track_inventory) === 1
    ? Math.max(0, Number(product?.stock || 0))
    : maxByConfig;
  const maxQuantity = Number(product?.track_inventory) === 1
    ? Math.max(1, Math.min(maxByConfig, maxByStock || 1))
    : maxByConfig;
  const canAddToCart = product?.is_available !== false && String(product?.stock_state || 'in_stock') !== 'out_of_stock' && maxByStock !== 0;

  productQty = 1;

  if (emptyState) emptyState.hidden = true;
  if (layout) layout.hidden = false;

  if (stockBadge) {
    stockBadge.className = `stock-badge ${badgeState.className}`;
    stockBadge.textContent = badgeState.label;
  }
  if (categoryLabel) categoryLabel.textContent = product?.category_name || 'Shop item';
  if (title) title.textContent = product?.name || 'Product';
  if (price) {
    price.innerHTML = `
      ${formatShopCurrency(product?.price || 0, product?.currency || 'VND')}
      ${product?.compare_at_price
        ? `<span style="font-size:16px;color:var(--text3);font-weight:400;text-decoration:line-through">${formatShopCurrency(product.compare_at_price, product.currency || 'VND')}</span>`
        : ''}
    `;
  }
  if (description) {
    description.textContent = product?.detail_description || product?.description || product?.summary || 'No description available for this product yet.';
  }
  if (qtyHint) {
    qtyHint.textContent = Number(product?.track_inventory) === 1
      ? (canAddToCart
        ? `Up to ${maxQuantity} item${maxQuantity === 1 ? '' : 's'} available right now`
        : 'Currently unavailable')
      : `Max ${maxByConfig} per order`;
  }
  if (highlights) {
    highlights.innerHTML = detailHighlights.length
      ? detailHighlights.map(item => `<div><strong>${escapeHtml(item.label)}:</strong> ${escapeHtml(item.value)}</div>`).join('')
      : '<div>Freshly prepared and ready for your next movie night.</div>';
  }
  if (mainImage) {
    mainImage.src = firstImage;
    mainImage.alt = product?.primary_image_alt || product?.name || 'Product';
  }
  if (thumbs) {
    thumbs.innerHTML = detailGallery.map((item, index) => `
      <div class="product-thumb ${index === 0 ? 'active' : ''}" data-image-url="${escapeHtml(item.image_url || '')}">
        <img src="${escapeHtml(item.image_url || '')}" alt="${escapeHtml(item.alt_text || product?.name || 'Product image')}">
      </div>
    `).join('');
  }
  if (relatedGrid && relatedEmpty) {
    if (Array.isArray(relatedProducts) && relatedProducts.length) {
      relatedGrid.innerHTML = relatedProducts.map(renderProductCard).join('');
      relatedEmpty.hidden = true;
    } else {
      relatedGrid.innerHTML = '';
      relatedEmpty.hidden = false;
      relatedEmpty.innerHTML = '<div><strong>No related products found.</strong>Browse the full shop catalog for more picks.</div>';
    }
  }

  const metaText = document.getElementById('productDetailMetaText');
  const statusText = document.getElementById('productRequestStatus');
  if (metaText) metaText.textContent = `${product?.category_name || 'Shop item'}${product?.brand ? ` / ${product.brand}` : ''}`;
  if (statusText) {
    statusText.textContent = badgeState.label;
    statusText.style.color = '';
  }

  const qtyEl = document.getElementById('productQty');
  if (qtyEl) qtyEl.textContent = '1';

  [addToCartButton, buyNowButton].forEach(button => {
    if (!button) return;

    button.dataset.productId = String(Number(product?.id || 0));
    button.dataset.productName = String(product?.name || 'Product');
    button.disabled = !canAddToCart;
  });
  if (addToCartButton) {
    addToCartButton.textContent = canAddToCart ? 'Add to Cart' : 'Out of Stock';
  }
  if (buyNowButton) {
    buyNowButton.textContent = canAddToCart ? 'Buy Now' : 'Unavailable';
  }
}

function renderProductDetailEmpty(message) {
  const emptyState = document.getElementById('productDetailEmpty');
  const layout = document.getElementById('productDetailLayout');
  const relatedGrid = document.getElementById('relatedProductsGrid');
  const relatedEmpty = document.getElementById('relatedProductsEmpty');
  const metaText = document.getElementById('productDetailMetaText');
  const statusText = document.getElementById('productRequestStatus');

  if (layout) layout.hidden = true;
  if (emptyState) {
    emptyState.hidden = false;
    emptyState.innerHTML = `<div><strong>Product unavailable.</strong>${escapeHtml(message || 'Please choose another product from the shop catalog.')}</div>`;
  }
  if (relatedGrid) relatedGrid.innerHTML = '';
  if (relatedEmpty) relatedEmpty.hidden = true;
  if (metaText) metaText.textContent = 'Product details unavailable';
  if (statusText) {
    statusText.textContent = 'Unavailable';
    statusText.style.color = '#fca5a5';
  }
}

async function initializeProductDetailPage() {
  const layout = document.getElementById('productDetailLayout');
  if (!layout) return;

  const slug = new URLSearchParams(window.location.search).get('slug');
  productQty = 1;
  const qtyEl = document.getElementById('productQty');
  if (qtyEl) qtyEl.textContent = '1';

  if (!slug) {
    renderProductDetailEmpty('Missing product slug in URL.');
    return;
  }

  try {
    const data = await fetchJson(shopApiUrl(`/api/shop/products/${encodeURIComponent(slug)}`));
    shopCatalogState.currentProduct = data.product || null;
    shopCatalogState.gallery = Array.isArray(data.gallery) ? data.gallery : [];
    shopCatalogState.relatedItems = Array.isArray(data.related_products) ? data.related_products : [];

    if (!shopCatalogState.currentProduct) {
      renderProductDetailEmpty('Product could not be found.');
      return;
    }

    renderProductDetailView(shopCatalogState.currentProduct, shopCatalogState.gallery, shopCatalogState.relatedItems);
  } catch (error) {
    renderProductDetailEmpty(error.message || 'Unable to load product details.');
  }
}

function addToCartProduct(name, quantity = 1) {
  cartCount += Math.max(1, Number(quantity || 1));
  updateCartBadges();
  showToast('馃洅', 'Added to Cart', `${name} has been added!`);
}

function normalizeAddToCartPayload(input, quantity = 1) {
  if (typeof input === 'object' && input !== null) {
    return {
      productId: Number(input.productId || input.product_id || 0),
      name: String(input.name || 'Product'),
      quantity: Math.max(1, Number(input.quantity || quantity || 1))
    };
  }

  return {
    productId: 0,
    name: String(input || 'Product'),
    quantity: Math.max(1, Number(quantity || 1))
  };
}

function addToCart(input = null) {
  if (typeof window.addToCart === 'function' && window.addToCart !== addToCart) {
    return window.addToCart(input);
  }

  const payload = input || {
    productId: Number(shopCatalogState.currentProduct?.id || 0),
    name: shopCatalogState.currentProduct?.name || 'Product',
    quantity: productQty
  };

  addToCartProduct(payload);
}

function buyNow(input = null) {
  if (typeof window.buyNow === 'function' && window.buyNow !== buyNow) {
    return window.buyNow(input);
  }

  addToCart(input);
  navigateTo('cart');
}

function addToCartProduct(input, quantity = 1) {
  if (typeof window.addToCartProduct === 'function' && window.addToCartProduct !== addToCartProduct) {
    return window.addToCartProduct(input, quantity);
  }

  const payload = normalizeAddToCartPayload(input, quantity);
  cartCount += payload.quantity;
  updateCartBadges();
  showToast('+', 'Added to Cart', `${payload.name} has been added!`);
}

document.addEventListener('click', event => {
  const addProductButton = event.target.closest('[data-add-product-name]');
  if (addProductButton) {
    event.stopPropagation();
    addToCartProduct({
      productId: Number(addProductButton.dataset.addProductId || 0),
      name: addProductButton.dataset.addProductName || 'Product',
      quantity: 1
    });
    return;
  }

  const addCurrentProductButton = event.target.closest('[data-add-current-product]');
  if (addCurrentProductButton) {
    addToCart({
      productId: Number(addCurrentProductButton.dataset.productId || 0),
      name: addCurrentProductButton.dataset.productName || shopCatalogState.currentProduct?.name || 'Product',
      quantity: productQty
    });
    return;
  }

  const buyCurrentProductButton = event.target.closest('[data-buy-current-product]');
  if (buyCurrentProductButton) {
    buyNow({
      productId: Number(buyCurrentProductButton.dataset.productId || 0),
      name: buyCurrentProductButton.dataset.productName || shopCatalogState.currentProduct?.name || 'Product',
      quantity: productQty
    });
    return;
  }

  const shareButton = event.target.closest('#productShareButton');
  if (shareButton) {
    const shareUrl = window.location.href;
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(shareUrl)
        .then(() => showToast('馃敄', 'Link copied', 'Product link copied to clipboard.'))
        .catch(() => showToast('鈩癸笍', 'Copy failed', 'Unable to copy the product link right now.'));
    } else {
      showToast('鈩癸笍', 'Copy unavailable', 'Clipboard access is not supported in this browser.');
    }
    return;
  }

  const productCard = event.target.closest('[data-product-link]');
  if (productCard) {
    window.location.href = productCard.dataset.productLink || appUrl('/shop');
    return;
  }

  const categoryButton = event.target.closest('[data-category-slug]');
  if (categoryButton) {
    shopCatalogState.activeCategorySlug = categoryButton.dataset.categorySlug || 'all';
    shopCatalogState.currentPage = 1;
    renderShopCategoryTabs();
    loadShopProducts(1);
    return;
  }

  const paginationButton = event.target.closest('[data-shop-page]');
  if (paginationButton && !paginationButton.disabled) {
    loadShopProducts(Number(paginationButton.dataset.shopPage || 1));
    return;
  }

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
      mainImage.src = productThumb.dataset.imageUrl || thumbImage.src;
      mainImage.alt = thumbImage.alt || mainImage.alt;
    }
  }
});

function setAuthErrors(prefix, errors) {
  const fieldMap = {
    name: 'Name',
    phone: 'Phone',
    email: 'Email',
    identifier: 'Identifier',
    password: 'Password'
  };

  Object.entries(fieldMap).forEach(([field, suffix]) => {
    const el = document.getElementById(`${prefix}${suffix}Error`);
    if (el) el.textContent = errors?.[field]?.[0] || '';
  });

  const generalError = document.getElementById(`${prefix}GeneralError`);
  if (generalError) {
    const message = errors?.credentials?.[0] || errors?.server?.[0] || '';
    generalError.textContent = message;
    generalError.style.display = message ? '' : 'none';
  }
}

function clearAuthErrors(prefix) {
  setAuthErrors(prefix, {});
  ['ConfirmPassword', 'Terms'].forEach(field => {
    const el = document.getElementById(`${prefix}${field}Error`);
    if (el) el.textContent = '';
  });
}

function currentAppRelativeUrl() {
  const pathname = String(window.location.pathname || '/');
  const search = String(window.location.search || '');

  if (APP_BASE_PATH && pathname.startsWith(APP_BASE_PATH)) {
    const relativePath = pathname.slice(APP_BASE_PATH.length) || '/';
    return `${relativePath.startsWith('/') ? relativePath : `/${relativePath}`}${search}`;
  }

  return `${pathname.startsWith('/') ? pathname : `/${pathname}`}${search}`;
}

function sanitizeAuthRedirectPath(value) {
  const candidate = String(value || '').trim();
  if (!candidate || !candidate.startsWith('/') || candidate.startsWith('//') || candidate.includes('\\')) {
    return '/';
  }

  try {
    const url = new URL(candidate, window.location.origin);
    if (/(^|\/)\.\.?(\/|$)/.test(url.pathname)) {
      return '/';
    }

    let normalizedPath = url.pathname;
    if (APP_BASE_PATH && (normalizedPath === APP_BASE_PATH || normalizedPath.startsWith(`${APP_BASE_PATH}/`))) {
      normalizedPath = `/${normalizedPath.slice(APP_BASE_PATH.length).replace(/^\/+/, '')}`;
    }

    const normalized = `${normalizedPath}${url.search}${url.hash}`;
    if (normalized === '/login' || normalized.startsWith('/login?') || normalized === '/register' || normalized.startsWith('/register?')) {
      return '/';
    }

    return normalized;
  } catch (error) {
    return '/';
  }
}

function buildLoginUrl(redirectPath = currentAppRelativeUrl()) {
  const target = sanitizeAuthRedirectPath(redirectPath);
  return target === '/'
    ? appUrl('/login')
    : appUrl(`/login?redirect=${encodeURIComponent(target)}`);
}

function redirectToLogin(redirectPath = currentAppRelativeUrl()) {
  window.location.href = buildLoginUrl(redirectPath);
}

function resolvePostLoginRedirect(form) {
  const formRedirect = form?.querySelector('input[name="redirect"]')?.value || '';
  const queryRedirect = new URLSearchParams(window.location.search).get('redirect') || '';

  return sanitizeAuthRedirectPath(formRedirect || queryRedirect || '/');
}

function redirectProfileToLogin() {
  if (isProfilePage()) {
    redirectToLogin();
  }
}

function saveAuthToken(token, options = {}) {
  const persistent = options.persistent !== false;

  if (token) {
    if (persistent) {
      localStorage.setItem('cinemax_token', token);
      sessionStorage.removeItem('cinemax_token');
    } else {
      sessionStorage.setItem('cinemax_token', token);
      localStorage.removeItem('cinemax_token');
    }
  } else {
    localStorage.removeItem('cinemax_token');
    sessionStorage.removeItem('cinemax_token');
  }

  document.dispatchEvent(new CustomEvent('cinemax:auth-changed', {
    detail: { isLoggedIn: Boolean(token) }
  }));
}

function getAuthToken() {
  return localStorage.getItem('cinemax_token') || sessionStorage.getItem('cinemax_token');
}

function clearAuthToken() {
  localStorage.removeItem('cinemax_token');
  sessionStorage.removeItem('cinemax_token');
  document.dispatchEvent(new CustomEvent('cinemax:auth-changed', {
    detail: { isLoggedIn: false }
  }));
}

function ensureAuthForPage() {
  const authOnlyPages = new Set(['profile', 'my-tickets']);
  const activePage = document.body?.dataset?.activePage || '';
  if (!authOnlyPages.has(activePage)) return;

  if (!getAuthToken()) {
    updateAuthUI(false);
    redirectToLogin();
  }
}

async function updatePassword() {
  const currentPassword = document.getElementById('currentPassword')?.value;
  const newPassword = document.getElementById('newPassword')?.value;
  const confirmPassword = document.getElementById('confirmPassword')?.value;

  if (!currentPassword || !newPassword || !confirmPassword) {
    showToast('!', 'Missing details', 'Please fill in all password fields.');
    return;
  }

  if (newPassword !== confirmPassword) {
    showToast('!', 'Password mismatch', 'The new passwords do not match.');
    return;
  }

  const token = getAuthToken();
  if (!token) {
    redirectToLogin();
    return;
  }

  try {
    const res = await fetch(appUrl('/api/auth/update-password'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`
      },
      body: JSON.stringify({
        current_password: currentPassword,
        new_password: newPassword,
        confirm_password: confirmPassword
      })
    });

    const data = await res.json();
    if (!res.ok) {
      const firstError = data.errors ? Object.values(data.errors)[0][0] : 'Password update failed.';
      showToast('!', 'Error', firstError);
      return;
    }

    showToast('+', 'Password updated', 'Your password has been updated.');
    document.getElementById('currentPassword').value = '';
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmPassword').value = '';
  } catch (error) {
    showToast('!', 'Connection error', 'Unable to update your password right now.');
  }
}

async function handleLogin(event) {
  event.preventDefault();
  clearAuthErrors('login');

  const form = event.target;
  const identifier = String(form.identifier?.value || form.email?.value || '').trim();
  const password = String(form.password?.value || '');
  const remember = Boolean(form.remember?.checked);

  if (!identifier) {
    setAuthErrors('login', { identifier: ['Please enter your email or phone number.'] });
    return;
  }

  if (!password) {
    setAuthErrors('login', { password: ['Please enter your password.'] });
    return;
  }

  try {
    const res = await fetch(appUrl('/api/auth/login'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ identifier, password })
    });
    const data = await res.json();

    if (!res.ok) {
      setAuthErrors('login', data.errors || {});
      showToast('!', 'Login failed', 'Please check your credentials and try again.');
      return;
    }

    saveAuthToken(data.data?.token, { persistent: remember });
    await hydrateProfile();
    showToast('+', 'Login successful', 'Welcome back.');
    window.location.href = appUrl(resolvePostLoginRedirect(form));
  } catch (error) {
    showToast('!', 'Connection error', 'Unable to sign in right now.');
  }
}

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
  loadHomePopularProducts();
  initializeShopCatalogPage();
  initializeProductDetailPage();
});
