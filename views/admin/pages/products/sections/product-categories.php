<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">9</div><div class="stat-label">Categories</div></div>
  <div class="stat-card gold" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">3</div><div class="stat-label">Featured</div></div>
  <div class="stat-card green" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">142</div><div class="stat-label">Products Tagged</div></div>
  <div class="stat-card gray" style="padding:16px;background:var(--bg2);border:1px solid var(--border);"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">1</div><div class="stat-label">Hidden</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="productCategorySearch" type="text" placeholder="Search categories..." oninput="filterProductCategories(this.value)">
    </div>
    <select id="productCategoryVisibility" class="select-filter" onchange="filterProductCategories()">
      <option>All Visibility</option>
      <option>Featured</option>
      <option>Standard</option>
      <option>Hidden</option>
    </select>
    <div class="toolbar-right">
      <span id="productCategoryCount" style="font-size:12px;color:var(--text-dim);">9 categories</span>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Name</th><th>Description</th><th>Products</th><th>Display</th><th>Updated</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody id="productCategoriesBody"></tbody>
    </table>
  </div>
  <div id="productCategoriesPagination"></div>
</div>

<script>
const productCategoriesData = [
  {name:'Snacks',desc:'Popcorn, nachos, candy, and grab-and-go bites.',products:52,display:'Featured',updated:'2026-03-12',status:'Active'},
  {name:'Beverages',desc:'Soft drinks, coffee, tea, and bottled water.',products:21,display:'Featured',updated:'2026-03-10',status:'Active'},
  {name:'Merchandise',desc:'Branded apparel, mugs, and collectibles.',products:28,display:'Featured',updated:'2026-03-09',status:'Active'},
  {name:'Combos',desc:'Bundled items for upsell at checkout.',products:14,display:'Standard',updated:'2026-03-08',status:'Active'},
  {name:'Kids Packs',desc:'Smaller bundles designed for family bookings.',products:8,display:'Standard',updated:'2026-03-05',status:'Active'},
  {name:'Limited Editions',desc:'Short-run tie-in products for new releases.',products:5,display:'Standard',updated:'2026-03-01',status:'Pending'},
  {name:'Gift Cards',desc:'Physical and digital gift card offers.',products:4,display:'Standard',updated:'2026-02-27',status:'Active'},
  {name:'Seasonal',desc:'Holiday and campaign-only product selections.',products:6,display:'Hidden',updated:'2026-02-21',status:'Cancelled'},
  {name:'Healthy Picks',desc:'Lighter snack and drink alternatives.',products:4,display:'Standard',updated:'2026-02-18',status:'Pending'},
];

function productCategoryFormBody(category = {}) {
  const display = category.display || 'Standard';
  const status = category.status || 'Active';
  const channels = category.channels || ['POS counter', 'Mobile app'];

  return `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Collection Setup</div>
      <div class="surface-card-copy">Define how this product category is merchandised across the kiosk, mobile app, and combo upsell surfaces.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Category Name</label><input class="input" placeholder="Snacks" value="${category.name || ''}"></div>
      <div class="field"><label>Display</label><select class="select">${buildOptions(['Featured', 'Standard', 'Hidden'], display)}</select></div>
      <div class="field"><label>Status</label><select class="select">${buildOptions(['Active', 'Pending', 'Cancelled'], status)}</select></div>
      <div class="field"><label>Sort Order</label><input class="input" type="number" placeholder="10" value="${category.sort || ''}"></div>
      <div class="field"><label>Collection Slug</label><input class="input" placeholder="snacks" value="${category.slug || ''}"></div>
      <div class="field"><label>Icon Label</label><input class="input" placeholder="POP" value="${category.icon || ''}"></div>
      <div class="field form-full"><label>Description</label><textarea class="textarea" placeholder="Category description">${category.desc || ''}</textarea></div>
      <div class="field form-full"><label>Discovery Surfaces</label>
        <div class="check-grid">
          ${['POS counter', 'Mobile app', 'Combo builder', 'Homepage promo rail', 'Order upsell', 'Self-checkout'].map(channel => `
            <label class="check-option">
              <input type="checkbox"${channels.includes(channel) ? ' checked' : ''}>
              <span>${channel}</span>
            </label>`).join('')}
        </div>
      </div>
      <div class="field form-full"><label>Preview</label>
        <div class="preview-banner">
          <div class="preview-banner-title">${category.name || 'New product category'}</div>
          <div class="preview-banner-copy">${category.desc || 'Write a short description to explain how the category should feel in shop navigation and checkout upsell.'}</div>
          <div class="meta-pills">
            <span class="badge ${display === 'Featured' ? 'gold' : display === 'Hidden' ? 'gray' : 'blue'}">${display}</span>
            <span class="badge ${status === 'Cancelled' ? 'red' : status === 'Pending' ? 'orange' : 'green'}">${status}</span>
            <span class="badge gray">${category.products || 0} products</span>
          </div>
        </div>
      </div>
    </div>
  </div>`;
}

function openProductCategoryModal(title, category = {}) {
  const isEdit = /^Edit/i.test(title);
  openModal(title, productCategoryFormBody(category), {
    description: isEdit
      ? 'Adjust merchandising visibility, copy, and discovery surfaces for this product collection.'
      : 'Create a new product category and preview where it should appear in the snack shop journey.',
    note: 'UI preview only. Category settings are not persisted yet.',
    submitLabel: isEdit ? 'Update Category' : 'Create Category',
    successMessage: isEdit ? 'Category preview updated!' : 'Category preview staged!',
  });
}

function handleProductSectionAction() {
  openProductCategoryModal('Add Category');
}

function renderProductCategories(data) {
  const startItem = data.length === 0 ? 0 : 1;
  document.getElementById('productCategoriesBody').innerHTML = data.map(category => `
    <tr>
      <td><div class="td-bold">${category.name}</div></td>
      <td class="td-muted">${category.desc}</td>
      <td><span class="badge gray">${category.products} items</span></td>
      <td><span class="badge ${category.display === 'Featured' ? 'gold' : category.display === 'Hidden' ? 'gray' : 'blue'}">${category.display}</span></td>
      <td class="td-muted">${category.updated}</td>
      <td>${statusBadge(category.status)}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="showToast('Viewing ${category.name}','info')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn edit" title="Edit" onclick="openProductCategoryModal('Edit Category', {name:'${category.name}',desc:'${category.desc}',display:'${category.display}',status:'${category.status}',products:'${category.products}'})"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
      </div></td>
    </tr>`).join('');
  document.getElementById('productCategoriesPagination').innerHTML = buildPagination(`Showing ${startItem}-${data.length} of ${data.length} categories`, Math.max(1, Math.ceil(data.length / 10)));
}

function filterProductCategories(q) {
  const searchInput = document.getElementById('productCategorySearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedVisibility = document.getElementById('productCategoryVisibility')?.value || 'All Visibility';
  const filtered = productCategoriesData.filter(category => {
    const matchesQuery = searchTerm === '' || category.name.toLowerCase().includes(searchTerm) || category.desc.toLowerCase().includes(searchTerm);
    const matchesVisibility = selectedVisibility === 'All Visibility' || category.display === selectedVisibility;
    return matchesQuery && matchesVisibility;
  });

  renderProductCategories(filtered);
  document.getElementById('productCategoryCount').textContent = `${filtered.length} categories`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterProductCategories();
});
</script>
