<?php

namespace Tests\Feature;

use Modules\Order\Entities\Order;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    private function addToCart($product, int $qty): void
    {
        $this->postJson('/cart/items', ['product_id' => $product->id, 'qty' => $qty])->assertOk();
    }


    public function test_guest_can_place_a_cod_order(): void
    {
        $product = $this->product(['qty' => 10, 'price' => 100]);
        $this->addToCart($product, 2);

        $response = $this->postJson('/checkout', $this->checkoutPayload());

        $response->assertOk();

        $order = Order::findOrFail($response->json('orderId'));

        $this->assertSame('pending_payment', $order->status);
        $this->assertSame('cod', $order->getRawOriginal('payment_method'));
        $this->assertNull($order->customer_id);
        $this->assertEquals(200, $order->sub_total->amount());
        $this->assertEquals(50, $order->shipping_cost->amount());
        $this->assertEquals(250, $order->total->amount());
        $this->assertSame(8, $product->fresh()->qty);
    }


    public function test_completing_a_cod_order_marks_it_pending_and_clears_the_cart(): void
    {
        $product = $this->product();
        $this->addToCart($product, 1);

        $orderId = $this->postJson('/checkout', $this->checkoutPayload())->json('orderId');

        $this->get("/checkout/{$orderId}/complete?paymentMethod=cod")
            ->assertRedirect(route('checkout.complete.show'));

        $this->assertSame('pending', Order::find($orderId)->status);
        $this->assertEmpty($this->getJson('/cart/get')->json('items'));
    }


    public function test_checkout_is_rejected_when_stock_ran_out_after_adding_to_cart(): void
    {
        $product = $this->product(['qty' => 3]);
        $this->addToCart($product, 3);

        $product->update(['qty' => 1]);

        $this->postJson('/checkout', $this->checkoutPayload())->assertStatus(400);

        $this->assertSame(0, Order::where('customer_email', 'sara@example.test')->count());
        $this->assertSame(1, $product->fresh()->qty);
    }


    public function test_unsupported_payment_method_is_rejected(): void
    {
        $this->addToCart($this->product(), 1);

        $this->postJson('/checkout', $this->checkoutPayload(['payment_method' => 'nope']))
            ->assertStatus(422);
    }


    public function test_terms_must_be_accepted(): void
    {
        $this->addToCart($this->product(), 1);

        $this->postJson('/checkout', $this->checkoutPayload(['terms_and_conditions' => 0]))
            ->assertStatus(422);
    }


    public function test_empty_cart_cannot_check_out(): void
    {
        $this->postJson('/checkout', $this->checkoutPayload())->assertRedirect();
    }


    public function test_double_submit_does_not_create_two_orders_or_take_stock_twice(): void
    {
        $product = $this->product(['qty' => 10]);
        $this->addToCart($product, 2);

        $first = $this->postJson('/checkout', $this->checkoutPayload());
        $second = $this->postJson('/checkout', $this->checkoutPayload());

        $first->assertOk();
        $this->assertSame(
            $first->json('orderId'),
            $second->json('orderId'),
            'a repeated submit must return the same pending order'
        );
        $this->assertSame(1, Order::where('customer_email', 'sara@example.test')->count());
        $this->assertSame(8, $product->fresh()->qty);
    }


    public function test_cannot_oversell_when_stock_is_taken_between_check_and_decrement(): void
    {
        $product = $this->product(['qty' => 2]);
        $this->addToCart($product, 2);

        // Another buyer takes the stock after the cart middleware ran its
        // check but before this order decrements it.
        $this->app['events']->listen('eloquent.created: ' . Order::class, function () use ($product) {
            \DB::table('products')->where('id', $product->id)->update(['qty' => 1]);
        });

        $this->postJson('/checkout', $this->checkoutPayload());

        $this->assertGreaterThanOrEqual(0, $product->fresh()->qty, 'stock must never go negative');
    }
}
