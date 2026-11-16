@props(['tone' => 'light'])

<section {{ $attributes->class([
    'rounded-panel border p-6 sm:p-8 lg:px-10 lg:py-9',
    'border-line bg-white' => $tone === 'light',
    'border-ink bg-ink text-white' => $tone === 'ink',
]) }}>
    {{ $slot }}
</section>
