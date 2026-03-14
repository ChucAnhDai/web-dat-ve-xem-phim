<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card blue" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">142</div><div class="stat-label">Total Products</div></div>
  <div class="stat-card green" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">118</div><div class="stat-label">In Stock</div></div>
  <div class="stat-card orange" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">16</div><div class="stat-label">Low Stock</div></div>
  <div class="stat-card red" style="padding:16px;"><div style="font-size:24px;font-family:'Bebas Neue',sans-serif;">8</div><div class="stat-label">Out of Stock</div></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="toolbar-search">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
      <input id="productSearch" type="text" placeholder="Search products..." oninput="filterProducts(this.value)">
    </div>
    <select id="productCategoryFilter" class="select-filter" onchange="filterProducts()">
      <option>All Categories</option>
      <option>Snacks</option>
      <option>Beverages</option>
      <option>Merchandise</option>
      <option>Combos</option>
    </select>
    <select id="productStockFilter" class="select-filter" onchange="filterProducts()">
      <option>All Stock</option>
      <option>In Stock</option>
      <option>Low Stock</option>
      <option>Out of Stock</option>
    </select>
    <div class="toolbar-right">
      <span id="productCount" style="font-size:12px;color:var(--text-dim);">10 products</span>
      <button class="btn btn-ghost btn-sm" onclick="showToast('Products exported','success')">Export</button>
    </div>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr>
        <th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Brand</th><th>Weight</th><th>Created</th><th>Actions</th>
      </tr></thead>
      <tbody id="productsBody"></tbody>
    </table>
  </div>
  <div id="productsPagination"></div>
</div>

<script>
const productsData = [
  {name:'Large Popcorn Combo',cat:'Snacks',price:8.50,stock:240,brand:'CineShop',weight:'380g',date:'2025-11-01',thumb:'PC',lvl:'high'},
  {name:'Coca-Cola 500ml',cat:'Beverages',price:3.20,stock:12,brand:'Coca-Cola',weight:'500ml',date:'2025-10-15',thumb:'CC',lvl:'low'},
  {name:'CineShop Hoodie',cat:'Merchandise',price:45.00,stock:38,brand:'CineShop',weight:'450g',date:'2026-01-10',thumb:'HD',lvl:'high'},
  {name:'Nacho + Dip Set',cat:'Snacks',price:6.00,stock:0,brand:'CineSnack',weight:'250g',date:'2025-12-01',thumb:'ND',lvl:'out'},
  {name:'Premium Candy Mix',cat:'Snacks',price:4.50,stock:185,brand:'SweetBox',weight:'200g',date:'2025-09-20',thumb:'CM',lvl:'high'},
  {name:'Water Bottle 1L',cat:'Beverages',price:2.00,stock:8,brand:'AquaFresh',weight:'1L',date:'2025-11-30',thumb:'WB',lvl:'low'},
  {name:'Movie Mug',cat:'Merchandise',price:22.00,stock:55,brand:'CineShop',weight:'320g',date:'2026-01-22',thumb:'MG',lvl:'high'},
  {name:'Hot Dog Bundle',cat:'Snacks',price:7.50,stock:3,brand:'FastBite',weight:'180g',date:'2026-02-01',thumb:'HB',lvl:'low'},
  {name:'Orange Juice 330ml',cat:'Beverages',price:2.80,stock:0,brand:'Fresh',weight:'330ml',date:'2025-12-15',thumb:'OJ',lvl:'out'},
  {name:'CineShop Cap',cat:'Merchandise',price:18.00,stock:92,brand:'CineShop',weight:'120g',date:'2026-02-20',thumb:'CP',lvl:'high'},
];

function handleProductSectionAction() {
  openModal('Add New Product', productFormBody());
}

function renderProducts(data) {
  const startItem = data.length === 0 ? 0 : 1;
  document.getElementById('productsBody').innerHTML = data.map(product => {
    const stockClass = product.lvl === 'high' ? 'stock-high' : product.lvl === 'low' ? 'stock-low' : 'stock-out';
    const stockLabel = product.lvl === 'high' ? 'In Stock' : product.lvl === 'low' ? 'Low Stock' : 'Out of Stock';
    return `<tr>
      <td><div class="poster-img-placeholder">${product.thumb}</div></td>
      <td><div class="td-bold">${product.name}</div></td>
      <td><span class="badge gray">${product.cat}</span></td>
      <td style="font-weight:700;color:var(--gold);">$${product.price.toFixed(2)}</td>
      <td><div class="stock-indicator ${stockClass}"><div class="stock-dot"></div>${product.stock} (${stockLabel})</div></td>
      <td class="td-muted">${product.brand}</td>
      <td class="td-muted">${product.weight}</td>
      <td class="td-muted">${product.date}</td>
      <td><div class="actions-row">
        <button class="action-btn view" title="View" onclick="showToast('Viewing ${product.name}','info')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn edit" title="Edit" onclick="openModal('Edit Product', productFormBody({name:'${product.name}',price:${product.price},stock:${product.stock},brand:'${product.brand}'}))"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" title="Delete" onclick="showToast('Product deleted','error')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`;
  }).join('');
  document.getElementById('productsPagination').innerHTML = buildPagination(`Showing ${startItem}-${data.length} of ${data.length} products`, Math.max(1, Math.ceil(data.length / 10)));
}

function filterProducts(q) {
  const searchInput = document.getElementById('productSearch');
  const searchTerm = typeof q === 'string' ? q.trim().toLowerCase() : (searchInput?.value || '').trim().toLowerCase();
  const selectedCategory = document.getElementById('productCategoryFilter')?.value || 'All Categories';
  const selectedStock = document.getElementById('productStockFilter')?.value || 'All Stock';
  const filtered = productsData.filter(product => {
    const matchesQuery = searchTerm === '' || product.name.toLowerCase().includes(searchTerm) || product.cat.toLowerCase().includes(searchTerm) || product.brand.toLowerCase().includes(searchTerm);
    const matchesCategory = selectedCategory === 'All Categories' || product.cat === selectedCategory;
    const matchesStock = selectedStock === 'All Stock' || (selectedStock === 'In Stock' && product.lvl === 'high') || (selectedStock === 'Low Stock' && product.lvl === 'low') || (selectedStock === 'Out of Stock' && product.lvl === 'out');
    return matchesQuery && matchesCategory && matchesStock;
  });

  renderProducts(filtered);
  document.getElementById('productCount').textContent = `${filtered.length} products`;
}

document.addEventListener('DOMContentLoaded', function () {
  filterProducts();
});
</script>
