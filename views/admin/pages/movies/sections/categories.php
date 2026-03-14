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

function movieCategorySurfaceOption(title, copy, checked = true) {
  return `<label class="check-option">
    <input type="checkbox"${checked ? ' checked' : ''}>
    <span><strong>${title}</strong><small>${copy}</small></span>
  </label>`;
}

function movieCategoryFormBody(category = {}) {
  const slug = category.slug || (category.name || '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  const featured = category.featured || 'Standard';
  const status = category.status || 'Active';
  const movieCount = category.movies || 0;

  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Discovery Setup</div>
      <div class="surface-card-copy">Shape how this genre appears in filters, curated shelves, and landing page modules before wiring backend logic.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Category Name</label><input class="input" placeholder="Action" value="${category.name || ''}"></div>
      <div class="field"><label>Slug</label><input class="input" placeholder="action" value="${slug}"></div>
      <div class="field"><label>Display Priority</label><select class="select">${buildOptions(['Featured', 'Standard', 'Hidden'], featured)}</select></div>
      <div class="field"><label>Status</label><select class="select">${buildOptions(['Active', 'Pending', 'Cancelled'], status)}</select></div>
      <div class="field"><label>Accent Theme</label><select class="select">${buildOptions(['Red Spotlight', 'Gold Premium', 'Blue Focus', 'Neutral Slate'], category.theme || 'Red Spotlight')}</select></div>
      <div class="field"><label>Hero Label</label><input class="input" placeholder="Weekend crowd-pleasers" value="${category.hero || ''}"></div>
      <div class="field form-full"><label>Description</label><textarea class="textarea" placeholder="Write a short description for the genre card...">${category.desc || ''}</textarea><div class="helper-text">This copy is reused in admin previews and customer-facing genre summaries.</div></div>
      <div class="field form-full"><label>Discovery Surfaces</label><div class="check-grid">
        ${movieCategorySurfaceOption('Catalog filters', 'Show this category in the public movie filter bar.', true)}
        ${movieCategorySurfaceOption('Homepage highlights', 'Allow editors to pin this genre into hero rails.', featured === 'Featured')}
        ${movieCategorySurfaceOption('Member recommendations', 'Use this tag in personalized carousels.', status !== 'Cancelled')}
        ${movieCategorySurfaceOption('Campaign landing pages', 'Expose the genre in campaign templates.', featured !== 'Hidden')}
      </div></div>
      <div class="field form-full"><label>Preview Card</label>
        <div class="preview-banner">
          <div class="preview-banner-title">${category.name || 'New Category Preview'}</div>
          <div class="preview-banner-copy">${category.desc || 'Use this preview to shape the tone of the genre block before backend integration.'}</div>
          <div class="meta-pills">
            <span class="badge ${featured === 'Featured' ? 'gold' : featured === 'Hidden' ? 'gray' : 'blue'}">${featured}</span>
            <span class="badge gray">${movieCount} titles linked</span>
            <span class="badge ${status === 'Cancelled' ? 'red' : status === 'Pending' ? 'orange' : 'green'}">${status}</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function openMovieCategoryModal(title, category = {}) {
  const isEdit = /^Edit/i.test(title);
  openModal(title, movieCategoryFormBody(category), {
    description: isEdit
      ? 'Refine the genre card, merchandising priority, and filter visibility for this category.'
      : 'Add a new movie category and prepare the way it appears across the catalog.',
    note: 'UI preview only. Category settings are not persisted yet.',
    submitLabel: isEdit ? 'Update Category' : 'Create Category',
    successMessage: isEdit ? 'Category preview updated!' : 'Category preview staged!',
  });
}

function handleMovieSectionAction() {
  openMovieCategoryModal('Add Category');
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
        <button class="action-btn edit" title="Edit" onclick="openMovieCategoryModal('Edit Category', {name:'${c.name}',desc:'${c.desc}',featured:'${c.featured}',status:'${c.status}',movies:${c.movies}})">
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
