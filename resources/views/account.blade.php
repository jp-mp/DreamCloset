@extends('layouts.app')

@section('title', 'Mi cuenta')

@section('content')
    <div class="grid max-w-2xl gap-6">
        <div class="grid gap-3">
            <p class="text-xs font-bold tracking-widest text-muted uppercase">Tu perfil</p>
            <h1 class="font-heading text-display font-semibold tracking-tight">Mi cuenta</h1>
            <p class="wrap-anywhere text-muted">Hola, {{ $user->name }}.</p>
        </div>

        <x-panel aria-labelledby="datos-cuenta">
            <h2 id="datos-cuenta" class="font-heading text-3xl leading-tight font-semibold">Datos de tu cuenta</h2>

            <dl class="mt-5 grid gap-4">
                <div class="grid gap-1">
                    <dt class="text-sm font-bold">Correo electrónico</dt>
                    <dd class="wrap-anywhere text-muted">{{ $user->email }}</dd>
                </div>
                <div class="grid gap-1 border-t border-line pt-4">
                    <dt class="text-sm font-bold">Ubicación guardada</dt>
                    <dd data-saved-location class="wrap-anywhere text-muted">
                        @if ($user->latitude !== null && $user->longitude !== null)
                            {{ $user->location_name ?? 'Mi ubicación' }}
                        @else
                            Aún no has guardado una ubicación.
                        @endif
                    </dd>
                </div>
            </dl>
        </x-panel>

        <x-panel id="ubicacion" aria-labelledby="editar-ubicacion" class="scroll-mt-24">
            <h2 id="editar-ubicacion" class="font-heading text-3xl font-semibold">Editar ubicación</h2>
            <p class="mt-2 text-sm text-muted">Usaremos esta ubicación para mostrar tu pronóstico en Inicio y Mi semana.</p>

            <form method="POST" action="{{ route('location.update', absolute: false) }}" data-location-form class="mt-5 grid gap-4">
                @csrf
                @method('PUT')

                <div>
                    <button type="button" data-geolocate class="dc-action-secondary disabled:opacity-60">Usar mi ubicación</button>
                    <p data-geolocation-status role="status" class="mt-2 text-sm text-muted"></p>
                </div>

                <x-form-field name="name" label="Nombre de la ubicación (opcional)" :value="$user->location_name" maxlength="120" placeholder="Por ejemplo, Santiago" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form-field name="latitude" label="Latitud" type="number" step="any" min="-90" max="90" :value="$user->latitude" required />
                    <x-form-field name="longitude" label="Longitud" type="number" step="any" min="-180" max="180" :value="$user->longitude" required />
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="dc-action-primary disabled:opacity-60">Guardar ubicación</button>
                    <p data-save-status role="status" class="text-sm text-muted"></p>
                </div>
            </form>
        </x-panel>

        <div class="grid gap-3 sm:flex sm:flex-wrap">
            <a href="{{ route('home') }}" class="dc-action-primary">Ir a Inicio</a>
            <form method="POST" action="{{ route('logout') }}" class="grid">
                @csrf
                <button type="submit" class="dc-action-secondary">Cerrar sesión</button>
            </form>
        </div>
    </div>
@endsection
