<?php

namespace Modules\Egypt\Services;

use Modules\Cart\Facades\Cart;
use Modules\Order\Entities\Order;
use Modules\Product\Entities\Product;

/**
 * Ad / analytics tracking: IDs come from the Egypt settings page. Events are
 * pushed to the GA4 dataLayer and, when the pixels are loaded directly (no
 * GTM container), also to Meta Pixel and TikTok Pixel.
 */
class Tracking
{
    public const SETTINGS = [
        'egypt_gtm_id', 'egypt_ga4_id', 'egypt_meta_pixel_id', 'egypt_tiktok_pixel_id',
        'egypt_meta_capi_token', 'egypt_meta_test_event_code',
    ];


    public function enabled(): bool
    {
        return Features::enabled('tracking');
    }


    public function id(string $name): string
    {
        return trim((string)setting("egypt_{$name}", ''));
    }


    /**
     * With a GTM container, pixels are expected to be managed inside GTM and
     * only the dataLayer is fed; otherwise the pixels are loaded here.
     */
    public function loadsPixelsDirectly(): bool
    {
        return $this->id('gtm_id') === '';
    }


    public function currency(): string
    {
        return currency();
    }


    /**
     * Events rendered on the server for the current page (ViewContent,
     * InitiateCheckout, Purchase). AddToCart is fired from the browser.
     */
    public function pageEvents(?string $routeName, $product = null): array
    {
        // Tracking must never take a page down.
        try {
            return $this->buildPageEvents($routeName, $product);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
    }


    private function buildPageEvents(?string $routeName, $product): array
    {
        return match ($routeName) {
            'products.show' => $product instanceof Product ? [$this->viewContent($product)] : [],
            'checkout.create' => Cart::items()->isNotEmpty() ? [$this->initiateCheckout()] : [],
            'checkout.complete.show' => $this->purchaseFromSession(),
            default => [],
        };
    }


    public function viewContent(Product $product): array
    {
        return [
            'name' => 'ViewContent',
            'items' => [$this->itemFromProduct($product, 1)],
            'value' => $this->money($product->selling_price->convertToCurrentCurrency()->amount()),
        ];
    }


    public function initiateCheckout(): array
    {
        $items = Cart::items()->map(fn ($cartItem) => $this->itemFromProduct($cartItem->product, $cartItem->qty))->values()->all();

        return [
            'name' => 'InitiateCheckout',
            'items' => $items,
            'value' => $this->money(Cart::subTotal()->convertToCurrentCurrency()->amount()),
        ];
    }


    public function purchase(Order $order): array
    {
        $rate = (float)($order->currency_rate ?: 1);

        $items = $order->products->map(fn ($line) => [
            'id' => (string)($line->product->sku ?? $line->product_id),
            'name' => (string)($line->product->name ?? ''),
            'price' => $this->money((float)$line->unit_price->amount() * $rate),
            'quantity' => (int)$line->qty,
        ])->values()->all();

        return [
            'name' => 'Purchase',
            'eventId' => 'order-' . $order->id,
            'transactionId' => (string)$order->id,
            'currency' => $order->currency,
            'items' => $items,
            'value' => $this->money((float)$order->total->amount() * $rate),
            'shipping' => $this->money((float)$order->shipping_cost->amount() * $rate),
        ];
    }


    private function purchaseFromSession(): array
    {
        $order = session('placed_order');

        return $order instanceof Order ? [$this->purchase($order)] : [];
    }


    private function itemFromProduct(Product $product, int $qty): array
    {
        return [
            'id' => (string)($product->sku ?: $product->id),
            'name' => (string)$product->name,
            'price' => $this->money($product->selling_price->convertToCurrentCurrency()->amount()),
            'quantity' => $qty,
        ];
    }


    private function money($amount): float
    {
        return round((float)$amount, 2);
    }
}
