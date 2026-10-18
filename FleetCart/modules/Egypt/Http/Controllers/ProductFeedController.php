<?php

namespace Modules\Egypt\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\Product\Entities\Product;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * RSS 2.0 + Google namespace product feed. The same file feeds Google
 * Merchant Center and Meta Commerce Manager (Facebook / Instagram shops).
 */
class ProductFeedController extends Controller
{
    public function index(): StreamedResponse
    {
        $locale = request('lang') === 'en' ? 'en' : (app()->getLocale() === 'en' ? 'en' : 'ar');
        app()->setLocale($locale);

        return response()->stream(function () use ($locale) {
            $currency = setting('default_currency');

            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>';
            echo '<title>' . e(setting('store_name')) . '</title>';
            echo '<link>' . e(url('/')) . '</link>';
            echo '<description>' . e(setting('store_name')) . '</description>';

            Product::with(['categories', 'brand', 'files'])
                ->where('is_active', 1)
                ->orderBy('id')
                ->chunkById(200, function ($products) use ($currency) {
                    foreach ($products as $product) {
                        echo $this->item($product, $currency);
                    }
                });

            echo '</channel></rss>';
        }, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }


    private function item(Product $product, string $currency): string
    {
        $price = number_format((float)$product->price->amount(), 2, '.', '');
        $selling = number_format((float)$product->selling_price->amount(), 2, '.', '');
        $image = $product->base_image->path ?? null;
        $category = $product->categories->first();

        $xml = '<item>';
        $xml .= '<g:id>' . e($product->sku ?: $product->id) . '</g:id>';
        $xml .= '<title>' . e($product->name) . '</title>';
        $xml .= '<description>' . e(strip_tags($product->short_description ?: $product->description)) . '</description>';
        $xml .= '<link>' . e($product->url()) . '</link>';

        if ($image) {
            $xml .= '<g:image_link>' . e($image) . '</g:image_link>';
        }

        $xml .= '<g:availability>' . ($product->isInStock() ? 'in stock' : 'out of stock') . '</g:availability>';
        $xml .= '<g:condition>new</g:condition>';
        $xml .= "<g:price>{$price} {$currency}</g:price>";

        if ($selling < $price) {
            $xml .= "<g:sale_price>{$selling} {$currency}</g:sale_price>";
        }

        if ($product->brand) {
            $xml .= '<g:brand>' . e($product->brand->name) . '</g:brand>';
        }

        if ($category) {
            $xml .= '<g:product_type>' . e($category->name) . '</g:product_type>';
        }

        return $xml . '</item>';
    }
}
