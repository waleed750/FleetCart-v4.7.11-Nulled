@php($seo = app(\Modules\Egypt\Services\Seo::class))
{{-- Included from the storefront layout <head> --}}
<link rel="canonical" href="{{ $seo->canonical() }}">
@foreach ($seo->alternates() as $hreflang => $href)
    <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
@endforeach

<script type="application/ld+json">{!! json_encode($seo->organization($logo ?? null), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@if (isset($egyptProduct) && $egyptProduct)
    <script type="application/ld+json">{!! json_encode($seo->breadcrumb($egyptProduct), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif

<style>
    /* RTL: off-canvas menus extend the page to the left, which made it scrollable and the first paint blank */
    html { overflow-x: clip; }
</style>

@if ($simpleCheckout)
    <style>
        /* Egypt checkout: phone + governorate based, no e-mail / ZIP / country fields */
        .col-md-9:has(> .form-group > #customer-email),
        .col-md-9:has(> .form-group > #billing-zip),
        .col-md-9:has(> .form-group > #billing-country),
        .col-md-9:has(> .form-group > #shipping-zip),
        .col-md-9:has(> .form-group > #shipping-country) { display: none !important; }
    </style>
@endif

@if (count($disabledPaths))
    <style>
        @foreach ($disabledPaths as $pattern)
            @unless (str_contains(rtrim($pattern, '*'), '*'))
                a[href*="/{{ rtrim($pattern, '*') }}"] { display: none !important; }
            @endunless
        @endforeach
    </style>
@endif

@if ($tracking->enabled())
    @include('egypt::partials.tracking')
@endif
