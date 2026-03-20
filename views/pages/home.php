<div class="page active" id="page-home">
  <div class="hero">
    <div class="hero-bg" id="heroBg"></div>
    <div class="hero-poster" id="heroPoster">
      <img src="" alt="" onerror="this.style.background='#1a1a2e'">
    </div>
    <div class="hero-content">
      <div class="hero-badge" id="heroBadge">🔥 Featured Movie</div>
      <h1 class="hero-title" id="heroTitle">Loading...</h1>
      <div class="hero-meta">
        <span class="rating-stars" id="heroStars"></span>
        <span id="heroRating"></span>
        <span class="dot"></span>
        <span id="heroDuration"></span>
        <span class="dot"></span>
        <span id="heroGenre"></span>
      </div>
      <p class="hero-desc" id="heroDesc"></p>
      <div class="hero-actions" id="heroActions">
        <button class="btn btn-primary btn-lg" onclick="navigateTo('movies')">🎬 Book Ticket</button>
        <button class="btn btn-secondary btn-lg">▶ Watch Trailer</button>
      </div>
    </div>
    <div class="hero-dots">
      <div class="hero-dot active"></div>
      <div class="hero-dot"></div>
      <div class="hero-dot"></div>
    </div>
  </div>

  <!-- Stats -->
  <div class="stats-row">
    <div class="stat-card">
      <div class="stat-card-icon">🎬</div>
      <div class="stat-card-val" id="statMovies">--</div>
      <div class="stat-card-label">Movies Showing</div>
      <div class="stat-card-change change-up" id="statMoviesLabel">▲ Updating...</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon">🎭</div>
      <div class="stat-card-val" id="statRooms">--</div>
      <div class="stat-card-label">Cinema Halls</div>
      <div class="stat-card-change change-up" id="statRoomsLabel">▲ Updating...</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon">🍿</div>
      <div class="stat-card-val" id="statProducts">--</div>
      <div class="stat-card-label">Shop Products</div>
      <div class="stat-card-change change-up" id="statProductsLabel">▲ Updating...</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon">⭐</div>
      <div class="stat-card-val" id="statRating">--</div>
      <div class="stat-card-label">Avg Rating</div>
      <div class="stat-card-change change-up" id="statRatingLabel">▲ Updating...</div>
    </div>
  </div>

  <!-- Now Showing -->
  <section class="section-mb">
    <div class="section-header">
      <h2 class="section-title">Now <span>Showing</span></h2>
      <button class="btn btn-ghost btn-sm" onclick="navigateTo('movies')">View all →</button>
    </div>
    <div class="movies-grid" id="nowShowingGrid"></div>
  </section>

  <!-- Coming Soon -->
  <section class="section-mb">
    <div class="section-header">
      <h2 class="section-title">Coming <span>Soon</span></h2>
      <button class="btn btn-ghost btn-sm" onclick="navigateTo('movies')">View all →</button>
    </div>
    <div class="movies-grid" id="comingSoonGrid"></div>
  </section>

  <!-- Mid-Hero Banner (New) -->
  <div class="mid-hero section-mb" id="midHero">
    <div class="mid-hero-bg" id="midHeroBg"></div>
    <div class="mid-hero-content">
      <div class="mid-hero-badges" id="midHeroBadges"></div>
      <h2 class="mid-hero-title" id="midHeroTitle"></h2>
      <p class="mid-hero-subtitle" id="midHeroSubtitle"></p>
      <div class="mid-hero-genres" id="midHeroGenres"></div>
      <div class="mid-hero-actions">
        <button class="btn-play" id="midHeroPlay" title="Play Trailer"><i class="fas fa-play"></i></button>
        <button class="btn-circle" id="midHeroInfo" title="View Detail"><i class="fas fa-exclamation"></i></button>
        <button class="btn-circle"></button>
      </div>
    </div>
    <div class="mid-hero-thumbs-container">
      <div class="mid-hero-thumbs" id="midHeroThumbs"></div>
    </div>
  </div>

  <!-- Popular Products -->
  <section class="section-mb">
    <div class="section-header">
      <h2 class="section-title">Popular <span>Products</span></h2>
      <button class="btn btn-ghost btn-sm" onclick="navigateTo('shop')">Shop all →</button>
    </div>
    <div class="products-grid" id="popularProductsGrid"></div>
  </section>

  <!-- Promos -->
  <section class="section-mb">
    <div class="section-header">
      <h2 class="section-title">Special <span>Offers</span></h2>
    </div>
    <div class="promo-grid">
      <div class="promo-card promo-card-1">
        <div class="promo-emoji">🎟️</div>
        <h3>2-for-1 Tuesdays</h3>
        <p>Buy one ticket, get one free every Tuesday</p>
        <button class="btn btn-secondary btn-sm" type="button">Claim Now</button>
      </div>
      <div class="promo-card promo-card-2">
        <div class="promo-emoji">🍿</div>
        <h3>Combo Deal</h3>
        <p>Large popcorn + 2 drinks only $12.99</p>
        <button class="btn btn-outline btn-sm" onclick="navigateTo('shop')">Order Now</button>
      </div>
      <div class="promo-card promo-card-3">
        <div class="promo-emoji">👑</div>
        <h3>VIP Membership</h3>
        <p>Get 20% off all tickets & free upgrades</p>
        <button class="btn btn-secondary btn-sm" type="button" style="background:rgba(201,168,76,0.2);border-color:var(--gold);color:var(--gold)">Join VIP</button>
      </div>
    </div>
  </section>
</div>
