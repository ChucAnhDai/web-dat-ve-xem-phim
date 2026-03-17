# Shop Foundation v1

## Scope

Shop Foundation v1 defines the architecture and persistence contract for:

- `product_categories`
- `products`
- `product_images`
- `product_details`
- `carts`
- `cart_items`
- `shop_orders`
- `order_details`
- `promotions`
- `product_promotions`

This phase does not wire the full shop UI yet. It prepares the system so later FE/BE work can follow one stable contract.

## Delivery Order

The shop module will be developed in this order:

1. Foundation
2. Admin Product Catalog
3. User Shop Catalog
4. Cart
5. Shop Checkout and Payment
6. Admin Shop Orders
7. Promotions and Pricing Rules

## Module Boundaries

### Product Catalog

Owns:

- category metadata
- product core fields
- inventory state
- product media
- product presentation metadata

Does not own:

- pricing rules from promotions
- cart state
- order state

### Cart

Owns:

- guest or authenticated cart header
- cart line items
- quantity limits
- cart expiry and abandonment

Does not own:

- final order snapshots
- payment lifecycle

### Shop Checkout

Owns:

- validating purchasable products
- freezing the order snapshot
- pricing summary used to create the order
- payment handoff

Does not own:

- catalog authoring
- promotion authoring UI

### Shop Orders

Owns:

- order header lifecycle
- order detail snapshots
- fulfillment progression
- reconciliation with payments

### Promotions

Owns:

- reusable campaign definitions
- product promotion assignment
- eligibility and pricing rule inputs

Promotion calculation will be added after product, cart, and checkout foundations are live.

## Data Model Contract

### product_categories

Required core fields:

- `name`
- `slug`
- `visibility`
- `status`
- `display_order`

Allowed `visibility` values:

- `featured`
- `standard`
- `hidden`

Allowed `status` values:

- `active`
- `inactive`
- `archived`

### products

Required core fields:

- `category_id`
- `slug`
- `sku`
- `name`
- `price`
- `stock`
- `currency`
- `track_inventory`
- `status`
- `visibility`
- `sort_order`

Allowed `status` values:

- `draft`
- `active`
- `inactive`
- `archived`

Allowed `visibility` values:

- `featured`
- `standard`
- `hidden`

### product_images

Required core fields:

- `product_id`
- `asset_type`
- `image_url`
- `sort_order`
- `is_primary`
- `status`

Allowed `asset_type` values:

- `thumbnail`
- `gallery`
- `banner`
- `lifestyle`

Allowed `status` values:

- `draft`
- `active`
- `archived`

### carts

Required core fields:

- either `user_id` or `session_token`
- `currency`
- `status`
- `expires_at`

Allowed `status` values:

- `active`
- `converted`
- `abandoned`

### cart_items

Required core fields:

- `cart_id`
- `product_id`
- `quantity`
- `price`

Constraint:

- unique key on `(cart_id, product_id)`

### shop_orders

Required core fields:

- `order_code`
- optional `user_id`
- optional `session_token`
- contact snapshot
- fulfillment metadata
- shipping snapshot
- pricing snapshot
- `status`
- timestamps for lifecycle milestones

Allowed `fulfillment_method` values:

- `pickup`
- `delivery`

Allowed `status` values:

- `pending`
- `confirmed`
- `preparing`
- `ready`
- `shipping`
- `completed`
- `cancelled`
- `expired`
- `refunded`

Notes:

- `pending` is used for the pre-payment or awaiting-confirmation state.
- payment state remains the source of truth in `payments`.
- shop order records must contain pricing snapshots and address snapshots so later catalog changes do not mutate historical orders.

### order_details

Required core fields:

- `order_id`
- optional `product_id`
- `product_name_snapshot`
- `product_sku_snapshot`
- `quantity`
- `price`
- `discount_amount`
- `line_total`

Notes:

- shop order details are immutable historical snapshots.
- they must not depend on live catalog values after order creation.

### promotions

Required core fields:

- `code`
- `title`
- `discount_type`
- `discount_value`
- `status`
- schedule window

Allowed `discount_type` values:

- `percent`
- `fixed`

Allowed `status` values:

- `draft`
- `scheduled`
- `active`
- `expired`
- `archived`

### product_promotions

Required core fields:

- `product_id`
- `promotion_id`
- `priority`
- `status`

Allowed `status` values:

- `active`
- `archived`

Constraint:

- unique key on `(product_id, promotion_id)`

## Transaction Boundaries

### Product Catalog Writes

Single DB transaction per create or update flow:

- write category or product
- write related product details
- write image metadata
- commit only after all rows are valid

### Cart Mutations

Single DB transaction per cart mutation:

- load cart by user or session
- validate product status and stock visibility
- upsert line item
- update cart timestamp

### Shop Checkout

Single DB transaction per order creation:

- validate products and inventory snapshot
- calculate totals
- create shop order header
- create order detail snapshots
- reserve or decrement inventory depending on fulfillment rules
- create payment record when required
- mark cart as converted

Rollback must happen on any validation or persistence failure.

## Validation Rules

### Catalog

- slugs and SKUs must be unique
- prices cannot be negative
- stock cannot be negative
- archived records cannot be shown in public catalog queries

### Cart

- quantity must be within configured limits
- only active products can be added
- hidden or archived products are rejected

### Checkout

- all line items must still exist and be purchasable
- order totals must be computed on the server
- client totals are advisory only
- payment method and fulfillment method must be enum-validated

## Security

### FE

- never trust client-side totals, stock, or promotion calculations
- do not expose privileged admin-only fields in public responses
- sanitize route params and user-supplied search input before rendering

### BE

- admin product and promotion endpoints require admin middleware
- user order endpoints require ownership checks
- guest cart endpoints must bind to server-side session tokens
- order creation must use server-generated order codes and idempotency keys when payment is involved

## Logging and Observability

Every service slice should emit structured logs for:

- validation rejection
- business rule rejection
- transaction failure
- payment handoff
- inventory inconsistency

Recommended log context:

- `user_id`
- `session_token` preview
- `order_code`
- `product_id`
- `cart_id`
- `payment_method`
- `duration_ms`

## Test Strategy

### Unit

- validators
- pricing helpers
- status transition guards
- config contracts

### Integration

- product persistence and list scopes
- cart mutation consistency
- checkout transaction rollback
- shop order and payment reconciliation

### Feature

- admin product endpoints
- public catalog endpoints
- cart endpoints
- shop checkout endpoints
- my orders endpoints

## Non-Goals for This Phase

- full promotion calculation engine
- shipping carrier integration
- refund automation
- analytics dashboards

## Immediate Next Slice

After Foundation v1 lands, the next implementation slice is:

1. admin product categories API
2. admin products API
3. admin product images API
4. tests for product validators and product management service
