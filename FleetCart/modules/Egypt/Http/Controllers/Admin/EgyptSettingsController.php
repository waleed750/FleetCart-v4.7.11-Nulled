<?php

namespace Modules\Egypt\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\Egypt\Entities\FeatureToggleAudit;
use Modules\Egypt\Entities\Governorate;
use Modules\Egypt\Services\Features;
use Modules\Egypt\Services\Tracking;
use Modules\Setting\Entities\Setting;

class EgyptSettingsController extends Controller
{
    public function index()
    {
        return view('egypt::admin.index', [
            'governorates' => Governorate::orderBy('position')->get(),
            'features' => Features::all(),
            'audits' => FeatureToggleAudit::orderByDesc('id')->limit(50)->get(),
            'tracking' => app(Tracking::class),
            'readiness' => $this->readiness(),
            'canEditFeatures' => auth()->user()->hasAccess('admin.egypt.features'),
            'canEdit' => auth()->user()->hasAccess('admin.egypt.edit'),
        ]);
    }


    public function updateTracking(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'egypt_gtm_id' => ['nullable', 'regex:/^GTM-[A-Z0-9]{4,12}$/'],
            'egypt_ga4_id' => ['nullable', 'regex:/^G-[A-Z0-9]{6,14}$/'],
            'egypt_meta_pixel_id' => ['nullable', 'regex:/^\d{8,20}$/'],
            'egypt_tiktok_pixel_id' => ['nullable', 'regex:/^[A-Z0-9]{10,30}$/'],
            'egypt_meta_capi_token' => ['nullable', 'string', 'max:512'],
            'egypt_meta_test_event_code' => ['nullable', 'string', 'max:40'],
        ]);

        // The CAPI token is never shown again; an empty field keeps the stored one.
        if (empty($data['egypt_meta_capi_token'])) {
            unset($data['egypt_meta_capi_token']);
        }

        if ($request->boolean('clear_capi_token')) {
            $data['egypt_meta_capi_token'] = '';
        }

        Setting::setMany(array_map(fn ($value) => $value ?? '', $data));

        return back()->with('success', trans('egypt::admin.saved'));
    }


    public function updateShipping(Request $request): RedirectResponse
    {
        $request->validate([
            'egypt_default_shipping_cost' => ['required', 'numeric', 'min:0'],
            'rates' => ['required', 'array'],
            'rates.*.shipping_cost' => ['required', 'numeric', 'min:0', 'max:100000'],
            'rates.*.free_shipping_over' => ['nullable', 'numeric', 'min:0'],
            'rates.*.eta_days_min' => ['required', 'integer', 'min:0', 'max:60'],
            'rates.*.eta_days_max' => ['required', 'integer', 'min:0', 'max:60'],
        ]);

        Setting::set('egypt_default_shipping_cost', $request->input('egypt_default_shipping_cost'));

        foreach (Governorate::all() as $governorate) {
            $row = $request->input("rates.{$governorate->id}");

            if ($row === null) {
                continue;
            }

            $governorate->update([
                'shipping_cost' => $row['shipping_cost'],
                'free_shipping_over' => $row['free_shipping_over'] === null || $row['free_shipping_over'] === '' ? null : $row['free_shipping_over'],
                'eta_days_min' => $row['eta_days_min'],
                'eta_days_max' => max($row['eta_days_min'], $row['eta_days_max']),
                'is_active' => isset($row['is_active']),
            ]);
        }

        return back()->with('success', trans('egypt::admin.saved'));
    }


    public function updateFeatures(Request $request): RedirectResponse
    {
        $request->validate(['features' => ['array'], 'features.*' => [Rule::in(['1'])]]);

        $selected = array_keys($request->input('features', []));

        foreach (Features::keys() as $key) {
            Features::set($key, in_array($key, $selected, true), auth()->user(), $request->ip());
        }

        return back()->with('success', trans('egypt::admin.saved'));
    }


    private function readiness(): array
    {
        $supportedLocales = array_keys(supported_locales());

        return [
            'currency' => setting('default_currency') === 'EGP',
            'country' => setting('supported_countries') === ['EG'],
            'languages' => in_array('ar', $supportedLocales, true),
            'cod' => (bool)setting('cod_enabled'),
            'shipping' => Governorate::where('is_active', true)->count() > 0,
            'tracking' => app(Tracking::class)->id('gtm_id') !== '' || app(Tracking::class)->id('meta_pixel_id') !== '' || app(Tracking::class)->id('ga4_id') !== '',
        ];
    }
}
