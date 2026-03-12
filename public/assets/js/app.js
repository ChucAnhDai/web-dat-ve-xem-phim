const pageMap = {
  home: 'page-home',
  movies: 'page-movies',
  showtimes: 'page-showtimes',
  shop: 'page-shop',
  cart: 'page-cart',
  auth: 'page-auth',
  profile: 'page-profile',
  'my-tickets': 'page-my-tickets',
  'my-orders': 'page-my-orders'
};

function navigateTo(page) {
  Object.values(pageMap).forEach(id => {
    const el = document.getElementById(id);
    if (el) el.classList.remove('active');
  });
  const target = document.getElementById(pageMap[page]);
  if (target) {
    target.classList.add('active');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  document.querySelectorAll('.nav-item').forEach(el => {
    el.classList.remove('active');
  });
  closeSidebar();
}

let hamburgerBtn;
let sidebar;
let sidebarOverlay;
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
  const loginForm = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  const loginBtn = document.getElementById('loginTabBtn');
  const registerBtn = document.getElementById('registerTabBtn');
  if (!loginForm || !registerForm || !loginBtn || !registerBtn) return;
  if (tab === 'register') {
    loginForm.style.display = 'none';
    registerForm.style.display = 'block';
    loginBtn.classList.remove('active');
    registerBtn.classList.add('active');
  } else {
    loginForm.style.display = 'block';
    registerForm.style.display = 'none';
    loginBtn.classList.add('active');
    registerBtn.classList.remove('active');
  }
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

async function handleLogin(event) {
  event.preventDefault();
  clearAuthErrors('login');
  const form = event.target;
  const payload = {
    email: form.email.value.trim(),
    password: form.password.value
  };
  try {
    const res = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (!res.ok) {
      setAuthErrors('login', data.errors || {});
      showToast('⚠️', 'Đăng nhập thất bại', 'Vui lòng kiểm tra thông tin.');
      return;
    }
    saveAuthToken(data.data?.token);
    await hydrateProfile();
    updateAuthUI(true);
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
  const confirmPassword = form.confirm_password?.value || '';
  const acceptedTerms = Boolean(form.terms?.checked);

  if (form.password.value !== confirmPassword) {
    const confirmError = document.getElementById('registerConfirmPasswordError');
    if (confirmError) confirmError.textContent = 'Mật khẩu xác nhận không khớp.';
    return;
  }

  if (!acceptedTerms) {
    const termsError = document.getElementById('registerTermsError');
    if (termsError) termsError.textContent = 'Bạn cần đồng ý với điều khoản để tiếp tục.';
    return;
  }

  const payload = {
    name: form.name.value.trim(),
    phone: form.phone.value.trim(),
    email: form.email.value.trim(),
    password: form.password.value,
    role: form.role.value
  };
  try {
    const res = await fetch('/api/auth/register', {
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
    updateAuthUI(true);
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
    return;
  }
  try {
    await fetch('/api/auth/logout', {
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
}

async function hydrateProfile() {
  const token = getAuthToken();
  if (!token) {
    updateAuthUI(false);
    return;
  }
  try {
    const res = await fetch('/api/auth/profile', {
      headers: { Authorization: `Bearer ${token}` }
    });
    if (!res.ok) {
      updateAuthUI(false);
      return;
    }
    const data = await res.json();
    const name = data.data?.name || 'User';
    const email = data.data?.email || 'user@example.com';
    const initials = name.split(' ').map(n => n[0]).slice(0, 2).join('').toUpperCase();
    const avatar = document.getElementById('authAvatarInitials');
    if (avatar) avatar.textContent = initials || 'US';

    const profileName = document.querySelector('.profile-name');
    const profileEmail = document.querySelector('.profile-email');
    if (profileName) profileName.textContent = name;
    if (profileEmail) profileEmail.textContent = email;
    updateAuthUI(true);
  } catch (error) {
    updateAuthUI(false);
  }
}

function initAuthUI() {
  const token = getAuthToken();
  if (token) {
    hydrateProfile();
  } else {
    updateAuthUI(false);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  initSidebar();
  initAuthUI();
  const loginForm = document.getElementById('loginForm');
  const registerForm = document.getElementById('registerForm');
  if (loginForm) loginForm.addEventListener('submit', handleLogin);
  if (registerForm) registerForm.addEventListener('submit', handleRegister);
});
