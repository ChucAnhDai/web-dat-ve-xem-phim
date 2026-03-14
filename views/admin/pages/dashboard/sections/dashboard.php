<div class="stats-grid">
  <div class="stat-card red">
    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2"/><path d="M7 2v20M17 2v20M2 12h20"/></svg></div>
    <div class="stat-value">248</div>
    <div class="stat-label">Total Movies</div>
    <div class="stat-change up">+12 this month</div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
    <div class="stat-value">14,872</div>
    <div class="stat-label">Total Users</div>
    <div class="stat-change up">+340 this week</div>
  </div>
  <div class="stat-card gold">
    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></div>
    <div class="stat-value">1,294</div>
    <div class="stat-label">Tickets Sold Today</div>
    <div class="stat-change up">+18% vs yesterday</div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
    <div class="stat-value">$84,290</div>
    <div class="stat-label">Shop Revenue</div>
    <div class="stat-change up">+$6,120 this month</div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/></svg></div>
    <div class="stat-value">47</div>
    <div class="stat-label">Orders Pending</div>
    <div class="stat-change down">Needs attention</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg></div>
    <div class="stat-value">18</div>
    <div class="stat-label">Active Promotions</div>
    <div class="stat-change up">3 expiring soon</div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Ticket Sales</div><div class="card-sub">Last 14 days</div></div>
    </div>
    <div class="card-body">
      <div class="chart-area" id="dashboardTicketChart"></div>
      <div class="chart-labels" id="dashboardTicketLabels"></div>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <div><div class="card-title">Revenue Split</div><div class="card-sub">Cinema vs shop</div></div>
    </div>
    <div class="card-body">
      <svg id="dashboardRevenueChart" viewBox="0 0 500 180" style="width:100%;height:200px;"></svg>
    </div>
  </div>
</div>

<div class="grid-main-side">
  <div style="display:flex;flex-direction:column;gap:20px;">
    <div class="card">
      <div class="card-header">
        <div><div class="card-title">Recent Ticket Orders</div><div class="card-sub">Latest booking transactions</div></div>
        <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/ticket-orders" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Order ID</th><th>User</th><th>Movie</th><th>Seats</th><th>Total</th><th>Status</th></tr></thead>
          <tbody id="dashboardTicketOrders"></tbody>
        </table>
      </div>
    </div>
    <div class="card">
      <div class="card-header">
        <div><div class="card-title">Recent Shop Orders</div><div class="card-sub">Latest product purchases</div></div>
        <a href="<?php echo htmlspecialchars($appBase, ENT_QUOTES, 'UTF-8'); ?>/admin/shop-orders" class="btn btn-ghost btn-sm">View All</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Order ID</th><th>User</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
          <tbody id="dashboardShopOrders"></tbody>
        </table>
      </div>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:20px;">
    <div class="card">
      <div class="card-header"><div class="card-title">Top Movies</div><span class="badge gold">This Week</span></div>
      <div class="card-body" id="dashboardTopMovies"></div>
    </div>
    <div class="card">
      <div class="card-header"><div class="card-title">Low Stock Alert</div><span class="badge red">5 items</span></div>
      <div class="card-body no-pad" id="dashboardLowStock"></div>
    </div>
  </div>
</div>

<script>
function handleDashboardSectionAction() {
  showToast('Report exported', 'success');
}

