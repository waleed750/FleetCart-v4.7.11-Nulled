# Security and upgrade log

## 1. Upgrade log (all gates re-run by hand, not taken from a report)

| Step | Branch / commit | Result |
|---|---|---|
| 0–1 Baseline, Laravel 11.46 on PHP 8.3 | (Mac, `3214419`) | installed, 96 tables, pages 200 |
| 2.1 Laravel 11 → 12.70 | `upgrade/laravel-12` | (Mac) |
| 2.2 Laravel 12 → **13.37** | `upgrade/laravel-13` `b3a45c6` | all gates pass (below) |
| 2.3 Blockers | same | see §2 |
| 2.4 Other PHP deps | `1cd684e` | excel 4, importer fix |
| 2.5 JS | `cc12fb3` | tinymce 7, swiper 12, sass; build and browser check pass |
| 3 Tests + fixes | `aea4923` | 34 tests green; fixes in audit §4.3 |
| 4 Egypt build | `bb55031` | 62 tests green, browser walkthrough, see 05 |

Gate results on 13.37 (PHP 8.3.6, MariaDB 10.11):
`composer validate/install` ok · `route:list` 292 routes (298 before: the 6 missing are
Debugbar's, which Debugbar 4 no longer registers in production; Laravel 13 adds the
`QUERY` verb to the two `any()` checkout routes) · `config:cache`, `route:cache`,
`view:cache`, `optimize:clear` exit 0 · `migrate` then `migrate:rollback` on a scratch
DB ok · installer `POST /install` → 96 tables · `npm run build` ok · smoke URLs
`/ /products /cart /login /register /admin/login` 200 (`/sitemap.xml` and `/ar` were counted as 200 at the time but were the soft-404 page; see audit §4.3 #7-8) · admin login
(wrong password redirects back, right one enters `/admin`) and `/admin /admin/products
/admin/orders /admin/settings` 200 · TinyMCE 7 and Swiper 12 load in headless Chromium
with no console errors.

## 2. Packages that blocked Laravel 13 and what was done

| Package | Problem | Resolution |
|---|---|---|
| yajra/laravel-datatables-oracle ^12 | `illuminate/view ^12` | ^13 (constraint lives in `modules/Admin/composer.json`) |
| cartalyst/sentinel ^9 | `illuminate/support ^12` | ^10 (`modules/User/composer.json`) |
| typicms/nestablecollection ^5 | `~12.0` | ^6 (Category, Menu modules) |
| mcamara/laravel-localization 2.3.0 | Laravel ≤12 | resolved to 2.4.x |
| darryldecode/cart 4.2.6 | Laravel ≤12 | `dev-master` (only revision declaring `^13.0`); **pinned by lock, upstream has no tagged 13 release** |
| nwidart/laravel-modules ^12 | — | ^13 |
| laravel/scout 10, spatie/laravel-sitemap 7, laravel/tinker 2, debugbar 3 | Laravel ≤12 | scout 11, sitemap 8, tinker 3, debugbar 4 |
| beyondcode/laravel-dump-server | abandoned, no Laravel 12+ | removed (unused) |
| nunomaduro/phpinsights | held `psr/cache` / `symfony/cache` at a vulnerable version | removed (dev tool, unused) |
| cache/predis-adapter, swayok/cache-filesystem-adapter | pin `psr/cache ^1\|^2` | kept; they are why `symfony/cache` cannot move, but it is no longer installed |

`vendor/` was never edited. Hold-backs worth knowing: **guzzle 8** and **cache/predis-adapter 3** exist
but were deliberately not adopted — neither is required by Laravel 13 (guzzle 7.16 is current for it) and
both are major bumps with no feature this project needs. Re-evaluate when a package forces it.

## 3. Dependency advisories

| Date | Tool | Before | After |
|---|---|---|---|
| step 1 (Laravel 12) | `composer audit` | 12 packages (2 critical) | — |
| now | `composer audit` | — | **0 advisories** (also 0 with `--no-dev`) |
| Mac baseline | `npm audit` | 12 (4 critical) | — |
| now | `npm audit` | — | **7** (2 moderate, 5 high, 0 critical) |

Remaining npm advisories and why they are accepted for now:

- `braces` → `micromatch` → `fast-glob` → `globby` → `rollup-plugin-copy` (an EnvaySoft git
  fork): **build-time only**, runs on developer/CI machines, never shipped. No patched `braces`
  release exists yet.
- `selectize@0.12.6` → `sifter` → `optimist`/`minimist`/`csv-parse` (overrides pin patched
  `minimist`/`csv-parse`, remaining items are moderate): **admin-only** jQuery widget, no
  maintained drop-in (the `@selectize/selectize` fork has a different dist layout).

Abandoned Composer packages (no security advisory, replace later):
`paypal/paypal-checkout-sdk` + `paypal/paypalhttp` (→ `paypal/paypal-server-sdk`),
`doctrine/annotations` (transitive). Irrelevant for a COD-only store.

## 4. Security fixes (see audit §4.3)

Ownership check on order completion/cancellation, payment-method pinning, conditional stock
decrement, idempotent cancel, double-submit guard. Covered by `OrderSecurityTest` and
`CheckoutTest`.

## 5. Open security items

1. **Rotate credentials committed earlier.** Commit `9c80289` tracked `.env` backups with a real
   `DB_PASSWORD` and `APP_KEY` (and mail/AWS/Mailchimp placeholders). Files are now untracked and
   ignored, but they live in git history. Rotate the DB password and `APP_KEY`; if the repo was ever
   shared, rewrite history or treat the values as burned.
2. **Nulled licence** (audit §4.1): not deployable for a client.
3. **Production hardening checklist** (to do at hosting time, not yet verified): `APP_DEBUG=false`,
   `APP_ENV=production`, HTTPS + `SESSION_SECURE_COOKIE=true`, change `APP_KEY`, remove `/install`
   after setup (it already redirects when installed), restrict `/admin` by IP or add 2FA, rate-limit
   `/login` and `/admin/login`, back up DB + `storage/app/public`, queue worker and scheduler cron.
4. Checkout callbacks from external gateways need a signed token before any online gateway is
   re-enabled (audit §4.3 caveat).
5. `admin` account created by the installer in this environment uses a throwaway password
   (`LocalPass123`, database `fleetcart_local`, **local only**). Do not reuse.

## 6. How to reproduce

```bash
cd FleetCart
composer install && npm ci && npm run build
cp .env.example .env && php artisan key:generate   # then run the web installer once
tests/prepare-db.sh fleetcart_local                # clones the installed DB to fleetcart_test
vendor/bin/phpunit                                 # 62 tests, ~30 s
```

## 7. Decision

**Conditional Go** for continuing the Egyptian build on Laravel 13.37 as a *local prototype*:
dependencies clean, core order/stock paths tested and fixed, no backdoor indicators.
**No-Go for client deployment** of this copy until (a) a licensed package replaces it,
(b) credentials in git history are rotated, (c) the hardening list in §5 is done.
