<?php

namespace Modules\Egypt\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Currency\Entities\CurrencyRate;
use Modules\Egypt\Entities\Governorate;
use Modules\Egypt\Services\Features;
use Modules\Egypt\Services\GovernorateList;
use Modules\Setting\Entities\Setting;
use Modules\User\Entities\Role;

/**
 * php artisan egypt:install
 *
 * Turns a fresh FleetCart install into the Egyptian store configuration:
 * EGP, Egypt only, Arabic (RTL) + English, COD only, governorate shipping.
 * Idempotent: safe to run again; existing governorate prices are kept.
 */
class InstallCommand extends Command
{
    protected $signature = 'egypt:install {--default-locale=ar : ar or en}';

    protected $description = 'Configure the store for Egypt (EGP, COD only, governorate shipping, Arabic)';

    /** Gateways switched off (kept in the code base, just disabled). */
    private const ONLINE_GATEWAYS = [
        'paypal', 'stripe', 'paytm', 'razorpay', 'instamojo', 'authorizenet', 'paystack', 'mercadopago',
        'flutterwave', 'iyzico', 'payfast', 'bank_transfer', 'check_payment', 'bkash', 'nagad', 'sslcommerz',
    ];


    public function handle(): int
    {
        $locale = $this->option('default-locale') === 'en' ? 'en' : 'ar';

        Artisan::call('migrate', ['--force' => true]);
        $this->info('Migrations applied.');

        $this->seedGovernorates();
        $this->configureStore($locale);
        $this->grantPermissions();

        Cache::flush();

        $this->info('Egypt store configuration applied. Open /admin and review "Egypt store" in the sidebar.');

        return self::SUCCESS;
    }


    private function seedGovernorates(): void
    {
        $position = 0;

        foreach (GovernorateList::ALL as $code => [$en, $ar, $cost, $etaMin, $etaMax]) {
            $governorate = Governorate::firstOrNew(['code' => $code]);

            // Keep prices an admin already changed; always refresh names/order.
            if (!$governorate->exists) {
                $governorate->shipping_cost = $cost;
                $governorate->eta_days_min = $etaMin;
                $governorate->eta_days_max = $etaMax;
                $governorate->is_active = true;
            }

            $governorate->name_en = $en;
            $governorate->name_ar = $ar;
            $governorate->position = ++$position;
            $governorate->save();
        }

        $this->info(count(GovernorateList::ALL) . ' governorates ready.');
    }


    private function configureStore(string $locale): void
    {
        $settings = [
            'default_currency' => 'EGP',
            'supported_currencies' => ['EGP'],
            'default_country' => 'EG',
            'supported_countries' => ['EG'],
            'default_locale' => $locale,
            'supported_locales' => ['ar', 'en'],
            'default_timezone' => 'Africa/Cairo',
            'cod_enabled' => 1,
            'flat_rate_enabled' => 0,
            'free_shipping_enabled' => 0,
            'local_pickup_enabled' => 0,
            'egypt_governorate_shipping_enabled' => 1,
            'egypt_default_shipping_cost' => 60,
        ];

        foreach (self::ONLINE_GATEWAYS as $gateway) {
            $settings["{$gateway}_enabled"] = 0;
        }

        Setting::setMany($settings);

        $this->setTranslatable('cod_label', ['ar' => 'الدفع عند الاستلام', 'en' => 'Cash on delivery']);
        $this->setTranslatable('cod_description', ['ar' => 'ادفع نقداً عند استلام طلبك.', 'en' => 'Pay in cash when you receive your order.']);

        $this->fillMissingArabicSettings();

        CurrencyRate::updateOrCreate(['currency' => 'EGP'], ['rate' => 1]);

        foreach (Features::keys() as $key) {
            Features::set($key, Features::enabled($key));
        }

        $this->info("Currency EGP, country EG, languages ar/en (default {$locale}), COD only, governorate shipping.");
    }


    /**
     * Translatable settings (store name, tagline, labels, footer text...) that
     * only exist in English would render blank in Arabic. Copy the English text
     * as a starting point; the owner then edits it in Settings.
     */
    private function fillMissingArabicSettings(): void
    {
        Setting::where('is_translatable', true)->get()->each(function (Setting $setting) {
            $rows = DB::table('setting_translations')->where('setting_id', $setting->id)->pluck('value', 'locale');

            if (isset($rows['en']) && !isset($rows['ar']) && unserialize($rows['en']) !== '') {
                $this->saveTranslation($setting, 'ar', unserialize($rows['en']));
            }
        });
    }


    /**
     * Written straight to the table: the translation model hides rows of other
     * locales while the app runs in Arabic. Values are stored serialised, the
     * way the Setting translation mutator does it.
     */
    private function saveTranslation(Setting $setting, string $locale, $value): void
    {
        DB::table('setting_translations')->updateOrInsert(
            ['setting_id' => $setting->id, 'locale' => $locale],
            ['value' => serialize($value)]
        );
    }


    private function setTranslatable(string $key, array $values): void
    {
        $setting = Setting::updateOrCreate(['key' => $key], ['is_translatable' => true]);

        foreach ($values as $locale => $value) {
            $this->saveTranslation($setting, $locale, $value);
        }
    }


    private function grantPermissions(): void
    {
        foreach (Role::whereKey(1)->get() as $role) {
            $current = $role->permissions ?? [];

            foreach (['admin.egypt.index', 'admin.egypt.edit', 'admin.egypt.features'] as $permission) {
                $current[$permission] = true;
            }

            $role->permissions = $current;
            $role->save();
        }

        $this->info('Admin role granted Egypt store permissions.');
    }
}
