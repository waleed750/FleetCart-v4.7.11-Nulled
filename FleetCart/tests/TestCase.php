<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Modules\Product\Entities\Product;

/**
 * Feature tests run against an already installed database (fleetcart_test,
 * a clone of an installed store) and roll every test back in a transaction.
 * Create it with tests/prepare-db.sh
 *
 * phpunit.xml sets processIsolation because the module/route/localization
 * providers keep static state that does not survive a second app boot.
 */
abstract class TestCase extends BaseTestCase
{
    use DatabaseTransactions;

    public function createApplication(): Application
    {
        $app = require __DIR__ . '/../bootstrap/app.php';


        $app->make(Kernel::class)->bootstrap();

        return $app;
    }


    protected function setUp(): void
    {
        parent::setUp();

        // Always use the scratch test database, never the real local one.
        $this->assertSame('fleetcart_test', config('database.connections.mysql.database'));
    }


    protected function product(array $attributes = []): Product
    {
        static $n = 0;
        $n++;

        return Product::create(array_merge([
            'name' => "Test Product {$n}",
            'sku' => "TEST-{$n}-" . uniqid(),
            'description' => 'desc',
            'price' => 100,
            'manage_stock' => 1,
            'qty' => 10,
            'in_stock' => 1,
            'is_active' => 1,
        ], $attributes));
    }


    protected function checkoutPayload(array $override = []): array
    {
        $address = [
            'first_name' => 'Sara',
            'last_name' => 'Ali',
            'address_1' => '1 Tahrir St',
            'city' => 'Cairo',
            'state' => 'C',
            'zip' => '11511',
            'country' => 'EG',
        ];

        return array_merge([
            'customer_email' => 'sara@example.test',
            'customer_phone' => '01012345678',
            'billing' => $address,
            'shipping' => $address,
            'payment_method' => 'cod',
            'shipping_method' => 'flat_rate',
            'terms_and_conditions' => 1,
        ], $override);
    }
}
