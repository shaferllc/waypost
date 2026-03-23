@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-sage text-start text-base font-medium text-sage-dark bg-sage-light/10 focus:outline-none focus:text-sage-deeper focus:bg-sage-light/15 focus:border-sage-dark transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-ink/60 hover:text-ink hover:bg-cream-100 hover:border-cream-300 focus:outline-none focus:text-ink focus:bg-cream-100 focus:border-cream-300 transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
