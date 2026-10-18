# QA matrix

A = automated (`vendor/bin/phpunit`), B = driven in a real browser during this build, M = manual check still to do on the target environment, – = not covered.

| # | Area | Case | Status |
|---|---|---|---|
| 1 | Catalogue | simple product page renders, price, stock | A, B |
| 2 | Catalogue | variant (size) product: select size, own price/stock | B |
| 3 | Catalogue | sale price shown with strike-through | B |
| 4 | Catalogue | CSV import (incl. row without brand) | B (manual upload) |
| 5 | Cart | add / update / remove, stock limit, out of stock | A |
| 6 | Cart | variant added to cart and reduces variant stock | A, B |
| 7 | Coupons | percent, min spend, usage limit, expired, inactive, unknown | A |
| 8 | Checkout | guest COD order, totals, stock decrement | A, B |
| 9 | Checkout | governorate prices (Cairo, Giza, Aswan, Asyut), free-above, reprice on change | A, B |
| 10 | Checkout | simple form: phone rules, no e-mail/ZIP/country | A, B |
| 11 | Checkout | double submit → one order, one stock decrement | A |
| 12 | Checkout | oversell protection | A |
| 13 | Checkout | stranger cannot cancel or complete another order; payment method cannot be swapped | A |
| 14 | Checkout | cancel is idempotent, variant stock restored | A |
| 15 | Payments | only COD offered even if another gateway is enabled | A |
| 16 | Orders | order stored with EGP, locale, governorate, shipping | B |
| 17 | Orders | admin status change, order e-mail/SMS in Arabic | M |
| 18 | Orders | admin cancel restores stock | M (unknown, see 03) |
| 19 | Admin | login, guests and customers blocked | A |
| 20 | Admin | Egypt page: shipping, tracking IDs validation, switches, audit | A |
| 21 | Admin | roles/permissions for staff (non-super-admin) | A (switch lock), M (full matrix) |
| 22 | Switches | disabled feature → 404 | A |
| 23 | SEO | canonical, Organization, Breadcrumb, product schema | A, B |
| 24 | SEO | hreflang ar/en/x-default | B |
| 25 | SEO | `/sitemap.xml` valid XML with alternates | A, B |
| 26 | SEO | real 404 status | A |
| 27 | SEO | product feed valid XML, prices in EGP | A |
| 28 | Tracking | ViewContent / AddToCart / InitiateCheckout / Purchase in dataLayer | A (markup), B (events) |
| 29 | Tracking | GTM present → pixels not loaded twice | A |
| 30 | Tracking | Pixel/GA4/TikTok received by the platforms | M (needs accounts) |
| 31 | Tracking | Meta CAPI accepted, de-duplicated with the pixel | M (needs token + test code) |
| 32 | Arabic | RTL layout, translated storefront and checkout | B |
| 33 | Arabic | language pack complete, placeholders intact | A |
| 34 | Arabic | missing Arabic content falls back | A (config), B (product without Arabic text) |
| 35 | Arabic | e-mails/invoice/print in RTL | M |
| 36 | Responsive | phone widths 360–414 px, RTL | M |
| 37 | Performance | home/product/checkout under 3 s on 4G, images optimised | M |
| 38 | Security | rate-limit login, HTTPS-only cookies, headers | M (see 07) |
| 39 | Upgrade | composer audit, npm audit, migrate + rollback | done (04) |
