<?php
$dashboardSection = $activePage ?? 'dashboard';
$sectionMeta = [
    'dashboard' => [
        'breadcrumb' => 'Dashboard',
        'title' => 'Dashboard Overview',
        'subtitle' => "Welcome back, Admin. Here's what's happening today.",
        'button' => 'Export Report',
        'buttonClass' => 'btn btn-secondary',
    ],
    'banners' => [
        'breadcrumb' => 'Banners',
        'title' => 'Banner Management',
        'subtitle' => 'Manage homepage and campaign banners',
        'button' => 'Add Banner',
        'buttonClass' => 'btn btn-primary',
    ],
    'notifications' => [
        'breadcrumb' => 'Notifications',
        'title' => 'Notification Center',
        'subtitle' => 'Create notices and track delivery status',
        'button' => 'Compose Notice',
        'buttonClass' => 'btn btn-primary',
    ],
    'system-settings' => [
        'breadcrumb' => 'System Settings',
        'title' => 'System Settings',
        'subtitle' => 'Configure booking, payments, and integrations',
        'button' => 'Save Settings',
        'buttonClass' => 'btn btn-primary',
    ],
    'admin-profile' => [
        'breadcrumb' => 'Admin Profile',
        'title' => 'Admin Profile',
        'subtitle' => 'Manage your account details and preferences',
        'button' => 'Edit Profile',
        'buttonClass' => 'btn btn-primary',
    ],
];
$sectionViews = [
    'dashboard' => __DIR__ . '/sections/dashboard.php',
    'banners' => __DIR__ . '/sections/banners.php',
    'notifications' => __DIR__ . '/sections/notifications.php',
    'system-settings' => __DIR__ . '/sections/system-settings.php',
    'admin-profile' => __DIR__ . '/sections/admin-profile.php',
];
$meta = $sectionMeta[$dashboardSection] ?? $sectionMeta['dashboard'];
$sectionView = $sectionViews[$dashboardSection] ?? $sectionViews['dashboard'];
?>
<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">&gt;</span><span><?php echo htmlspecialchars($meta['breadcrumb'], ENT_QUOTES, 'UTF-8'); ?></span></div>
      <h1 class="page-title"><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="page-sub"><?php echo htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <button class="<?php echo htmlspecialchars($meta['buttonClass'], ENT_QUOTES, 'UTF-8'); ?>" onclick="handleDashboardSectionAction()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?php echo htmlspecialchars($meta['button'], ENT_QUOTES, 'UTF-8'); ?>
    </button>
  </div>

  <?php include $sectionView; ?>
</div>
