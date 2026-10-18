# Egypt store build (Phase 4)

Everything lives in `modules/Egypt` plus small, marked edits in core files. Enable once with

```bash
php artisan migrate && php artisan egypt:install      # idempotent
php artisan egypt:demo                                # optional: bilingual demo catalogue (never on a live store)
```

## Decisions taken (you had left these open)

| Question in the handoff | What was done | Change it by |
|---|---|---|
| Design tool | none supplied, so the stock theme is kept; only RTL/Arabic fixes were made | send the Figma file (Figma preferred); the redesign is **not done** |
| Admin redesign | not done; instead a purpose-built "Egypt store" admin page | needs design input |
| Laravel 13 blocked? | not blocked (needed 6 constraint bumps, see 04) | – |
| Nulled copy | local prototype only; licence needed before a client sees it | buy the licence |
| Newest PHP | not needed, Laravel 13 runs on PHP 8.3 | – |
| Languages | Arabic + English, **Arabic default**, RTL for Arabic | `php artisan egypt:install --default-locale=en` |

## What it does

| Area | Behaviour | Where |
|---|---|---|
| Currency / country | EGP, Egypt only, timezone Africa/Cairo | `egypt:install` writes the settings |
| Payments | cash on delivery only; the other 16 gateways are switched off in settings and also filtered out on the server while the `cod_only` switch is on (code kept) | `EgyptServiceProvider::enforceCodOnly` |
| Shipping | method "Delivery to your governorate": price per governorate (27, ar/en names, ISO codes), optional free-above amount and delivery days, default price before a governorate is picked; the order summary reprices when the customer changes governorate; stored on the order | `Services/GovernorateShipping`, `GovernorateMethod`, admin page |
| Checkout form | phone (Egyptian mobile regex, `+20`/`0020`/`0` accepted) + name + street + city + governorate. E-mail, ZIP and country are not asked (placeholder e-mail `<phone>@no-email.invalid`, ZIP `00000`, country EG). A store with one country preselects it | `StoreOrderRequest`, Vue `checkout/create/main.js`, head CSS |
| Arabic | ~750 customer-facing strings (storefront, cart, checkout, account, login, validation, order e-mails/SMS/statuses, coupon messages); admin stays English except the Egypt page. Missing Arabic content falls back to English (and the reverse) instead of blank | `modules/*/Resources/lang/ar`, `TranslationFallback` |
| RTL | `dir=rtl`, mirrored layout via the existing `postcss-rtlcss` build, off-canvas scroll bug fixed | head partial |
| SEO | canonical, hreflang (+x-default), Organization and BreadcrumbList JSON-LD (Product, WebSite, Open Graph already existed), dynamic bilingual `/sitemap.xml` with alternates (cached 1 h, **do not run Admin > Sitemaps**), real 404 status | `Services/Seo`, `SitemapController` |
| Product feed | `/feeds/products.xml?lang=ar|en` (RSS + Google namespace) for Google Merchant and Meta Commerce Manager | `ProductFeedController` |
| Tracking | GTM, GA4, Meta Pixel, TikTok Pixel IDs in admin. With a GTM ID only the dataLayer is fed; without it the pixels load directly. Events: ViewContent, AddToCart, InitiateCheckout, Purchase (shared `event_id = order-<id>`). Optional Meta Conversions API Purchase (token stored, never shown again, PII hashed with SHA-256, never blocks an order) | `Services/Tracking`, `MetaConversionsApi`, `partials/tracking` |
| Super-admin switches | 13 switches (cod_only, simple_checkout, tracking, wishlist, compare, blog, reviews, newsletter, contact_form, customer_registration, coupons, flash_sales, product_feeds). Off = the URLs answer 404 on the server and the links are hidden. Only holders of `admin.egypt.features` (the Admin role by default) can change them; every change is logged (who, IP, old → new) | `Services/Features`, `EnforceFeatures`, admin page |

## Verified how

- 62 PHPUnit tests (`vendor/bin/phpunit`): governorate pricing and re-pricing, free-shipping threshold, COD-only, simple checkout and phone rules, switches (404, audit, permission), admin page, sitemap and feed XML, tracking markup, GTM de-duplication, Purchase event, CAPI hashing, language pack integrity (every key has an English twin, placeholders match).
- Real Chromium walkthrough on the Arabic site: RTL render, Arabic product page with size variants and sale price, add to cart (`add_to_cart` event), checkout with governorate prices (Cairo 50 → total 400, Aswan 85 → 435, Asyut 75), COD order placed, `Purchase` event with `order-<id>`, variant stock 5 → 4.

## Not done / needs you

1. **Visual redesign** of storefront and admin (no Stitch/Lovable/Figma file was provided; the Figma MCP was not exercised).
2. **Real content**: product photos (demo items use the placeholder image), real catalogue, brand assets, final shipping fees, Arabic text for pages/menus/banners, store name in Arabic (copied from English as a placeholder).
3. **Meta/TikTok live validation**: IDs, test events in Events Manager, and the CAPI token need your accounts; the code paths are unit-tested but not exercised against the real platforms.
4. WhatsApp order notification, Egyptian courier APIs (Bosta, Aramex, ...), order-status SMS provider.
5. Unpaid-order expiry (stock stays reserved for `pending_payment` orders; with COD that is the short window between submit and the confirmation redirect).
6. Admin language: English.
7. Licence (see 04 §5): this is a nulled copy.
