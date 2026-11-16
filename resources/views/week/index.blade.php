@extends('layouts.app')

@section('title', 'Mi semana')

@section('content')
    <div data-weather="{{ route('weather.saved', absolute: false) }}" class="grid gap-8 md:gap-10">
        <header class="grid gap-3">
            <p class="wrap-anywhere text-xs font-bold tracking-widest text-muted uppercase">
                {{ request()->user()->location_name ?? 'Mi semana' }}<span data-week-range hidden></span>
            </p>
            <h1 class="font-heading text-display font-semibold tracking-tight">Prepárate para tu semana</h1>
            <p class="text-muted">Anticipa los cambios del clima y elige mejor qué usar.</p>
        </header>
        <section aria-label="Pronóstico semanal">
            <x-data-status loading="Cargando el pronóstico…" class="rounded-card border border-line bg-white px-5 py-6">
                <a href="{{ route('account.show') }}#ubicacion" data-weather-account hidden class="mt-3 rounded-sm text-sm font-bold underline underline-offset-4">Configurar ubicación</a>
            </x-data-status>
            <x-forecast-days />
        </section>

        <x-panel tone="ink" hidden data-week-chart-panel>
            <figure data-week-chart hidden class="w-full">
                <figcaption class="text-xs font-bold tracking-wider text-white/80 uppercase">Temperaturas máximas previstas</figcaption>
                <ul data-chart-bars class="mt-4 grid grid-cols-7 items-end gap-1.5 sm:gap-2.5"></ul>
            </figure>
            <template data-chart-bar>
                <li class="grid min-w-0 justify-items-center gap-1.5 text-center">
                    <span data-chart-value class="text-xs font-bold"></span>
                    <span data-chart-column aria-hidden="true" class="w-full max-w-8 rounded-t bg-[#aec5b9]"></span>
                    <span data-chart-label class="text-[11px] text-white/85"></span>
                </li>
            </template>
        </x-panel>

        <x-outfit-recommendations period="week" />
        <a href="{{ route('home') }}" class="dc-action-secondary justify-self-start">Volver al inicio</a>
    </div>
@endsection
