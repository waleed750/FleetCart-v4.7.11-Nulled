@extends('admin::layout')

@component('admin::components.page.header')
    @slot('title', trans('egypt::admin.title'))

    <li class="active">{{ trans('egypt::admin.title') }}</li>
@endcomponent

@section('content')
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="list-unstyled">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Readiness --}}
    <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">{{ trans('egypt::admin.readiness.title') }}</h3></div>
        <div class="box-body">
            <ul class="list-unstyled">
                @foreach ($readiness as $key => $ok)
                    <li>
                        <i class="fa {{ $ok ? 'fa-check-circle text-success' : 'fa-times-circle text-danger' }}"></i>
                        {{ trans("egypt::admin.readiness.{$key}") }}
                    </li>
                @endforeach
            </ul>
            @if (in_array(false, $readiness, true))
                <p class="text-muted"><code>{{ trans('egypt::admin.readiness.fix') }}</code></p>
            @endif
        </div>
    </div>

    {{-- Tracking --}}
    <form method="POST" action="{{ route('admin.egypt.tracking.update') }}">
        @csrf
        @method('PUT')
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">{{ trans('egypt::admin.tracking.title') }}</h3></div>
            <div class="box-body">
                <p class="text-muted">{{ trans('egypt::admin.tracking.help') }}</p>

                @foreach (['gtm_id', 'ga4_id', 'meta_pixel_id', 'tiktok_pixel_id', 'meta_test_event_code'] as $field)
                    <div class="form-group">
                        <label for="egypt_{{ $field }}">{{ trans("egypt::admin.tracking.{$field}") }}</label>
                        <input type="text" class="form-control" id="egypt_{{ $field }}" name="egypt_{{ $field }}"
                               value="{{ old("egypt_{$field}", $tracking->id($field)) }}" autocomplete="off" @disabled(!$canEdit)>
                    </div>
                @endforeach

                <div class="form-group">
                    <label for="egypt_meta_capi_token">{{ trans('egypt::admin.tracking.meta_capi_token') }}</label>
                    <input type="password" class="form-control" id="egypt_meta_capi_token" name="egypt_meta_capi_token"
                           value="" placeholder="{{ $tracking->id('meta_capi_token') !== '' ? '••••••••••' : '' }}" autocomplete="new-password" @disabled(!$canEdit)>
                    <span class="help-block">{{ trans('egypt::admin.tracking.meta_capi_token_help') }}</span>
                    @if ($tracking->id('meta_capi_token') !== '')
                        <label class="checkbox-inline"><input type="checkbox" name="clear_capi_token" value="1"> {{ trans('egypt::admin.tracking.clear_capi_token') }}</label>
                    @endif
                </div>

                <p>{{ trans('egypt::admin.tracking.feed') }}:
                    <a href="{{ route('egypt.feeds.products', ['lang' => 'ar']) }}" target="_blank" rel="noopener">AR</a> ·
                    <a href="{{ route('egypt.feeds.products', ['lang' => 'en']) }}" target="_blank" rel="noopener">EN</a>
                </p>
            </div>
            @if ($canEdit)
                <div class="box-footer"><button type="submit" class="btn btn-primary">{{ trans('egypt::admin.tracking.save') }}</button></div>
            @endif
        </div>
    </form>

    {{-- Shipping --}}
    <form method="POST" action="{{ route('admin.egypt.shipping.update') }}">
        @csrf
        @method('PUT')
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">{{ trans('egypt::admin.shipping.title') }}</h3></div>
            <div class="box-body">
                <div class="form-group" style="max-width: 320px">
                    <label for="egypt_default_shipping_cost">{{ trans('egypt::admin.shipping.default_cost') }}</label>
                    <input type="number" step="0.01" min="0" class="form-control" id="egypt_default_shipping_cost" name="egypt_default_shipping_cost"
                           value="{{ old('egypt_default_shipping_cost', setting('egypt_default_shipping_cost', 60)) }}" @disabled(!$canEdit)>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-condensed">
                        <thead>
                            <tr>
                                <th>{{ trans('egypt::admin.shipping.governorate') }}</th>
                                <th>{{ trans('egypt::admin.shipping.active') }}</th>
                                <th>{{ trans('egypt::admin.shipping.cost') }}</th>
                                <th>{{ trans('egypt::admin.shipping.free_over') }}</th>
                                <th>{{ trans('egypt::admin.shipping.eta_min') }}</th>
                                <th>{{ trans('egypt::admin.shipping.eta_max') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($governorates as $g)
                                <tr>
                                    <td>{{ $g->name_en }} · {{ $g->name_ar }}</td>
                                    <td><input type="checkbox" name="rates[{{ $g->id }}][is_active]" value="1" @checked($g->is_active) @disabled(!$canEdit)></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control input-sm" name="rates[{{ $g->id }}][shipping_cost]" value="{{ $g->shipping_cost }}" @disabled(!$canEdit)></td>
                                    <td><input type="number" step="0.01" min="0" class="form-control input-sm" name="rates[{{ $g->id }}][free_shipping_over]" value="{{ $g->free_shipping_over }}" @disabled(!$canEdit)></td>
                                    <td><input type="number" min="0" max="60" class="form-control input-sm" name="rates[{{ $g->id }}][eta_days_min]" value="{{ $g->eta_days_min }}" @disabled(!$canEdit)></td>
                                    <td><input type="number" min="0" max="60" class="form-control input-sm" name="rates[{{ $g->id }}][eta_days_max]" value="{{ $g->eta_days_max }}" @disabled(!$canEdit)></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($canEdit)
                <div class="box-footer"><button type="submit" class="btn btn-primary">{{ trans('egypt::admin.shipping.save') }}</button></div>
            @endif
        </div>
    </form>

    {{-- Feature switches --}}
    <form method="POST" action="{{ route('admin.egypt.features.update') }}">
        @csrf
        @method('PUT')
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">{{ trans('egypt::admin.features.title') }}</h3></div>
            <div class="box-body">
                <p class="text-muted">{{ trans('egypt::admin.features.help') }}</p>
                @unless ($canEditFeatures)
                    <p class="text-warning"><i class="fa fa-lock"></i> {{ trans('egypt::admin.features.locked') }}</p>
                @endunless

                @foreach ($features as $key => $enabled)
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="features[{{ $key }}]" value="1" @checked($enabled) @disabled(!$canEditFeatures)>
                            {{ trans("egypt::admin.features.labels.{$key}") }}
                        </label>
                    </div>
                @endforeach

                <h4>{{ trans('egypt::admin.features.audit') }}</h4>
                @if ($audits->isEmpty())
                    <p class="text-muted">{{ trans('egypt::admin.features.none') }}</p>
                @else
                    <table class="table table-condensed">
                        <thead>
                            <tr>
                                <th>{{ trans('egypt::admin.features.when') }}</th>
                                <th>{{ trans('egypt::admin.features.who') }}</th>
                                <th>{{ trans('egypt::admin.features.feature') }}</th>
                                <th>{{ trans('egypt::admin.features.change') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($audits as $audit)
                                <tr>
                                    <td>{{ $audit->created_at }}</td>
                                    <td>{{ $audit->user_email ?? '—' }} <small class="text-muted">{{ $audit->ip }}</small></td>
                                    <td>{{ trans("egypt::admin.features.labels.{$audit->feature_key}") }}</td>
                                    <td>{{ $audit->old_value ? trans('egypt::admin.features.on') : trans('egypt::admin.features.off') }} → {{ $audit->new_value ? trans('egypt::admin.features.on') : trans('egypt::admin.features.off') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            @if ($canEditFeatures)
                <div class="box-footer"><button type="submit" class="btn btn-primary">{{ trans('egypt::admin.features.save') }}</button></div>
            @endif
        </div>
    </form>
@endsection
