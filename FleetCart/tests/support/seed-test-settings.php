<?php

/**
 * Applies the store configuration the feature tests rely on to the
 * fleetcart_test database. Run through tests/prepare-db.sh.
 */

use Modules\Setting\Entities\Setting;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

if (config('database.connections.mysql.database') !== 'fleetcart_test') {
    fwrite(STDERR, "Refusing to seed: DB_DATABASE is not fleetcart_test\n");
    exit(1);
}

Setting::setMany([
    'cod_enabled' => 1,
    'cod_label' => 'Cash on Delivery',
    'cod_description' => 'Pay when you receive the order',
    'flat_rate_enabled' => 1,
    'flat_rate_label' => 'Flat rate',
    'flat_rate_cost' => 50,
    'supported_countries' => ['BD', 'EG'],
    'default_country' => 'EG',
]);

echo "fleetcart_test settings seeded\n";
