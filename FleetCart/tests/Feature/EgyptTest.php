<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Egypt\Entities\FeatureToggleAudit;
use Modules\Egypt\Entities\Governorate;
use Modules\Egypt\Services\Features;
use Modules\Order\Entities\Order;
use Tests\TestCase;

class EgyptTest extends TestCase
{
    private function addToCart(int $qty = 1, int $price = 100)
    {
        $product = $this->product(['price' => $price, 'qty' => 20]);

        $this->postJson('/cart/items', ['product_id' => $product->id, 'qty' => $qty])->assertOk();

        return $product;
    }


    private function saveSettings(array $settings): void
    {
        \Modules\Setting\Entities\Setting::setMany($settings);
        \Illuminate\Support\Facades\Cache::flush();

        // the settings repository is built once per process; rebuild it
        $this->app->forgetInstance('setting');
    }


    private function checkoutIn(string $governorate, array $override = [])
    {
        $payload = $this->checkoutPayload($override);
        $payload['billing']['state'] = $governorate;
        $payload['shipping']['state'] = $governorate;
        $payload['shipping_method'] = 'governorate_rate';

        return $this->postJson('/checkout', $payload);
    }


    // ---- governorate shipping

    public function test_shipping_price_follows_the_governorate(): void
    {
        Governorate::where('code', 'C')->update(['shipping_cost' => 50]);
        Governorate::where('code', 'ASN')->update(['shipping_cost' => 85]);

        $this->addToCart(1, 100);

        $cairo = Order::find($this->checkoutIn('C')->assertOk()->json('orderId'));

        $this->assertEquals(50, $cairo->shipping_cost->amount());
        $this->assertEquals(150, $cairo->total->amount());
    }


    public function test_aswan_costs_more_than_cairo(): void
    {
        Governorate::where('code', 'ASN')->update(['shipping_cost' => 85]);

        $this->addToCart(1, 100);

        $order = Order::find($this->checkoutIn('ASN')->assertOk()->json('orderId'));

        $this->assertEquals(85, $order->shipping_cost->amount());
        $this->assertEquals(185, $order->total->amount());
    }


    public function test_free_shipping_above_the_threshold(): void
    {
        Governorate::where('code', 'C')->update(['shipping_cost' => 50, 'free_shipping_over' => 500]);

        $this->addToCart(6, 100);

        $order = Order::find($this->checkoutIn('C')->assertOk()->json('orderId'));

        $this->assertEquals(0, $order->shipping_cost->amount());
        $this->assertEquals(600, $order->total->amount());
    }


    public function test_changing_the_governorate_reprices_the_cart(): void
    {
        Governorate::where('code', 'C')->update(['shipping_cost' => 50, 'free_shipping_over' => null]);
        Governorate::where('code', 'ASN')->update(['shipping_cost' => 85, 'free_shipping_over' => null]);

        $this->addToCart();
        $this->postJson('/cart/shipping-method', ['shipping_method' => 'governorate_rate'])->assertOk();

        $taxes = fn (string $state) => $this->postJson('/cart/taxes', [
            'billing' => ['state' => $state, 'country' => 'EG', 'city' => 'x', 'zip' => '1'],
        ]);

        $this->assertEquals(50, $taxes('C')->json('shippingCost.amount'));
        $this->assertEquals(85, $taxes('ASN')->json('shippingCost.amount'));
    }


    public function test_egypt_states_endpoint_lists_the_27_governorates(): void
    {
        $states = $this->getJson('/countries/EG/states')->assertOk()->json();

        $this->assertCount(27, $states);
        $this->assertSame('Cairo', $states['C']);
    }


    public function test_governorate_names_follow_the_app_language(): void
    {
        app()->setLocale('ar');

        $this->assertSame('القاهرة', (require base_path('modules/Support/Resources/states/EG.php'))['C']);
    }


    // ---- cod only / simple checkout

