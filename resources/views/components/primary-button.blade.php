<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-lg bg-sage px-4 py-2.5 text-sm font-semibold text-white shadow hover:bg-sage-dark focus:outline-none focus:ring-2 focus:ring-sage focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
