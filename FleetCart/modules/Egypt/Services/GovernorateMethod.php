<?php

namespace Modules\Egypt\Services;

use JsonSerializable;
use Modules\Shipping\Method;
use Modules\Support\Money;

/**
 * Shipping method whose label and cost are resolved when they are read, not
 * when the method is registered at boot (the session and locale do not exist
 * yet at that point).
 */
class GovernorateMethod extends Method implements JsonSerializable
{
    public function __construct(private GovernorateShipping $shipping)
    {
        $this->name = GovernorateShipping::METHOD;

        // Drop the eagerly-initialised properties so __get() takes over.
        unset($this->label, $this->cost);
    }


    public function __get(string $property)
    {
        return match ($property) {
            'label' => trans('egypt::shipping.label'),
            'cost' => Money::inDefaultCurrency($this->shipping->cost()),
            default => null,
        };
    }


    /**
     * The cart sends available methods to the checkout page as JSON, and
     * json_encode() does not go through __get().
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'cost' => $this->cost,
        ];
    }
}