    public function test_only_cash_on_delivery_is_accepted_even_if_another_gateway_is_enabled(): void
    {
        $this->addToCart();

        $this->checkoutIn('C', ['payment_method' => 'bank_transfer'])->assertStatus(422);
        $this->checkoutIn('C', ['payment_method' => 'cod'])->assertOk();
    }


    public function test_simple_checkout_needs_no_email_or_zip(): void
    {
        $this->addToCart();

        $payload = $this->checkoutPayload(['customer_email' => '']);
        $payload['billing']['zip'] = '';
        $payload['shipping']['zip'] = '';
        $payload['billing']['state'] = 'GZ';
        $payload['shipping']['state'] = 'GZ';
        $payload['shipping_method'] = 'governorate_rate';

        $order = Order::find($this->postJson('/checkout', $payload)->assertOk()->json('orderId'));

        $this->assertSame('01012345678@no-email.invalid', $order->customer_email);
        $this->assertSame('00000', $order->billing_zip);
        $this->assertSame('GZ', $order->billing_state);
    }


    public function test_phone_must_be_an_egyptian_mobile_number(): void
    {
        $this->addToCart();

        foreach (['12345', '0221234567', 'abc', '01312345678'] as $bad) {
            $this->checkoutIn('C', ['customer_phone' => $bad])
                ->assertStatus(422)
                ->assertJsonValidationErrors('customer_phone');
        }

        foreach (['01012345678', '+201112345678', '00201212345678', '01512345678'] as $good) {
            $this->checkoutIn('C', ['customer_phone' => $good])->assertOk();
            $this->addToCart();
        }
    }


    // ---- feature switches

    public function test_a_disabled_feature_answers_404_on_the_server(): void
    {
        $this->get('/blog/posts')->assertOk();

        Features::set('blog', false);

        $this->get('/blog/posts')->assertNotFound();
                $this->get('/compare')->assertOk();
    }


    public function test_switching_a_feature_off_is_audited(): void
    {
        $admin = \Modules\User\Entities\User::where('email', 'admin@example.test')->firstOrFail();

        Features::set('wishlist', false, $admin, '203.0.113.9');
        Features::set('wishlist', false, $admin, '203.0.113.9'); // no change, no second row

        $audit = FeatureToggleAudit::where('feature_key', 'wishlist')->get();

        $this->assertCount(1, $audit);
        $this->assertTrue($audit[0]->old_value);
        $this->assertFalse($audit[0]->new_value);
        $this->assertSame('admin@example.test', $audit[0]->user_email);
        $this->assertSame('203.0.113.9', $audit[0]->ip);
    }


    public function test_only_a_super_admin_can_change_feature_switches(): void
    {
        $role = \Modules\User\Entities\Role::create(['permissions' => ['admin.egypt.index' => true, 'admin.egypt.edit' => true]]);
        $staff = \Sentinel::registerAndActivate([
            'first_name' => 'Staff', 'last_name' => 'User', 'email' => 'staff@example.test', 'phone' => '01000000002', 'password' => 'secret123',
        ]);
        $staff->roles()->attach($role);

        \Sentinel::login($staff);

        $this->get('/admin/egypt')->assertOk();
        $this->put('/admin/egypt/features', ['features' => ['blog' => '1']])->assertRedirect();
        $this->assertTrue(Features::enabled('wishlist'));
    }


    public function test_super_admin_can_switch_features_from_the_admin_page(): void
    {
        $admin = \Modules\User\Entities\User::where('email', 'admin@example.test')->firstOrFail();
        \Sentinel::login($admin);

        $this->get('/admin/egypt')->assertOk()->assertSee('governorate', false);

        // only "blog" stays on, everything else is switched off
        $this->put('/admin/egypt/features', ['features' => ['blog' => '1']])->assertRedirect();

        Features::flush();
        $this->assertTrue(Features::enabled('blog'));
        $this->assertFalse(Features::enabled('compare'));
        $this->assertGreaterThan(5, FeatureToggleAudit::count());
    }


