---
name: Dev Cart Replacement Plan
overview: Audit what is already implemented in `dev/`, identify remaining gaps, and execute a focused migration to replace `wicart.js` with a faster custom cart while preserving current UX and ensuring checkout sends orders to both AMO and database.
todos:
  - id: status-refresh
    content: Update plan/todo statuses to reflect completed baseline and isolate remaining cart work
    status: completed
  - id: new-cart-module
    content: Implement new fast cart module in /dev/js/cart.js without wicart.js dependency
    status: completed
  - id: template-rewire
    content: Replace wicart includes/usages in dev templates/pages with the new cart module
    status: completed
  - id: checkout-contract
    content: Ensure checkout payload contract matches sendmail.php and verify AMO + DB writes
    status: completed
  - id: perf-cleanup
    content: Remove runtime loading of wicart.js and reduce duplicate cart initialization
    status: completed
  - id: acceptance-run
    content: Run end-to-end checks for cart UX, order creation, AMO/CRM integration, and no-wicart runtime
    status: completed
isProject: false
---

# Dev Status And New Cart Plan

## Current Implementation Status

### Ready

- `dev/` is already a standalone deploy root with core site assets copied.
- DB foundation exists: [`/Users/sanchos/Desktop/public_html/dev/config/db.php`](/Users/sanchos/Desktop/public_html/dev/config/db.php), [`/Users/sanchos/Desktop/public_html/dev/database/schema.sql`](/Users/sanchos/Desktop/public_html/dev/database/schema.sql), setup script [`/Users/sanchos/Desktop/public_html/dev/setup.php`](/Users/sanchos/Desktop/public_html/dev/setup.php).
- Order backend persists to DB with fallback logic in [`/Users/sanchos/Desktop/public_html/dev/sendmail.php`](/Users/sanchos/Desktop/public_html/dev/sendmail.php).
- AMO/CRM path is present (`require_once 'amo/order.php'`) and Bitrix send helper is in [`/Users/sanchos/Desktop/public_html/dev/rest.php`](/Users/sanchos/Desktop/public_html/dev/rest.php).
- Admin pages exist for orders/products/customers in [`/Users/sanchos/Desktop/public_html/dev/admin/`](/Users/sanchos/Desktop/public_html/dev/admin/).
- Product SEO fields/fallback are already wired in [`/Users/sanchos/Desktop/public_html/dev/templates/single_product_template.php`](/Users/sanchos/Desktop/public_html/dev/templates/single_product_template.php).

### Not Ready / Needs Correction

- `wicart.js` is still active and deeply referenced in templates/pages:
  - [`/Users/sanchos/Desktop/public_html/dev/index.php`](/Users/sanchos/Desktop/public_html/dev/index.php)
  - [`/Users/sanchos/Desktop/public_html/dev/templates/single_product_template.php`](/Users/sanchos/Desktop/public_html/dev/templates/single_product_template.php)
  - [`/Users/sanchos/Desktop/public_html/dev/templates/header.php`](/Users/sanchos/Desktop/public_html/dev/templates/header.php)
  - [`/Users/sanchos/Desktop/public_html/dev/templates/order_form.php`](/Users/sanchos/Desktop/public_html/dev/templates/order_form.php)
  - plus legacy pages (`info.php`, `oferta.php`, `policy.php`) in `dev/`.
- Cart UI/logic is currently coupled to global `cart.*` methods from `wicart.js`.
- Performance optimization is not yet systematic (still legacy script payload and old basket flow).
- Plan metadata statuses are outdated (`pending`) and do not reflect partial completion.

## Target Outcome

- Remove `wicart.js` completely from runtime.
- Keep current cart visual and user flow equivalent (add to cart, cart popup, checkout popup).
- Ensure checkout sends order payload to DB and AMO/CRM through existing `sendmail.php` pipeline.
- Improve frontend responsiveness by simplifying cart code and reducing legacy script overhead.

## Implementation Plan

### 1) Refresh plan tracking and scope

- Update todo statuses to reflect completed baseline (DB/admin/SEO already done) and isolate remaining work to cart migration + hardening.
- Mark `wicart removal` as a dedicated milestone with acceptance checks.

### 2) Build new lightweight cart module (no `wicart.js`)

- Add a new script, e.g. [`/Users/sanchos/Desktop/public_html/dev/js/cart.js`](/Users/sanchos/Desktop/public_html/dev/js/cart.js), with:
  - localStorage-backed basket,
  - methods compatible with existing template hooks (`addToCart`, open/close popup, recalc, sendOrder),
  - minimal DOM rendering using current markup/CSS classes.
- Keep API surface compatible enough to avoid broad template rewrites.

### 3) Rewire templates/pages to new cart

- Replace `wicart.js` includes with `cart.js` in:
  - [`/Users/sanchos/Desktop/public_html/dev/index.php`](/Users/sanchos/Desktop/public_html/dev/index.php)
  - [`/Users/sanchos/Desktop/public_html/dev/templates/single_product_template.php`](/Users/sanchos/Desktop/public_html/dev/templates/single_product_template.php)
  - [`/Users/sanchos/Desktop/public_html/dev/info.php`](/Users/sanchos/Desktop/public_html/dev/info.php)
  - [`/Users/sanchos/Desktop/public_html/dev/oferta.php`](/Users/sanchos/Desktop/public_html/dev/oferta.php)
  - [`/Users/sanchos/Desktop/public_html/dev/policy.php`](/Users/sanchos/Desktop/public_html/dev/policy.php)
- Keep existing cart trigger attributes (`onclick="cart..."`) functional via the new module.

### 4) Checkout contract and payload verification

- Ensure new cart sends payload fields expected by backend:
  - `name`, `email`, `phone`, `contact_method`, `contact_username`, `comments`, `coupon`, `order_result`, `client_order_uuid`.
- Keep endpoint at [`/Users/sanchos/Desktop/public_html/dev/sendmail.php`](/Users/sanchos/Desktop/public_html/dev/sendmail.php) to preserve AMO + DB side effects.
- Validate successful paths and fallback behavior when DB is unavailable.

### 5) Performance-focused cleanup

- Remove runtime dependence on `wicart.js` and unused variants (`wicart_bak.js`, `wicart_original.js`, etc. can remain as archive but not loaded).
- Avoid duplicate cart initialization blocks across templates.
- Keep cart JS small and avoid heavy DOM rebuilds on every operation.

### 6) Acceptance test pass (must pass)

- Add product from listing and product page.
- Open cart popup and verify item/qty/total rendering.
- Submit checkout form; verify:
  - order row in DB,
  - order items in DB,
  - customer link in DB,
  - AMO/CRM flow still triggered.
- Verify SEO fallback unchanged.
- Verify no page loads `js/wicart.js` anymore.

## Notes For This Revised Plan

- Existing backend progress is reusable; no rollback to legacy needed.
- The highest-risk change is frontend cart replacement; we isolate this while keeping backend contract stable.
- `dev/` remains self-contained and deployable as zip-only root.
