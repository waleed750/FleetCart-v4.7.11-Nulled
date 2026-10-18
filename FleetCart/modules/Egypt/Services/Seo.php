<?php

namespace Modules\Egypt\Services;

use Illuminate\Support\Collection;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;
use Modules\Product\Entities\Product;

/**
 * Head markup the base storefront does not emit: canonical, hreflang,
 * Organization and BreadcrumbList structured data.
 */
class Seo
{
    public function canonical(): string
    {
        return url()->current();
    }


    /**
     * hreflang alternates for every supported language plus x-default.
     */
    public function alternates(): array
    {
        $locales = array_keys(supported_locales());

        if (count($locales) < 2) {
            return [];
        }

        $alternates = [];

        foreach ($locales as $locale) {
            $alternates[$locale] = LaravelLocalization::getLocalizedURL($locale, request()->url(), [], true);
        }

        $alternates['x-default'] = LaravelLocalization::getLocalizedURL(setting('default_locale'), request()->url(), [], true);

        return $alternates;
    }


    public function organization(?string $logo): array
    {
        $sameAs = collect(['facebook', 'twitter', 'instagram', 'youtube'])
            ->map(fn ($network) => setting("storefront_{$network}_link"))
            ->filter()
            ->values()
            ->all();

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => setting('store_name'),
            'url' => url('/'),
            'logo' => $logo,
            'telephone' => setting('store_phone'),
            'email' => setting('store_email'),
            'sameAs' => $sameAs ?: null,
        ]);
    }


    public function breadcrumb(Product $product): array
    {
        $items = [['name' => trans('storefront::layouts.home'), 'url' => url('/')]];

        foreach ($this->firstCategoryPath($product->categories->nest()) as $category) {
            $items[] = ['name' => $category->name, 'url' => $category->url()];
        }

        $items[] = ['name' => $product->name, 'url' => $product->url()];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)->values()->map(fn ($item, $index) => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ])->all(),
        ];
    }


    /**
     * First root-to-leaf chain of the (nested) categories the product is in.
     */
    private function firstCategoryPath(Collection $categories): array
    {
        $path = [];
        $level = $categories;

        while ($level->isNotEmpty()) {
            $category = $level->first();
            $path[] = $category;
            $level = $category->items ?? collect();
        }

        return $path;
    }
}