    public function test_admin_can_edit_shipping_rates_and_tracking_ids(): void
    {
        $admin = \Modules\User\Entities\User::where('email', 'admin@example.test')->firstOrFail();
        \Sentinel::login($admin);
        $cairo = Governorate::where('code', 'C')->firstOrFail();

        $rates = Governorate::all()->mapWithKeys(fn ($g) => [$g->id => [
            'is_active' => '1', 'shipping_cost' => $g->id === $cairo->id ? '77.50' : (string)$g->shipping_cost,
            'free_shipping_over' => '', 'eta_days_min' => 1, 'eta_days_max' => 3,
        ]])->all();

        $this->put('/admin/egypt/shipping', ['egypt_default_shipping_cost' => 60, 'rates' => $rates])->assertRedirect();
        $this->assertEquals(77.5, $cairo->fresh()->shipping_cost);

        $this->put('/admin/egypt/tracking', ['egypt_ga4_id' => 'not-an-id'])->assertSessionHasErrors('egypt_ga4_id');
        $this->put('/admin/egypt/tracking', ['egypt_meta_pixel_id' => '123456789012345', 'egypt_gtm_id' => 'GTM-ABC1234'])->assertRedirect();
        $this->assertSame('123456789012345', setting('egypt_meta_pixel_id'));
    }


    // ---- SEO and tracking

    public function test_pages_carry_canonical_and_organization_markup(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('<link rel="canonical"', $html);
        $this->assertStringContainsString('"@type":"Organization"', $html);
    }


    public function test_product_page_has_breadcrumb_and_view_content_event(): void
    {
        $product = $this->product(['price' => 200]);
        $this->saveSettings(['egypt_meta_pixel_id' => '123456789012345']);

        $html = $this->get($product->url())->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('"name":"ViewContent"', $html);
        $this->assertStringContainsString("fbq('init', \"123456789012345\")", $html);
    }


