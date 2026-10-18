<?php

namespace Modules\Egypt\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Modules\Blog\Entities\BlogPost;
use Modules\Brand\Entities\Brand;
use Modules\Category\Entities\Category;
use Modules\Egypt\Services\Features;
use Modules\Page\Entities\Page;
use Modules\Product\Entities\Product;

/**
 * Dynamic bilingual sitemap with hreflang alternates for every URL.
 *
 * The stock generator (Admin > Sitemaps) writes a static one-language
 * sitemap.xml by hand; a static file would shadow this route, so do not run it.
 */
class SitemapController extends Controller
{
    private const TTL = 3600;


    public function index(): Response
    {
        $xml = Cache::remember('egypt.sitemap', self::TTL, fn () => $this->build());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }


    public function build(): string
    {
        $locales = array_keys(supported_locales());
        $default = setting('default_locale');
        $entries = [];

        $add = function (string $url) use (&$entries, $locales, $default) {
            $path = $this->path($url, $locales);
            $entries[] = ['locs' => collect($locales)->mapWithKeys(fn ($l) => [$l => url("/{$l}{$path}")])->all(), 'default' => url("/{$default}{$path}")];
        };

        $add(url('/'));

        Product::where('is_active', 1)->chunkById(500, fn ($rows) => $rows->each(fn ($m) => $add($m->url())));
        Category::where('is_active', 1)->get()->each(fn ($m) => $add($m->url()));
        Brand::where('is_active', 1)->get()->each(fn ($m) => $add($m->url()));
        Page::where('is_active', 1)->get()->each(fn ($m) => $add($m->url()));

        if (Features::enabled('blog')) {
            BlogPost::all()->each(fn ($m) => $add($m->url()));
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($entries as $entry) {
            foreach ($entry['locs'] as $locale => $loc) {
                $xml .= '<url><loc>' . e($loc) . '</loc>';

                foreach ($entry['locs'] as $altLocale => $altLoc) {
                    $xml .= '<xhtml:link rel="alternate" hreflang="' . $altLocale . '" href="' . e($altLoc) . '"/>';
                }

                $xml .= '<xhtml:link rel="alternate" hreflang="x-default" href="' . e($entry['default']) . '"/></url>' . "\n";
            }
        }

        return $xml . '</urlset>';
    }


    /**
     * Path of a storefront URL without host and without its language prefix.
     */
    private function path(string $url, array $locales): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $segments = explode('/', trim($path, '/'));

        if ($segments !== [''] && in_array($segments[0], $locales, true)) {
            array_shift($segments);
        }

        return $segments === [] || $segments === [''] ? '' : '/' . implode('/', $segments);
    }
}
