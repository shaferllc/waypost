<picture>
    <source srcset="{{ asset('images/waypost-mark.webp') }}" type="image/webp" />
    <img
        src="{{ asset('images/waypost.svg') }}"
        alt="{{ config('app.name') }}"
        {{ $attributes->merge(['class' => 'block h-9 w-auto max-h-11 object-contain']) }}
        width="783"
        height="831"
        decoding="async"
    />
</picture>
