<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center rounded-lg border border-cream-300 bg-cream-50 px-4 py-2.5 text-sm font-semibold text-ink shadow-sm hover:bg-cream-100 focus:outline-none focus:ring-2 focus:ring-sage focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
