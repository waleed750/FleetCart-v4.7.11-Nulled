<?php

namespace Tests\Feature;

use Modules\Order\Entities\Order;
use Tests\TestCase;

class OrderSecurityTest extends TestCase
{
    private function placeOrder(): int
    {
        $product = $this->product();
        $this->postJson('/cart/items', ['product_id' => $product->id, 'qty' => 1])->assertOk();

        return $this->postJson('/checkout', $this->checkoutPayload())->json('orderId');
    }


    /**
     * Test requests share one session, so a different visitor is simulated
     * by dropping the ownership marker the placing session was given.
     */
    private function asStranger(int $orderId): void
    {
        \Illuminate\Support\Facades\Cache::forget("checkout.owner.{$orderId}");
    }


    public function test_a_stranger_cannot_cancel_someone_elses_order(): void
    {
        $orderId = $this->placeOrder();

        // A different visitor does not own the order: the ownership marker
        // belongs to the session that placed it.
        $this->asStranger($orderId);
        $this->get("/checkout/{$orderId}/payment-canceled")->assertForbidden();

        $this->assertNotNull(Order::find($orderId), 'order must survive a request from another session');
    }


    public function test_a_stranger_cannot_complete_someone_elses_unpaid_order(): void
    {
        $orderId = $this->placeOrder();
        Order::find($orderId)->update(['payment_method' => 'stripe']);

        $this->asStranger($orderId);
        $this->get("/checkout/{$orderId}/complete?paymentMethod=cod")->assertForbidden();

        $this->assertSame('pending_payment', Order::find($orderId)->status);
    }


    public function test_the_owner_can_complete_but_not_swap_the_payment_method(): void
    {
        $orderId = $this->placeOrder();

        $this->get("/checkout/{$orderId}/complete?paymentMethod=bank_transfer")->assertForbidden();
        $this->assertSame('pending_payment', Order::find($orderId)->status);

        $this->get("/checkout/{$orderId}/complete?paymentMethod=cod")->assertRedirect();
        $this->assertSame('pending', Order::find($orderId)->status);
    }


    public function test_cancelling_twice_does_not_restore_stock_twice(): void
    {
        $product = $this->product(['qty' => 10]);
        $this->postJson('/cart/items', ['product_id' => $product->id, 'qty' => 2])->assertOk();
        $orderId = $this->postJson('/checkout', $this->checkoutPayload())->json('orderId');

        $this->get("/checkout/{$orderId}/payment-canceled");
        $this->get("/checkout/{$orderId}/payment-canceled");

        $this->assertSame(10, $product->fresh()->qty);
    }


    public function test_cancelling_restores_stock_for_variants_too(): void
    {
        $product = $this->product(['qty' => 10, 'manage_stock' => 1]);
        $variant = new \Modules\Product\Entities\ProductVariant([
            'uid' => 'v-' . uniqid(),
            'uids' => 'x',
            'name' => 'M',
            'price' => 100,
            'manage_stock' => 1,
            'qty' => 5,
            'in_stock' => 1,
            'is_default' => 1,
            'is_active' => 1,
            'position' => 1,
        ]);
        $variant->product_id = $product->id;
        $variant->save();

        $this->postJson('/cart/items', ['product_id' => $product->id, 'variant_id' => $variant->id, 'qty' => 2])->assertOk();
        $orderId = $this->postJson('/checkout', $this->checkoutPayload())->json('orderId');

        $this->assertSame(3, $variant->fresh()->qty);

        $this->get("/checkout/{$orderId}/payment-canceled");

        $this->assertSame(5, $variant->fresh()->qty, 'variant stock must be restored');
        $this->assertSame(10, $product->fresh()->qty, 'parent product stock must not change');
    }
}