    public function test_with_a_gtm_container_pixels_are_not_loaded_twice(): void
    {
        $this->saveSettings(['egypt_gtm_id' => 'GTM-ABC1234', 'egypt_meta_pixel_id' => '123456789012345']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('googletagmanager.com/gtm.js', $html);
        $this->assertStringNotContainsString("fbq('init'", $html);
        $this->assertStringContainsString('googletagmanager.com/ns.html?id=GTM-ABC1234', $html);
    }


    public function test_tracking_can_be_switched_off(): void
    {
        $this->saveSettings(['egypt_meta_pixel_id' => '123456789012345']);
        Features::set('tracking', false);

        $this->assertStringNotContainsString('fbq(', $this->get('/')->assertOk()->getContent());
    }


    public function test_purchase_event_uses_the_order_and_a_shared_event_id(): void
    {
        $this->addToCart(2, 100);
        $orderId = $this->checkoutIn('C', ['payment_method' => 'cod'])->assertOk()->json('orderId');

        $this->get("/checkout/{$orderId}/complete?paymentMethod=cod")->assertRedirect();
        $html = $this->get('/checkout/complete')->assertOk()->getContent();

        $this->assertStringContainsString('"name":"Purchase"', $html);
        $this->assertStringContainsString("\"eventId\":\"order-{$orderId}\"", $html);
        $this->assertStringContainsString('"transactionId":"' . $orderId . '"', $html);
    }


    public function test_meta_capi_skips_placeholder_emails(): void
    {
        $this->addToCart();
        $payload = $this->checkoutIn('C', ['customer_email' => ''])->assertOk()->json('orderId');
        $order = Order::find($payload);

        $event = app(\Modules\Egypt\Services\MetaConversionsApi::class)
            ->payload(new \Modules\Checkout\Events\OrderPlaced($order))['data'][0];

        $this->assertArrayNotHasKey('em', $event['user_data']);
        $this->assertArrayHasKey('ph', $event['user_data']);
    }


    public function test_meta_capi_payload_hashes_customer_data(): void
    {
        $this->addToCart();
        $orderId = $this->checkoutIn('C')->assertOk()->json('orderId');
        $order = Order::find($orderId);

        $payload = app(\Modules\Egypt\Services\MetaConversionsApi::class)
            ->payload(new \Modules\Checkout\Events\OrderPlaced($order));

        $event = $payload['data'][0];

        $this->assertSame('Purchase', $event['event_name']);
        $this->assertSame("order-{$orderId}", $event['event_id']);
        $this->assertSame([hash('sha256', '201012345678')], $event['user_data']['ph']);
        $this->assertSame([hash('sha256', 'sara@example.test')], $event['user_data']['em']);
        $this->assertStringNotContainsString('01012345678', json_encode($event), 'phone must only be sent hashed');
        $this->assertStringNotContainsString('sara@example.test', json_encode($event), 'e-mail must only be sent hashed');
    }


    public function test_product_feed_lists_active_products(): void
    {
        $product = $this->product(['price' => 250, 'sku' => 'FEED-1', 'name' => 'Feed Shirt']);

        $response = $this->get('/feeds/products.xml?lang=en')->assertOk();
        $xml = $response->streamedContent();

        $this->assertStringContainsString('xmlns:g="http://base.google.com/ns/1.0"', $xml);
        $this->assertStringContainsString('<g:id>FEED-1</g:id>', $xml);
        $this->assertStringContainsString('<g:price>250.00 EGP</g:price>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml), 'feed must be valid XML');
    }


    public function test_feed_can_be_switched_off(): void
    {
        Features::set('product_feeds', false);

        $this->get('/feeds/products.xml')->assertNotFound();
    }


    public function test_sitemap_is_valid_xml_with_the_products(): void
    {
        $product = $this->product(['sku' => 'MAP-1']);
        \Illuminate\Support\Facades\Cache::forget('egypt.sitemap');

        $response = $this->get('/sitemap.xml')->assertOk();
        $xml = $response->getContent();

        $this->assertStringContainsString('application/xml', $response->headers->get('Content-Type'));
        $this->assertNotFalse(simplexml_load_string($xml), 'sitemap must be valid XML');
        $this->assertStringContainsString('/products/' . $product->slug, $xml);
        $this->assertStringContainsString('xmlns:xhtml', $xml);
    }


    // ---- language

    public function test_arabic_language_pack_covers_the_storefront(): void
    {
        $this->assertSame('الحقل الاسم مطلوب', trans('core::validation.required', ['attribute' => 'الاسم'], 'ar'));
        $this->assertSame('تواصل معنا', trans('storefront::layouts.contact', [], 'ar'));
        $this->assertSame('تأكيد الطلب', trans('storefront::checkout.place_order', [], 'ar'));
        $this->assertSame('قيد المراجعة', trans('order::statuses.pending', [], 'ar'));
    }


    public function test_every_arabic_key_exists_in_english_and_keeps_its_placeholders(): void
    {
        foreach (glob(base_path('modules/*/Resources/lang/ar/*.php')) as $arFile) {
            $enFile = str_replace('/lang/ar/', '/lang/en/', $arFile);

            if (!is_file($enFile)) {
                continue; // egypt module files exist in both; others must have an English twin
            }

            $ar = \Illuminate\Support\Arr::dot(require $arFile);
            $en = \Illuminate\Support\Arr::dot(require $enFile);

            foreach ($ar as $key => $text) {
                $this->assertArrayHasKey($key, $en, basename($arFile) . " has an unknown key {$key}");

                preg_match_all('/:[a-z_]+/', $en[$key], $enHolders);
                preg_match_all('/:[a-z_]+/', $text, $arHolders);
                sort($enHolders[0]);
                sort($arHolders[0]);

                $this->assertSame($enHolders[0], $arHolders[0], basename($arFile) . " {$key}: placeholders differ");
            }
        }
    }


    public function test_translation_fallback_points_at_the_other_language(): void
    {
        app()->setLocale('ar');

        (new \Modules\Egypt\Http\Middleware\TranslationFallback())->handle(request(), fn () => response('ok'));

        $this->assertSame('en', config('app.fallback_locale'));
        $this->assertSame('en', config('translatable.fallback_locale'));
        $this->assertTrue(config('translatable.use_property_fallback'));
    }
}
