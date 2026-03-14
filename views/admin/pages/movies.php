<?php
$movieSection = $activePage ?? 'movies';
$sectionMeta = [
    'movies' => [
        'breadcrumb' => 'Movies',
        'title' => 'Movie Management',
        'subtitle' => 'Manage your cinema movie catalog',
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
        'subtitle' => 'Manage posters, banners, and media assets',
        'button' => 'Upload Image',
    ],
    'reviews' => [
        'breadcrumb' => 'Reviews',
        'title' => 'Review Moderation',
        'subtitle' => 'Monitor ratings and moderate customer feedback',
        'button' => 'Open Queue',
    ],
];
$meta = $sectionMeta[$movieSection] ?? $sectionMeta['movies'];
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

  <?php if ($movieSection === 'movies'): ?>
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
      <div class="stat-card red" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">248</div>
        <div class="stat-label">Total Movies</div>
      </div>
      <div class="stat-card green" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">84</div>
        <div class="stat-label">Now Showing</div>
      </div>
      <div class="stat-card blue" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">32</div>
        <div class="stat-label">Coming Soon</div>
      </div>
      <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">132</div>
        <div class="stat-label">Ended</div>
      </div>
    </div>

    <div class="card">
      <div class="toolbar">
        <div class="toolbar-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input type="text" placeholder="Search movies..." oninput="filterMovies(this.value)">
        </div>
        <select class="select-filter" onchange="filterMovies()">
          <option>All Categories</option>
          <option>Action</option><option>Drama</option><option>Comedy</option>
          <option>Horror</option><option>Sci-Fi</option><option>Animation</option><option>Romance</option><option>Thriller</option>
        </select>
        <select class="select-filter" onchange="filterMovies()">
          <option>All Status</option>
          <option>Now Showing</option><option>Coming Soon</option><option>Ended</option>
        </select>
        <div class="toolbar-right">
          <span style="font-size:12px;color:var(--text-dim);" id="movieCount">248 movies</span>
          <button class="btn btn-ghost btn-sm" onclick="showToast('Exported to CSV','success')">Export</button>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>Poster</th><th>Movie Title</th><th>Category</th><th>Duration</th>
            <th>Release Date</th><th>Rating</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody id="moviesBody"></tbody>
        </table>
      </div>
      <div id="moviesPagination"></div>
    </div>

    <script>
    const moviesData = [
      {title:'Avengers: Doomsday',cat:'Action',dur:'150 min',release:'2026-03-01',rating:4.8,status:'Now Showing',thumb:'AD'},
      {title:'The Last Breath',cat:'Drama',dur:'128 min',release:'2026-02-14',rating:4.5,status:'Now Showing',thumb:'LB'},
      {title:'Cosmic Voyage II',cat:'Sci-Fi',dur:'142 min',release:'2026-03-10',rating:4.7,status:'Now Showing',thumb:'CV'},
      {title:'Haunted Echoes',cat:'Horror',dur:'108 min',release:'2026-01-28',rating:3.9,status:'Now Showing',thumb:'HE'},
      {title:'Funny Bones 3',cat:'Comedy',dur:'95 min',release:'2026-03-20',rating:4.2,status:'Coming Soon',thumb:'FB'},
      {title:'Dragon Quest',cat:'Animation',dur:'112 min',release:'2026-04-01',rating:4.6,status:'Coming Soon',thumb:'DQ'},
      {title:'The Heist',cat:'Thriller',dur:'132 min',release:'2025-12-15',rating:4.4,status:'Ended',thumb:'TH'},
      {title:'Neon City',cat:'Sci-Fi',dur:'138 min',release:'2026-04-15',rating:0,status:'Coming Soon',thumb:'NC'},
      {title:'Love In Tokyo',cat:'Romance',dur:'104 min',release:'2025-11-01',rating:4.1,status:'Ended',thumb:'LT'},
      {title:'Iron Circuit',cat:'Action',dur:'145 min',release:'2026-05-01',rating:0,status:'Coming Soon',thumb:'IC'},
      {title:'Shadow Protocol',cat:'Thriller',dur:'125 min',release:'2026-02-28',rating:4.3,status:'Now Showing',thumb:'SP'},
      {title:'Ocean Drift',cat:'Drama',dur:'118 min',release:'2025-10-10',rating:4.0,status:'Ended',thumb:'OD'},
    ];

    function handleMovieSectionAction() {
      openModal('Add New Movie', movieFormBody());
    }

    function renderMovies(data) {
      document.getElementById('moviesBody').innerHTML = data.map(m => `
        <tr>
          <td><div class="poster-img-placeholder">${m.thumb}</div></td>
          <td><div class="td-bold">${m.title}</div></td>
          <td><span class="badge gray">${m.cat}</span></td>
          <td class="td-muted">${m.dur}</td>
          <td class="td-muted">${m.release}</td>
          <td>${stars(m.rating)}</td>
          <td>${statusBadge(m.status)}</td>
          <td><div class="actions-row">
            <button class="action-btn view" title="View" onclick="showToast('Viewing ${m.title}','info')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="action-btn edit" title="Edit" onclick="openModal('Edit Movie', movieFormBody({title:'${m.title}',cat:'${m.cat}',dur:'${m.dur}',release:'${m.release}',rating:${m.rating}}))">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="action-btn del" title="Delete" onclick="showToast('${m.title} deleted','error')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            </button>
            <button class="action-btn gold" title="Add Showtime" onclick="window.location.href='<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/showtimes'">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
            </button>
          </div></td>
        </tr>`).join('');
      document.getElementById('moviesPagination').innerHTML = buildPagination(`Showing 1-${data.length} of ${data.length} movies`, Math.ceil(data.length / 10));
    }

    function filterMovies(q = '') {
      const filtered = moviesData.filter(m =>
        m.title.toLowerCase().includes(q.toLowerCase()) ||
        m.cat.toLowerCase().includes(q.toLowerCase())
      );
      renderMovies(filtered);
      document.getElementById('movieCount').textContent = `${filtered.length} movies`;
    }

    document.addEventListener('DOMContentLoaded', function () {
      renderMovies(moviesData);
    });
    </script>
  <?php elseif ($movieSection === 'categories'): ?>
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
      <div class="stat-card red" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">12</div>
        <div class="stat-label">Active Categories</div>
      </div>
      <div class="stat-card gold" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">4</div>
        <div class="stat-label">Featured Genres</div>
      </div>
      <div class="stat-card blue" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">248</div>
        <div class="stat-label">Movies Tagged</div>
      </div>
      <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">2</div>
        <div class="stat-label">Hidden Categories</div>
      </div>
    </div>

    <div class="card">
      <div class="toolbar">
        <div class="toolbar-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input type="text" placeholder="Search categories..." oninput="filterMovieCategories(this.value)">
        </div>
        <select class="select-filter" onchange="filterMovieCategories()">
          <option>All Visibility</option>
          <option>Featured</option>
          <option>Standard</option>
          <option>Hidden</option>
        </select>
        <div class="toolbar-right">
          <span style="font-size:12px;color:var(--text-dim);" id="movieCategoryCount">12 categories</span>
          <button class="btn btn-ghost btn-sm" onclick="showToast('Category list exported','success')">Export</button>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>Name</th><th>Description</th><th>Movies</th><th>Featured</th><th>Updated</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody id="movieCategoriesBody"></tbody>
        </table>
      </div>
      <div id="movieCategoriesPagination"></div>
    </div>

    <script>
    const movieCategoriesData = [
      {name:'Action',desc:'Blockbusters, action set pieces, and adventure titles.',movies:48,featured:'Featured',updated:'2026-03-13',status:'Active'},
      {name:'Drama',desc:'Character-driven stories with emotional depth.',movies:36,featured:'Standard',updated:'2026-03-12',status:'Active'},
      {name:'Comedy',desc:'Light entertainment and audience-friendly releases.',movies:22,featured:'Standard',updated:'2026-03-08',status:'Active'},
      {name:'Horror',desc:'Thrillers, slashers, and supernatural releases.',movies:18,featured:'Featured',updated:'2026-03-11',status:'Active'},
      {name:'Sci-Fi',desc:'Future worlds, science fiction, and space stories.',movies:27,featured:'Featured',updated:'2026-03-10',status:'Active'},
      {name:'Animation',desc:'Family animation and studio tentpoles.',movies:16,featured:'Standard',updated:'2026-03-09',status:'Pending'},
      {name:'Romance',desc:'Relationship-driven stories and date-night picks.',movies:14,featured:'Standard',updated:'2026-03-05',status:'Active'},
      {name:'Thriller',desc:'Mystery, suspense, and crime-driven narratives.',movies:19,featured:'Standard',updated:'2026-03-07',status:'Active'},
      {name:'Documentary',desc:'Real-world stories and special event screenings.',movies:7,featured:'Hidden',updated:'2026-03-01',status:'Cancelled'},
      {name:'Anime',desc:'Japanese animated features and fan event releases.',movies:11,featured:'Featured',updated:'2026-03-06',status:'Active'},
      {name:'Indie',desc:'Festival picks and smaller theatrical runs.',movies:17,featured:'Standard',updated:'2026-02-27',status:'Pending'},
      {name:'Classics',desc:'Reissues and anniversary special screenings.',movies:13,featured:'Hidden',updated:'2026-02-25',status:'Cancelled'},
    ];

    function handleMovieSectionAction() {
      showToast('Open category form','info');
    }

    function renderMovieCategories(data) {
      document.getElementById('movieCategoriesBody').innerHTML = data.map(c => `
        <tr>
          <td><div class="td-bold">${c.name}</div></td>
          <td class="td-muted">${c.desc}</td>
          <td><span class="badge gray">${c.movies} titles</span></td>
          <td><span class="badge ${c.featured === 'Featured' ? 'gold' : 'gray'}">${c.featured}</span></td>
          <td class="td-muted">${c.updated}</td>
          <td>${statusBadge(c.status)}</td>
          <td><div class="actions-row">
            <button class="action-btn view" title="View" onclick="showToast('Viewing ${c.name}','info')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="action-btn edit" title="Edit" onclick="showToast('Edit ${c.name}','info')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="action-btn del" title="Hide" onclick="showToast('${c.name} updated','warning')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            </button>
          </div></td>
        </tr>`).join('');
      document.getElementById('movieCategoriesPagination').innerHTML = buildPagination(`Showing 1-${data.length} of ${data.length} categories`, Math.ceil(data.length / 10));
    }

    function filterMovieCategories(q = '') {
      const filtered = movieCategoriesData.filter(c =>
        c.name.toLowerCase().includes(q.toLowerCase()) ||
        c.desc.toLowerCase().includes(q.toLowerCase())
      );
      renderMovieCategories(filtered);
      document.getElementById('movieCategoryCount').textContent = `${filtered.length} categories`;
    }

    document.addEventListener('DOMContentLoaded', function () {
      renderMovieCategories(movieCategoriesData);
    });
    </script>
  <?php elseif ($movieSection === 'movie-images'): ?>
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
      <div class="stat-card red" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">426</div>
        <div class="stat-label">Total Assets</div>
      </div>
      <div class="stat-card green" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">188</div>
        <div class="stat-label">Approved Posters</div>
      </div>
      <div class="stat-card blue" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">91</div>
        <div class="stat-label">Hero Banners</div>
      </div>
      <div class="stat-card orange" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">23</div>
        <div class="stat-label">Pending Review</div>
      </div>
    </div>

    <div class="card">
      <div class="toolbar">
        <div class="toolbar-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input type="text" placeholder="Search images..." oninput="filterMovieImages(this.value)">
        </div>
        <select class="select-filter" onchange="filterMovieImages()">
          <option>All Asset Types</option>
          <option>Poster</option>
          <option>Banner</option>
          <option>Gallery</option>
        </select>
        <div class="toolbar-right">
          <span style="font-size:12px;color:var(--text-dim);" id="movieImageCount">8 assets</span>
          <button class="btn btn-ghost btn-sm" onclick="showToast('Asset manifest exported','success')">Export</button>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>Preview</th><th>Movie</th><th>Asset Type</th><th>Resolution</th><th>Size</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody id="movieImagesBody"></tbody>
        </table>
      </div>
      <div id="movieImagesPagination"></div>
    </div>

    <script>
    const movieImagesData = [
      {code:'PS',movie:'Avengers: Doomsday',type:'Poster',resolution:'2000x3000',size:'1.8 MB',status:'Active'},
      {code:'HB',movie:'Avengers: Doomsday',type:'Banner',resolution:'2400x900',size:'2.1 MB',status:'Active'},
      {code:'GL',movie:'The Last Breath',type:'Gallery',resolution:'1920x1080',size:'1.1 MB',status:'Pending'},
      {code:'PS',movie:'Cosmic Voyage II',type:'Poster',resolution:'2000x3000',size:'1.7 MB',status:'Active'},
      {code:'GL',movie:'Haunted Echoes',type:'Gallery',resolution:'1920x1080',size:'960 KB',status:'Pending'},
      {code:'HB',movie:'Funny Bones 3',type:'Banner',resolution:'2400x900',size:'1.6 MB',status:'Active'},
      {code:'PS',movie:'Dragon Quest',type:'Poster',resolution:'2000x3000',size:'1.5 MB',status:'Active'},
      {code:'GL',movie:'Neon City',type:'Gallery',resolution:'1920x1080',size:'1.0 MB',status:'Cancelled'},
    ];

    function handleMovieSectionAction() {
      showToast('Open upload dialog','info');
    }

    function renderMovieImages(data) {
      document.getElementById('movieImagesBody').innerHTML = data.map(i => `
        <tr>
          <td><div class="poster-img-placeholder">${i.code}</div></td>
          <td><div class="td-bold">${i.movie}</div></td>
          <td><span class="badge gray">${i.type}</span></td>
          <td class="td-muted">${i.resolution}</td>
          <td class="td-muted">${i.size}</td>
          <td>${statusBadge(i.status)}</td>
          <td><div class="actions-row">
            <button class="action-btn view" title="Preview" onclick="showToast('Preview ${i.movie} ${i.type}','info')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="action-btn edit" title="Replace" onclick="showToast('Replace ${i.movie} ${i.type}','info')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <button class="action-btn del" title="Archive" onclick="showToast('${i.movie} asset archived','warning')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            </button>
          </div></td>
        </tr>`).join('');
      document.getElementById('movieImagesPagination').innerHTML = buildPagination(`Showing 1-${data.length} of ${data.length} assets`, Math.ceil(data.length / 10));
    }

    function filterMovieImages(q = '') {
      const filtered = movieImagesData.filter(i =>
        i.movie.toLowerCase().includes(q.toLowerCase()) ||
        i.type.toLowerCase().includes(q.toLowerCase())
      );
      renderMovieImages(filtered);
      document.getElementById('movieImageCount').textContent = `${filtered.length} assets`;
    }

    document.addEventListener('DOMContentLoaded', function () {
      renderMovieImages(movieImagesData);
    });
    </script>
  <?php else: ?>
    <div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
      <div class="stat-card red" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">1,284</div>
        <div class="stat-label">Published Reviews</div>
      </div>
      <div class="stat-card gold" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">4.4</div>
        <div class="stat-label">Average Rating</div>
      </div>
      <div class="stat-card orange" style="padding:16px;">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">17</div>
        <div class="stat-label">Pending Moderation</div>
      </div>
      <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);">
        <div style="font-size:24px;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;">9</div>
        <div class="stat-label">Hidden Reviews</div>
      </div>
    </div>

    <div class="card">
      <div class="toolbar">
        <div class="toolbar-search">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
          <input type="text" placeholder="Search reviews..." oninput="filterMovieReviews(this.value)">
        </div>
        <select class="select-filter" onchange="filterMovieReviews()">
          <option>All Status</option>
          <option>Confirmed</option>
          <option>Pending</option>
          <option>Cancelled</option>
        </select>
        <div class="toolbar-right">
          <span style="font-size:12px;color:var(--text-dim);" id="movieReviewCount">8 reviews</span>
          <button class="btn btn-ghost btn-sm" onclick="showToast('Review report exported','success')">Export</button>
        </div>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>User</th><th>Movie</th><th>Rating</th><th>Comment</th><th>Status</th><th>Submitted</th><th>Actions</th>
          </tr></thead>
          <tbody id="movieReviewsBody"></tbody>
        </table>
      </div>
      <div id="movieReviewsPagination"></div>
    </div>

    <script>
    const movieReviewsData = [
      {user:'Nguyen An',movie:'Avengers: Doomsday',rating:5,comment:'Great pacing and visuals from start to finish.',status:'Confirmed',submitted:'2026-03-13'},
      {user:'Tran Mai',movie:'The Last Breath',rating:4,comment:'Strong acting and a memorable final scene.',status:'Confirmed',submitted:'2026-03-12'},
      {user:'Le Quang',movie:'Cosmic Voyage II',rating:5,comment:'Huge scale and excellent sound design.',status:'Pending',submitted:'2026-03-12'},
      {user:'Pham Linh',movie:'Haunted Echoes',rating:3,comment:'Good atmosphere but a little slow in the middle.',status:'Confirmed',submitted:'2026-03-11'},
      {user:'Hoang Vy',movie:'Funny Bones 3',rating:4,comment:'Crowd pleasing and funny enough for families.',status:'Pending',submitted:'2026-03-10'},
      {user:'Vu Khanh',movie:'Dragon Quest',rating:5,comment:'Beautiful animation and a lot of heart.',status:'Confirmed',submitted:'2026-03-09'},
      {user:'Bui Nam',movie:'Neon City',rating:2,comment:'The plot was hard to follow.',status:'Cancelled',submitted:'2026-03-08'},
      {user:'Dao Nhi',movie:'Iron Circuit',rating:4,comment:'Promising teaser and strong early buzz.',status:'Pending',submitted:'2026-03-07'},
    ];

    function handleMovieSectionAction() {
      showToast('Opening moderation queue','info');
    }

    function renderMovieReviews(data) {
      document.getElementById('movieReviewsBody').innerHTML = data.map(r => `
        <tr>
          <td><div class="td-bold">${r.user}</div></td>
          <td class="td-muted">${r.movie}</td>
          <td>${stars(r.rating)}</td>
          <td class="td-muted">${r.comment}</td>
          <td>${statusBadge(r.status)}</td>
          <td class="td-muted">${r.submitted}</td>
          <td><div class="actions-row">
            <button class="action-btn view" title="View" onclick="showToast('Viewing review from ${r.user}','info')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            </button>
            <button class="action-btn edit" title="Approve" onclick="showToast('Review approved','success')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>
            </button>
            <button class="action-btn del" title="Hide" onclick="showToast('Review hidden','warning')">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
            </button>
          </div></td>
        </tr>`).join('');
      document.getElementById('movieReviewsPagination').innerHTML = buildPagination(`Showing 1-${data.length} of ${data.length} reviews`, Math.ceil(data.length / 10));
    }

    function filterMovieReviews(q = '') {
      const filtered = movieReviewsData.filter(r =>
        r.user.toLowerCase().includes(q.toLowerCase()) ||
        r.movie.toLowerCase().includes(q.toLowerCase()) ||
        r.comment.toLowerCase().includes(q.toLowerCase())
      );
      renderMovieReviews(filtered);
      document.getElementById('movieReviewCount').textContent = `${filtered.length} reviews`;
    }

    document.addEventListener('DOMContentLoaded', function () {
      renderMovieReviews(movieReviewsData);
    });
    </script>
  <?php endif; ?>
</div>
