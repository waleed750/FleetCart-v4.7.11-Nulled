<?php

namespace Tests\Feature;

use Tests\TestCase;

class CartTest extends TestCase
{
    public function test_product_can_be_added_to_the_cart(): void
    {
        $product = $this->product(['qty' => 5]);

        $this->postJson('/cart/items', ['product_id' => $product->id, 'qty' => 2])
            ->assertOk();

        $items = $this->getJson('/cart/get')->assertOk()->json('items');

        $this->assertCount(1, $items);
        $this->assertSame(2, array_values($items)[0]['qty']);
    }


    public function test_cannot_add_more_than_the_available_stock(): void
    {
        $product = $this->product(['qty' => 2]);

        $this->postJson('/cart/items', ['product_id' => $product->id, 'qty' => 3])
            ->assertStatus(400);
    }


    public function test_out_of_stock_product_cannot_be_added(): void
    {
        $product = $this->product(['qty' => 0, 'in_stock' => 0]);

        $this->postJson('/cart/items', ['product_id' => $product->id, 'qty' => 1])
            ->assertStatus(400);
    }


    public function test_cart_quantity_can_be_updated_and_removed(): void
    {
        $product = $this->product(['qty' => 5]);

        $this->postJson('/cart/items', ['product_id' => $product->id, 'qty' => 1])->assertOk();
        $id = array_key_first($this->getJson('/cart/get')->json('items'));

        $this->putJson("/cart/items/{$id}", ['qty' => 3])->assertOk();
        $this->assertSame(3, $this->getJson('/cart/get')->json("items.{$id}.qty"));

        $this->deleteJson("/cart/items/{$id}")->assertOk();
        $this->assertEmpty($this->getJson('/cart/get')->json('items'));
    }
}
