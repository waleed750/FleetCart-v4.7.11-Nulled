<?php

namespace Modules\Egypt\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Cart\Facades\Cart;
use Modules\Egypt\Entities\Governorate;
use Throwable;

/**
 * Shipping price by delivery governorate.
 *
 * The governorate is the state of the shipping address (or the billing address
 * when the customer ships to the billing address). It is remembered in the
 * session as soon as the checkout form sends it, so the price shown in the
 * order summary follows the customer's choice.
 */
class GovernorateShipping
{
    public const METHOD = 'governorate_rate';

    private const SESSION_KEY = 'egypt.governorate';


    public function remember(?string $code): void
    {
        if ($code && Governorate::where('code', $code)->exists()) {
            session([self::SESSION_KEY => $code]);
        }
    }


    public function rememberFromRequest($request): void
    {
        $address = $request->boolean('ship_to_a_different_address')
            ? $request->input('shipping', [])
            : $request->input('billing', []);

        $this->remember($address['state'] ?? null);
    }


    public function current(): ?Governorate
    {
        $code = session(self::SESSION_KEY);

        return $code ? Governorate::where('code', $code)->first() : null;
    }


    /**
     * Shipping cost in the default currency for the remembered governorate.
     */
    public function cost(): float
    {
        $governorate = $this->current();

        if (!$governorate) {
            return (float)setting('egypt_default_shipping_cost', 60);
        }

        if ($governorate->free_shipping_over !== null && $this->cartSubtotal() >= $governorate->free_shipping_over) {
            return 0.0;
        }

        return $governorate->shipping_cost;
    }


    /**
     * If the customer already picked the governorate method, price it again
     * after the governorate changed.
     */
    public function refreshCart(): void
    {
        if (Cart::hasShippingMethod() && Cart::shippingMethod()->name() === self::METHOD) {
            Cart::addShippingMethod(\Modules\Shipping\Facades\ShippingMethod::get(self::METHOD));
        }
    }


    public static function activeRates()
    {
        try {
            return Schema::hasTable('egypt_governorates')
                ? Governorate::where('is_active', true)->orderBy('position')->get()
                : collect();
        } catch (Throwable) {
            return collect();
        }
    }


    private function cartSubtotal(): float
    {
        return (float)Cart::subTotal()->amount();
    }
}
