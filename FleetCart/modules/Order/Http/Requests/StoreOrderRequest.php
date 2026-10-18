<?php

namespace Modules\Order\Http\Requests;

use Exception;
use Modules\Support\Country;
use Modules\Cart\Facades\Cart;
use Illuminate\Validation\Rule;
use Modules\Payment\Facades\Gateway;
use Modules\Core\Http\Requests\Request;
use Modules\Checkout\Exceptions\CheckoutException;

class StoreOrderRequest extends Request
{
    /**
     * Available attributes.
     *
     * @var string
     */
    protected $availableAttributes = 'checkout::attributes';


    /**
     * Validate the class instance.
     *
     * @return void
     * @throws Exception
     */
    public function prepareForValidation()
    {
        $this->applySimpleCheckoutDefaults();

        if (!Cart::allItemsAreVirtual() && !$this->input('shipping_method')) {
            throw new CheckoutException(trans('checkout::messages.no_shipping_method'));
        }
    }


    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return array_merge(
            [
                'customer_email' => ['required', 'email', $this->emailUniqueRule()],
                'customer_phone' => $this->phoneRules(),
                'create_an_account' => 'boolean',
                'password' => 'required_if:create_an_account,1',
                'ship_to_a_different_address' => 'boolean',
                'payment_method' => ['required', Rule::in(Gateway::names())],
                'terms_and_conditions' => 'accepted',
                'shipping_method' => Cart::allItemsAreVirtual() ? 'nullable' : 'required',
            ],
            $this->billingAddressRules(),
            $this->shippingAddressRules()
        );
    }


    private function emailUniqueRule()
    {
        return $this->create_an_account ? Rule::unique('users', 'email') : null;
    }


    private function billingAddressRules()
    {
        return [
            'billing.first_name' => 'required',
            'billing.last_name' => 'required',
            'billing.address_1' => 'required',
            'billing.city' => 'required',
            'billing.zip' => 'required',
            'billing.country' => ['required', Rule::in(Country::supportedCodes())],
            'billing.state' => 'required',
        ];
    }


    private function shippingAddressRules()
    {
        return [
            'shipping.first_name' => 'required_if:ship_to_a_different_address,1',
            'shipping.last_name' => 'required_if:ship_to_a_different_address,1',
            'shipping.address_1' => 'required_if:ship_to_a_different_address,1',
            'shipping.city' => 'required_if:ship_to_a_different_address,1',
            'shipping.zip' => 'required_if:ship_to_a_different_address,1',
            'shipping.country' => ['required_if:ship_to_a_different_address,1', Rule::in(Country::supportedCodes())],
            'shipping.state' => 'required_if:ship_to_a_different_address,1',
        ];
    }


    /**
     * Egypt "simple checkout": the customer gives a phone number and a
     * governorate. E-mail and ZIP are optional and country is fixed, so they are
     * filled in here and the rest of the order flow stays untouched.
     */
    private function applySimpleCheckoutDefaults(): void
    {
        if (!$this->simpleCheckout()) {
            return;
        }

        $digits = preg_replace('/\D+/', '', (string)$this->input('customer_phone'));

        if (!$this->filled('customer_email') && $digits !== '') {
            $this->merge(['customer_email' => "{$digits}@no-email.invalid"]);
        }

        foreach (['billing', 'shipping'] as $type) {
            $address = $this->input($type);

            if (!is_array($address) || ($type === 'shipping' && !$this->boolean('ship_to_a_different_address'))) {
                continue;
            }

            $address['zip'] = ($address['zip'] ?? '') !== '' ? $address['zip'] : '00000';
            $address['country'] = ($address['country'] ?? '') !== '' ? $address['country'] : (setting('default_country') ?: 'EG');

            $this->merge([$type => $address]);
        }
    }


    private function phoneRules(): array
    {
        // 010 / 011 / 012 / 015 mobiles, with or without +20 / 0020
        return $this->simpleCheckout()
            ? ['required', 'regex:/^(?:\+?20|0020|0)?1[0125]\d{8}$/']
            : ['required'];
    }


    private function simpleCheckout(): bool
    {
        return class_exists(\Modules\Egypt\Services\Features::class)
            && \Modules\Egypt\Services\Features::enabled('simple_checkout');
    }
}
