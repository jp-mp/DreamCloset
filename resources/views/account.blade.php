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

            <form id="editar-nombre" method="POST" action="{{ route('account.name') }}"
                class="mt-4 grid scroll-mt-28 gap-3 border-t border-line pt-4">
                @csrf
                @method('PATCH')
                <x-form-field name="profile_name" label="Nombre de usuario" :value="old('profile_name', $user->name)" autocomplete="name" maxlength="100" required />
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="dc-action-secondary">Guardar nombre</button>
                    @if (session('profile_name_status'))
                        <p role="status" class="text-sm text-muted">{{ session('profile_name_status') }}</p>
                    @endif
                </div>
            </form>

            <form id="easter-eggs" method="POST" action="{{ route('account.easter-eggs') }}" class="mt-4 flex scroll-mt-28 flex-wrap items-center justify-between gap-3 border-t border-line pt-4">
                @csrf
                @method('PATCH')
                <div class="grid gap-1">
                    <p id="easter-eggs-label" class="text-sm font-bold">Easter eggs</p>
                    <p id="easter-eggs-description" class="text-sm text-muted">Muestra una bebida alcohólica del día en Inicio.</p>
                    @error('easter_eggs')
                        <p class="text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" role="switch" aria-checked="{{ $user->easter_eggs ? 'true' : 'false' }}"
                    aria-labelledby="easter-eggs-label" aria-describedby="easter-eggs-description"
                    name="easter_eggs" value="{{ $user->easter_eggs ? '0' : '1' }}"
                    class="group inline-flex min-h-11 items-center gap-2 rounded-xl px-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">
                    <span aria-hidden="true" class="relative h-6 w-11 rounded-full bg-[#a1acb0] transition-colors group-aria-checked:bg-ink">
                        <span class="absolute top-1 left-1 size-4 rounded-full bg-white shadow-sm transition-transform group-aria-checked:translate-x-5"></span>
                    </span>
                    <span aria-hidden="true">{{ $user->easter_eggs ? 'Activado' : 'Desactivado' }}</span>
                </button>
            </form>
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
