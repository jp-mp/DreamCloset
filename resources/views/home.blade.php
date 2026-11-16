@extends('layouts.app')

@section('title', 'Inicio')

@section('content')
    <div data-weather="{{ route('weather.saved', absolute: false) }}" class="grid gap-8 md:gap-10">
        <header class="grid gap-3">
            <p class="text-xs font-bold tracking-widest text-muted uppercase">Tu resumen de hoy</p>
            <h1 class="wrap-anywhere font-heading text-display font-semibold tracking-tight">Hola, {{ str(request()->user()->name)->trim()->before(' ') }}</h1>
            <p class="text-muted">Consulta el clima antes de elegir qué ponerte.</p>
        </header>

        <x-panel aria-labelledby="clima-actual" tone="ink">
            <div class="grid gap-4">
                <p class="wrap-anywhere text-xs font-bold tracking-widest text-white/75 uppercase">
                    {{ request()->user()->location_name ?? 'Clima local' }}
                    <span data-current-date hidden> · <time></time></span>
                </p>
                <h2 id="clima-actual" class="font-heading text-3xl font-semibold">Clima de hoy</h2>
                <x-data-status loading="Cargando el clima…">
                    <a href="{{ route('account.show') }}#ubicacion" data-weather-account hidden class="mt-3 rounded-sm text-sm font-bold underline underline-offset-4">Configurar ubicación</a>
                </x-data-status>
                <div data-weather-current hidden class="grid gap-5">
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                        <strong data-temperature class="font-heading text-6xl leading-none font-semibold sm:text-7xl"></strong>
                        <p class="inline-flex items-center gap-2"><span data-current-icon class="text-3xl" aria-hidden="true"></span><span data-current-condition></span></p>
                    </div>
                    <dl class="flex flex-wrap gap-x-8 gap-y-3 text-sm">
                        <div><dt class="text-white/75">Máx. / Mín.</dt><dd data-current-range class="mt-1 font-bold"></dd></div>
                        <div><dt class="text-white/75">Lluvia</dt><dd data-current-rain class="mt-1 font-bold"></dd></div>
                        <div><dt class="text-white/75">Viento</dt><dd data-current-wind class="mt-1 font-bold"></dd></div>
                    </dl>
                </div>
            </div>
        </x-panel>

        <section aria-labelledby="outfit-demo" class="grid gap-5 rounded-panel border border-line bg-white p-3 sm:p-4 md:grid-cols-2 md:items-center md:gap-8">
            <img src="{{ asset('images/outfit-demo.webp') }}" alt="Polera blanca, sobrecamisa verde, jeans y zapatillas claras sobre lino" class="aspect-[4/5] w-full rounded-card bg-canvas object-cover" width="900" height="1125" loading="lazy">
            <div class="grid gap-4 px-3 pb-4 sm:px-4 md:py-5">
                <p class="justify-self-start rounded-full bg-[#f0e3db] px-3 py-1.5 text-xs font-bold">Recomendación de ejemplo</p>
                <h2 id="outfit-demo" class="font-heading text-4xl leading-none font-semibold sm:text-5xl">Tu outfit de hoy</h2>
                <p class="text-muted">Una combinación de muestra para un día fresco.</p>
                <ul class="grid grid-cols-2 gap-2.5 text-sm font-medium">
                    @foreach (['Polera blanca', 'Sobrecamisa verde', 'Jeans rectos', 'Zapatillas claras'] as $garment)
                        <li class="rounded-xl border border-line bg-canvas px-3 py-2">{{ $garment }}</li>
                    @endforeach
                </ul>
                <div class="rounded-xl bg-canvas p-4">
                    <h3 class="text-sm font-bold">Por qué funciona</h3>
                    <p class="mt-1 text-sm text-muted">Una sobrecamisa permite ajustar el abrigo si cambia la temperatura.</p>
                </div>
            </div>
        </section>

        <section aria-labelledby="proximos-dias" class="grid gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="proximos-dias" class="font-heading text-4xl font-semibold">Los próximos días</h2>
                <a href="{{ route('week.index') }}" class="dc-action-secondary">Ver mi semana</a>
            </div>
            <x-forecast-days />
        </section>

        <div class="flex flex-wrap items-center justify-between gap-4 rounded-panel border border-line bg-[#e8eeeb] px-6 py-7 sm:px-10">
            <h2 class="font-heading text-3xl font-semibold">Tu estilo comienza en tu clóset</h2>
            <a href="{{ route('closet.index') }}" class="dc-action-primary">Explorar mi clóset</a>
        </div>
    </div>
@endsection
