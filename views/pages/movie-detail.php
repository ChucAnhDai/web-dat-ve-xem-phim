<div style="margin-bottom:16px">
  <a href="<?php echo $publicBase; ?>/movies" class="btn btn-ghost btn-sm">← Back to Movies</a>
</div>

<div class="detail-hero">
  <div>
    <div class="detail-poster">
      <img src="https://image.tmdb.org/t/p/w500/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg" alt="Dune: Part Two">
    </div>
  </div>
  <div class="detail-info">
    <div class="detail-tags">
      <span class="tag tag-genre">Sci-Fi</span>
      <span class="tag tag-genre">Adventure</span>
      <span class="tag tag-genre">Drama</span>
    </div>
    <h1 class="detail-title">Dune: Part Two</h1>
    <div class="detail-meta-row">
      <span class="rating-stars">★★★★★</span>
      <span style="font-weight:700">8.9</span>
      <span style="color:var(--text3)">/ 10</span>
      <span class="dot"></span>
      <span>2h 46m</span>
      <span class="dot"></span>
      <span>PG-13</span>
      <span class="dot"></span>
      <span>2024</span>
    </div>
    <p class="detail-desc">Paul Atreides unites with Chani and the Fremen while seeking revenge against the conspirators who destroyed his family. Facing a choice between the love of his life and the fate of the known universe, he endeavors to prevent a terrible future only he can foresee.</p>
    <div class="detail-credits">
      <div class="credit-item"><label>Director</label><p>Denis Villeneuve</p></div>
      <div class="credit-item"><label>Writer</label><p>Jon Spaihts</p></div>
      <div class="credit-item"><label>Studio</label><p>Warner Bros.</p></div>
      <div class="credit-item"><label>Lead</label><p>Timothée Chalamet</p></div>
      <div class="credit-item"><label>Lead</label><p>Zendaya</p></div>
      <div class="credit-item"><label>Cast</label><p>Rebecca Ferguson</p></div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap">
      <a href="<?php echo $publicBase; ?>/seat-selection" class="btn btn-primary btn-lg">🎫 Book Ticket</a>
      <button class="btn btn-secondary btn-lg" onclick="showToast('▶','Trailer','Loading Dune: Part Two trailer...')">▶ Watch Trailer</button>
      <button class="btn btn-secondary btn-lg" onclick="showToast('🔖','Saved','Added to your watchlist.')">🔖 Watchlist</button>
    </div>
  </div>
</div>

<div class="trailer-section">
  <div class="trailer-placeholder" onclick="showToast('🎬','Playing Trailer','Dune: Part Two — Official Trailer')">
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,rgba(229,9,20,0.1),rgba(0,0,0,0.5))"></div>
    <div style="text-align:center;position:relative;z-index:2">
      <div class="play-btn-big">▶</div>
      <p style="font-size:13px;color:var(--text2);margin-top:10px">Watch Official Trailer</p>
    </div>
  </div>
</div>

<div class="showtime-section">
  <h3 style="font-family:'Bebas Neue',cursive;font-size:22px;letter-spacing:0.5px;margin-bottom:16px">🕐 Available Showtimes</h3>
  <div class="date-tabs">
    <div class="date-tab active"><div class="day">Today</div><div class="date">11</div></div>
    <div class="date-tab"><div class="day">Wed</div><div class="date">12</div></div>
    <div class="date-tab"><div class="day">Thu</div><div class="date">13</div></div>
    <div class="date-tab"><div class="day">Fri</div><div class="date">14</div></div>
    <div class="date-tab"><div class="day">Sat</div><div class="date">15</div></div>
    <div class="date-tab"><div class="day">Sun</div><div class="date">16</div></div>
  </div>
  <div class="cinema-row">
    <div class="cinema-name">🎭 Hall 1 — IMAX</div>
    <div class="time-chips">
      <div class="time-chip full">10:00 AM</div>
      <a href="<?php echo $publicBase; ?>/seat-selection" class="time-chip">1:30 PM</a>
      <a href="<?php echo $publicBase; ?>/seat-selection" class="time-chip" style="background:var(--red);border-color:var(--red);color:#fff">4:45 PM</a>
      <a href="<?php echo $publicBase; ?>/seat-selection" class="time-chip">8:00 PM</a>
      <a href="<?php echo $publicBase; ?>/seat-selection" class="time-chip">10:30 PM</a>
    </div>
  </div>
  <div class="cinema-row">
    <div class="cinema-name">🎭 Hall 3 — Standard</div>
    <div class="time-chips">
      <div class="time-chip full">11:00 AM</div>
      <div class="time-chip full">2:15 PM</div>
      <a href="<?php echo $publicBase; ?>/seat-selection" class="time-chip">5:30 PM</a>
      <a href="<?php echo $publicBase; ?>/seat-selection" class="time-chip">9:15 PM</a>
    </div>
  </div>
  <div class="cinema-row">
    <div class="cinema-name">🎭 Hall 5 — 4DX</div>
    <div class="time-chips">
      <a href="<?php echo $publicBase; ?>/seat-selection" class="time-chip">3:00 PM</a>
      <a href="<?php echo $publicBase; ?>/seat-selection" class="time-chip">7:15 PM</a>
    </div>
  </div>
</div>

<!-- Related Movies -->
<div style="margin-top:36px">
  <div class="section-header">
    <h2 class="section-title">You May Also <span>Like</span></h2>
    <a href="<?php echo $publicBase; ?>/movies" class="btn btn-ghost btn-sm">View all →</a>
  </div>
  <div class="movies-grid" id="relatedMovies"></div>
</div>

<script>
  // This will be executed after app.js
  document.addEventListener('DOMContentLoaded', function() {
    if (typeof movies !== 'undefined') {
      document.getElementById('relatedMovies').innerHTML = movies.filter(m=>m.status==='now').slice(1,5).map(m=>renderMovieCard(m)).join('');
    }
    
    document.querySelectorAll('.date-tab').forEach(tab => {
      tab.addEventListener('click', () => {
        document.querySelectorAll('.date-tab').forEach(t=>t.classList.remove('active'));
        tab.classList.add('active');
      });
    });
  });
</script>
