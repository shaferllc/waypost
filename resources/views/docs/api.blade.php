<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('API documentation') }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div
            class="max-w-3xl mx-auto sm:px-6 lg:px-8 px-4 text-ink/90
                [&_h1]:text-2xl [&_h1]:font-bold [&_h1]:text-ink [&_h1]:mb-4
                [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-ink [&_h2]:mt-10 [&_h2]:mb-3 [&_h2]:pt-2 [&_h2]:border-t [&_h2]:border-cream-300 first:[&_h2]:mt-0 first:[&_h2]:pt-0 first:[&_h2]:border-0
                [&_p]:mb-3 [&_p]:leading-relaxed
                [&_a]:text-sage-dark [&_a]:underline [&_a]:hover:text-sage-deeper
                [&_ul]:list-disc [&_ul]:ps-5 [&_ul]:mb-4 [&_ul]:space-y-1
                [&_ol]:list-decimal [&_ol]:ps-5 [&_ol]:mb-4 [&_ol]:space-y-1
                [&_li]:leading-relaxed
                [&_code]:text-sm [&_code]:bg-cream-200 [&_code]:px-1 [&_code]:py-0.5 [&_code]:rounded [&_code]:text-ink
                [&_pre]:bg-ink [&_pre]:text-cream-100 [&_pre]:p-4 [&_pre]:rounded-xl [&_pre]:overflow-x-auto [&_pre]:text-sm [&_pre]:mb-4 [&_pre]:shadow-inner
                [&_pre_code]:bg-transparent [&_pre_code]:p-0 [&_pre_code]:text-inherit
                [&_table]:w-full [&_table]:text-sm [&_table]:mb-4 [&_table]:border-collapse
                [&_th]:border [&_th]:border-cream-300 [&_th]:bg-cream-100 [&_th]:px-3 [&_th]:py-2 [&_th]:text-start [&_th]:font-semibold
                [&_td]:border [&_td]:border-cream-300 [&_td]:px-3 [&_td]:py-2 [&_td]:align-top
                [&_hr]:border-cream-300 [&_hr]:my-8
                [&_strong]:font-semibold [&_strong]:text-ink"
        >
            {!! $html !!}
        </div>
    </div>
</x-app-layout>
