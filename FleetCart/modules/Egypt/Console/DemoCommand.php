<?php

namespace Modules\Egypt\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Modules\Brand\Entities\Brand;
use Modules\Category\Entities\Category;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\ProductVariant;
use Modules\Variation\Entities\Variation;

/**
 * php artisan egypt:demo
 *
 * A small bilingual fashion catalogue (3 departments, 12 products, sizes as
 * variants with their own stock) to try the store before real data exists.
 * Safe to run twice: products are keyed by SKU. Do not run on a live store.
 */
class DemoCommand extends Command
{
    protected $signature = 'egypt:demo {--fresh : remove the demo products first}';

    protected $description = 'Create a demo fashion catalogue (bilingual, with size variants)';

    /** [sku, en, ar, department, price, special price, sizes?] */
    private const PRODUCTS = [
        ['W-DRS-001', 'Linen Summer Dress', 'فستان كتان صيفي', 'women', 1250, 990, true],
        ['W-ABY-002', 'Everyday Abaya', 'عباءة يومية', 'women', 1450, null, true],
        ['W-BLS-003', 'Satin Blouse', 'بلوزة ساتان', 'women', 780, null, true],
        ['W-HJB-004', 'Cotton Hijab Scarf', 'وشاح حجاب قطن', 'women', 240, 199, false],
        ['M-TSH-001', 'Cotton T-Shirt', 'تيشيرت قطن', 'men', 350, null, true],
        ['M-SHR-002', 'Oxford Shirt', 'قميص أكسفورد', 'men', 690, 590, true],
        ['M-JNS-003', 'Slim Fit Jeans', 'بنطلون جينز ضيق', 'men', 950, null, true],
        ['M-GLB-004', 'Classic Galabeya', 'جلابية كلاسيك', 'men', 880, null, true],
        ['K-TSH-001', 'Kids Printed T-Shirt', 'تيشيرت أطفال مطبوع', 'kids', 220, null, true],
        ['K-DRS-002', 'Girls Party Dress', 'فستان حفلات بناتي', 'kids', 640, 520, true],
        ['K-SET-003', 'Boys Summer Set', 'طقم صيفي ولادي', 'kids', 480, null, true],
        ['K-PJM-004', 'Kids Pyjama Set', 'بيجامة أطفال', 'kids', 360, null, true],
    ];

    private const SIZES = ['S', 'M', 'L', 'XL'];


    public function handle(): int
    {
        // slugs are built from the product name of the active language: keep them English
        app()->setLocale('en');

        if ($this->option('fresh')) {
            $this->removeDemo();
        }

        $brand = $this->brand();
        $departments = $this->departments();
        $size = $this->sizeVariation();

        foreach (self::PRODUCTS as [$sku, $en, $ar, $dept, $price, $special, $sizes]) {
            $this->product($sku, $en, $ar, $departments[$dept], $brand, $price, $special, $sizes ? $size : null);
        }

        Cache::flush();
        $this->info(count(self::PRODUCTS) . ' demo products ready (sizes S-XL with 5 pieces each).');

        return self::SUCCESS;
    }


    private function removeDemo(): void
    {
        $skus = array_column(self::PRODUCTS, 0);

        Product::withoutGlobalScopes()->whereIn('sku', $skus)->get()->each(function (Product $product) {
            $product->variants()->withoutGlobalScope('active')->forceDelete();
            $product->variations()->detach();
            $product->categories()->detach();
            $product->forceDelete();
        });

        $this->info('Demo products removed.');
    }


    private function brand(): Brand
    {
        return Brand::whereHas('translations', fn ($q) => $q->where('name', 'Nile Wear'))->first()
            ?? Brand::create(['slug' => 'nile-wear', 'is_active' => 1, 'en' => ['name' => 'Nile Wear'], 'ar' => ['name' => 'نايل وير']]);
    }


    private function departments(): array
    {
        $names = ['women' => ['Women', 'نساء'], 'men' => ['Men', 'رجال'], 'kids' => ['Kids', 'أطفال']];
        $result = [];

        foreach ($names as $key => [$en, $ar]) {
            $result[$key] = Category::whereHas('translations', fn ($q) => $q->where('name', $en))->first()
                ?? Category::create(['slug' => $key, 'is_active' => 1, 'is_searchable' => 1, 'position' => count($result), 'en' => ['name' => $en], 'ar' => ['name' => $ar]]);
        }

        return $result;
    }


    private function sizeVariation(): Variation
    {
        $variation = Variation::where('uid', 'demo-size')->first();

        if ($variation) {
            return $variation;
        }

        $variation = Variation::create(['uid' => 'demo-size', 'type' => 'text', 'is_global' => true, 'position' => 1, 'en' => ['name' => 'Size'], 'ar' => ['name' => 'المقاس']]);

        foreach (self::SIZES as $i => $label) {
            $variation->values()->create(['uid' => 'demo-size-' . strtolower($label), 'value' => $label, 'position' => $i + 1, 'en' => ['label' => $label], 'ar' => ['label' => $label]]);
        }

        return $variation;
    }


    private function product(string $sku, string $en, string $ar, Category $category, Brand $brand, int $price, ?int $special, ?Variation $size): void
    {
        if (Product::withoutGlobalScopes()->where('sku', $sku)->exists()) {
            return;
        }

        $product = Product::create([
            'sku' => $sku,
            'slug' => Str::slug($en),
            'brand_id' => $brand->id,
            'price' => $price,
            'special_price' => $special,
            'special_price_type' => $special ? 'fixed' : null,
            'manage_stock' => $size ? 0 : 1,
            'qty' => $size ? null : 20,
            'in_stock' => 1,
            'is_active' => 1,
            'new_from' => now()->subDays(3),
            'new_to' => now()->addDays(30),
            'en' => ['name' => $en, 'short_description' => $en, 'description' => "<p>{$en}</p>"],
            'ar' => ['name' => $ar, 'short_description' => $ar, 'description' => "<p>{$ar}</p>"],
        ]);

        $product->categories()->sync([$category->id]);

        if ($size) {
            $product->variations()->attach($size->id);

            foreach (self::SIZES as $i => $label) {
                $variant = new ProductVariant([
                    'uid' => 'demo-' . Str::lower($sku) . '-' . strtolower($label),
                    'uids' => 'demo-size-' . strtolower($label),
                    'name' => $label,
                    'price' => $price,
                    'special_price' => $special,
                    'special_price_type' => $special ? 'fixed' : null,
                    'manage_stock' => 1,
                    'qty' => 5,
                    'in_stock' => 1,
                    'is_default' => $i === 0,
                    'is_active' => 1,
                    'position' => $i + 1,
                ]);
                $variant->product_id = $product->id;
                $variant->save();
            }
        }
    }
}
