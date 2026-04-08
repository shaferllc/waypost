<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-xl bg-sage px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-sage/25 hover:bg-sage-dark focus:outline-none focus:ring-2 focus:ring-sage focus:ring-offset-2 transition ease-in-out duration-150 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
