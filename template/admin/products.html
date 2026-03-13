<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Products — CineShop Admin</title>
<link rel="stylesheet" href="shared.css">
</head>
<body>
<div class="layout">
    <div id="sidebarMount"></div>
<div class="main-wrap" id="mainWrap">
        <div id="headerMount"></div>
<div class="page">
      <div class="page-header">
        <div>
          <div class="breadcrumb"><span>Home</span><span class="sep">›</span><span>Shop</span><span class="sep">›</span><span>Products</span></div>
          <h1 class="page-title">Product Management</h1>
          <p class="page-sub">Manage your online shop inventory</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('Add New Product', productFormBody())">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Add Product
        </button>
      </div>

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
            <input type="text" placeholder="Search products..." oninput="filterProducts(this.value)">
          </div>
          <select class="select-filter" onchange="filterProducts()">
            <option>All Categories</option><option>Snacks</option><option>Beverages</option><option>Merchandise</option><option>Combos</option>
          </select>
          <select class="select-filter">
            <option>All Stock</option><option>In Stock</option><option>Low Stock</option><option>Out of Stock</option>
          </select>
          <div class="toolbar-right">
            <span style="font-size:12px;color:var(--text-dim);" id="productCount">142 products</span>
            <button class="btn btn-ghost btn-sm" onclick="showToast('Exported','success')">Export</button>
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
    </div>
  </div>
</div>
<script src="shared.js"></script>
<script>
const productsData = [
  {name:'Large Popcorn Combo',cat:'Snacks',price:8.50,stock:240,brand:'CineShop',weight:'380g',date:'2025-11-01',emoji:'🍿',lvl:'high'},
  {name:'Coca-Cola 500ml',cat:'Beverages',price:3.20,stock:12,brand:'Coca-Cola',weight:'500ml',date:'2025-10-15',emoji:'🥤',lvl:'low'},
  {name:'CineShop Hoodie',cat:'Merchandise',price:45.00,stock:38,brand:'CineShop',weight:'450g',date:'2026-01-10',emoji:'👕',lvl:'high'},
  {name:'Nacho + Dip Set',cat:'Snacks',price:6.00,stock:0,brand:'CineSnack',weight:'250g',date:'2025-12-01',emoji:'🌮',lvl:'out'},
  {name:'Premium Candy Mix',cat:'Snacks',price:4.50,stock:185,brand:'SweetBox',weight:'200g',date:'2025-09-20',emoji:'🍬',lvl:'high'},
  {name:'Water Bottle 1L',cat:'Beverages',price:2.00,stock:8,brand:'AquaFresh',weight:'1L',date:'2025-11-30',emoji:'💧',lvl:'low'},
  {name:'Movie Mug',cat:'Merchandise',price:22.00,stock:55,brand:'CineShop',weight:'320g',date:'2026-01-22',emoji:'☕',lvl:'high'},
  {name:'Hot Dog Bundle',cat:'Snacks',price:7.50,stock:3,brand:'FastBite',weight:'180g',date:'2026-02-01',emoji:'🌭',lvl:'low'},
  {name:'Orange Juice 330ml',cat:'Beverages',price:2.80,stock:0,brand:'Fresh',weight:'330ml',date:'2025-12-15',emoji:'🍊',lvl:'out'},
  {name:'CineShop Cap',cat:'Merchandise',price:18.00,stock:92,brand:'CineShop',weight:'120g',date:'2026-02-20',emoji:'🧢',lvl:'high'},
];

function renderProducts(data) {
  document.getElementById('productsBody').innerHTML = data.map(p=>{
    const sc = p.lvl==='high'?'stock-high':p.lvl==='low'?'stock-low':'stock-out';
    const sl = p.lvl==='high'?'In Stock':p.lvl==='low'?'Low Stock':'Out of Stock';
    return `<tr>
      <td><div class="product-thumb">${p.emoji}</div></td>
      <td><div class="td-bold">${p.name}</div></td>
      <td><span class="badge gray">${p.cat}</span></td>
      <td style="font-weight:700;color:var(--gold);">$${p.price.toFixed(2)}</td>
      <td><div class="stock-indicator ${sc}"><div class="stock-dot"></div>${p.stock} (${sl})</div></td>
      <td class="td-muted">${p.brand}</td>
      <td class="td-muted">${p.weight}</td>
      <td class="td-muted">${p.date}</td>
      <td><div class="actions-row">
        <button class="action-btn view" onclick="showToast('Viewing ${p.name}','info')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg></button>
        <button class="action-btn edit" onclick="openModal('Edit Product', productFormBody({name:'${p.name}',price:${p.price},stock:${p.stock},brand:'${p.brand}'}))"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button>
        <button class="action-btn del" onclick="showToast('Product deleted','error')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg></button>
      </div></td>
    </tr>`;
  }).join('');
  document.getElementById('productsPagination').innerHTML = buildPagination(`Showing 1–${data.length} of ${data.length} products`);
}

function filterProducts(q='') {
  const filtered = productsData.filter(p => p.name.toLowerCase().includes(q.toLowerCase()) || p.cat.toLowerCase().includes(q.toLowerCase()));
  renderProducts(filtered);
  document.getElementById('productCount').textContent = `${filtered.length} products`;
}

function productFormBody(p={}) {
  return `<div class="form-grid">
    <div class="field"><label>Product Name</label><input class="input" placeholder="Enter product name" value="${p.name||''}"></div>
    <div class="field"><label>Category</label><select class="select"><option>Snacks</option><option>Beverages</option><option>Merchandise</option><option>Combos</option></select></div>
    <div class="field"><label>Price ($)</label><input class="input" type="number" placeholder="0.00" value="${p.price||''}"></div>
    <div class="field"><label>Stock Qty</label><input class="input" type="number" placeholder="0" value="${p.stock||''}"></div>
    <div class="field"><label>Brand</label><input class="input" placeholder="Brand name" value="${p.brand||''}"></div>
    <div class="field"><label>Weight</label><input class="input" placeholder="250g"></div>
    <div class="field"><label>Origin</label><input class="input" placeholder="Vietnam"></div>
    <div class="field"><label>SKU</label><input class="input" placeholder="CS-001"></div>
    <div class="field form-full"><label>Description</label><textarea class="textarea" placeholder="Product description..."></textarea></div>
    <div class="field form-full"><label>Product Images</label>
      <div class="upload-zone" onclick="showToast('File picker','info')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        <p>Drop files here or <span>browse</span></p>
      </div>
    </div>
  </div>`;
}

document.addEventListener('DOMContentLoaded', function(){

  renderProducts(productsData);
});
</script>
    <div id="footerMount"></div>
</body>
</html>
