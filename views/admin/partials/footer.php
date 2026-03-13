<footer class="footer">
  <div class="footer-inner">
    <div class="footer-brand">
      <div class="footer-logo">
        <div class="logo-mark" style="width:30px;height:30px;font-size:15px;">CS</div>
        <div>
          <div style="font-family:'Bebas Neue',sans-serif;font-size:15px;letter-spacing:1px;line-height:1;">CineShop</div>
          <div style="font-size:10px;color:var(--text-dim);text-transform:uppercase;letter-spacing:0.5px;">Admin System</div>
        </div>
      </div>
      <p style="font-size:12px;color:var(--text-dim);line-height:1.7;margin-top:10px;max-width:260px;">
        Cinema &amp; Online Shop Management System — one unified dashboard for your entire cinema operation.
      </p>
    </div>

    <div class="footer-nav">
      <div class="footer-col">
        <div class="footer-col-title">Cinema</div>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin/movies" class="footer-link">Movies</a>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin/cinemas" class="footer-link">Cinemas</a>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin/showtimes" class="footer-link">Showtimes</a>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin/seats" class="footer-link">Seats</a>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin/ticket-orders" class="footer-link">Ticket Orders</a>
      </div>
      <div class="footer-col">
        <div class="footer-col-title">Shop</div>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin/products" class="footer-link">Products</a>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin/shop-orders" class="footer-link">Shop Orders</a>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin/payments" class="footer-link">Payments</a>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin/promotions" class="footer-link">Promotions</a>
      </div>
      <div class="footer-col">
        <div class="footer-col-title">System</div>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin/users" class="footer-link">Users</a>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin" class="footer-link">Dashboard</a>
        <a href="<?php echo htmlspecialchars($appBase); ?>/admin" class="footer-link">Settings</a>
        <a href="#" class="footer-link" onclick="showToast('Help docs coming soon','info');return false;">Help &amp; Docs</a>
      </div>
    </div>
  </div>

  <div class="footer-bottom">
    <div style="display:flex;align-items:center;gap:10px;font-size:11px;color:var(--text-dim);flex-wrap:wrap;gap:8px;">
      <span>© 2026 CineShop Admin. All rights reserved.</span>
      <span style="color:var(--border);">·</span>
      <span id="footerClock"></span>
    </div>
    <div style="display:flex;align-items:center;gap:14px;">
      <span style="font-size:10px;font-weight:700;letter-spacing:0.5px;background:var(--bg3);border:1px solid var(--border);padding:2px 8px;border-radius:99px;color:var(--text-muted);">v2.4.1</span>
      <a href="#" class="footer-link" onclick="showToast('Privacy Policy','info');return false;">Privacy</a>
      <a href="#" class="footer-link" onclick="showToast('Terms of Service','info');return false;">Terms</a>
    </div>
  </div>
</footer>

<script>
(function updateClock() {
  const el = document.getElementById('footerClock');
  if (el) {
    el.textContent = new Date().toLocaleString('vi-VN', {
      weekday:'short', year:'numeric', month:'short', day:'numeric',
      hour:'2-digit', minute:'2-digit'
    });
  }
  setTimeout(updateClock, 30000);
})();
</script>
