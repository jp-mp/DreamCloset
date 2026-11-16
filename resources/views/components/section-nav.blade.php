@props(['variant' => 'desktop'])

<nav
    aria-label="{{ $variant === 'menu' ? 'Menú de secciones' : 'Navegación principal' }}"
    @class([
        'hidden md:block md:self-stretch' => $variant === 'desktop',
        'fixed inset-x-3 bottom-[calc(10px+env(safe-area-inset-bottom))] z-20 rounded-[17px] border border-line bg-white/95 p-1 shadow-lg backdrop-blur md:hidden' => $variant === 'bottom',
    ])
>
    <ul @class([
        'flex h-full items-stretch gap-x-[34px]' => $variant === 'desktop',
        'grid grid-cols-3 gap-1' => $variant === 'bottom',
        'grid' => $variant === 'menu',
    ])>
        @foreach (['home' => 'Inicio', 'week.index' => 'Mi semana', 'closet.index' => 'Mi clóset'] as $name => $label)
            <li class="flex min-w-0">
                <a href="{{ route($name) }}"
                    @if (request()->routeIs($name)) aria-current="page" @endif
                    @class([
                        'flex w-full items-center font-medium',
                        'justify-center border-b-2 border-transparent px-1 text-sm whitespace-nowrap text-muted hover:text-ink focus-visible:[outline-offset:-3px] aria-[current=page]:border-accent aria-[current=page]:font-bold aria-[current=page]:text-ink' => $variant === 'desktop',
                        'min-h-[53px] flex-col justify-center gap-0.5 rounded-xl px-1 text-center text-[11px] leading-tight text-muted hover:bg-canvas aria-[current=page]:bg-ink aria-[current=page]:text-white' => $variant === 'bottom',
                        'min-h-12 justify-between rounded-lg px-3 text-sm hover:bg-canvas aria-[current=page]:font-bold' => $variant === 'menu',
                    ])
                >
                    @if ($variant === 'bottom')
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            @switch($name)
                                @case('home')
                                    <path d="m3 10 9-7 9 7v10H3zM9 20v-6h6v6" />
                                    @break
                                @case('week.index')
                                    <rect x="3" y="5" width="18" height="16" rx="2" />
                                    <path d="M3 10h18M8 3v4m8-4v4" />
                                    @break
                                @case('closet.index')
                                    <rect x="5" y="3" width="14" height="18" rx="1" />
                                    <path d="M12 3v18m-2.5-10v2m5-2v2" />
                                    @break
                            @endswitch
                        </svg>
                    @endif
                    {{ $label }}
                    @if ($variant === 'menu')
                        <span aria-hidden="true">→</span>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
</nav>
