<?php

namespace Modules\Egypt\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Egypt\Entities\FeatureToggle;
use Modules\Egypt\Entities\FeatureToggleAudit;
use Throwable;

/**
 * Super-admin feature switches.
 *
 * `paths` features are enforced on the server by EnforceFeatures (a disabled
 * feature answers 404 whatever the UI shows). `behaviour` features are read by
 * the code that implements them.
 */
class Features
{
    public const DEFINITIONS = [
        'cod_only'              => ['default' => true,  'paths' => []],
        'simple_checkout'       => ['default' => true,  'paths' => []],
        'tracking'              => ['default' => true,  'paths' => []],
        'wishlist'              => ['default' => true,  'paths' => ['account/wishlist*']],
        'compare'               => ['default' => true,  'paths' => ['compare*']],
        'blog'                  => ['default' => true,  'paths' => ['blog*']],
        'reviews'               => ['default' => true,  'paths' => ['products/*/reviews*', 'reviews/products*', 'account/reviews*']],
        'newsletter'            => ['default' => true,  'paths' => ['subscribers', 'storefront/newsletter-popup']],
        'contact_form'          => ['default' => true,  'paths' => ['contact']],
        'customer_registration' => ['default' => true,  'paths' => ['register']],
        'coupons'               => ['default' => true,  'paths' => ['cart/coupon']],
        'flash_sales'           => ['default' => true,  'paths' => ['storefront/flash-sale-products']],
        'product_feeds'         => ['default' => true,  'paths' => ['feeds/*']],
    ];

    private static ?array $cache = null;


    public static function keys(): array
    {
        return array_keys(self::DEFINITIONS);
    }


    public static function enabled(string $key): bool
    {
        $states = self::states();

        return $states[$key] ?? (self::DEFINITIONS[$key]['default'] ?? true);
    }


    public static function all(): array
    {
        $result = [];

        foreach (self::keys() as $key) {
            $result[$key] = self::enabled($key);
        }

        return $result;
    }


    /**
     * URI patterns (without locale prefix) of every disabled path feature.
     */
    public static function disabledPaths(): array
    {
        $paths = [];

        foreach (self::DEFINITIONS as $key => $definition) {
            if (!self::enabled($key)) {
                $paths = array_merge($paths, $definition['paths']);
            }
        }

        return $paths;
    }


    /**
     * Change a switch and write the audit trail.
     */
    public static function set(string $key, bool $enabled, $user = null, ?string $ip = null): bool
    {
        if (!isset(self::DEFINITIONS[$key])) {
            return false;
        }

        $old = self::enabled($key);

        if ($old === $enabled) {
            return false;
        }

        FeatureToggle::updateOrCreate(
            ['key' => $key],
            ['is_enabled' => $enabled, 'updated_by' => $user?->id]
        );

        FeatureToggleAudit::create([
            'feature_key' => $key,
            'old_value' => $old,
            'new_value' => $enabled,
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'ip' => $ip,
        ]);

        self::flush();

        return true;
    }


    public static function flush(): void
    {
        self::$cache = null;
    }


    private static function states(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            if (!Schema::hasTable('egypt_feature_toggles')) {
                return self::$cache = [];
            }

            return self::$cache = FeatureToggle::pluck('is_enabled', 'key')
                ->map(fn ($value) => (bool)$value)
                ->all();
        } catch (Throwable) {
            return self::$cache = [];
        }
    }
}
