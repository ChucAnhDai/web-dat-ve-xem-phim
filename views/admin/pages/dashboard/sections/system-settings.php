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
  const body = `<div style="display:flex;flex-direction:column;gap:18px;">
    <div class="surface-card">
      <div class="surface-card-title">Control Center Preview</div>
      <div class="surface-card-copy">Adjust site-wide defaults for booking, support, and integrations in a single staging form before wiring persistence.</div>
    </div>

    <div class="form-grid">
      <div class="field"><label>Site Name</label><input class="input" value="CineShop Admin"></div>
      <div class="field"><label>Timezone</label><select class="select">${buildOptions(['Asia/Saigon', 'Asia/Bangkok', 'UTC'], 'Asia/Saigon')}</select></div>
      <div class="field"><label>Support Email</label><input class="input" value="support@cineshop.com"></div>
      <div class="field"><label>Currency</label><select class="select">${buildOptions(['USD', 'VND'], 'USD')}</select></div>
      <div class="field"><label>Seat Hold (min)</label><input class="input" value="10"></div>
      <div class="field"><label>Refund Window (hours)</label><input class="input" value="24"></div>
      <div class="field"><label>Max Seats / Order</label><input class="input" value="10"></div>
      <div class="field"><label>Auto Release</label><select class="select">${buildOptions(['Enabled', 'Disabled'], 'Enabled')}</select></div>
      <div class="field form-full"><label>Integration Alerts</label>
        <div class="check-grid">
          ${['Gateway sync warnings', 'Storage quota alerts', 'Mail queue alerts', 'Settlement anomalies', 'Booking release issues', 'Staff login alerts'].map(item => `
            <label class="check-option">
              <input type="checkbox" checked>
              <span>${item}</span>
            </label>`).join('')}
        </div>
      </div>
    </div>
  </div>`;

  openModal('Save Settings', body, {
    description: 'Review the global platform defaults and integration alerting rules before saving settings.',
    note: 'UI preview only. System settings are not persisted yet.',
    submitLabel: 'Save Settings',
    successMessage: 'System settings preview updated!',
  });
}
</script>
