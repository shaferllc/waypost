@extends(config('fleet_idp.account.layout', 'layouts.guest'))

@section('title', trans('fleet-idp::account.reset_title').' — '.config('app.name'))

@section('content')
    <div class="mb-6">
        <h1 class="text-2xl font-bold tracking-tight text-ink">{{ trans('fleet-idp::account.reset_title') }}</h1>
        <p class="mt-1 text-sm text-ink/70">{{ trans('fleet-idp::account.reset_intro') }}</p>
    </div>

    <form method="post" action="{{ route(config('fleet_idp.account.route_names.reset_store', 'password.update')) }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <div>
            <x-input-label for="email" :value="trans('fleet-idp::account.email')" />
            <x-text-input
                id="email"
                class="mt-1 block w-full"
                type="email"
                name="email"
                value="{{ old('email', $email) }}"
                required
                autofocus
                autocomplete="username"
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="trans('fleet-idp::account.password')" />
            <x-text-input
                id="password"
                class="mt-1 block w-full"
                type="password"
                name="password"
                required
                autocomplete="new-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" :value="trans('fleet-idp::account.confirm_password')" />
            <x-text-input
                id="password_confirmation"
                class="mt-1 block w-full"
                type="password"
                name="password_confirmation"
                required
                autocomplete="new-password"
            />
        </div>

        <div class="flex justify-end pt-2">
            <x-primary-button class="w-full justify-center sm:w-auto">
                {{ trans('fleet-idp::account.reset_submit') }}
            </x-primary-button>
        </div>
    </form>

    @if (Route::has('login'))
        <p class="mt-8 text-center text-sm text-ink/70">
            <a href="{{ route('login') }}" wire:navigate class="font-semibold text-sage-dark hover:text-sage-deeper">{{ trans('fleet-idp::account.back_login') }}</a>
        </p>
    @endif
@endsection
