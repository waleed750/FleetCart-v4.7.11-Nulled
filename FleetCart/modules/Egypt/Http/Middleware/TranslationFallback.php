<?php

namespace Modules\Egypt\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Content (product names, descriptions, menu items...) missing in the visitor's
 * language is shown in the other one instead of staying blank. Runs after the
 * localization middleware has set the request locale.
 */
class TranslationFallback
{
    public function handle(Request $request, Closure $next)
    {
        $other = app()->getLocale() === 'ar' ? 'en' : 'ar';

        // FleetCart loads translation rows for [current locale, app.fallback_locale]
        // (see Support\Eloquent\TranslationModel) and uses the same fallback for
        // language files. Point it at the other language of the store.
        app()->setFallbackLocale($other);

        config([
            'translatable.use_fallback' => true,
            'translatable.use_property_fallback' => true,
            'translatable.fallback_locale' => $other,
        ]);

        return $next($request);
    }
}
