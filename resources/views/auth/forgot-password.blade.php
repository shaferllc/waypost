{{--
  Forgot password — states from LocalForgotPasswordController:
  fleet_idp_pending_fleet_reset, fleet_idp_fleet_reset_confirm, status, errors.
--}}
@extends(config('fleet_idp.account.layout', 'layouts.guest'))

@php($pending = session('fleet_idp_pending_fleet_reset'))
@php($fleetSendConfirm = session('fleet_idp_fleet_reset_confirm'))
@php($showFleetFallback = is_array($pending) && ! empty($pending['url']) && ! empty($pending['email']) && (string) ($pending['source'] ?? '') === 'fleet_only')
@php($showFleetSendConfirm = is_array($fleetSendConfirm) && ! empty($fleetSendConfirm['email']) && in_array((string) ($fleetSendConfirm['source'] ?? ''), ['linked', 'fleet_only'], true))

@section('title', trans('fleet-idp::account.forgot_title').' — '.config('app.name'))

@section('content')
    @if ($showFleetFallback)
        <div class="space-y-4" role="region" aria-labelledby="fleet-idp-forgot-confirm-title">
            <div>
                <h1 id="fleet-idp-forgot-confirm-title" class="text-2xl font-bold tracking-tight text-ink">
                    {{ trans('fleet-idp::account.fleet_confirm_title_fleet_only') }}
                </h1>
                <p class="mt-2 text-sm text-ink/80" role="status">
                    {{ trans('fleet-idp::account.fleet_confirm_fleet_only_detail', ['email' => $pending['email']]) }}
                </p>
                @php($pe = $pending['provision_error'] ?? null)
                @if (is_string($pe) && $pe !== '')
                    <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50/80 p-3 text-sm text-ink/90" role="note">
                        <p class="font-semibold text-ink">{{ trans('fleet-idp::account.fleet_fallback_provision_hint_title') }}</p>
                        <p class="mt-1">
                            @switch($pe)
                                @case('missing_provisioning_token')
                                    {{ trans('fleet-idp::account.fleet_fallback_provision_missing_token') }}
                                    @break
                                @case('missing_idp_url')
                                    {{ trans('fleet-idp::account.fleet_fallback_provision_missing_url') }}
                                    @break
                                @case('unauthorized')
                                    {{ trans('fleet-idp::account.fleet_fallback_provision_unauthorized') }}
                                    @break
                                @case('service_unavailable')
                                    {{ trans('fleet-idp::account.fleet_fallback_provision_unavailable') }}
                                    @break
                                @case('exception')
                                    <span>{{ trans('fleet-idp::account.fleet_fallback_provision_generic') }}</span>
                                    <span class="mt-1 block text-xs text-ink/70">{{ trans('fleet-idp::account.fleet_fallback_provision_ssl') }}</span>
                                    @break
                                @default
                                    @if (is_numeric($pending['provision_http_status'] ?? null))
                                        {{ trans('fleet-idp::account.fleet_fallback_provision_http', ['status' => (string) $pending['provision_http_status']]) }}
                                    @else
                                        {{ trans('fleet-idp::account.fleet_fallback_provision_generic') }}
                                    @endif
                            @endswitch
                        </p>
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-3 pt-1 sm:flex-row sm:flex-wrap sm:items-center">
                <a
                    href="{{ $pending['url'] }}"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-sage px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-sage-dark focus:outline-none focus:ring-2 focus:ring-sage focus:ring-offset-2 sm:w-auto"
                >
                    {{ trans('fleet-idp::account.fleet_continue_reset') }}
                </a>
                <a
                    href="{{ route(config('fleet_idp.account.route_names.forgot_request', 'password.request'), ['restart' => 1]) }}"
                    class="text-center text-sm font-medium text-sage-dark hover:text-sage-deeper sm:w-auto"
                >
                    {{ trans('fleet-idp::account.fleet_try_different_email') }}
                </a>
            </div>

            @if (Route::has('login'))
                <p class="text-center text-sm text-ink/70">
                    <a href="{{ route('login') }}" wire:navigate class="font-semibold text-sage-dark hover:text-sage-deeper">{{ trans('fleet-idp::account.back_login') }}</a>
                </p>
            @endif
        </div>
    @elseif ($showFleetSendConfirm)
        @php($isLinked = ($fleetSendConfirm['source'] ?? '') === 'linked')
        @php($isLikelyDomain = ($fleetSendConfirm['prompt'] ?? 'standard') === 'likely_domain')

        <div class="space-y-4" role="region" aria-labelledby="fleet-idp-fleet-send-confirm-title">
            <div>
                <p class="text-sm font-semibold text-sage-dark" role="status">
                    {{ $isLikelyDomain
                        ? trans('fleet-idp::account.fleet_reset_confirm_likely_notice')
                        : trans('fleet-idp::account.fleet_reset_confirm_address_notice') }}
                </p>
                <h1 id="fleet-idp-fleet-send-confirm-title" class="mt-2 text-2xl font-bold tracking-tight text-ink">
                    {{ $isLikelyDomain
                        ? trans('fleet-idp::account.fleet_reset_confirm_likely_title')
                        : trans('fleet-idp::account.fleet_reset_confirm_title') }}
                </h1>
                <p class="mt-2 text-sm text-ink/80">
                    @if ($isLikelyDomain)
                        {{ $isLinked
                            ? trans('fleet-idp::account.fleet_reset_confirm_likely_detail_linked', ['email' => $fleetSendConfirm['email']])
                            : trans('fleet-idp::account.fleet_reset_confirm_likely_detail_fleet_only', ['email' => $fleetSendConfirm['email']]) }}
                    @else
                        {{ $isLinked
                            ? trans('fleet-idp::account.fleet_reset_confirm_linked_detail', ['email' => $fleetSendConfirm['email']])
                            : trans('fleet-idp::account.fleet_reset_confirm_fleet_only_detail', ['email' => $fleetSendConfirm['email']]) }}
                    @endif
                </p>
            </div>

            <form
                method="post"
                action="{{ route(config('fleet_idp.account.route_names.forgot_fleet_send', 'password.email.fleet')) }}"
                class="flex flex-col gap-3 pt-1 sm:flex-row sm:flex-wrap sm:items-center"
            >
                @csrf
                <x-primary-button type="submit" class="w-full justify-center sm:w-auto">
                    {{ trans('fleet-idp::account.fleet_reset_confirm_send') }}
                </x-primary-button>
                <a
                    href="{{ route(config('fleet_idp.account.route_names.forgot_request', 'password.request'), ['restart' => 1]) }}"
                    class="text-center text-sm font-medium text-sage-dark hover:text-sage-deeper sm:w-auto"
                >
                    {{ trans('fleet-idp::account.fleet_try_different_email') }}
                </a>
            </form>

            @if (Route::has('login'))
                <p class="text-center text-sm text-ink/70">
                    <a href="{{ route('login') }}" wire:navigate class="font-semibold text-sage-dark hover:text-sage-deeper">{{ trans('fleet-idp::account.back_login') }}</a>
                </p>
            @endif
        </div>
    @else
        <div class="mb-6">
            <h1 class="text-2xl font-bold tracking-tight text-ink">{{ trans('fleet-idp::account.forgot_title') }}</h1>
            @unless (session('status'))
                <p class="mt-1 text-sm text-ink/70">{{ trans('fleet-idp::account.forgot_intro') }}</p>
            @endunless
        </div>

        <x-auth-session-status class="mb-4" :status="session('status')" />

        <form method="post" action="{{ route(config('fleet_idp.account.route_names.forgot_store', 'password.email')) }}" class="space-y-4">
            @csrf
            <div>
                <x-input-label for="email" :value="trans('fleet-idp::account.email')" />
                <x-text-input
                    id="email"
                    class="mt-1 block w-full"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>

            <div class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-end">
                @if (Route::has('login'))
                    <a
                        href="{{ route('login') }}"
                        wire:navigate
                        class="text-center text-sm font-medium text-sage-dark hover:text-sage-deeper sm:me-auto sm:text-start"
                    >
                        {{ trans('fleet-idp::account.back_login') }}
                    </a>
                @endif

                <x-primary-button class="w-full justify-center sm:w-auto">
                    {{ trans('fleet-idp::account.send_link') }}
                </x-primary-button>
            </div>
        </form>
    @endif
@endsection
