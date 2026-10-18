@php
    $gtm = $tracking->id('gtm_id');
    $ga4 = $tracking->id('ga4_id');
    $meta = $tracking->id('meta_pixel_id');
    $tiktok = $tracking->id('tiktok_pixel_id');
    $direct = $tracking->loadsPixelsDirectly();
    $currency = currency();
@endphp
<script>
    window.dataLayer = window.dataLayer || [];
</script>

@if ($gtm !== '')
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',{!! json_encode($gtm) !!});</script>
@endif

@if ($direct && $ga4 !== '')
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 }}"></script>
    <script>
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', {!! json_encode($ga4) !!});
    </script>
@endif

@if ($direct && $meta !== '')
    <script>
        !function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', {!! json_encode($meta) !!});
        fbq('track', 'PageView');
    </script>
@endif

@if ($direct && $tiktok !== '')
    <script>
        !function (w, d, t) {w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load({!! json_encode($tiktok) !!});ttq.page();}(window, document, 'ttq');
    </script>
@endif

<script>
    /**
     * One call feeds every channel: GA4 dataLayer (ecommerce), Meta Pixel, TikTok Pixel.
     * Names follow each platform: ViewContent / AddToCart / InitiateCheckout / Purchase.
     */
    window.egyptTrack = function (event) {
        var currency = event.currency || {!! json_encode($currency) !!};
        var items = (event.items || []).map(function (i) {
            return {item_id: i.id, item_name: i.name, price: i.price, quantity: i.quantity};
        });
        var ga4Names = {ViewContent: 'view_item', AddToCart: 'add_to_cart', InitiateCheckout: 'begin_checkout', Purchase: 'purchase'};
        var ecommerce = {currency: currency, value: event.value, items: items};

        if (event.transactionId) {
            ecommerce.transaction_id = event.transactionId;
            ecommerce.shipping = event.shipping;
        }

        window.dataLayer.push({ecommerce: null});
        window.dataLayer.push({event: ga4Names[event.name], ecommerce: ecommerce});

        if (typeof window.fbq === 'function') {
            window.fbq('track', event.name, {
                content_type: 'product',
                content_ids: (event.items || []).map(function (i) { return i.id; }),
                contents: (event.items || []).map(function (i) { return {id: i.id, quantity: i.quantity, item_price: i.price}; }),
                value: event.value,
                currency: currency
            }, event.eventId ? {eventID: event.eventId} : undefined);
        }

        if (window.ttq && typeof window.ttq.track === 'function') {
            window.ttq.track(event.name === 'Purchase' ? 'CompletePayment' : event.name, {
                content_type: 'product',
                contents: (event.items || []).map(function (i) { return {content_id: i.id, content_name: i.name, quantity: i.quantity, price: i.price}; }),
                value: event.value,
                currency: currency
            }, event.eventId ? {event_id: event.eventId} : undefined);
        }
    };

    @foreach ($trackingEvents as $trackingEvent)
        window.egyptTrack({!! json_encode($trackingEvent, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!});
    @endforeach

    // AddToCart: watch the storefront's own cart requests instead of patching its code.
    (function () {
        function install() {
            if (!window.axios || window.__egyptCartHook) { return; }
            window.__egyptCartHook = true;
            window.axios.interceptors.response.use(function (response) {
                try {
                    var cfg = response.config || {};
                    if ((cfg.method || '').toLowerCase() === 'post' && /\/cart\/items$/.test(cfg.url || '')) {
                        var body = typeof cfg.data === 'string' ? JSON.parse(cfg.data) : (cfg.data || {});
                        var items = response.data && response.data.items ? Object.values(response.data.items) : [];
                        var line = items.find(function (i) { return String(i.product.id) === String(body.product_id); });
                        if (line) {
                            var price = line.product.selling_price.inCurrentCurrency.amount;
                            var qty = Number(body.qty || 1);
                            window.egyptTrack({
                                name: 'AddToCart',
                                value: Math.round(price * qty * 100) / 100,
                                items: [{id: String(line.product.sku || line.product.id), name: line.product.name || line.product.slug, price: price, quantity: qty}]
                            });
                        }
                    }
                } catch (e) { /* tracking must never break the cart */ }
                return response;
            });
        }
        install();
        document.addEventListener('DOMContentLoaded', install);
        window.addEventListener('load', install);
    })();
</script>
