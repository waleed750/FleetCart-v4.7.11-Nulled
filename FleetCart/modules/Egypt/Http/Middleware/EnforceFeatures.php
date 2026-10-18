<?php

namespace Modules\Egypt\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\Egypt\Services\Features;

/**
 * Answers 404 for every URL that belongs to a disabled feature. Hiding links in
 * the UI is cosmetic; this is the part that actually enforces the switch.
 */
class EnforceFeatures
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('admin*')) {
            return $next($request);
        }

        $path = $this->pathWithoutLocale($request);

        foreach (Features::disabledPaths() as $pattern) {
            if (Str::is($pattern, $path)) {
                abort(404);
            }
        }

        return $next($request);
    }


    private function pathWithoutLocale(Request $request): string
    {
        $segments = explode('/', trim($request->path(), '/'));

        if (count($segments) > 0 && array_key_exists($segments[0], supported_locales())) {
            array_shift($segments);
        }

        return implode('/', $segments);
    }
}
