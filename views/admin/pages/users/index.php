<?php
$userSection = $activePage ?? 'users';
$sectionMeta = [
    'users' => [
        'breadcrumb' => 'Users',
        'title' => 'User Management',
        'subtitle' => 'Manage platform users, roles, and permissions',
        'button' => 'Add User',
        'buttonClass' => 'btn btn-primary',
    ],
    'user-addresses' => [
        'breadcrumb' => 'User Addresses',
        'title' => 'User Addresses',
        'subtitle' => 'Review saved addresses for shipping and support',
        'button' => 'Add Address',
        'buttonClass' => 'btn btn-primary',
    ],
    'roles' => [
        'breadcrumb' => 'Roles',
        'title' => 'Roles & Permissions',
        'subtitle' => 'Manage access policies for admins, staff, and customers',
        'button' => 'Add Role',
        'buttonClass' => 'btn btn-primary',
    ],
];
$sectionViews = [
    'users' => __DIR__ . '/sections/users.php',
    'user-addresses' => __DIR__ . '/sections/user-addresses.php',
    'roles' => __DIR__ . '/sections/roles.php',
];
$meta = $sectionMeta[$userSection] ?? $sectionMeta['users'];
$sectionView = $sectionViews[$userSection] ?? $sectionViews['users'];
?>
<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">&gt;</span><span><?php echo htmlspecialchars($meta['breadcrumb'], ENT_QUOTES, 'UTF-8'); ?></span></div>
      <h1 class="page-title"><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="page-sub"><?php echo htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <button class="<?php echo htmlspecialchars($meta['buttonClass'], ENT_QUOTES, 'UTF-8'); ?>" onclick="handleUserSectionAction()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?php echo htmlspecialchars($meta['button'], ENT_QUOTES, 'UTF-8'); ?>
    </button>
  </div>

  <?php include $sectionView; ?>
</div>
