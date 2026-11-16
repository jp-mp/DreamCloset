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

        <x-panel aria-labelledby="resumen-semanal" tone="ink">
            <div class="grid gap-6 md:grid-cols-2 md:items-start">
                <div class="grid gap-3">
                    <p class="justify-self-start rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold">Recomendación de ejemplo</p>
                    <h2 id="resumen-semanal" class="font-heading text-4xl font-semibold">Tu resumen semanal</h2>
                    <p class="text-white/85">Cuando las mañanas son frescas y las tardes templadas, las capas ligeras facilitan ajustar el abrigo.</p>
                </div>
                <figure data-week-chart hidden class="border-white/20 md:border-l md:pl-6">
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
            </div>
        </x-panel>

        <section aria-labelledby="consejos-semana" class="grid gap-4">
            <h2 id="consejos-semana" class="font-heading text-4xl font-semibold">Tres consejos de ejemplo</h2>
            <div class="grid gap-3 md:grid-cols-3">
                @foreach ([
                    ['Reserva una capa exterior', 'Ten a mano una capa ligera si cambia la temperatura.'],
                    ['Revisa el pronóstico diario', 'Guíate por la mínima y la máxima antes de salir.'],
                    ['Elige el calzado según la lluvia', 'Si se espera lluvia, considera calzado cerrado e impermeable.'],
                ] as [$title, $tip])
                    <article class="rounded-card border border-line bg-white p-5">
                        <h3 class="font-heading text-2xl font-semibold">{{ $title }}</h3>
                        <p class="mt-2 text-sm text-muted">{{ $tip }}</p>
                    </article>
                @endforeach
            </div>
        </section>
        <a href="{{ route('home') }}" class="dc-action-secondary justify-self-start">Volver al inicio</a>
    </div>
@endsection
