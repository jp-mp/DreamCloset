<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>@yield('title') · DreamCloset</title>
        @fonts(['dm-sans', 'newsreader'])
        @vite('resources/css/app.css')
    </head>
    <body class="dreamcloset-shell flex min-h-screen flex-col bg-canvas font-interface text-ink antialiased">
        <header class="border-b border-line">
            <div class="mx-auto flex min-h-[65px] max-w-shell items-center px-4 sm:px-6 md:min-h-[77px] lg:px-8">
                <x-brand :href="url('/')" class="text-lg" />
            </div>
        </header>

        <main class="mx-auto grid w-full max-w-lg flex-1 items-center px-4 py-8 sm:px-6 sm:py-10">
            <x-panel aria-labelledby="auth-title">
                <div class="mb-6 grid gap-2.5">
                    <p class="text-xs font-bold tracking-widest text-muted uppercase">Tu clóset virtual</p>
                    <h1 id="auth-title" class="font-heading text-[2.5rem] leading-none font-semibold tracking-tight text-balance sm:text-5xl">@yield('title')</h1>
                    <p class="text-sm leading-relaxed text-muted">@yield('description')</p>
                </div>

                @if ($errors->any())
                    <p role="alert" class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800">
                        Revisa los campos indicados e inténtalo de nuevo.
                    </p>
                @endif

                @yield('content')
            </x-panel>
        </main>

        <footer class="border-t border-line px-4 py-5 text-center text-sm text-muted">
            Tu clóset, tu clima, tu outfit ideal.
        </footer>
    </body>
</html>
