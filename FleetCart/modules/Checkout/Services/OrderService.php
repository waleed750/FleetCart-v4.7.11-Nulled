<?php

namespace Modules\Checkout\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Modules\Cart\CartTax;
use Modules\Cart\CartItem;
use Modules\Cart\Facades\Cart;
use Modules\Order\Entities\Order;
use Modules\Address\Entities\Address;
use Modules\FlashSale\Entities\FlashSale;
use Modules\Currency\Entities\CurrencyRate;
use Modules\Account\Entities\DefaultAddress;
use Modules\Shipping\Facades\ShippingMethod;

class OrderService
{
    /**
     * Seconds a visitor keeps access to a freshly created order
     * (completing or cancelling it from the payment redirect).
     */
    private const OWNERSHIP_TTL = 7200;


    public function create($request)
    {
        $this->mergeShippingAddress($request);
        $this->saveAddress($request);
        $this->addShippingMethodToCart($request);

        // One transaction: if stock can not be taken (CartItemsStockException)
        // the order, its lines and the coupon usage are rolled back together.
        $order = DB::transaction(function () use ($request) {
            return tap($this->store($request), function ($order) {
                $this->storeOrderProducts($order);
                $this->storeOrderDownloads($order);
                $this->storeFlashSaleProductOrders($order);
                $this->incrementCouponUsage($order);
                $this->attachTaxes($order);
                $this->reduceStock();
            });
        });

        $this->rememberOwner($order);

        return $order;
    }


    /**
     * Run the callback while holding a per-visitor database lock.
     *
     * A MySQL named lock is used because the cache store of this app does not
     * implement atomic locks.
     */
    public function withCheckoutLock(callable $callback)
    {
        $name = 'fleetcart.checkout.' . $this->visitorKey();

        DB::select('SELECT GET_LOCK(?, 10)', [$name]);

        try {
            return $callback();
        } finally {
            DB::select('SELECT RELEASE_LOCK(?)', [$name]);
        }
    }


    /**
     * Fingerprint of what is in the cart, used to recognise a repeated
     * submit of the same checkout.
     */
    public function cartSignature($request): string
    {
        return md5(json_encode([
            Cart::items()->map(fn (CartItem $item) => [$item->id, $item->qty])->values(),
            $request->payment_method,
            $request->shipping_method,
            $request->customer_email,
            $request->billing,
        ]));
    }


    /**
     * The order a previous, identical submit of this cart already created.
     */
    public function findDuplicate(string $signature): ?Order
    {
        $orderId = Cache::get($this->duplicateKey($signature));

        return $orderId
            ? Order::where('id', $orderId)->where('status', Order::PENDING_PAYMENT)->first()
            : null;
    }


    public function rememberSignature(string $signature, Order $order): void
    {
        Cache::put($this->duplicateKey($signature), $order->id, 600);
    }


    public function isOwnedByVisitor(int $orderId): bool
    {
        if (Cache::get("checkout.owner.{$orderId}") === $this->visitorKey()) {
            return true;
        }

        return auth()->check()
            && Order::where('id', $orderId)->where('customer_id', auth()->id())->exists();
    }


    /**
     * Identifies the visitor's browsing session. It is the cart's instance
     * name, which the cart module sets to the session id.
     */
    private function visitorKey(): string
    {
        return Cart::getInstanceName();
    }


    private function rememberOwner(Order $order): void
    {
        Cache::put("checkout.owner.{$order->id}", $this->visitorKey(), self::OWNERSHIP_TTL);
    }


    private function duplicateKey(string $signature): string
    {
        return 'checkout.duplicate.' . $this->visitorKey() . '.' . $signature;
    }


    public function reduceStock()
    {
        Cart::reduceStock();
    }


    public function delete(Order $order)
    {
        $order->delete();

        Cart::restoreStock();
    }


    private function mergeShippingAddress($request)
    {
        $request->merge([
            'shipping' => $request->ship_to_a_different_address ? $request->shipping : $request->billing,
        ]);
    }


