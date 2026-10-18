<?php

namespace Modules\Egypt\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Checkout\Events\OrderPlaced;
use Throwable;

/**
 * Optional server-side Purchase event for Meta (Conversions API). Uses the same
 * event_id as the browser pixel (`order-<id>`) so Meta de-duplicates the pair.
 * Never throws: tracking must not break an order.
 */
class MetaConversionsApi
{
    private const ENDPOINT = 'https://graph.facebook.com/v21.0/%s/events';


    public function __construct(private Tracking $tracking)
    {
    }


    public function handle(OrderPlaced $event): void
    {
        $pixel = $this->tracking->id('meta_pixel_id');
        $token = $this->tracking->id('meta_capi_token');

        if (!$this->tracking->enabled() || $pixel === '' || $token === '') {
            return;
        }

        try {
            $payload = $this->payload($event);

            if ($code = $this->tracking->id('meta_test_event_code')) {
                $payload['test_event_code'] = $code;
            }

            $response = Http::timeout(5)->asJson()->post(
                sprintf(self::ENDPOINT, $pixel) . '?access_token=' . urlencode($token),
                $payload
            );

            if ($response->failed()) {
                Log::warning('Meta CAPI rejected the event', ['status' => $response->status(), 'body' => $response->body()]);
            }
        } catch (Throwable $e) {
            Log::warning('Meta CAPI failed: ' . $e->getMessage());
        }
    }


    public function payload(OrderPlaced $event): array
    {
        $order = $event->order;
        $purchase = $this->tracking->purchase($order);

        return [
            'data' => [[
                'event_name' => 'Purchase',
                'event_time' => now()->timestamp,
                'event_id' => $purchase['eventId'],
                'action_source' => 'website',
                'event_source_url' => url('/'),
                'user_data' => array_filter([
                    'ph' => [$this->hash($this->normalisePhone((string)$order->customer_phone))],
                    'em' => $this->isRealEmail($order->customer_email) ? [$this->hash(strtolower($order->customer_email))] : null,
                    'fn' => [$this->hash(strtolower((string)$order->customer_first_name))],
                    'ln' => [$this->hash(strtolower((string)$order->customer_last_name))],
                    'ct' => [$this->hash(strtolower((string)$order->billing_city))],
                    'country' => [$this->hash('eg')],
                    'client_ip_address' => request()->ip(),
                    'client_user_agent' => request()->userAgent(),
                ]),
                'custom_data' => [
                    'currency' => $purchase['currency'],
                    'value' => $purchase['value'],
                    'order_id' => $purchase['transactionId'],
                    'content_type' => 'product',
                    'contents' => array_map(fn ($i) => ['id' => $i['id'], 'quantity' => $i['quantity'], 'item_price' => $i['price']], $purchase['items']),
                ],
            ]],
        ];
    }


    public function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($digits, '0020')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = '20' . substr($digits, 1);
        }

        return $digits;
    }


    private function isRealEmail(?string $email): bool
    {
        return $email && !str_ends_with($email, '@no-email.invalid');
    }


    private function hash(string $value): string
    {
        return hash('sha256', trim($value));
    }
}
