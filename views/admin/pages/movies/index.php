<?php
$movieSection = $activePage ?? 'movies';
$sectionMeta = [
    'movies' => [
        'breadcrumb' => 'Movies',
        'title' => 'Movie Management',
        'subtitle' => 'Manage the movie catalog using schema-aligned metadata',
        'button' => 'Add Movie',
    ],
    'categories' => [
        'breadcrumb' => 'Categories',
        'title' => 'Movie Categories',
        'subtitle' => 'Manage genres and keep your catalog organized',
        'button' => 'Add Category',
    ],
    'movie-images' => [
        'breadcrumb' => 'Movie Images',
        'title' => 'Movie Image Library',
        'subtitle' => 'Manage posters, banners, and gallery assets stored in movie_images',
        'button' => 'Add Asset',
    ],
    'reviews' => [
        'breadcrumb' => 'Reviews',
        'title' => 'Review Moderation',
        'subtitle' => 'Approve, reject, and control review visibility',
        'button' => 'Moderate Pending',
    ],
];
$sectionViews = [
    'movies' => __DIR__ . '/sections/movies.php',
    'categories' => __DIR__ . '/sections/categories.php',
    'movie-images' => __DIR__ . '/sections/movie-images.php',
    'reviews' => __DIR__ . '/sections/reviews.php',
];
$meta = $sectionMeta[$movieSection] ?? $sectionMeta['movies'];
$sectionView = $sectionViews[$movieSection] ?? $sectionViews['movies'];
?>
<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">&gt;</span><span><?php echo htmlspecialchars($meta['breadcrumb'], ENT_QUOTES, 'UTF-8'); ?></span></div>
      <h1 class="page-title"><?php echo htmlspecialchars($meta['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
      <p class="page-sub"><?php echo htmlspecialchars($meta['subtitle'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <button class="btn btn-primary" onclick="handleMovieSectionAction()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?php echo htmlspecialchars($meta['button'], ENT_QUOTES, 'UTF-8'); ?>
    </button>
  </div>

  <?php include $sectionView; ?>
</div>