    private function saveAddress($request)
    {
        if (auth()->guest()) {
            return;
        }

        if ($request->newBillingAddress) {
            $address = auth()
                ->user()
                ->addresses()
                ->create($this->extractAddress($request->billing));

            $this->makeDefaultAddress($address);
        }

        if ($request->ship_to_a_different_address && $request->newShippingAddress) {
            auth()
                ->user()
                ->addresses()
                ->create($this->extractAddress($request->shipping));
        }
    }


    private function extractAddress($data)
    {
        return [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'address_1' => $data['address_1'],
            'address_2' => $data['address_2'] ?? null,
            'city' => $data['city'],
            'state' => $data['state'],
            'zip' => $data['zip'],
            'country' => $data['country'],
        ];
    }


    private function makeDefaultAddress(Address $address)
    {
        if (
            auth()
                ->user()
                ->addresses()
                ->count() > 1
        ) {
            return;
        }

        DefaultAddress::create([
            'address_id' => $address->id,
            'customer_id' => auth()->id(),
        ]);
    }


    private function addShippingMethodToCart($request)
    {
        if (!Cart::allItemsAreVirtual() && !Cart::hasShippingMethod()) {
            Cart::addShippingMethod(ShippingMethod::get($request->shipping_method));
        }
    }


    private function store($request)
    {
        return Order::create([
            'customer_id' => auth()->id(),
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'customer_first_name' => $request->billing['first_name'],
            'customer_last_name' => $request->billing['last_name'],
            'billing_first_name' => $request->billing['first_name'],
            'billing_last_name' => $request->billing['last_name'],
            'billing_address_1' => $request->billing['address_1'],
            'billing_address_2' => $request->billing['address_2'] ?? null,
            'billing_city' => $request->billing['city'],
            'billing_state' => $request->billing['state'],
            'billing_zip' => $request->billing['zip'],
            'billing_country' => $request->billing['country'],
            'shipping_first_name' => $request->shipping['first_name'],
            'shipping_last_name' => $request->shipping['last_name'],
            'shipping_address_1' => $request->shipping['address_1'],
            'shipping_address_2' => $request->shipping['address_2'] ?? null,
            'shipping_city' => $request->shipping['city'],
            'shipping_state' => $request->shipping['state'],
            'shipping_zip' => $request->shipping['zip'],
            'shipping_country' => $request->shipping['country'],
            'sub_total' => Cart::subTotal()->amount(),
            'shipping_method' => Cart::shippingMethod()->name(),
            'shipping_cost' => Cart::shippingCost()->amount(),
            'coupon_id' => Cart::coupon()->id(),
            'discount' => Cart::discount()->amount(),
            'total' => Cart::total()->amount(),
            'payment_method' => $request->payment_method,
            'currency' => currency(),
            'currency_rate' => CurrencyRate::for(currency()),
            'locale' => locale(),
            'status' => Order::PENDING_PAYMENT,
            'note' => $request->order_note,
        ]);
    }


    private function storeOrderProducts(Order $order)
    {
        Cart::items()->each(function (CartItem $cartItem) use ($order) {
            $order->storeProducts($cartItem);
        });
    }


    private function storeOrderDownloads(Order $order)
    {
        Cart::items()->each(function (CartItem $cartItem) use ($order) {
            $order->storeDownloads($cartItem);
        });
    }


    private function storeFlashSaleProductOrders(Order $order)
    {
        Cart::items()->each(function (CartItem $cartItem) use ($order) {
            if (!FlashSale::contains($cartItem->product)) {
                return;
            }

            FlashSale::pivot($cartItem->product)
                ->orders()
                ->attach([
                    $cartItem->product->id => [
                        'order_id' => $order->id,
                        'qty' => $cartItem->qty,
                    ],
                ]);
        });
    }


    private function incrementCouponUsage()
    {
        Cart::coupon()->usedOnce();
    }


    private function attachTaxes(Order $order)
    {
        Cart::taxes()->each(function (CartTax $cartTax) use ($order) {
            $order->attachTax($cartTax);
        });
    }
}
