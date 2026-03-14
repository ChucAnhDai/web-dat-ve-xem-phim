<div class="grid-2">
  <div class="card">
    <div class="card-header"><div class="card-title">General Settings</div></div>
    <div class="card-body">
      <div class="form-grid">
        <div class="field"><label>Site Name</label><input class="input" value="CineShop Admin"></div>
        <div class="field"><label>Timezone</label><input class="input" value="Asia/Saigon"></div>
        <div class="field"><label>Support Email</label><input class="input" value="support@cineshop.com"></div>
        <div class="field"><label>Currency</label><input class="input" value="USD"></div>
      </div>
    </div>
  </div>
  <div class="card">
    <div class="card-header"><div class="card-title">Booking Rules</div></div>
    <div class="card-body">
      <div class="form-grid">
        <div class="field"><label>Seat Hold (min)</label><input class="input" value="10"></div>
        <div class="field"><label>Refund Window (hours)</label><input class="input" value="24"></div>
        <div class="field"><label>Max Seats / Order</label><input class="input" value="10"></div>
        <div class="field"><label>Auto Release</label><input class="input" value="Enabled"></div>
      </div>
    </div>
  </div>
</div>

<div class="card" style="margin-top:20px;">
  <div class="card-header"><div class="card-title">Integrations</div></div>
  <div class="card-body">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
      <div class="card" style="margin:0;"><div class="card-body"><div class="td-bold">MoMo Gateway</div><div class="td-muted">Connected / last sync 5 min ago</div></div></div>
      <div class="card" style="margin:0;"><div class="card-body"><div class="td-bold">VNPay Gateway</div><div class="td-muted">Connected / last sync 3 min ago</div></div></div>
      <div class="card" style="margin:0;"><div class="card-body"><div class="td-bold">Mail Service</div><div class="td-muted">Connected / queue healthy</div></div></div>
      <div class="card" style="margin:0;"><div class="card-body"><div class="td-bold">Storage</div><div class="td-muted">81% of quota used</div></div></div>
    </div>
  </div>
</div>

<script>
function handleDashboardSectionAction() {
  showToast('System settings saved', 'success');
}
</script>
