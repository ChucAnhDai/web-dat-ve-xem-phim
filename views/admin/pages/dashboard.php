<div class="page">
  <div class="page-header">
    <div>
      <div class="breadcrumb"><span>Home</span><span class="sep">›</span><span>Dashboard</span></div>
      <h1 class="page-title">Dashboard Overview</h1>
      <p class="page-sub">Welcome back, Admin — here's what's happening today.</p>
    </div>
    <button class="btn btn-secondary" onclick="showToast('Report exported!','success')">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      Export Report
    </button>
  </div>

  <div class="stats-grid">
    <div class="stat-card red">
      <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2"/><path d="M7 2v20M17 2v20M2 12h20"/></svg></div>
      <div class="stat-value">248</div>
      <div class="stat-label">Total Movies</div>
      <div class="stat-change up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>+12 this month</div>
    </div>
    <div class="stat-card blue">
      <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div>
      <div class="stat-value">14,872</div>
      <div class="stat-label">Total Users</div>
      <div class="stat-change up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>+340 this week</div>
    </div>
    <div class="stat-card gold">
      <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12v6a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h6"/></svg></div>
      <div class="stat-value">1,294</div>
      <div class="stat-label">Tickets Sold Today</div>
      <div class="stat-change up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>+18% vs yesterday</div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
      <div class="stat-value">$84,290</div>
      <div class="stat-label">Shop Revenue</div>
      <div class="stat-change up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>+$6,120 this month</div>
    </div>
    <div class="stat-card orange">
      <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/></svg></div>
      <div class="stat-value">47</div>
      <div class="stat-label">Orders Pending</div>
      <div class="stat-change down"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>Needs attention</div>
    </div>
    <div class="stat-card purple">
      <div class="stat-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg></div>
      <div class="stat-value">18</div>
      <div class="stat-label">Active Promotions</div>
      <div class="stat-change up"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>3 expiring soon</div>
    </div>
  </div>

  <div class="grid-2">
    <div class="card">
      <div class="card-header">
        <div><div class="card-title">Ticket Sales</div><div class="card-sub">Daily — last 14 days</div></div>
        <div class="tabs">
          <button class="tab-btn active">14D</button>
          <button class="tab-btn">30D</button>
          <button class="tab-btn">90D</button>
        </div>
      </div>
      <div class="card-body">
        <div class="chart-area" id="ticketChart"></div>
        <div class="chart-labels" id="ticketLabels"></div>
      </div>
    </div>
    <div class="card">
      <div class="card-header">
        <div><div class="card-title">Revenue Overview</div><div class="card-sub">Monthly — current year</div></div>
        <div style="display:flex;gap:12px;align-items:center;">
          <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text-muted);"><div style="width:8px;height:8px;border-radius:2px;background:var(--red);"></div>Cinema</div>
          <div style="display:flex;align-items:center;gap:5px;font-size:11px;color:var(--text-muted);"><div style="width:8px;height:8px;border-radius:2px;background:var(--gold);"></div>Shop</div>
        </div>
      </div>
      <div class="card-body">
        <svg id="revChart" viewBox="0 0 500 180" style="width:100%;height:200px;"></svg>
      </div>
    </div>
  </div>

  <div class="grid-main-side">
    <div style="display:flex;flex-direction:column;gap:20px;">
      <div class="card">
        <div class="card-header">
          <div><div class="card-title">Recent Ticket Orders</div><div class="card-sub">Latest booking transactions</div></div>
          <a href="<?php echo htmlspecialchars($appBase); ?>/admin/ticket-orders" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Order ID</th><th>User</th><th>Movie</th><th>Seats</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
            <tbody id="ticketOrdersBody"></tbody>
          </table>
        </div>
      </div>
      <div class="card">
        <div class="card-header">
          <div><div class="card-title">Recent Shop Orders</div><div class="card-sub">Latest product purchases</div></div>
          <a href="<?php echo htmlspecialchars($appBase); ?>/admin/shop-orders" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Order ID</th><th>User</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th></tr></thead>
            <tbody id="shopOrdersBody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <div style="display:flex;flex-direction:column;gap:20px;">
      <div class="card">
        <div class="card-header"><div class="card-title">Top Movies</div><span class="badge gold">This Week</span></div>
        <div class="card-body" id="topMoviesList"></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Upcoming Showtimes</div><span class="badge blue">Today</span></div>
        <div class="card-body no-pad" id="upcomingShowtimes"></div>
      </div>
      <div class="card">
        <div class="card-header"><div class="card-title">Low Stock Alert</div><span class="badge red">5 items</span></div>
        <div class="card-body no-pad" id="lowStockList"></div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const tc = document.getElementById('ticketChart');
  const tl = document.getElementById('ticketLabels');
  const vals = [820,1100,940,1380,1020,1240,1560,980,1420,1680,1240,1820,1440,1294];
  const days = ['27','28','1','2','3','4','5','6','7','8','9','10','11','12'];
  const max = Math.max(...vals);
  tc.innerHTML = vals.map((v,i)=>{
    const h = Math.max(8,(v/max)*180);
    return `<div class="chart-bar" data-val="${v}" style="height:${h}px;background:linear-gradient(to top,var(--red) 0%,rgba(229,9,20,${0.4+(v/max)*0.6}) 100%);"></div>`;
  }).join('');
  tl.innerHTML = days.map(d=>`<span>${d}</span>`).join('');

  const svg = document.getElementById('revChart');
  const cinema = [28000,32000,41000,38000,52000,48000,61000,55000,72000,68000,84000,90000];
  const shop =   [12000,15000,18000,22000,19000,25000,30000,27000,35000,38000,42000,50000];
  const W=500,H=180,pad=20,maxV=Math.max(...cinema,...shop);
  function pts(a){return a.map((v,i)=>`${pad+(i/(a.length-1))*(W-pad*2)},${H-pad-((v/maxV)*(H-pad*2))}`).join(' ');} 
  function area(a){const p=a.map((v,i)=>`${pad+(i/(a.length-1))*(W-pad*2)},${H-pad-((v/maxV)*(H-pad*2))}`);return `${pad},${H-pad} ${p.join(' ')} ${W-pad},${H-pad}`;}
  const months=['J','F','M','A','M','J','J','A','S','O','N','D'];
  svg.innerHTML=`<defs><linearGradient id="cg" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#E50914" stop-opacity="0.3"/><stop offset="100%" stop-color="#E50914" stop-opacity="0"/></linearGradient><linearGradient id="sg" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="#C9A84C" stop-opacity="0.3"/><stop offset="100%" stop-color="#C9A84C" stop-opacity="0"/></linearGradient></defs>
    <polygon points="${area(cinema)}" fill="url(#cg)"/>
    <polygon points="${area(shop)}" fill="url(#sg)"/>
    <polyline points="${pts(cinema)}" fill="none" stroke="#E50914" stroke-width="2" stroke-linejoin="round"/>
    <polyline points="${pts(shop)}" fill="none" stroke="#C9A84C" stroke-width="2" stroke-linejoin="round"/>
    ${months.map((m,i)=>`<text x="${pad+(i/(months.length-1))*(W-pad*2)}" y="${H}" font-size="9" fill="#606070" text-anchor="middle">${m}</text>`).join('')}`;

  const orders=[
    {id:'#TK-2031',user:'Nguyen Van A',movie:'Avengers: Doomsday',seats:2,total:'$14.00',status:'Completed',date:'Today 14:32'},
    {id:'#TK-2030',user:'Tran Thi B',movie:'Cosmic Voyage II',seats:4,total:'$28.00',status:'Completed',date:'Today 13:14'},
    {id:'#TK-2029',user:'Hoang Minh C',movie:'The Last Breath',seats:1,total:'$7.00',status:'Pending',date:'Today 12:05'},
    {id:'#TK-2028',user:'Pham Staff',movie:'Haunted Echoes',seats:3,total:'$21.00',status:'Completed',date:'Today 11:20'},
    {id:'#TK-2027',user:'Vu Quoc E',movie:'Avengers: Doomsday',seats:2,total:'$14.00',status:'Cancelled',date:'Today 10:44'},
  ];
  document.getElementById('ticketOrdersBody').innerHTML=orders.map(o=>`
    <tr>
      <td class="td-id">${o.id}</td>
      <td class="td-bold">${o.user}</td>
      <td class="td-muted">${o.movie}</td>
      <td>${o.seats} seats</td>
      <td style="font-weight:700;">${o.total}</td>
      <td>${statusBadge(o.status)}</td>
      <td class="td-muted">${o.date}</td>
    </tr>`).join('');

  const sorders=[
    {id:'#SH-0042',user:'Hoang Minh C',items:3,total:'$22.50',payment:'MoMo',status:'Shipped'},
    {id:'#SH-0041',user:'Nguyen Van A',items:2,total:'$11.70',payment:'VNPay',status:'Delivered'},
    {id:'#SH-0040',user:'Tran Thi B',items:5,total:'$45.00',payment:'PayPal',status:'Pending'},
    {id:'#SH-0039',user:'Vu Quoc E',items:1,total:'$3.20',payment:'Cash',status:'Delivered'},
  ];
  document.getElementById('shopOrdersBody').innerHTML=sorders.map(o=>`
    <tr>
      <td class="td-id">${o.id}</td>
      <td class="td-bold">${o.user}</td>
      <td>${o.items} item${o.items>1?'s':''}</td>
      <td style="font-weight:700;color:var(--gold);">${o.total}</td>
      <td>${statusBadge(o.payment)}</td>
      <td>${statusBadge(o.status)}</td>
    </tr>`).join('');

  const topMovies=[
    {title:'Avengers: Doomsday',tickets:4820,emoji:'🦸'},
    {title:'Cosmic Voyage II',tickets:3240,emoji:'🚀'},
    {title:'The Last Breath',tickets:2180,emoji:'🎭'},
    {title:'Haunted Echoes',tickets:1640,emoji:'👻'},
    {title:'Dragon Quest',tickets:980,emoji:'🐉'},
  ];
  document.getElementById('topMoviesList').innerHTML=topMovies.map((d,i)=>`
    <div class="top-item">
      <div class="top-rank r${i<3?i+1:'n'}">${i+1}</div>
      <span style="font-size:22px;">${d.emoji}</span>
      <div class="top-info"><div class="top-name">${d.title}</div><div class="top-meta">${d.tickets.toLocaleString()} tickets</div></div>
      <div class="top-val">${(d.tickets/100).toFixed(0)}%</div>
    </div>`).join('');

  const shows=[
    {movie:'🦸 Avengers: Doomsday',time:'14:30',room:'Room 1',seats:'32 left'},
    {movie:'🚀 Cosmic Voyage II',time:'15:00',room:'Room 3',seats:'18 left'},
    {movie:'🎭 The Last Breath',time:'16:15',room:'Room 2',seats:'45 left'},
    {movie:'👻 Haunted Echoes',time:'17:30',room:'Room 4',seats:'12 left'},
  ];
  document.getElementById('upcomingShowtimes').innerHTML=shows.map(s=>`
    <div style="display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid rgba(255,255,255,0.03);">
      <div style="background:var(--red);color:#fff;padding:4px 8px;border-radius:5px;font-size:11px;font-weight:700;font-family:'Bebas Neue',sans-serif;letter-spacing:1px;white-space:nowrap;">${s.time}</div>
      <div style="flex:1;min-width:0;"><div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${s.movie}</div><div style="font-size:11px;color:var(--text-muted);">${s.room}</div></div>
      <span class="badge ${parseInt(s.seats)<20?'orange':'green'}" style="flex-shrink:0;">${s.seats}</span>
    </div>`).join('');

  const products=[
    {name:'Coca-Cola 500ml',cat:'Beverages',stock:12,emoji:'🥤',lvl:'low'},
    {name:'Nacho + Dip Set',cat:'Snacks',stock:0,emoji:'🌮',lvl:'out'},
    {name:'Water Bottle 1L',cat:'Beverages',stock:8,emoji:'💧',lvl:'low'},
    {name:'Hot Dog Bundle',cat:'Snacks',stock:3,emoji:'🌭',lvl:'low'},
    {name:'Mango Slush',cat:'Beverages',stock:0,emoji:'🥭',lvl:'out'},
  ];
  document.getElementById('lowStockList').innerHTML=products.map(p=>`
    <div style="display:flex;align-items:center;gap:10px;padding:10px 16px;border-bottom:1px solid rgba(255,255,255,0.03);">
      <span style="font-size:18px;">${p.emoji}</span>
      <div style="flex:1;min-width:0;"><div style="font-size:12px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${p.name}</div><div style="font-size:11px;color:var(--text-muted);">${p.cat}</div></div>
      <span class="badge ${p.lvl==='out'?'red':'orange'}">${p.stock===0?'Out':p.stock+' left'}</span>
    </div>`).join('');
});
</script>
