@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-cream-300 focus:border-sage focus:ring-sage rounded-xl shadow-sm transition placeholder:text-ink/35']) }}>
