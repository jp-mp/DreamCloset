@props(['href' => null])

<a href="{{ $href ?? route('home') }}" {{ $attributes->class(['inline-flex items-center gap-2.5 rounded-lg font-bold tracking-tight text-ink']) }}>
    <span class="grid size-9 shrink-0 place-items-center rounded-xl bg-ink text-white" aria-hidden="true">
        <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="round" stroke-linejoin="round" focusable="false">
            <path d="M6.5 13.6a3.05 3.05 0 0 1 .8-5.99 4.55 4.55 0 0 1 8.62-.21 3.1 3.1 0 0 1 1.58 6.2" />
            <path d="M12 14v-1.1a1.2 1.2 0 1 1 1.2-1.2M12 14l-6 3.2a.9.9 0 0 0 .4 1.7h11.2a.9.9 0 0 0 .4-1.7L12 14Z" />
        </svg>
    </span>
    <span>DreamCloset</span>
</a>
