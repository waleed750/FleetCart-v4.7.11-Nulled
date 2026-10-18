# Source audit — FleetCart 4.7.11 (nulled copy)

Audited 2026-10-18 on branch `upgrade/laravel-13`. Everything below was read from
the code or produced by a command; where something was not verified it says so.

## 1. Versions (after the Phase 2 upgrade)

| Item | Version | Evidence |
|---|---|---|
| FleetCart | 4.7.11 | `vite.config.js` `VERSION`, build asset suffix `-v4.7.11` |
| Laravel | 13.37.0 | `php artisan about` |
| PHP (constraint / tested) | `^8.3` / 8.3.6 | `composer.json`, `php -v` |
| Node / npm | 22.22 / 10.9 | `node -v` |
| Vite / Sass | 7.3.7 / 1.105.2 | `npm ls` |
| MariaDB | 10.11 | `mariadb --version` (production-grade MySQL 8 also fine) |
| Modules | 36 (`nwidart/laravel-modules` 13) | `ls modules`, `modules.json` |
| Routes | 292 (+6 Debugbar routes only in debug) | `php artisan route:list --json` |
| Tables | 96 | installer output, `information_schema` |

Key packages: sentinel 10 (auth/roles), yajra datatables 13, scout 11, excel 4,
tinymce 7, swiper 12, vue 3, alpine, sass.

## 2. Repository layout

```
FleetCart/            Laravel app root (this is the project root)
  app/                installer, license, exception handler, middleware
  modules/            36 feature modules (Entities, Http, Routes, Resources, Database)
  resources/lang/en   only English ships
  database/           core migrations (module migrations live in modules/*/Database)
  public/             index.php, build/ (vite), samples/, storage link
  tests/              PHPUnit feature tests (added in Phase 3)
../Documentation      vendor docs (static HTML)
../Samples/Import     electronics sample CSV (not fashion)
../FleetCart.zip      untouched original package
```

Modules by route count: User 35, Blog 26, Account 18, Attribute 14, Menu 13,
Product 11, Page 9, Brand 9, Category 9, Coupon 9, Translation 9, Storefront 9,
Cart 9, Option 8, Review 8, Tag 8, Variation 8, FlashSale 7, Order 7, Slider 7,
Tax 7, Currency 6, Support 6, Compare 6, Media 5, Checkout 5, Payment 4, Admin 2,
Importer 2, Setting 2, Transaction 2, Contact 2, Report 1, Newsletter 1.

## 3. Request flow: cart → order → stock → payment

1. `POST /cart/items` (`CartItemController@store`, `CheckItemStock` middleware) puts a
   product or variant in a session-backed cart (`Modules\Cart\Cart`, extends
   darryldecode/cart; the cart instance name is the session id).
2. `POST /checkout` (`CheckoutController@store`) → `StoreOrderRequest` validation →
   `CheckCartItemsStock` middleware → `OrderService::create()`:
   order row (`status = pending_payment`), order lines, downloads, flash-sale rows,
   coupon usage, taxes, then **stock decrement** → gateway `purchase()`.
3. `GET|POST /checkout/{id}/complete` (`CheckoutCompleteController@store`) →
   gateway `complete()` → `OrderPlaced` event → listeners: status `pending`,
   emails, SMS, clear cart, remember placed order in session.
4. `/checkout/{id}/payment-canceled` deletes the unpaid order and returns the stock.

Stock is therefore reserved **at order creation**, before payment, and released on
cancel. An abandoned `pending_payment` order keeps its stock (no expiry job exists).

## 4. Source-level findings

### 4.1 Nulled licence
`app/License.php`: `valid()` returns `true`, `shouldRecheck()` and
`shouldCreateLicense()` return `false` before any real logic. The class still
contains a client for `https://license.envaysoft.com`. **Not deployable for a paying
client** — buy a CodeCanyon licence and rebuild from the clean package. Do not
"repair" this file in this copy.

### 4.2 Backdoor / malware scan (completed)
Scope: `app/ modules/ routes/ config/ database/ bootstrap/ public/ resources/`,
all of `vendor/`, compiled JS in `public/build`.

