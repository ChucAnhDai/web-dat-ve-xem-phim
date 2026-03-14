<header class="header">
  <button class="header-toggle" onclick="toggleSidebar()">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <line x1="3" y1="6" x2="21" y2="6"/>
      <line x1="3" y1="12" x2="21" y2="12"/>
      <line x1="3" y1="18" x2="21" y2="18"/>
    </svg>
  </button>

  <div class="search-wrap">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
      <circle cx="11" cy="11" r="8"/>
      <path d="M21 21l-4.35-4.35"/>
    </svg>
    <input class="search-input" id="globalSearch" type="text" placeholder="Search movies, users, orders...">
  </div>

  <div class="header-right">
    <button class="header-btn hide-mobile" onclick="showToast('No new messages','info')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>
      </svg>
    </button>

    <button class="header-btn" onclick="showToast('3 new notifications','info')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>
        <path d="M13.73 21a2 2 0 01-3.46 0"/>
      </svg>
      <div class="notif-dot"></div>
    </button>

    <button class="quick-action-btn" id="headerQuickBtn" onclick="if(window.QUICK_ACTION)window.QUICK_ACTION();else showToast('Quick add','info')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <line x1="12" y1="5" x2="12" y2="19"/>
        <line x1="5" y1="12" x2="19" y2="12"/>
      </svg>
      <span id="quickActionLabel">Quick Add</span>
    </button>

    <div style="position:relative;">
      <div class="avatar" id="avatarBtn" onclick="document.getElementById('profileDrop').classList.toggle('open')">AD</div>
      <div class="profile-drop" id="profileDrop">
        <div class="profile-drop-info">
          <div class="profile-drop-avatar">AD</div>
          <div>
            <div style="font-weight:700;font-size:13px;color:var(--text);">Admin User</div>
            <div style="font-size:11px;color:var(--text-muted);">admin@cineshop.com</div>
          </div>
        </div>
        <div class="profile-drop-sep"></div>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin?section=admin-profile" class="profile-drop-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
          My Profile
        </a>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin?section=system-settings" class="profile-drop-item">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 010 14.14M4.93 4.93a10 10 0 000 14.14"/></svg>
          Settings
        </a>
        <div class="profile-drop-sep"></div>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin/logout" class="profile-drop-item" style="color:var(--red);">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
          Sign Out
        </a>
      </div>
    </div>
  </div>
</header>