document.addEventListener('DOMContentLoaded', function () {
  const ticketValues = [820,1100,940,1380,1020,1240,1560,980,1420,1680,1240,1820,1440,1294];
  const ticketLabels = ['27','28','1','2','3','4','5','6','7','8','9','10','11','12'];
  const ticketChart = document.getElementById('dashboardTicketChart');
  const maxValue = Math.max(...ticketValues);
  ticketChart.innerHTML = ticketValues.map(value => {
    const height = Math.max(8, (value / maxValue) * 180);
    return `<div class="chart-bar" data-val="${value}" style="height:${height}px;background:linear-gradient(to top,var(--red) 0%,rgba(229,9,20,0.4) 100%);"></div>`;
  }).join('');
  document.getElementById('dashboardTicketLabels').innerHTML = ticketLabels.map(label => `<span>${label}</span>`).join('');

  const revenueSvg = document.getElementById('dashboardRevenueChart');
  const cinema = [28000,32000,41000,38000,52000,48000,61000,55000,72000,68000,84000,90000];
  const shop = [12000,15000,18000,22000,19000,25000,30000,27000,35000,38000,42000,50000];
  const width = 500;
  const height = 180;
  const pad = 20;
  const maxRevenue = Math.max(...cinema, ...shop);
  const points = data => data.map((value, index) => `${pad + (index / (data.length - 1)) * (width - pad * 2)},${height - pad - ((value / maxRevenue) * (height - pad * 2))}`).join(' ');
  const area = data => {
    const path = data.map((value, index) => `${pad + (index / (data.length - 1)) * (width - pad * 2)},${height - pad - ((value / maxRevenue) * (height - pad * 2))}`);
    return `${pad},${height - pad} ${path.join(' ')} ${width - pad},${height - pad}`;
  };
  revenueSvg.innerHTML = `<defs><linearGradient id="dashboardCinemaGradient" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#E50914" stop-opacity="0.3"/><stop offset="100%" stop-color="#E50914" stop-opacity="0"/></linearGradient><linearGradient id="dashboardShopGradient" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#C9A84C" stop-opacity="0.3"/><stop offset="100%" stop-color="#C9A84C" stop-opacity="0"/></linearGradient></defs><polygon points="${area(cinema)}" fill="url(#dashboardCinemaGradient)"/><polygon points="${area(shop)}" fill="url(#dashboardShopGradient)"/><polyline points="${points(cinema)}" fill="none" stroke="#E50914" stroke-width="2"/><polyline points="${points(shop)}" fill="none" stroke="#C9A84C" stroke-width="2"/>`;

  const ticketOrders = [
    {id:'#TK-2031',user:'Nguyen Van A',movie:'Avengers: Doomsday',seats:2,total:'$14.00',status:'Completed'},
    {id:'#TK-2030',user:'Tran Thi B',movie:'Cosmic Voyage II',seats:4,total:'$28.00',status:'Completed'},
    {id:'#TK-2029',user:'Hoang Minh C',movie:'The Last Breath',seats:1,total:'$7.00',status:'Pending'},
    {id:'#TK-2028',user:'Pham Staff',movie:'Haunted Echoes',seats:3,total:'$21.00',status:'Completed'},
  ];
  document.getElementById('dashboardTicketOrders').innerHTML = ticketOrders.map(order => `<tr><td class="td-id">${order.id}</td><td class="td-bold">${order.user}</td><td class="td-muted">${order.movie}</td><td>${order.seats} seats</td><td style="font-weight:700;">${order.total}</td><td>${statusBadge(order.status)}</td></tr>`).join('');

  const shopOrders = [
    {id:'#SH-0042',user:'Hoang Minh C',items:3,total:'$22.50',payment:'MoMo',status:'Shipped'},
    {id:'#SH-0041',user:'Nguyen Van A',items:2,total:'$11.70',payment:'VNPay',status:'Delivered'},
    {id:'#SH-0040',user:'Tran Thi B',items:5,total:'$45.00',payment:'PayPal',status:'Pending'},
    {id:'#SH-0039',user:'Vu Quoc E',items:1,total:'$3.20',payment:'Cash',status:'Delivered'},
  ];
  document.getElementById('dashboardShopOrders').innerHTML = shopOrders.map(order => `<tr><td class="td-id">${order.id}</td><td class="td-bold">${order.user}</td><td>${order.items}</td><td style="font-weight:700;color:var(--gold);">${order.total}</td><td>${statusBadge(order.payment)}</td><td>${statusBadge(order.status)}</td></tr>`).join('');

  const topMovies = [
    {title:'Avengers: Doomsday',tickets:4820},
    {title:'Cosmic Voyage II',tickets:3240},
    {title:'The Last Breath',tickets:2180},
    {title:'Haunted Echoes',tickets:1640},
  ];
  document.getElementById('dashboardTopMovies').innerHTML = topMovies.map((movie, index) => `<div class="top-item"><div class="top-rank r${index < 3 ? index + 1 : 'n'}">${index + 1}</div><div class="top-info"><div class="top-name">${movie.title}</div><div class="top-meta">${movie.tickets.toLocaleString()} tickets</div></div><div class="top-val">${Math.round(movie.tickets / 100)}%</div></div>`).join('');

  const lowStock = [
    {name:'Coca-Cola 500ml',cat:'Beverages',stock:'12 left',level:'orange'},
    {name:'Nacho + Dip Set',cat:'Snacks',stock:'Out',level:'red'},
    {name:'Water Bottle 1L',cat:'Beverages',stock:'8 left',level:'orange'},
    {name:'Hot Dog Bundle',cat:'Snacks',stock:'3 left',level:'orange'},
  ];
  document.getElementById('dashboardLowStock').innerHTML = lowStock.map(item => `<div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid rgba(255,255,255,0.03);"><div style="flex:1;min-width:0;"><div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${item.name}</div><div style="font-size:11px;color:var(--text-muted);">${item.cat}</div></div><span class="badge ${item.level}">${item.stock}</span></div>`).join('');
});
</script>
