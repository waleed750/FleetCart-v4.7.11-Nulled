<?php

namespace Modules\Egypt\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Egypt\Services\GovernorateShipping;

/**
 * Applied to the cart tax and checkout routes, which receive the address.
 * Keeps the shipping price in step with the governorate before the request is
 * handled, so both the cart total returned to the browser and the order that
 * is stored use the right price.
 */
class SyncGovernorateShipping
{
    public function __construct(private GovernorateShipping $shipping)
    {
    }


    private const ROUTES = ['cart.taxes.store', 'cart.shipping_method.store', 'checkout.store'];


    public function handle(Request $request, Closure $next)
    {
        if (!in_array($request->route()?->getName(), self::ROUTES, true)) {
            return $next($request);
        }

        $this->shipping->rememberFromRequest($request);
        $this->shipping->refreshCart();

        return $next($request);
    }
}
