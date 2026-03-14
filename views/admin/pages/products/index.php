<?php
$productSection = $activePage ?? 'products';
$sectionMeta = [
    'products' => [
        'breadcrumb' => 'Products',
        'title' => 'Product Management',
        'subtitle' => 'Manage your online shop inventory',
        'button' => 'Add Product',
        'buttonClass' => 'btn btn-primary',
    ],
    'product-categories' => [
        'breadcrumb' => 'Product Categories',
        'title' => 'Product Categories',
        'subtitle' => 'Group inventory with clear merchandising categories',
        'button' => 'Add Category',
        'buttonClass' => 'btn btn-primary',
    ],
    'product-images' => [
        'breadcrumb' => 'Product Images',
        'title' => 'Product Image Library',
        'subtitle' => 'Manage thumbnails, packshots, and campaign assets',
        'button' => 'Upload Image',
        'buttonClass' => 'btn btn-primary',
    ],
];
$sectionViews = [
    'products' => __DIR__ . '/sections/products.php',
    'product-categories' => __DIR__ . '/sections/product-categories.php',
    'product-images' => __DIR__ . '/sections/product-images.php',
];
$meta = $sectionMeta[$productSection] ?? $sectionMeta['products'];
$sectionView = $sectionViews[$productSection] ?? $sectionViews['products'];
?>
<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">&gt;</span><span><?php echo htmlspecialchars($meta['breadcrumb'], ENT_QUOTES, 'UTF-8'); ?></span></div>
      <h1 class="page-title"><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="page-sub"><?php echo htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <button class="<?php echo htmlspecialchars($meta['buttonClass'], ENT_QUOTES, 'UTF-8'); ?>" onclick="handleProductSectionAction()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?php echo htmlspecialchars($meta['button'], ENT_QUOTES, 'UTF-8'); ?>
    </button>
  </div>

  <?php include $sectionView; ?>
</div>
