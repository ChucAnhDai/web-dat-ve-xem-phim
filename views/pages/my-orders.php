<div class="page-header">
  <h1 class="page-title">My Orders</h1>
  <p class="page-subtitle">Track and manage your purchases</p>
</div>

<div class="filter-bar">
  <div class="filter-chip active" data-filter="all">All Orders</div>
  <div class="filter-chip" data-filter="processing">Processing</div>
  <div class="filter-chip" data-filter="delivered">Delivered</div>
  <div class="filter-chip" data-filter="cancelled">Cancelled</div>
</div>

<div class="orders-table">
  <div class="table-header">
    <span>Order ID</span><span>Items</span><span>Date</span><span>Amount</span><span>Status</span><span>Action</span>
  </div>
  <div id="ordersBody"></div>
</div>

<!-- Order Detail Modal -->
<div class="modal-overlay" id="orderModal">
  <div class="modal">
    <div class="modal-close" onclick="closeModal()">✕</div>
    <div id="modalContent"></div>
  </div>
</div>

<script>
  // Mock data for simulation
  const ORDERS = [
    {id:'#CX-2026-001',items:[{name:'Classic Popcorn Combo',img:'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=100&q=80'},{name:'Large Cola',img:'https://images.unsplash.com/photo-1580031359745-f8ab35a1f9b5?w=100&q=80'},{name:'CinemaX T-Shirt',img:'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=100&q=80'}],date:'Mar 11, 2026',total:'$36.97',status:'delivered'},
    {id:'#CX-2026-002',items:[{name:'Large Cola',img:'https://images.unsplash.com/photo-1580031359745-f8ab35a1f9b5?w=100&q=80'}],date:'Mar 8, 2026',total:'$9.98',status:'processing'},
    {id:'#CX-2026-003',items:[{name:'CinemaX T-Shirt',img:'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?w=100&q=80'}],date:'Mar 2, 2026',total:'$24.99',status:'delivered'},
    {id:'#CX-2026-004',items:[{name:'VIP Combo Deluxe',img:'https://images.unsplash.com/photo-1576866209830-589e1bfbaa4d?w=100&q=80'},{name:'Nachos',img:'https://images.unsplash.com/photo-1513456852971-30c0b8199d4d?w=100&q=80'}],date:'Feb 28, 2026',total:'$30.98',status:'cancelled'},
    {id:'#CX-2026-005',items:[{name:'Caramel Popcorn',img:'https://images.unsplash.com/photo-1524041255072-7da0525d6b34?w=100&q=80'}],date:'Feb 20, 2026',total:'$6.99',status:'delivered'},
  ];

  let currentFilter = 'all';

  function renderOrders() {
    const list = currentFilter === 'all' ? ORDERS : ORDERS.filter(o=>o.status===currentFilter);
    const body = document.getElementById('ordersBody');
    if (!body) return;
    
    if (!list.length) { 
        body.innerHTML='<p style="color:var(--text2);padding:40px 20px;text-align:center">No orders found.</p>'; 
        return; 
    }
    
    body.innerHTML = list.map((o,i)=>`
      <div class="table-row">
        <span class="order-id">${o.id}</span>
        <div class="order-items-preview">
          ${o.items.slice(0,2).map(it=>`<div class="order-item-thumb"><img src="${it.img}" alt="" onerror="this.style.display='none'"></div>`).join('')}
          ${o.items.length>2?`<div class="more-items">+${o.items.length-2}</div>`:''}
        </div>
        <span style="font-size:12px;color:var(--text2)">${o.date}</span>
        <span style="font-weight:600">${o.total}</span>
        <span class="ticket-status status-${o.status === 'delivered' ? 'success' : (o.status === 'processing' ? 'pending' : 'danger')}">${o.status}</span>
        <button class="btn btn-secondary btn-sm" onclick="viewOrder(${i})">View</button>
      </div>`).join('');
  }

  function viewOrder(idx) {
    const filteredList = currentFilter === 'all' ? ORDERS : ORDERS.filter(o=>o.status===currentFilter);
    const o = filteredList[idx];
    if (!o) return;
    
    document.getElementById('modalContent').innerHTML = `
      <h3 style="font-family:'Bebas Neue',cursive;font-size:24px;letter-spacing:0.5px;margin-bottom:4px">Order ${o.id}</h3>
      <p style="font-size:12px;color:var(--text2);margin-bottom:16px">${o.date}</p>
      <div class="divider"></div>
      ${o.items.map(it=>`<div style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border2)">
        <div style="width:44px;height:44px;border-radius:6px;overflow:hidden;background:var(--bg3);flex-shrink:0"><img src="${it.img}" style="width:100%;height:100%;object-fit:cover" onerror="this.style.display='none'"></div>
        <span style="font-size:13px;font-weight:600;flex:1">${it.name}</span>
      </div>`).join('')}
      <div class="divider"></div>
      <div style="display:flex;justify-content:space-between;align-items:center">
        <span style="font-weight:700;font-size:15px">Total: <span style="color:var(--red)">${o.total}</span></span>
        <span class="ticket-status status-${o.status === 'delivered' ? 'success' : (o.status === 'processing' ? 'pending' : 'danger')}">${o.status}</span>
      </div>`;
    document.getElementById('orderModal').classList.add('open');
  }

  function closeModal() { 
    const modal = document.getElementById('orderModal');
    if (modal) modal.classList.remove('open'); 
  }

  // Handle outside click for modal
  const modalOverlay = document.getElementById('orderModal');
  if (modalOverlay) {
      modalOverlay.addEventListener('click', e => { 
          if(e.target === e.currentTarget) closeModal(); 
      });
  }

  // Initialize event listeners for filters
  document.querySelectorAll('[data-filter]').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('[data-filter]').forEach(c=>c.classList.remove('active'));
      chip.classList.add('active');
      currentFilter = chip.dataset.filter;
      renderOrders();
    });
  });

  // Render on load
  document.addEventListener('DOMContentLoaded', renderOrders);
  // Also call immediately if DOM already loaded
  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    renderOrders();
  }
</script>
