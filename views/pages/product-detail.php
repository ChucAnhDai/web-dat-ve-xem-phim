<div class="product-detail-layout">
  <div class="product-gallery">
    <div class="product-main-img">
      <img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=600&q=80" alt="Product" id="mainProductImg">
    </div>
    <div class="product-thumbs">
      <div class="product-thumb active"><img src="https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=200&q=80" alt=""></div>
      <div class="product-thumb"><img src="https://images.unsplash.com/photo-1576866209830-589e1bfbaa4d?w=200&q=80" alt=""></div>
      <div class="product-thumb"><img src="https://images.unsplash.com/photo-1524041255072-7da0525d6b34?w=200&q=80" alt=""></div>
    </div>
  </div>
  <div class="product-detail-info">
    <div class="stock-badge in-stock">✓ In Stock</div>
    <div class="product-cat" style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">🎉 Combo Deals</div>
    <h1 class="product-detail-title">Ultimate Popcorn Combo</h1>
    <div class="product-detail-price">
      $14.99
      <span style="font-size:16px;color:var(--text3);font-weight:400;text-decoration:line-through">$18.99</span>
      <span style="background:rgba(229,9,20,0.15);color:var(--red);font-size:13px;font-weight:600;padding:2px 8px;border-radius:4px">-21%</span>
    </div>
    <p class="product-detail-desc">The perfect cinema companion! Includes 1 large popcorn (your choice of flavor), 2 medium drinks, and 1 pack of nachos. Perfect for sharing with someone special.</p>
    <div style="background:var(--bg3);border-radius:var(--radius-sm);padding:14px;margin-bottom:16px">
      <div style="font-size:12px;color:var(--text3);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600">Includes</div>
      <div style="font-size:13px;color:var(--text2);line-height:1.8">
        🍿 1× Large Popcorn (Butter / Caramel / Cheese)<br>
        🥤 2× Medium Soft Drink<br>
        🧀 1× Nachos with Salsa
      </div>
    </div>
    <div class="qty-row">
      <div class="qty-big">
        <button onclick="changeQty(-1)">−</button>
        <span id="productQty">1</span>
        <button onclick="changeQty(1)">+</button>
      </div>
      <span style="font-size:13px;color:var(--text3)">Max 10 per order</span>
    </div>
    <div class="action-row">
      <button class="btn btn-primary btn-lg" onclick="addToCart()">🛒 Add to Cart</button>
      <button class="btn btn-secondary btn-lg" onclick="buyNow()">⚡ Buy Now</button>
      <button class="btn btn-secondary btn-icon btn-lg" type="button">🔖</button>
    </div>
  </div>
</div>

<div class="section-mb">
  <div class="section-header">
    <h2 class="section-title">Related <span>Products</span></h2>
  </div>
  <div class="products-grid" id="relatedProductsGrid"></div>
</div>
