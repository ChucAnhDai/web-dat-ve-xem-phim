<div class="page active" id="page-home">
  <div class="hero">
    <div class="hero-bg"></div>
    <div class="hero-poster">
      <img src="https://image.tmdb.org/t/p/w500/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg" alt="Dune: Part Two" onerror="this.style.background='#1a1a2e'">
    </div>
    <div class="hero-content">
      <div class="hero-badge">🔥 Featured Movie</div>
      <h1 class="hero-title">Dune:<br>Part Two</h1>
      <div class="hero-meta">
        <span class="rating-stars">★★★★★</span>
        <span>4.8/5</span>
        <span class="dot"></span>
        <span>2h 46m</span>
        <span class="dot"></span>
        <span>Sci-Fi</span>
      </div>
      <p class="hero-desc">Paul Atreides unites with Chani and the Fremen while seeking revenge against the conspirators who destroyed his family.</p>
      <div class="hero-actions">
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
      <div class="stat-card-val">48</div>
      <div class="stat-card-label">Movies Showing</div>
      <div class="stat-card-change change-up">▲ 12 new this week</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon">🎭</div>
      <div class="stat-card-val">8</div>
      <div class="stat-card-label">Cinema Halls</div>
      <div class="stat-card-change change-up">▲ 2 reopened</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon">🍿</div>
      <div class="stat-card-val">120+</div>
      <div class="stat-card-label">Shop Products</div>
      <div class="stat-card-change change-up">▲ New combos added</div>
    </div>
    <div class="stat-card">
      <div class="stat-card-icon">⭐</div>
      <div class="stat-card-val">4.8</div>
      <div class="stat-card-label">Avg Rating</div>
      <div class="stat-card-change change-up">▲ Customer score</div>
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
