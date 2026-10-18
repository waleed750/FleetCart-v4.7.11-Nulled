<?php

namespace Modules\Egypt\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Checkout\Events\OrderPlaced;
use Modules\Egypt\Console\DemoCommand;
use Modules\Egypt\Console\InstallCommand;
use Modules\Egypt\Http\Controllers\SitemapController;
use Modules\Egypt\Http\Middleware\EnforceFeatures;
use Modules\Egypt\Http\Middleware\SyncGovernorateShipping;
use Modules\Egypt\Http\Middleware\TranslationFallback;
use Modules\Egypt\Services\Features;
use Modules\Egypt\Services\GovernorateMethod;
use Modules\Egypt\Services\GovernorateShipping;
use Modules\Egypt\Services\MetaConversionsApi;
use Modules\Egypt\Services\Tracking;
use Modules\Payment\Facades\Gateway;
use Modules\Shipping\Facades\ShippingMethod;
use Modules\Support\Manager;
use ReflectionProperty;

class EgyptServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Tracking::class);
        $this->app->singleton(GovernorateShipping::class);

        $this->commands([InstallCommand::class, DemoCommand::class]);
    }


    public function boot(Router $router): void
    {
        $router->pushMiddlewareToGroup('web', TranslationFallback::class);
        $router->pushMiddlewareToGroup('web', EnforceFeatures::class);
        $router->pushMiddlewareToGroup('web', SyncGovernorateShipping::class);

        Event::listen(OrderPlaced::class, [MetaConversionsApi::class, 'handle']);

        $this->shareLayoutData();

        // Outside the localised route group: the sitemap lives at /sitemap.xml.
        $router->middleware('web')->get('sitemap.xml', [SitemapController::class, 'index'])->name('egypt.sitemap');

        // Gateways and shipping methods are registered by their own modules'
        // boot(); adjust them once everything has booted.
        $this->app->booted(function () {
            $this->registerGovernorateShipping();
            $this->enforceCodOnly();
        });
    }


    private function registerGovernorateShipping(): void
    {
        if (!setting('egypt_governorate_shipping_enabled', true)) {
            return;
        }

        $shipping = $this->app->make(GovernorateShipping::class);

        ShippingMethod::register(GovernorateShipping::METHOD, fn () => new GovernorateMethod($shipping));
    }


    /**
     * With the cod_only switch on, only cash on delivery can be chosen, no
     * matter which gateways are enabled in the payment settings.
     */
    private function enforceCodOnly(): void
    {
        if (!Features::enabled('cod_only')) {
            return;
        }

        $drivers = new ReflectionProperty(Manager::class, 'drivers');
        $current = $drivers->getValue($gateway = Gateway::getFacadeRoot());

        $drivers->setValue($gateway, array_intersect_key($current, ['cod' => true]));
    }


    private function shareLayoutData(): void
    {
        View::composer('egypt::partials.*', function ($view) {
            $tracking = $this->app->make(Tracking::class);
            $route = request()->route()?->getName();

            $view->with([
                'tracking' => $tracking,
                'trackingEvents' => $tracking->enabled() ? $tracking->pageEvents($route, $this->app['view']->shared('egyptProduct')) : [],
                'disabledPaths' => Features::disabledPaths(),
                'simpleCheckout' => Features::enabled('simple_checkout'),
            ]);
        });

        View::composer('storefront::public.products.show', function ($view) {
            $this->app['view']->share('egyptProduct', $view->getData()['product'] ?? null);
        });
    }
}
