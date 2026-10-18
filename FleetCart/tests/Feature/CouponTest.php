<?php

namespace Tests\Feature;

use Modules\Coupon\Entities\Coupon;
use Modules\Order\Entities\Order;
use Tests\TestCase;

class CouponTest extends TestCase
{
    private function coupon(array $attributes = []): Coupon
    {
        return Coupon::create(array_merge([
            'name' => 'Ten percent',
            'code' => 'SAVE' . uniqid(),
            'is_percent' => 1,
            'value' => 10,
            'free_shipping' => 0,
            'is_active' => 1,
        ], $attributes));
    }


    private function cartWith(int $price = 100, int $qty = 2): void
    {
        $product = $this->product(['price' => $price, 'qty' => 20]);

        $this->postJson('/cart/items', ['product_id' => $product->id, 'qty' => $qty])->assertOk();
    }


    public function test_valid_coupon_discounts_the_order_total(): void
    {
        $coupon = $this->coupon();
        $this->cartWith(100, 2);

        $this->postJson('/cart/coupon', ['coupon' => $coupon->code])->assertOk();

        $orderId = $this->postJson('/checkout', $this->checkoutPayload())->json('orderId');
        $order = Order::find($orderId);

        $this->assertEquals(20, $order->discount->amount());
        $this->assertEquals(180 + 50, $order->total->amount());
        $this->assertSame(1, $coupon->fresh()->used);
    }


    public function test_unknown_coupon_is_rejected(): void
    {
        $this->cartWith();

        $this->postJson('/cart/coupon', ['coupon' => 'DOES-NOT-EXIST'])->assertNotFound();
    }


    public function test_expired_or_inactive_coupons_are_rejected(): void
    {
        $this->cartWith();

        $inactive = $this->coupon(['is_active' => 0]);
        $expired = $this->coupon(['start_date' => now()->subDays(10), 'end_date' => now()->subDay()]);

        $this->postJson('/cart/coupon', ['coupon' => $inactive->code])->assertClientError();
        $this->postJson('/cart/coupon', ['coupon' => $expired->code])->assertClientError();
    }


    public function test_minimum_spend_is_enforced(): void
    {
        $coupon = $this->coupon(['minimum_spend' => 1000]);
        $this->cartWith(100, 1);

        $this->postJson('/cart/coupon', ['coupon' => $coupon->code])->assertForbidden();
    }


    public function test_usage_limit_per_coupon_is_enforced(): void
    {
        $coupon = $this->coupon(['usage_limit_per_coupon' => 1]);
        $coupon->forceFill(['used' => 1])->save();
        $this->cartWith();

        $this->postJson('/cart/coupon', ['coupon' => $coupon->code])->assertForbidden();
    }
}
