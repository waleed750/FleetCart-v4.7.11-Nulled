<?php

namespace Tests\Feature;

use Modules\User\Entities\User;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    public function test_guests_are_sent_to_the_admin_login(): void
    {
        foreach (['/admin', '/admin/products', '/admin/orders', '/admin/settings', '/admin/users'] as $url) {
            $this->get($url)->assertRedirect();
        }
    }


    public function test_customer_accounts_cannot_enter_the_admin_area(): void
    {
        $customer = \Sentinel::registerAndActivate([
            'first_name' => 'Cust',
            'last_name' => 'Omer',
            'email' => 'customer@example.test',
            'phone' => '01000000001',
            'password' => 'secret123',
        ]);
        $customer->roles()->attach(\Modules\User\Entities\Role::find(setting('customer_role')));

        \Sentinel::login($customer);

        $this->get('/admin/products')->assertRedirect();
        $this->get('/admin/settings')->assertRedirect();
    }


    public function test_admin_can_open_the_main_admin_pages(): void
    {
        $admin = User::where('email', 'admin@example.test')->firstOrFail();

        \Sentinel::login($admin);

        foreach (['/admin', '/admin/products', '/admin/orders', '/admin/settings', '/admin/coupons'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}