| Check | Result |
|---|---|
| `eval`, `assert($_…)`, `create_function`, `gzinflate`/`str_rot13` chains, `preg_replace /e`, `shell_exec`/`passthru`/`proc_open`/`popen`/`system`/`exec`, `$_REQUEST(...)` callables, `php://input`, uploads moved by user path — own PHP | **0 hits** (one benign `openssl_private_decrypt(base64_decode())` in the Nagad payment library) |
| Same patterns in `vendor/` | **0 hits** |
| Long base64 literals in `vendor/*.php` | only AWS SDK endpoint data, test fixtures, phpspreadsheet encryption code |
| Outbound HTTP in own code | `License.php` (licence server), reCAPTCHA verify, SMS gateways (Twilio/Vonage), payment libraries. No unexpected hosts |
| External hosts in Blade views | Google Fonts, payment SDK scripts, social share links, reCAPTCHA. No analytics or unknown CDNs |
| Compiled JS `eval` / `new Function` / `atob` | only alpine, vue, dropzone, tinymce, vuedraggable, x-editable (framework internals) |
| `public/` | `index.php` is stock Laravel; no other PHP files; `samples/` holds CSV/zip only |
| Packages not from Packagist dist | 5 `dev-master` git packages: darryldecode/cart (upstream GitHub), paytm/js-checkout (upstream GitHub), and three EnvaySoft forks: jackiedo/dotenv-editor, maatwebsite/laravel-sidebar, tinycolor. The three forks are by the product vendor; they were included in the pattern scan above. |

Residual risk: pattern scans cannot prove absence. Before production, rebuild from a
clean, licensed package and diff against this tree.

### 4.3 Defects found and fixed in Phase 3 (each has a failing-then-passing test)
| # | Defect | Impact | Fix |
|---|---|---|---|
| 1 | `restoreStock()` incremented the parent **product** while `reduceStock()` decremented the **variant** | variant stock never came back on cancel; parent stock inflated | restore the same item that was taken |
| 2 | stock decrement was unconditional | concurrent buyers could drive stock below zero (oversell) | conditional `qty >= n` update inside a DB transaction; failure rolls the order back and returns 400 |
| 3 | no double-submit protection | a double click or retry created two orders and took stock twice | per-visitor MySQL named lock + de-duplication by cart signature |
| 4 | `/checkout/{id}/payment-canceled` (any method, CSRF-exempt) had no ownership check | any visitor could permanently delete any order by id and add stock | owner check + only `pending_payment` + idempotent |
| 5 | `/checkout/{id}/complete?paymentMethod=cod` had no ownership/method check | any visitor could mark **someone else's unpaid order** as placed by switching the method to COD | owner check + payment method must match the order + completed orders are not re-completed |
| 6 | importer crashed on rows without a Brand (undefined index) | CSV import silently produced no products | null-coalesce |

Caveat for #4/#5: ownership is the browser session that created the order. Gateways
that call back with a cross-site POST or server-to-server (no session cookie) would
now get 403. This store is COD-only so it is unaffected; if online gateways are ever
re-enabled, add a signed callback token per gateway.

### 4.4 Other observations (not changed)
- `Cache::lock()` is not usable: the custom cache store
  (`swayok/alternative-laravel-cache`) lacks `add()`. Any package calling a cache lock
  on the default store would fail. Workaround used: MySQL named lock.
- Checkout requires an **email** and a **ZIP** (`StoreOrderRequest`); both are awkward
  for Egyptian COD (see Phase 4 list).
- `Product::saved` syncs relations from `request()->all()`; any programmatic save during
  a request that carries input can wipe categories/tags. Stock changes avoid `save()`.
- Order creation does not touch inventory for flash-sale quantity atomically
  (`FlashSale` pivot rows are inserted, remaining qty is read by the cart middleware).
- No job expires unpaid `pending_payment` orders (stock stays reserved).
- `.env` backups were tracked in git (real DB password + APP_KEY in commit `9c80289`).
  Now untracked and ignored; **rotate both** — they remain in history.
