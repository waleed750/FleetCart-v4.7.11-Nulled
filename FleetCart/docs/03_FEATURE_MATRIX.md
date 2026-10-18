# Feature matrix — what FleetCart 4.7.11 really does

Status: **VERIFIED** = exercised by an automated test or a live request in this
audit; **CODE** = read in source, not executed end to end; **PARTIAL**;
**MISSING**; **UNKNOWN** = not examined.
Tests live in `tests/Feature` (`vendor/bin/phpunit`, 34 tests, prepare the DB with
`tests/prepare-db.sh`).

## Catalogue

| Feature | Status | Evidence / notes |
|---|---|---|
| Simple products, price, special price + schedule | VERIFIED | CartTest, CheckoutTest; `Product` entity |
| Variants (`product_variants`) with **own price and own stock** | VERIFIED | OrderSecurityTest variant restore; `Cart::store()` resolves variant, stock is taken from the variant |
| Variations (size/colour value sets) used to build variants | CODE | `modules/Variation`, `VariationValue`; admin UI not driven |
| Options (extra fields on a product, e.g. engraving) | CODE | `modules/Option`, `ChosenProductOptions` |
| Attributes + attribute sets (filterable) | CODE | `modules/Attribute`; importer creates them |
| Categories (nested), brands, tags | VERIFIED (import) | CSV import created nested categories `Men///T-Shirts` |
| Product reviews, wishlist, compare | CODE | `Review`, `AccountWishlistController`, `Compare` |
| Flash sales | CODE | `FlashSale`; stock check in `CheckCartItemsStock`; not atomic with order |
| Bulk import (CSV) | VERIFIED | live upload imported a product (after fixing the empty-brand crash). Sample file is electronics, not fashion |
| Media library / image upload | CODE | `Media` module; TinyMCE upload handler updated for v7 and loads without errors |
| Search (mysql / algolia / meilisearch) | CODE | installer option; mysql engine installed |

## Cart & checkout

| Feature | Status | Evidence / notes |
|---|---|---|
| Add / update / remove cart items | VERIFIED | CartTest |
| Cart rejects qty above stock and out-of-stock items | VERIFIED | CartTest |
| **Guest checkout** | VERIFIED | CheckoutTest `guest_can_place_a_cod_order` (customer_id null). Optional account creation exists |
| **Cash on delivery** | VERIFIED | order created `pending_payment`, `complete` flips it to `pending`, cart cleared |
| Online gateways (PayPal, Stripe, Razorpay, Paytm, PayFast, Paystack, Mercado Pago, Authorize.net, Instamojo, Iyzico, bKash, Nagad, SSLCommerz, Flutterwave, bank transfer, cheque) | CODE | 17 classes in `modules/Payment/Gateways`; **not exercised**, none needed for COD-only |
| Coupons: percent/fixed, free shipping, min/max spend, per-coupon & per-customer limits, date window, product/category include/exclude | VERIFIED (percent, min spend, limits, expiry, inactive, unknown) | CouponTest; include/exclude checkers CODE |
| Taxes (tax classes, rates, by address) | CODE | `modules/Tax`, `CartTax` |
| Shipping methods | PARTIAL | only **flat rate, free shipping, local pickup** (`ShippingServiceProvider`); one global price, no zones, weight or governorate rules |
| Required checkout fields | PARTIAL | email and ZIP are mandatory (`StoreOrderRequest`); no Egypt-friendly "phone only" flow |
| Multi-currency | CODE | `modules/Currency`; EGP present in `currencies.php`; store currently USD |

## Stock lifecycle & integrity (the part that matters for COD)

| Behaviour | Status | Notes |
|---|---|---|
| Stock reserved at order creation, restored on cancel | VERIFIED | CheckoutTest, OrderSecurityTest |
| Variant stock restored to the variant (not the parent) | VERIFIED — **was broken, fixed** | audit §4.3 #1 |
| No oversell under concurrency | VERIFIED — **was broken, fixed** | conditional decrement in a transaction; test simulates a competing purchase |
| Failed stock decrement leaves no order and no coupon usage | CODE | order, lines, coupon usage and stock run in one DB transaction; the tests assert stock never goes negative and a pre-check rejection creates no order, but do not separately assert the rollback of a mid-flight failure |
| Double submit / retry is idempotent | VERIFIED — **was broken, fixed** | same order id returned, stock taken once |
| Cancel is idempotent | VERIFIED — fixed | no second restore |
| Order access control (complete / cancel) | VERIFIED — **was broken, fixed** | stranger gets 403; payment method cannot be swapped |
| Stock returns when an admin cancels a placed order | UNKNOWN | not examined in the admin status flow |
| Unpaid-order expiry job | MISSING | `pending_payment` orders hold stock forever |

## Admin, roles, reports

| Feature | Status | Evidence / notes |
|---|---|---|
| Admin login + dashboard + products/orders/settings/coupons | VERIFIED | AdminAccessTest |
| Guests and customer accounts cannot enter admin | VERIFIED | AdminAccessTest |
| Roles & granular permissions (25 modules ship `permissions.php`, routes use `can:` middleware) | CODE | `modules/User`, Sentinel |
| Super-admin feature toggles | MISSING | planned for Phase 4 |
| Reports: sales, products purchase/stock/view, customers, coupons, tax, shipping, search, brand/category/tag | CODE | `modules/Report` (13 report classes), one route; not exercised |
| Order management, invoices/print, status emails/SMS | CODE | `Order`, `SendOrderStatusChangedEmail/Sms`, `print.scss` |
| Transactions log | CODE | `Transaction` module |

## Content & storefront

| Feature | Status | Evidence / notes |
|---|---|---|
| Sliders / banners, pages, menus, blog | CODE | `Slider`, `Page`, `Menu`, `Blog` modules; storefront settings tabs in admin |
| Contact form, newsletter (Mailchimp) | CODE | `Contact`, `Newsletter` |
| PWA / service worker | CODE | `silviolleite/laravelpwa`, `public/serviceworker.js` |
| Sitemap | VERIFIED | `/sitemap.xml` 200 (spatie sitemap 8) |
| Storefront renders: home, products, cart, login, register | VERIFIED | SmokeTest |

## Localisation & Egypt readiness

| Need | Status | Notes |
|---|---|---|
| Arabic UI strings | MISSING | only `en` lang folders exist; locale list includes `ar` and RTL detector handles `ar`, `fa`, etc. |
| RTL layout | PARTIAL | `postcss-rtlcss` in build, RTL email/layout hooks exist; needs visual QA with real Arabic content |
| Translatable product/category content, per-language meta | CODE | `astrotomic/laravel-translatable` |
| `hreflang`, `canonical`, JSON-LD | MISSING | none in views |
| Meta Pixel / GTM / GA4 / TikTok | MISSING | no tracking code anywhere |
| Governorate-based shipping | MISSING | no `EG` states file (state is free text); single flat rate |
| EGP currency | PARTIAL | currency defined, store runs USD; 2 decimals |
| COD-only visibility | PARTIAL | COD works; other gateways are disabled via settings, not removed |
| Egyptian phone validation / WhatsApp | MISSING | phone only `required` |

## Verdict by area

- Safe to build on: catalogue, cart, COD checkout, coupons, stock (after Phase 3 fixes).
- Needs work for Egypt: Arabic pack, governorate shipping, simpler checkout form, SEO and ad tracking, unpaid-order expiry.
