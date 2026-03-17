<div class="catalog-meta">
  <div id="productDetailMetaText">Loading product details...</div>
  <div class="catalog-request-status" id="productRequestStatus">Connecting to catalog</div>
</div>

<div class="catalog-empty-state" id="productDetailEmpty" hidden></div>

<div class="product-detail-layout" id="productDetailLayout">
  <div class="product-gallery">
    <div class="product-main-img">
      <img src="" alt="Product" id="mainProductImg">
    </div>
    <div class="product-thumbs" id="productThumbs"></div>
  </div>
  <div class="product-detail-info">
    <div class="stock-badge in-stock" id="productStockBadge">Loading</div>
    <div class="product-cat" id="productCategoryLabel" style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px">Category</div>
    <h1 class="product-detail-title" id="productDetailTitle">Product</h1>
    <div class="product-detail-price" id="productDetailPrice"></div>
    <p class="product-detail-desc" id="productDetailDesc"></p>
    <div style="background:var(--bg3);border-radius:var(--radius-sm);padding:14px;margin-bottom:16px">
      <div style="font-size:12px;color:var(--text3);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.5px;font-weight:600">Product Highlights</div>
      <div style="font-size:13px;color:var(--text2);line-height:1.8" id="productHighlightList">
        Loading...
      </div>
    </div>
    <div class="qty-row">
      <div class="qty-big">
        <button onclick="changeQty(-1)">-</button>
        <span id="productQty">1</span>
        <button onclick="changeQty(1)">+</button>
      </div>
      <span style="font-size:13px;color:var(--text3)" id="productQtyHint">Max 10 per order</span>
    </div>
    <div class="action-row">
      <button class="btn btn-primary btn-lg" type="button" data-add-current-product data-product-id="" data-product-name="">Add to Cart</button>
      <button class="btn btn-secondary btn-lg" type="button" data-buy-current-product data-product-id="" data-product-name="">Buy Now</button>
      <button class="btn btn-secondary btn-icon btn-lg" type="button" id="productShareButton">Copy Link</button>
    </div>
  </div>
</div>

<div class="section-mb">
  <div class="section-header">
    <h2 class="section-title">Related <span>Products</span></h2>
  </div>
  <div class="products-grid" id="relatedProductsGrid"></div>
  <div class="catalog-empty-state" id="relatedProductsEmpty" hidden></div>
</div>
