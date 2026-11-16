<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title', 'DreamCloset') · DreamCloset</title>
        @fonts(['dm-sans', 'newsreader'])
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="dreamcloset-shell flex min-h-screen flex-col bg-canvas font-interface text-ink antialiased">
        <a href="#contenido" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-40 focus:rounded-lg focus:bg-white focus:px-4 focus:py-3">
            Saltar al contenido
        </a>

        <header class="sticky top-0 z-30 border-b border-line bg-canvas/95 backdrop-blur">
            <div class="mx-auto grid min-h-[65px] max-w-shell grid-cols-[1fr_auto] items-center px-4 sm:px-6 md:min-h-[77px] md:grid-cols-[1fr_auto_1fr] lg:px-8">
                <x-brand class="justify-self-start text-lg" />

                <x-section-nav />

                <a href="{{ route('account.show') }}" @if (request()->routeIs('account.show')) aria-current="page" @endif class="hidden min-h-11 items-center gap-2 rounded-xl px-2 text-sm font-medium text-muted hover:text-ink aria-[current=page]:font-bold aria-[current=page]:text-ink md:inline-flex md:justify-self-end">
                    <span class="grid size-8 place-items-center rounded-full bg-line/50 text-xs font-bold text-ink" aria-hidden="true">
                        {{ str(request()->user()->name)->trim()->substr(0, 1)->upper() }}
                    </span>
                    Mi cuenta
                </a>

                <details data-mobile-menu class="group md:hidden">
                    <summary class="grid size-11 cursor-pointer list-none place-items-center rounded-xl [&::-webkit-details-marker]:hidden">
                        <span class="sr-only">Menú principal</span>
                        <svg class="size-5 group-open:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                            <path d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg class="hidden size-5 group-open:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true">
                            <path d="m6 6 12 12M18 6 6 18" />
                        </svg>
                    </summary>

                    <div class="absolute inset-x-3 top-full rounded-2xl border border-line bg-white p-2 shadow-lg">
                        <x-section-nav variant="menu" />
                        <a href="{{ route('account.show') }}" @if (request()->routeIs('account.show')) aria-current="page" @endif class="flex min-h-12 items-center justify-between rounded-lg border-t border-line px-3 text-sm font-medium hover:bg-canvas aria-[current=page]:font-bold">
                            Mi cuenta <span aria-hidden="true">→</span>
                        </a>
                    </div>
                </details>
            </div>
        </header>

        <main id="contenido" tabindex="-1" class="mx-auto w-full max-w-shell flex-1 scroll-mt-16 px-4 pt-9 pb-12 sm:px-6 md:scroll-mt-20 md:pt-16 md:pb-[90px]">
            @yield('content')
        </main>

        <footer class="border-t border-line pb-[calc(80px+env(safe-area-inset-bottom))] md:pb-0">
            <div class="mx-auto flex max-w-shell flex-wrap items-center justify-between gap-4 px-4 py-6 sm:px-6 lg:px-8">
                <x-brand />
                <p class="text-sm text-muted">Tu clóset, tu clima, tu outfit ideal.</p>
            </div>
        </footer>

        <x-section-nav variant="bottom" />
    </body>
</html>
