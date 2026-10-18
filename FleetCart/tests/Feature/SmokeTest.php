<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    public static function publicUrls(): array
    {
        return [
            'home' => ['/'],
            'products' => ['/products'],
            'cart' => ['/cart'],
            'login' => ['/login'],
            'register' => ['/register'],
            'admin login' => ['/admin/login'],
        ];
    }


    #[DataProvider('publicUrls')]
    public function test_public_page_loads(string $url): void
    {
        $this->get($url)->assertOk();
    }


    public function test_missing_pages_return_a_real_404(): void
    {
        $this->get('/products/this-product-does-not-exist')->assertNotFound();
        $this->get('/no/such/page')->assertNotFound();
    }


    public function test_admin_area_requires_login(): void
    {
        $this->get('/admin/products')->assertRedirect();
        $this->get('/admin/orders')->assertRedirect();
    }
}
